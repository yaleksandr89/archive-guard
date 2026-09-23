<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

use Throwable;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Internal\ArchiveFormatDetector;
use Yaleksandr\ArchiveGuard\Internal\Extraction\AtomicExtractionWorkspace;
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

    public function extract(string $archivePath, string $destinationPath, ArchivePolicy $policy, ExtractionOptions $options): ExtractionResult
    {
        [$format, $size] = $this->source($archivePath);
        if ($size > $policy->maxArchiveBytes) {
            throw new ArchiveRejectedException($this->tooLarge($format));
        }
        $workspace = match ($options->mode) {
            ExtractionMode::Atomic => new AtomicExtractionWorkspace($destinationPath),
        };
        $failure = null;
        try {
            $result = $format === ArchiveFormat::Zip
                ? new ZipExtractor()->extract($archivePath, $workspace->stagingPath(), $policy)
                : new TarExtractor()->extract($archivePath, $workspace->stagingPath(), $policy, $format, $size);
            $workspace->publish();
            return $result;
        } catch (Throwable $e) {
            $failure = $e;
            throw $e;
        } finally {
            $workspace->close($failure);
        }
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
