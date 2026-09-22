<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Inspection;

use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\InspectionResult;
use Yaleksandr\ArchiveGuard\Internal\Tar\TarReader;
use Yaleksandr\ArchiveGuard\Internal\Tar\TarScanner;

/** @internal */
final class TarInspector implements ArchiveInspector
{
    public function inspect(string $path, ArchivePolicy $policy, ArchiveFormat $format, int $sourceBytes): InspectionResult
    {
        $reader = new TarReader($path, $format === ArchiveFormat::TarGz, $sourceBytes, $policy->maxCompressionRatio);
        try {
            return new TarScanner()->scan($reader, $policy, $format)->inspection;
        } finally {
            $reader->close();
        }
    }
}
