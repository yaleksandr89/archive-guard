<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Extraction;

use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionResult;
use Yaleksandr\ArchiveGuard\Internal\NormalizedArchiveEntry;

/** @internal */
final class FilesystemExtractionTarget
{
    private string $root;
    /** @var array<string, true> */
    private array $created = [];
    /** @var resource|null */
    private $output = null;
    private int $files = 0;
    private int $directories = 0;
    private int $bytes = 0;
    private int $fileBytes = 0;
    private ?NormalizedArchiveEntry $active = null;

    /** @param list<NormalizedArchiveEntry> $entries */
    public function __construct(string $destination, array $entries, private readonly ArchivePolicy $policy)
    {
        if (str_contains($destination, "\0") || str_contains($destination, '://')) {
            throw new ExtractionException('Destination must be a local directory.');
        }
        $finalPath = rtrim($destination, '/\\');
        clearstatcache(true, $finalPath);
        if (is_link($finalPath) || !is_dir($destination)) {
            throw new ExtractionException('Destination must be an existing non-symlink directory.');
        }
        $root = realpath($destination);
        if ($root === false || !is_dir($root)) {
            throw new ExtractionException('Cannot resolve destination directory.');
        }
        $contents = @scandir($root);
        if ($contents === false || count($contents) !== 2) {
            throw new ExtractionException('Destination must be empty and readable.');
        }
        $this->root = $root;
        foreach ($entries as $entry) {
            if ($entry->path === '') {
                if (!$entry->directory) {
                    throw new ExtractionException('Root marker must be a directory.');
                }
                continue;
            }
            foreach (explode('/', $entry->path) as $part) {
                if ($part === '' || $part === '.' || $part === '..' || str_contains($part, "\0") || str_contains($part, '\\')) {
                    throw new ExtractionException('Invalid normalized target path.');
                }
            }
        }
    }

    public function directory(NormalizedArchiveEntry $entry): void
    {
        if ($entry->path !== '') {
            $this->parents($entry->path, true);
        }
    }

    public function beginFile(NormalizedArchiveEntry $entry): void
    {
        if ($this->output !== null || $entry->directory || $entry->path === '') {
            throw new ExtractionException('Invalid file extraction state.');
        }
        $this->parents($entry->path, false);
        $path = $this->target($entry->path);
        clearstatcache(true, $path);
        if (@lstat($path) !== false) {
            throw new ExtractionException('Extraction target already exists.');
        }
        $output = @fopen($path, 'xb');
        if ($output === false) {
            throw new ExtractionException('Cannot create extraction target exclusively.');
        }
        $this->output = $output;
        $this->active = $entry;
        $this->fileBytes = 0;
    }

    public function write(string $chunk): void
    {
        if ($this->output === null || $this->active === null) {
            throw new ExtractionException('No active output file.');
        }
        $length = strlen($chunk);
        if ($length > $this->policy->maxEntryUncompressedBytes - $this->fileBytes
            || $length > $this->policy->maxTotalUncompressedBytes - $this->bytes
            || $length > $this->active->size - $this->fileBytes) {
            throw new ExtractionException('Actual extracted bytes exceed accepted limits.');
        }
        $offset = 0;
        while ($offset < $length) {
            $written = @fwrite($this->output, substr($chunk, $offset));
            if ($written === false || $written === 0) {
                throw new ExtractionException('Cannot write extraction target.');
            }
            $offset += $written;
            $this->fileBytes += $written;
            $this->bytes += $written;
        }
    }

    public function finishFile(): void
    {
        if ($this->output === null || $this->active === null) {
            throw new ExtractionException('No active output file.');
        }
        if ($this->fileBytes !== $this->active->size) {
            throw new ExtractionException('Actual file size differs from accepted metadata.');
        }
        $closed = fclose($this->output);
        $this->output = null;
        $this->active = null;
        if (!$closed) {
            throw new ExtractionException('Cannot close extraction target.');
        }
        ++$this->files;
    }

    public function abortFile(): void
    {
        if ($this->output !== null) {
            fclose($this->output);
            $this->output = null;
            $this->active = null;
        }
    }

    public function result(ArchiveFormat $format): ExtractionResult
    {
        return new ExtractionResult($format, $this->files, $this->directories, $this->bytes);
    }

    private function parents(string $path, bool $includeLast): void
    {
        $parts = explode('/', $path);
        if (!$includeLast) {
            array_pop($parts);
        }
        $relative = '';
        $this->assertDirectory($this->root);
        foreach ($parts as $part) {
            $relative = $relative === '' ? $part : $relative . '/' . $part;
            $target = $this->target($relative);
            clearstatcache(true, $target);
            $stat = @lstat($target);
            if ($stat === false) {
                if (!@mkdir($target)) {
                    throw new ExtractionException('Cannot create extraction directory.');
                }
                $this->created[$relative] = true;
                ++$this->directories;
            } elseif (!isset($this->created[$relative])) {
                throw new ExtractionException('Unexpected existing extraction object.');
            }
            $this->assertDirectory($target);
        }
    }

    private function assertDirectory(string $path): void
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0040000) {
            throw new ExtractionException('Extraction path component is not a directory.');
        }
    }

    private function target(string $relative): string
    {
        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
