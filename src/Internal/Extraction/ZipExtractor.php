<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Extraction;

use SensitiveParameter;
use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\ExtractionResult;
use Yaleksandr\ArchiveGuard\Internal\NormalizedArchiveEntry;
use Yaleksandr\ArchiveGuard\Internal\Zip\ZipPayloadReader;
use Yaleksandr\ArchiveGuard\Internal\Zip\ZipScanner;
use ZipArchive;

/** @internal */
final class ZipExtractor
{
    public function extract(string $path, string $destination, ArchivePolicy $policy, #[SensitiveParameter] ?string $password = null): ExtractionResult
    {
        $zip = new ZipArchive();
        if (@$zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new ArchiveOpenException('Cannot open consistent ZIP archive.');
        }
        try {
            $scan = new ZipScanner()->scan($zip, $policy, $password !== null);
            if (!$scan->inspection->isAccepted()) {
                throw new ArchiveRejectedException($scan->inspection);
            }
            $target = new FilesystemExtractionTarget($destination, $scan->entries, $policy);
            try {
                $inspection = new ZipPayloadReader()->read($zip, $scan, $password, static function (NormalizedArchiveEntry $entry) use ($target): ?callable {
                    if ($entry->directory) {
                        $target->directory($entry);
                        return null;
                    }
                    $target->beginFile($entry);
                    return static function (string $chunk) use ($target): void {
                        if ($chunk === '') {
                            $target->finishFile();
                        } else {
                            $target->write($chunk);
                        }
                    };
                });
                if (!$inspection->isAccepted()) {
                    throw new ArchiveRejectedException($inspection);
                }
            } finally {
                $target->abortFile();
            }
            return $target->result(ArchiveFormat::Zip);
        } finally {
            $zip->close();
        }
    }
}
