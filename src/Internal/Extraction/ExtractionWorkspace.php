<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Extraction;

use Error;
use Exception;
use Throwable;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionMode;

/**
 * @internal
 * Cooperative locking only: unrelated filesystem mutations remain possible.
 * Publish is a same-parent rename, without crash durability/fsync guarantees.
 */
final class ExtractionWorkspace
{
    private readonly string $destination;
    private ?string $staging = null;
    /** @var resource|null */
    private $lock = null;

    /** @var array{dev: int, ino: int}|null */
    private ?array $rootIdentity = null;

    public static function atomic(string $destination): self
    {
        return new self($destination, ExtractionMode::Atomic);
    }

    public static function merge(string $destination): self
    {
        return new self($destination, ExtractionMode::Merge);
    }

    private function __construct(string $destination, private readonly ExtractionMode $mode)
    {
        if ($destination === '' || str_contains($destination, "\0") || str_contains($destination, '://')
            || str_starts_with($destination, '//') || str_starts_with($destination, '\\\\')
            || (preg_match('/^[a-z][a-z0-9+.-]*:/i', $destination) === 1
                && !(PHP_OS_FAMILY === 'Windows' && preg_match('/^[a-z]:[\\\\\/]/i', $destination) === 1))) {
            throw new ExtractionException('Destination must be a local filesystem path.');
        }
        $path = PHP_OS_FAMILY === 'Windows' ? str_replace('\\', '/', $destination) : $destination;
        if (str_starts_with($path, '//')) {
            throw new ExtractionException('Destination must be a local filesystem path.');
        }
        $path = rtrim($path, '/');
        $name = basename($path);
        if ($name === '' || $name === '.' || $name === '..') {
            throw new ExtractionException('Destination must name a directory.');
        }
        if (PHP_OS_FAMILY === 'Windows' && (preg_match('//u', $name) !== 1
            || preg_match('/[<>:"|?*\x00-\x1f]/', $name) === 1
            || str_ends_with($name, '.') || str_ends_with($name, ' ')
            || preg_match('/^(CON|PRN|AUX|NUL|COM[1-9¹²³]|LPT[1-9¹²³])(?:\.|$)/iu', $name) === 1)) {
            throw new ExtractionException('Destination name is incompatible with Windows.');
        }
        $parent = realpath(dirname($path));
        if ($parent === false || str_starts_with($parent, '//') || str_starts_with($parent, '\\\\')
            || !is_dir($parent) || !is_readable($parent) || !is_writable($parent)) {
            throw new ExtractionException('Destination parent must be an existing readable and writable directory.');
        }
        $prefix = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->destination = $prefix . $name;
        $namespace = $prefix . '.archive-guard-locks';
        if ($name === '.archive-guard-locks') {
            throw new ExtractionException('Destination name is reserved for archive-guard internal locking.');
        }
        $this->assertDestination($namespace);
        clearstatcache(true, $namespace);
        if (@lstat($namespace) === false) {
            // Another cooperating operation may create the namespace concurrently.
            @mkdir($namespace, 0700);
        }
        clearstatcache(true, $namespace);
        $namespaceStat = @lstat($namespace);
        if ($namespaceStat === false || ($namespaceStat['mode'] & 0170000) !== 0040000) {
            throw new ExtractionException('Lock namespace must be an actual non-link directory.');
        }
        $this->assertDestination($namespace);
        // Preserve the whole basename, without a prefix/suffix or case folding:
        // native filename equivalence determines which operations share a lock.
        $lockPath = $namespace . DIRECTORY_SEPARATOR . $name;
        clearstatcache(true, $lockPath);
        $stat = @lstat($lockPath);
        if ($stat !== false && ($stat['mode'] & 0170000) !== 0100000) {
            throw new ExtractionException('Cooperative lock path must be a regular file.');
        }
        $lock = @fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw new ExtractionException('Cannot open cooperative destination lock.');
        }
        $this->lock = $lock;
        try {
            if (!@flock($lock, LOCK_EX | LOCK_NB)) {
                throw new ExtractionException('Destination is locked by another extraction.');
            }
            $this->assertDestination();
            $staging = $prefix . '.archive-guard-stage-' . bin2hex(random_bytes(16));
            if (!@mkdir($staging, 0700)) {
                throw new ExtractionException('Cannot create sibling staging directory.');
            }
            $this->staging = $staging;
        } catch (Exception $e) {
            $this->close($e);
            if ($e instanceof ExtractionException) {
                throw $e;
            }
            // Includes Random\RandomException from staging-name generation.
            throw new ExtractionException('Cannot initialize extraction workspace.', previous: $e);
        } catch (Error $e) {
            // A failed constructor has no destructor cleanup; preserve the defect.
            $this->close($e);
            throw $e;
        }
    }

    public function stagingPath(): string
    {
        if ($this->staging === null) {
            throw new ExtractionException('No active staging directory.');
        }
        return $this->staging;
    }

    public function publish(): void
    {
        if ($this->mode !== ExtractionMode::Atomic) {
            throw new ExtractionException('Only Atomic workspaces can publish staging wholesale.');
        }
        $staging = $this->stagingPath();
        $this->assertDestination();
        // PHP has no portable renameat2(RENAME_NOREPLACE). The lock coordinates
        // package operations; the check/rename gap is not hostile-race protection.
        if (!@rename($staging, $this->destination)) {
            throw new ExtractionException('Cannot publish extracted directory.');
        }
        $this->staging = null;
    }

    public function close(?Throwable $primaryFailure = null): void
    {
        try {
            if ($this->staging !== null && !self::removeTree($this->staging)) {
                throw new ExtractionException('Cannot completely remove staging directory.');
            }
        } catch (Exception $e) {
            if ($primaryFailure === null) {
                if ($e instanceof ExtractionException) {
                    throw $e;
                }
                throw new ExtractionException('Cannot clean staging directory.', previous: $e);
            }
        } catch (Error $e) {
            // Suppress a cleanup defect only to preserve an existing primary failure.
            if ($primaryFailure === null) {
                throw $e;
            }
        } finally {
            $this->staging = null;
            if ($this->lock !== null) {
                // Closing releases flock even when cleanup failed. Keep the file
                // and namespace: unlinking could split contenders across inodes.
                fclose($this->lock);
                $this->lock = null;
            }
        }
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (Throwable) {
            // Destructors cannot report best-effort cleanup failures safely.
        }
    }

    public function mergeDestination(): string
    {
        if ($this->mode !== ExtractionMode::Merge) {
            throw new ExtractionException('Merge requires a Merge workspace.');
        }
        $this->assertDestination();
        return $this->destination;
    }

    private function assertDestination(?string $namespace = null): void
    {
        clearstatcache(true, $this->destination);
        $destinationStat = @lstat($this->destination);
        if ($destinationStat === false && $this->mode === ExtractionMode::Atomic) {
            return;
        }
        if ($namespace !== null) {
            clearstatcache(true, $namespace);
            $namespaceStat = @lstat($namespace);
            // Compare native object identities, including Windows volume/file IDs;
            // never approximate filesystem name equivalence with string folding.
            if ($destinationStat !== false && $namespaceStat !== false && $namespaceStat['ino'] !== 0
                && $destinationStat['dev'] === $namespaceStat['dev']
                && $destinationStat['ino'] === $namespaceStat['ino']) {
                throw new ExtractionException('Destination name is reserved for archive-guard internal locking.');
            }
        }
        if ($this->mode === ExtractionMode::Atomic) {
            throw new ExtractionException('Final destination must not exist.');
        }
        if ($destinationStat === false || ($destinationStat['mode'] & 0170000) !== 0040000
            || !is_readable($this->destination)
            || (PHP_OS_FAMILY !== 'Windows' && !is_executable($this->destination))) {
            throw new ExtractionException('Merge destination must be an existing readable actual directory.');
        }
        $identity = ['dev' => $destinationStat['dev'], 'ino' => $destinationStat['ino']];
        if ($this->rootIdentity !== null && $this->rootIdentity !== $identity) {
            throw new ExtractionException('Merge destination root changed.');
        }
        $this->rootIdentity = $identity;
    }

    private static function removeTree(string $path): bool
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false) {
            // A failed stat can mean inaccessible, not just absent.
            $siblings = @scandir(dirname($path));
            return $siblings !== false && !in_array(basename($path), $siblings, true);
        }
        $type = $stat['mode'] & 0170000;
        if ($type !== 0040000) {
            if (@unlink($path)) {
                return true;
            }
            // Windows directory symlinks are removed with rmdir, without traversal.
            return $type === 0120000 && PHP_OS_FAMILY === 'Windows' && @rmdir($path);
        }
        $entries = @scandir($path);
        if ($entries === false) {
            return false;
        }
        $removed = true;
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $removed = self::removeTree($path . DIRECTORY_SEPARATOR . $entry) && $removed;
            }
        }
        return @rmdir($path) && $removed;
    }
}
