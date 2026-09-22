<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests\Support;

use RuntimeException;

final class TarFixtureFactory
{
    public static function record(string $name, string $payload = '', string $type = '0', ?int $declaredSize = null, string $prefix = '', bool $gnu = false): string
    {
        $header = str_pad($name, 100, "\0") . "0000644\0" . "0000000\0" . "0000000\0"
            . sprintf('%011o', $declaredSize ?? strlen($payload)) . "\0" . "00000000000\0" . '        ' . $type
            . str_repeat("\0", 100) . ($gnu ? "ustar  \0" : "ustar\00000")
            . str_repeat("\0", 64) . "0000000\0" . "0000000\0" . str_pad($prefix, 155, "\0") . str_repeat("\0", 12);
        return self::checksum($header) . $payload . str_repeat("\0", (512 - strlen($payload) % 512) % 512);
    }
    public static function checksum(string $header): string
    {
        $header = substr_replace($header, '        ', 148, 8);
        $sum = array_sum(array_map(ord(...), str_split($header)));
        return substr_replace($header, sprintf('%06o', $sum) . "\0 ", 148, 8);
    }
    /** @param array<string, string> $values */
    public static function pax(array $values): string
    {
        $payload = '';
        foreach ($values as $key => $value) {
            $body = ' ' . $key . '=' . $value . "\n";
            $length = strlen($body) + 1;
            while (strlen((string) $length) + strlen($body) !== $length) {
                $length = strlen((string) $length) + strlen($body);
            }
            $payload .= $length . $body;
        }
        return $payload;
    }
    public static function archive(string $records): string
    {
        return $records . str_repeat("\0", 1024);
    }
    public static function gzip(string $contents): string
    {
        $data = gzencode($contents);
        if ($data === false) {
            throw new RuntimeException('Cannot compress fixture.');
        }
        return $data;
    }
}
