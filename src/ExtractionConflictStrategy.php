<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

enum ExtractionConflictStrategy
{
    case Reject;
    case Skip;
    case Overwrite;
}
