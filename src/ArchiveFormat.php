<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

enum ArchiveFormat: string
{
    case Zip = 'zip';
    case Tar = 'tar';
    case TarGz = 'tar.gz';
}
