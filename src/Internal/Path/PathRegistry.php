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

    public function register(string $path, bool $directory): bool
    {
        $collision = array_key_exists($path, $this->paths) || (!$directory && isset($this->prefixes[$path]));
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
