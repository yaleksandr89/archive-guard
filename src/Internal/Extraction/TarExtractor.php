<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Extraction;

use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionResult;
use Yaleksandr\ArchiveGuard\Internal\NormalizedArchiveEntry;
use Yaleksandr\ArchiveGuard\Internal\Tar\TarReader;
use Yaleksandr\ArchiveGuard\Internal\Tar\TarScanner;

/** @internal */
final class TarExtractor
{
    public function extract(string $path, string $destination, ArchivePolicy $policy, ArchiveFormat $format, int $sourceBytes): ExtractionResult
    {
        $reader = new TarReader($path, $format === ArchiveFormat::TarGz, $sourceBytes, $policy->maxCompressionRatio);
        try {
            $scanner = new TarScanner();
            $scan = $scanner->scan($reader, $policy, $format);
            if (!$scan->inspection->isAccepted()) {
                throw new ArchiveRejectedException($scan->inspection);
            }
            $target = new FilesystemExtractionTarget($destination, $scan->entries, $policy);
            $position = 0;
            try {
                $reader->rewind();
                $second = $scanner->scan($reader, $policy, $format, static function (NormalizedArchiveEntry $entry) use ($scan, $target, &$position): ?callable {
                    $expected = $scan->entries[$position] ?? null;
                    if ($expected === null || !$entry->matches($expected)) {
                        throw new ExtractionException('TAR source changed after validation.');
                    }
                    ++$position;
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
                if (!$second->inspection->isAccepted() || $position !== count($scan->entries)) {
                    throw new ExtractionException('Second TAR pass differs from accepted archive.');
                }
            } catch (ArchiveOpenException $e) {
                throw new ExtractionException('TAR source failed during extraction.', previous: $e);
            } finally {
                $target->abortFile();
            }
            return $target->result($format);
        } finally {
            $reader->close();
        }
    }
}
