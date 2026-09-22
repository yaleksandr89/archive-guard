<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Internal\ArchiveFormatDetector;
use Yaleksandr\ArchiveGuard\Internal\Inspection\TarInspector;
use Yaleksandr\ArchiveGuard\Internal\Inspection\ZipInspector;

final class ArchiveGuard
{
    public function inspect(string $archivePath, ArchivePolicy $policy): InspectionResult
    {
        if (str_contains($archivePath, "\0") || str_contains($archivePath, '://') || !is_file($archivePath) || !is_readable($archivePath)) {
            throw new ArchiveOpenException('Source must be a readable regular local file.');
        }
        clearstatcache(true, $archivePath);
        $size = @filesize($archivePath);
        if ($size === false) {
            throw new ArchiveOpenException('Cannot determine source size.');
        }
        $format = new ArchiveFormatDetector()->detect($archivePath);
        if ($size > $policy->maxArchiveBytes) {
            return new InspectionResult($format, [new Violation(ViolationCode::ArchiveTooLarge, 'Archive source exceeds byte limit.')]);
        }
        $inspector = $format === ArchiveFormat::Zip ? new ZipInspector() : new TarInspector();
        return $inspector->inspect($archivePath, $policy, $format, $size);
    }
}
