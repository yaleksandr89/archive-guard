<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests\Support;

use RuntimeException;
use SensitiveParameter;
use ZipArchive;

final class ZipFixtureFactory
{
    public static function corruptDeflatePayload(TemporaryWorkspace $workspace): string
    {
        $path = self::create($workspace, [['name' => 'payload.txt', 'payload' => str_repeat('payload', 1000)]]);
        $zip = new ZipArchive();
        if ($zip->open($path) !== true || !$zip->setCompressionIndex(0, ZipArchive::CM_DEFLATE) || !$zip->close()) {
            throw new RuntimeException('Cannot create deflated fixture.');
        }
        $bytes = file_get_contents($path);
        if ($bytes === false || substr($bytes, 0, 4) !== "PK\x03\x04") {
            throw new RuntimeException('Cannot locate ZIP local header.');
        }
        // Local header: name length at 26, extra length at 28, data after byte 30.
        $lengths = unpack('vname/vextra', substr($bytes, 26, 4));
        if ($lengths === false || !is_int($lengths['name']) || !is_int($lengths['extra'])) {
            throw new RuntimeException('Cannot locate deflated payload.');
        }
        $offset = 30 + $lengths['name'] + $lengths['extra'];
        // BFINAL=1, BTYPE=3 (reserved/invalid). Keep all ZIP metadata intact:
        // CHECKCONS checks headers; the DEFLATE decoder must reject this payload.
        $bytes[$offset] = "\x07";
        if (file_put_contents($path, $bytes) === false) {
            throw new RuntimeException('Cannot write corrupt fixture.');
        }
        return $path;
    }

    /** @param list<array{name: string, payload?: string, mode?: int, encrypted?: bool}> $entries */
    public static function create(TemporaryWorkspace $workspace, array $entries, bool $encrypted = false, #[SensitiveParameter] string $password = 'secret', int $method = ZipArchive::EM_AES_256): string
    {
        if ($entries === []) {
            return $workspace->file("PK\x05\x06" . str_repeat("\0", 18));
        }
        $path = $workspace->file();
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create ZIP fixture.');
        }
        foreach ($entries as $entry) {
            if (!(str_ends_with($entry['name'], '/') ? $zip->addEmptyDir($entry['name']) : $zip->addFromString($entry['name'], $entry['payload'] ?? 'abc'))) {
                throw new RuntimeException('Cannot add fixture entry.');
            }
            if (isset($entry['mode'])) {
                $zip->setExternalAttributesName($entry['name'], ZipArchive::OPSYS_UNIX, $entry['mode'] << 16);
            }
            if (($entry['encrypted'] ?? $encrypted) && !$zip->setEncryptionName($entry['name'], $method, $password)) {
                throw new RuntimeException('Cannot encrypt fixture.');
            }
        }
        if (!@$zip->close()) {
            throw new RuntimeException('Cannot finalize fixture.');
        }
        return $path;
    }
}
