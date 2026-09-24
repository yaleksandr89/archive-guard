<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Extraction;

use SensitiveParameter;
use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionResult;
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
            if ($password !== null && !@$zip->setPassword($password)) {
                throw new ExtractionException('Cannot configure ZIP password.');
            }
            $scan = new ZipScanner()->scan($zip, $policy, $password !== null);
            if (!$scan->inspection->isAccepted()) {
                throw new ArchiveRejectedException($scan->inspection);
            }
            $target = new FilesystemExtractionTarget($destination, $scan->entries, $policy);
            foreach ($scan->entries as $entry) {
                if ($entry->directory) {
                    $target->directory($entry);
                    continue;
                }
                $target->beginFile($entry);
                try {
                    $input = @$zip->getStreamIndex($entry->index, ZipArchive::FL_UNCHANGED);
                    if ($input === false) {
                        throw new ExtractionException('Cannot read accepted ZIP entry.');
                    }
                    try {
                        while (!feof($input)) {
                            $chunk = @fread($input, 8192);
                            if ($chunk === false || ($chunk === '' && !feof($input))) {
                                throw new ExtractionException('Cannot read ZIP entry payload.');
                            }
                            $target->write($chunk);
                        }
                    } finally {
                        fclose($input);
                    }
                    $target->finishFile();
                } finally {
                    $target->abortFile();
                }
            }
            return $target->result(ArchiveFormat::Zip);
        } finally {
            $zip->close();
        }
    }
}
