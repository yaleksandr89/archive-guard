<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

final readonly class ExtractionResult
{
    public function __construct(private ArchiveFormat $archiveFormat, private int $files, private int $directories, private int $bytes) {}

    public function format(): ArchiveFormat
    {
        return $this->archiveFormat;
    }
    public function filesExtracted(): int
    {
        return $this->files;
    }
    public function directoriesCreated(): int
    {
        return $this->directories;
    }
    public function bytesWritten(): int
    {
        return $this->bytes;
    }
}
