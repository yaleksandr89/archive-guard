<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Path;

/** @internal */
final class PlatformPathValidator
{
    public function isCompatible(string $path): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return true;
        }
        // Only deterministic current-platform rules belong here. Native aliases,
        // reparse points and races require actual filesystem checks during extraction.
        foreach (explode('/', $path) as $part) {
            if (preg_match('//u', $part) !== 1
                || preg_match('/[<>:"|?*\x00-\x1f]/', $part) === 1
                || str_ends_with($part, '.') || str_ends_with($part, ' ')
                || preg_match('/^(CON|PRN|AUX|NUL|COM[1-9¹²³]|LPT[1-9¹²³])(?:\.|$)/iu', $part) === 1) {
                return false;
            }
        }
        return true;
    }
}
