<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests\Support;

use RuntimeException;
use ZipArchive;

final class ZipFixtureFactory
{
    /** @param list<array{name: string, payload?: string, mode?: int}> $entries */
    public static function create(TemporaryWorkspace $workspace, array $entries, bool $encrypted = false): string
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
            if ($encrypted && !$zip->setEncryptionName($entry['name'], ZipArchive::EM_AES_256, 'secret')) {
                throw new RuntimeException('Cannot encrypt fixture.');
            }
        }
        if (!$zip->close()) {
            throw new RuntimeException('Cannot finalize fixture.');
        }
        return $path;
    }
}
