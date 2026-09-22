<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Inspection;

use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\InspectionResult;

/** @internal */
interface ArchiveInspector
{
    public function inspect(string $path, ArchivePolicy $policy, ArchiveFormat $format, int $sourceBytes): InspectionResult;
}
