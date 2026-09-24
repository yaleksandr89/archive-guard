<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Zip;

use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\InspectionResult;
use Yaleksandr\ArchiveGuard\Internal\ArchiveScanResult;
use Yaleksandr\ArchiveGuard\Internal\Inspection\InspectionAccumulator;
use Yaleksandr\ArchiveGuard\Internal\NormalizedArchiveEntry;
use Yaleksandr\ArchiveGuard\ViolationCode;
use ZipArchive;

/** @internal */
final class ZipScanner
{
    public function scan(ZipArchive $zip, ArchivePolicy $policy, bool $allowEncrypted = false): ArchiveScanResult
    {
        $state = new InspectionAccumulator($policy);
        $entries = [];
        if ($zip->numFiles > $policy->maxEntries) {
            $state->add(ViolationCode::TooManyEntries);
            return new ArchiveScanResult(new InspectionResult(ArchiveFormat::Zip, $state->violations), []);
        }
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $stat = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);
            if ($stat === false || $stat['size'] < 0 || $stat['comp_size'] < 0) {
                throw new ArchiveOpenException('Required ZIP metadata unavailable.');
            }
            $name = $stat['name'];
            if (!$zip->getExternalAttributesIndex($i, $system, $attributes, ZipArchive::FL_UNCHANGED) || !is_int($system) || !is_int($attributes)) {
                throw new ArchiveOpenException('ZIP attributes unavailable.');
            }
            $type = $system === ZipArchive::OPSYS_UNIX ? (($attributes >> 16) & 0170000) : 0;
            $directory = $type === 0040000 || ($type === 0 && str_ends_with(str_replace('\\', '/', $name), '/'));
            if ($type === 0120000) {
                $state->add(ViolationCode::SymlinkEntry, $name);
            } elseif ($type !== 0 && $type !== 0100000 && $type !== 0040000) {
                $state->add(ViolationCode::SpecialEntry, $name);
            }
            $canonical = $state->path($name, $directory);
            if ($directory && $stat['size'] !== 0) {
                $state->add(ViolationCode::UnsupportedFeature, $name);
            }
            if ($stat['encryption_method'] !== ZipArchive::EM_NONE) {
                if (!$allowEncrypted) {
                    $state->add(ViolationCode::EncryptedEntry, $name);
                } elseif (!ZipArchive::isEncryptionMethodSupported($stat['encryption_method'], false)) {
                    $state->add(ViolationCode::UnsupportedEncryption, $name);
                }
            }
            if (!ZipArchive::isCompressionMethodSupported($stat['comp_method'], false)) {
                $state->add(ViolationCode::UnsupportedCompression, $name);
            }
            $state->payload($stat['size'], $name);
            if ($policy->maxCompressionRatio !== null && $stat['size'] > 0 && ($stat['comp_size'] === 0 || $stat['size'] / $stat['comp_size'] > $policy->maxCompressionRatio)) {
                $state->add(ViolationCode::CompressionRatioExceeded, $name);
            }
            if ($canonical !== null) {
                $entries[] = new NormalizedArchiveEntry($i, $canonical, $directory, $stat['size']);
            }
        }
        return new ArchiveScanResult(new InspectionResult(ArchiveFormat::Zip, $state->violations), $entries);
    }
}
