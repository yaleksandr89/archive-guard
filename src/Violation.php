<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

final readonly class Violation
{
    public function __construct(public ViolationCode $code, public string $message, public ?string $entryName = null) {}
}
