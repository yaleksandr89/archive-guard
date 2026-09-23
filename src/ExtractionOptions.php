<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

final readonly class ExtractionOptions
{
    private function __construct(private ExtractionMode $mode, private ?ExtractionConflictStrategy $conflictStrategy) {}

    public static function atomic(): self
    {
        return new self(ExtractionMode::Atomic, null);
    }

    public static function merge(ExtractionConflictStrategy $conflictStrategy): self
    {
        return new self(ExtractionMode::Merge, $conflictStrategy);
    }

    public function mode(): ExtractionMode
    {
        return $this->mode;
    }

    public function conflictStrategy(): ?ExtractionConflictStrategy
    {
        return $this->conflictStrategy;
    }
}
