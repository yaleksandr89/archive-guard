<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

final readonly class InspectionResult
{
    /** @param list<Violation> $violations */
    public function __construct(private ArchiveFormat $archiveFormat, private array $violations) {}

    public function format(): ArchiveFormat
    {
        return $this->archiveFormat;
    }
    public function isAccepted(): bool
    {
        return $this->violations === [];
    }
    /** @return list<Violation> */
    public function violations(): array
    {
        return $this->violations;
    }
}
