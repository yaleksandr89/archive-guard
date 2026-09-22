<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Exception;

use Yaleksandr\ArchiveGuard\InspectionResult;

final class ArchiveRejectedException extends ArchiveGuardException
{
    public function __construct(private readonly InspectionResult $result)
    {
        parent::__construct('Archive rejected by policy or security validation.');
    }

    public function inspectionResult(): InspectionResult
    {
        return $this->result;
    }
}
