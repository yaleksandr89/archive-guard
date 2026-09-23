<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

final readonly class ExtractionOptions
{
    public function __construct(public ExtractionMode $mode) {}
}
