<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Inspection;

use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\InspectionResult;
use Yaleksandr\ArchiveGuard\Internal\Zip\ZipScanner;
use ZipArchive;

/** @internal */
final class ZipInspector implements ArchiveInspector
{
    public function inspect(string $path, ArchivePolicy $policy, ArchiveFormat $format, int $sourceBytes): InspectionResult
    {
        $zip = new ZipArchive();
        if (@$zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new ArchiveOpenException('Cannot open consistent ZIP archive.');
        }
        try {
            return new ZipScanner()->scan($zip, $policy)->inspection;
        } finally {
            $zip->close();
        }
    }
}
