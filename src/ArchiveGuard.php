<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Internal\ArchiveFormatDetector;
use Yaleksandr\ArchiveGuard\Internal\Extraction\TarExtractor;
use Yaleksandr\ArchiveGuard\Internal\Extraction\ZipExtractor;
use Yaleksandr\ArchiveGuard\Internal\Inspection\TarInspector;
use Yaleksandr\ArchiveGuard\Internal\Inspection\ZipInspector;

final class ArchiveGuard
{
    public function inspect(string $archivePath, ArchivePolicy $policy): InspectionResult
    {
        [$format, $size] = $this->source($archivePath);
        if ($size > $policy->maxArchiveBytes) {
            return $this->tooLarge($format);
        }
        $inspector = $format === ArchiveFormat::Zip ? new ZipInspector() : new TarInspector();
        return $inspector->inspect($archivePath, $policy, $format, $size);
    }

    public function extract(string $archivePath, string $destinationPath, ArchivePolicy $policy): ExtractionResult
    {
        [$format, $size] = $this->source($archivePath);
        if ($size > $policy->maxArchiveBytes) {
            throw new ArchiveRejectedException($this->tooLarge($format));
        }
        if ($format === ArchiveFormat::Zip) {
            return new ZipExtractor()->extract($archivePath, $destinationPath, $policy);
        }
        return new TarExtractor()->extract($archivePath, $destinationPath, $policy, $format, $size);
    }

    /** @return array{ArchiveFormat, int} */
    private function source(string $archivePath): array
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
        return [$format, $size];
    }

    private function tooLarge(ArchiveFormat $format): InspectionResult
    {
        return new InspectionResult($format, [new Violation(ViolationCode::ArchiveTooLarge, 'Archive source exceeds byte limit.')]);
    }
}
