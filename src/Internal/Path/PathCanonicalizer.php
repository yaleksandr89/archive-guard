<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Path;

/** @internal */
final class PathCanonicalizer
{
    public function canonicalize(string $name): ?string
    {
        $path = str_replace('\\', '/', $name);
        if (str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path) === 1) {
            return null;
        }
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                return null;
            }
            if ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }
        return implode('/', $segments);
    }
}
