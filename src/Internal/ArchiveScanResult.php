<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal;

use Yaleksandr\ArchiveGuard\InspectionResult;

/** @internal */
final readonly class ArchiveScanResult
{
    /** @param list<NormalizedArchiveEntry> $entries */
    public function __construct(public InspectionResult $inspection, public array $entries) {}
}
