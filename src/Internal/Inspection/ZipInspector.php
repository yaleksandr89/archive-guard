<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Inspection;

use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\InspectionResult;
use Yaleksandr\ArchiveGuard\ViolationCode;
use ZipArchive;

/** @internal */
final class ZipInspector implements ArchiveInspector
{
    public function inspect(string $path, ArchivePolicy $policy, ArchiveFormat $format, int $sourceBytes): InspectionResult
    {
        $zip = new ZipArchive();
        if (@$zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new ArchiveOpenException('Cannot open consistent ZIP archive.');
        }
        $state = new InspectionAccumulator($policy);
        try {
            if ($zip->numFiles > $policy->maxEntries) {
                $state->add(ViolationCode::TooManyEntries);
                return new InspectionResult($format, $state->violations);
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
                $state->path($name, $directory);
                if ($stat['encryption_method'] !== ZipArchive::EM_NONE) {
                    $state->add(ViolationCode::EncryptedEntry, $name);
                }
                if (!ZipArchive::isCompressionMethodSupported($stat['comp_method'], false)) {
                    $state->add(ViolationCode::UnsupportedCompression, $name);
                }
                $state->payload($stat['size'], $name);
                if ($policy->maxCompressionRatio !== null && $stat['size'] > 0 && ($stat['comp_size'] === 0 || $stat['size'] / $stat['comp_size'] > $policy->maxCompressionRatio)) {
                    $state->add(ViolationCode::CompressionRatioExceeded, $name);
                }
            }
        } finally {
            $zip->close();
        }
        return new InspectionResult($format, $state->violations);
    }
}
