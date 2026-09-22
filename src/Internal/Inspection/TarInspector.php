<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Inspection;

use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\InspectionResult;
use Yaleksandr\ArchiveGuard\Internal\Tar\TarHeader;
use Yaleksandr\ArchiveGuard\Internal\Tar\TarReader;
use Yaleksandr\ArchiveGuard\ViolationCode;

/** @internal */
final class TarInspector implements ArchiveInspector
{
    public function inspect(string $path, ArchivePolicy $policy, ArchiveFormat $format, int $sourceBytes): InspectionResult
    {
        $reader = new TarReader($path, $format === ArchiveFormat::TarGz, $sourceBytes, $policy->maxCompressionRatio);
        $state = new InspectionAccumulator($policy);
        $pending = [];
        $longName = null;
        $longLink = false;
        $local = false;
        $count = 0;
        try {
            while (true) {
                $block = $reader->read(512);
                if ($block === null) {
                    break;
                }
                if ($block === str_repeat("\0", 512)) {
                    $end = $reader->read(512);
                    if ($end === null) {
                        break;
                    }
                    if ($end !== $block || $local || $longName !== null || $longLink) {
                        throw new ArchiveOpenException('Invalid TAR end marker or orphan extension.');
                    }
                    $reader->finish();
                    break;
                }
                if (++$count > $policy->maxEntries) {
                    $state->add(ViolationCode::TooManyEntries);
                    break;
                }
                $header = TarHeader::parse($block);
                if ($header->unsupported) {
                    $state->add(ViolationCode::UnsupportedFeature, $header->name);
                    break;
                }
                $extension = in_array($header->type, ['x', 'g', 'L', 'K'], true);
                $name = $pending['path'] ?? $longName ?? $header->name;
                if (!$extension && !in_array($header->type, ['0', "\0", '5', '1', '2', '3', '4', '6'], true)) {
                    $state->add(ViolationCode::UnsupportedFeature, $name);
                    break;
                }
                $size = !$extension && isset($pending['size']) ? TarHeader::number($pending['size'], 10) : $header->size;
                if (!$state->payload($size, $extension ? null : $name)) {
                    break;
                }
                $payload = '';
                $remaining = $size;
                while ($remaining > 0) {
                    $chunk = min(8192, $remaining);
                    $data = $reader->read($chunk);
                    if ($data === null) {
                        break 2;
                    }
                    if ($extension) {
                        $payload .= $data;
                    }
                    $remaining -= $chunk;
                }
                if (!$reader->consume((512 - $size % 512) % 512)) {
                    break;
                }
                if ($extension) {
                    if ($header->type === 'L' || $header->type === 'K') {
                        if (!str_ends_with($payload, "\0") || str_contains(substr($payload, 0, -1), "\0")) {
                            throw new ArchiveOpenException('Malformed GNU long-name/link record.');
                        }
                        if ($header->type === 'L') {
                            if ($longName !== null || isset($pending['path'])) {
                                $state->add(ViolationCode::UnsupportedFeature);
                                break;
                            }
                            $longName = substr($payload, 0, -1);
                        } else {
                            if ($longLink) {
                                $state->add(ViolationCode::UnsupportedFeature);
                                break;
                            }
                            $longLink = true;
                        }
                    } else {
                        $values = $this->pax($payload);
                        foreach ($values as $key => $value) {
                            if (!in_array($key, ['path', 'linkpath', 'size', 'mtime', 'atime', 'ctime', 'uid', 'gid', 'uname', 'gname', 'charset', 'hdrcharset', 'comment'], true)
                                || ($header->type === 'g' && in_array($key, ['path', 'linkpath', 'size'], true))
                                || ($key === 'hdrcharset' && !in_array($value, ['BINARY', 'ISO-IR 10646 2000 UTF-8'], true))) {
                                $state->add(ViolationCode::UnsupportedFeature);
                                break 2;
                            }
                            if ($key === 'size' && preg_match('/^[0-9]+$/D', $value) !== 1) {
                                throw new ArchiveOpenException('Invalid PAX size.');
                            }
                        }
                        if ($header->type === 'x') {
                            if ($local || ($longName !== null && isset($values['path']))) {
                                $state->add(ViolationCode::UnsupportedFeature);
                                break;
                            }
                            $pending = $values;
                            $local = true;
                        }
                    }
                    continue;
                }
                $directory = $header->type === '5'
                    || (in_array($header->type, ['0', "\0"], true) && str_ends_with(str_replace('\\', '/', $name), '/'));
                $state->path($name, $directory);
                $code = match ($header->type) {
                    '0', "\0", '5' => null,
                    '1' => ViolationCode::HardlinkEntry,
                    '2' => ViolationCode::SymlinkEntry,
                    '3', '4', '6' => ViolationCode::SpecialEntry,
                };
                if ($code !== null) {
                    $state->add($code, $name);
                }
                if (($longLink || isset($pending['linkpath'])) && !in_array($header->type, ['1', '2'], true)) {
                    $state->add(ViolationCode::UnsupportedFeature, $name);
                }
                $pending = [];
                $longName = null;
                $longLink = false;
                $local = false;
            }
        } finally {
            $reader->close();
        }
        if ($reader->ratioExceeded()) {
            $state->add(ViolationCode::CompressionRatioExceeded);
        }
        return new InspectionResult($format, $state->violations);
    }

    /** @return array<string, string> */
    private function pax(string $payload): array
    {
        $values = [];
        $offset = 0;
        $length = strlen($payload);
        while ($offset < $length) {
            $space = strpos($payload, ' ', $offset);
            if ($space === false) {
                throw new ArchiveOpenException('Malformed PAX length.');
            }
            $digits = substr($payload, $offset, $space - $offset);
            if (preg_match('/^[1-9][0-9]*$/D', $digits) !== 1) {
                throw new ArchiveOpenException('Malformed PAX length.');
            }
            $recordLength = TarHeader::number($digits, 10);
            $prefixLength = $space - $offset + 1;
            if ($recordLength <= $prefixLength || $recordLength > $length - $offset) {
                throw new ArchiveOpenException('Invalid PAX record boundary.');
            }
            $record = substr($payload, $space + 1, $recordLength - $prefixLength);
            if (!str_ends_with($record, "\n")) {
                throw new ArchiveOpenException('PAX record lacks newline.');
            }
            $equal = strpos($record, '=');
            if ($equal === false || $equal === 0) {
                throw new ArchiveOpenException('Malformed PAX key/value.');
            }
            $key = substr($record, 0, $equal);
            $value = substr($record, $equal + 1, -1);
            if (array_key_exists($key, $values)) {
                throw new ArchiveOpenException('Duplicate PAX key.');
            }
            $values[$key] = $value;
            $offset += $recordLength;
        }
        return $values;
    }
}
