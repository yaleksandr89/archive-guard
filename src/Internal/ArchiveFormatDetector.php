<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal;

use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;

/** @internal */
final class ArchiveFormatDetector
{
    public function detect(string $path): ArchiveFormat
    {
        $prefix = @file_get_contents($path, false, null, 0, 512);
        if ($prefix === false) {
            throw new ArchiveOpenException('Cannot read archive.');
        }
        if (str_starts_with($prefix, "PK\x03\x04") || str_starts_with($prefix, "PK\x05\x06")) {
            return ArchiveFormat::Zip;
        }
        if (str_starts_with($prefix, "\x1f\x8b")) {
            return ArchiveFormat::TarGz;
        }
        if (strlen($prefix) === 512 && ($prefix === str_repeat("\0", 512) || substr($prefix, 257, 5) === 'ustar')) {
            return ArchiveFormat::Tar;
        }
        throw new ArchiveOpenException('Unrecognized archive content.');
    }
}
