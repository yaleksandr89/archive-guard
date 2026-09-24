<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Zip;

use SensitiveParameter;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\InspectionResult;
use Yaleksandr\ArchiveGuard\Internal\ArchiveScanResult;
use Yaleksandr\ArchiveGuard\Internal\NormalizedArchiveEntry;
use Yaleksandr\ArchiveGuard\Violation;
use Yaleksandr\ArchiveGuard\ViolationCode;
use ZipArchive;

/** @internal */
final class ZipPayloadReader
{
    /**
     * The optional sink receives bounded chunks and an empty completion marker.
     * Without a sink, inspection consumes and discards the same verified bytes.
     *
     * @param null|callable(NormalizedArchiveEntry): (null|callable(string): void) $onEntry
     */
    public function read(ZipArchive $zip, ArchiveScanResult $scan, #[SensitiveParameter] ?string $password = null, ?callable $onEntry = null): InspectionResult
    {
        if (!$scan->inspection->isAccepted()) {
            return $scan->inspection;
        }
        // Some runtimes refuse an empty password. Plain entries still need no
        // credential; encrypted entries must report a decryption failure, not absence.
        $passwordConfigured = $password === null || @$zip->setPassword($password);
        foreach ($scan->entries as $entry) {
            $sink = $onEntry === null ? null : $onEntry($entry);
            if ($entry->directory) {
                continue;
            }
            $stat = $zip->statIndex($entry->index, ZipArchive::FL_UNCHANGED);
            if ($stat === false) {
                throw new ArchiveOpenException('Required ZIP metadata unavailable.');
            }
            if (($stat['encryption_method'] !== ZipArchive::EM_NONE && !$passwordConfigured)
                || !$this->payload($zip, $entry, $sink)) {
                if ($password !== null && $stat['encryption_method'] !== ZipArchive::EM_NONE) {
                    return new InspectionResult($scan->inspection->format(), [
                        new Violation(ViolationCode::DecryptionFailed, 'Encrypted ZIP payload could not be decrypted or read.', $stat['name']),
                    ]);
                }
                throw new ArchiveOpenException('Cannot read ZIP payload matching accepted metadata.');
            }
            if ($sink !== null) {
                $sink('');
            }
        }
        return $scan->inspection;
    }

    /** @param null|callable(string): void $sink */
    private function payload(ZipArchive $zip, NormalizedArchiveEntry $entry, ?callable $sink): bool
    {
        $input = @$zip->getStreamIndex($entry->index, ZipArchive::FL_UNCHANGED);
        if ($input === false) {
            return false;
        }
        $bytes = 0;
        try {
            while (!feof($input)) {
                $chunk = @fread($input, 8192);
                if ($chunk === false || ($chunk === '' && !feof($input))) {
                    return false;
                }
                $length = strlen($chunk);
                // Metadata has already passed entry/total policy limits. Never send
                // excess bytes to either sink, even if the payload contradicts it.
                if ($length > $entry->size - $bytes) {
                    return false;
                }
                $bytes += $length;
                if ($sink !== null && $chunk !== '') {
                    $sink($chunk);
                }
            }
        } finally {
            $closed = @fclose($input);
        }
        return $closed && $bytes === $entry->size;
    }
}
