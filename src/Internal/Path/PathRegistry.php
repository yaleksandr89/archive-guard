<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Path;

/** @internal */
final class PathRegistry
{
    /** @var array<string, bool> */
    private array $paths = [];
    /** @var array<string, true> */
    private array $prefixes = [];
    /** @var array<string, string> */
    private array $spellings = [];

    public function register(string $path, bool $directory): bool
    {
        $collision = array_key_exists($path, $this->paths) || (!$directory && isset($this->prefixes[$path]));
        if (PHP_OS_FAMILY === 'Windows') {
            $prefix = '';
            foreach (explode('/', $path) as $part) {
                $prefix = $prefix === '' ? $part : $prefix . '/' . $part;
                // ASCII only; this does not approximate Unicode/NTFS equivalence.
                $key = strtolower($prefix);
                if (isset($this->spellings[$key]) && $this->spellings[$key] !== $prefix) {
                    $collision = true;
                }
                $this->spellings[$key] = $prefix;
            }
        }
        $parts = explode('/', $path);
        array_pop($parts);
        $prefix = '';
        foreach ($parts as $part) {
            $prefix = $prefix === '' ? $part : $prefix . '/' . $part;
            if (($this->paths[$prefix] ?? true) === false) {
                $collision = true;
            }
            $this->prefixes[$prefix] = true;
        }
        $this->paths[$path] = $directory;
        return !$collision;
    }
}
