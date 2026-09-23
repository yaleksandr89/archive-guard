<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Extraction;

use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionConflictStrategy;
use Yaleksandr\ArchiveGuard\ExtractionResult;

/**
 * @internal
 * Preflight is a snapshot, not protection against hostile filesystem races.
 * Apply is deliberately non-atomic; completed operations are never rolled back.
 */
final class MergeExtractionPublisher
{
    /** @var list<array{path: string, directory: bool, existing: bool, bytes: int}>|null */
    private ?array $plan = null;

    public function __construct(private readonly ExtractionWorkspace $workspace, private readonly ExtractionConflictStrategy $strategy) {}

    public function preflight(): void
    {
        $this->plan = null;
        $destination = $this->workspace->mergeDestination();
        $staging = $this->workspace->stagingPath();
        $this->assertReadableDirectory($staging);
        $plan = [];
        $this->inspect($staging, $destination, '', true, $plan);
        // A failed preflight never leaves a partially usable plan.
        $this->plan = $plan;
    }

    public function apply(ArchiveFormat $format): ExtractionResult
    {
        if ($this->plan === null) {
            throw new ExtractionException('Merge requires a completed preflight.');
        }
        $plan = $this->plan;
        $this->plan = null;
        $files = $directories = $bytes = $skipped = $overwritten = 0;
        $staging = $this->workspace->stagingPath();
        $this->workspace->mergeDestination();
        foreach ($plan as $operation) {
            $destination = $this->workspace->mergeDestination();
            $relative = $operation['path'];
            $this->assertParents($destination, $relative);
            $this->assertParents($staging, $relative);
            $source = $staging . DIRECTORY_SEPARATOR . $relative;
            $target = $destination . DIRECTORY_SEPARATOR . $relative;
            $sourceStat = $this->stat($source);
            $expectedType = $operation['directory'] ? 0040000 : 0100000;
            if ($sourceStat === false || ($sourceStat['mode'] & 0170000) !== $expectedType
                || (!$operation['directory'] && $sourceStat['size'] !== $operation['bytes'])) {
                throw new ExtractionException('Staged object changed after merge preflight.');
            }
            $targetStat = $this->stat($target);
            if ($operation['existing']) {
                if ($targetStat === false || ($targetStat['mode'] & 0170000) !== $expectedType) {
                    throw new ExtractionException('Merge target changed after preflight.');
                }
            } elseif ($targetStat !== false) {
                throw new ExtractionException('Merge target appeared after preflight.');
            }
            if ($operation['directory']) {
                if (!$operation['existing']) {
                    if (!@mkdir($target)) {
                        throw new ExtractionException('Cannot create merge directory.');
                    }
                    ++$directories;
                }
                continue;
            }
            if ($operation['existing'] && $this->strategy === ExtractionConflictStrategy::Skip) {
                ++$skipped;
                continue;
            }
            // Sibling staging keeps this on one filesystem. rename replaces the
            // directory entry, never writes through a file symlink/hardlink.
            // Checks above coordinate cooperative writers, not hostile TOCTOU.
            if (!@rename($source, $target)) {
                throw new ExtractionException('Cannot apply staged merge file.');
            }
            ++$files;
            $bytes += $operation['bytes'];
            if ($operation['existing']) {
                ++$overwritten;
            }
        }
        return new ExtractionResult($format, $files, $directories, $bytes, $skipped, $overwritten);
    }

    /** @param list<array{path: string, directory: bool, existing: bool, bytes: int}> $plan */
    private function inspect(string $staging, string $destination, string $relative, bool $destinationExists, array &$plan): void
    {
        $sourceDirectory = $relative === '' ? $staging : $staging . DIRECTORY_SEPARATOR . $relative;
        $entries = @scandir($sourceDirectory);
        if ($entries === false) {
            throw new ExtractionException('Cannot inspect staged merge directory.');
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $relative === '' ? $entry : $relative . DIRECTORY_SEPARATOR . $entry;
            $source = $staging . DIRECTORY_SEPARATOR . $path;
            $stat = $this->stat($source);
            if ($stat === false || !in_array($stat['mode'] & 0170000, [0040000, 0100000], true)) {
                throw new ExtractionException('Unexpected object in merge staging.');
            }
            $directory = ($stat['mode'] & 0170000) === 0040000;
            $target = $destination . DIRECTORY_SEPARATOR . $path;
            // Never descend through a destination component until it has been
            // classified as an actual directory. Native lookup handles aliases.
            $targetStat = $destinationExists ? $this->stat($target) : false;
            if ($targetStat !== false) {
                if (($targetStat['mode'] & 0170000) !== ($directory ? 0040000 : 0100000)) {
                    throw new ExtractionException('Incompatible object at merge destination.');
                }
                if ($directory) {
                    $this->assertReadableDirectory($target);
                } elseif ($this->strategy === ExtractionConflictStrategy::Reject) {
                    throw new ExtractionException('Existing regular file conflicts with merge.');
                } elseif ($this->strategy === ExtractionConflictStrategy::Overwrite) {
                    $this->assertWritableDirectory(dirname($target));
                    if (PHP_OS_FAMILY === 'Windows' && !is_writable($target)) {
                        throw new ExtractionException('Merge overwrite target is not writable.');
                    }
                }
            }
            if ($targetStat === false && $destinationExists) {
                $this->assertWritableDirectory(dirname($target));
            }
            $plan[] = ['path' => $path, 'directory' => $directory, 'existing' => $targetStat !== false, 'bytes' => $directory ? 0 : $stat['size']];
            if ($directory) {
                $this->inspect($staging, $destination, $path, $targetStat !== false, $plan);
            }
        }
    }

    private function assertParents(string $root, string $relative): void
    {
        $this->assertReadableDirectory($root);
        $parts = explode(DIRECTORY_SEPARATOR, $relative);
        array_pop($parts);
        foreach ($parts as $part) {
            $root .= DIRECTORY_SEPARATOR . $part;
            $this->assertReadableDirectory($root);
        }
    }

    private function assertReadableDirectory(string $path): void
    {
        $stat = $this->stat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0040000 || !is_readable($path)
            || (PHP_OS_FAMILY !== 'Windows' && !is_executable($path))) {
            throw new ExtractionException('Merge path must be a readable actual directory.');
        }
    }

    private function assertWritableDirectory(string $path): void
    {
        $this->assertReadableDirectory($path);
        if (!is_writable($path)) {
            throw new ExtractionException('Merge mutation parent is not writable.');
        }
    }

    /** @return array{mode: int, size: int}|false */
    private function stat(string $path): array|false
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat !== false) {
            return ['mode' => $stat['mode'], 'size' => $stat['size']];
        }
        // Fail closed on inaccessible objects instead of equating stat failure
        // with absence. Parents have already been checked without following links.
        $siblings = @scandir(dirname($path));
        if ($siblings === false || in_array(basename($path), $siblings, true)) {
            throw new ExtractionException('Cannot inspect merge object.');
        }
        return false;
    }
}
