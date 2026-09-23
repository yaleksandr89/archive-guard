<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

final readonly class ExtractionResult
{
    public function __construct(private ArchiveFormat $archiveFormat, private int $files, private int $directories, private int $bytes, private int $skipped = 0, private int $overwritten = 0) {}

    public function filesSkipped(): int
    {
        return $this->skipped;
    }
    public function filesOverwritten(): int
    {
        return $this->overwritten;
    }
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
