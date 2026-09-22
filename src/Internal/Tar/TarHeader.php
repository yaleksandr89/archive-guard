<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Tar;

use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;

/** @internal */
final readonly class TarHeader
{
    private function __construct(public string $name, public string $type, public int $size, public bool $unsupported) {}
    public static function parse(string $block): self
    {
        $checksum = self::number(substr($block, 148, 8), 8);
        $sum = 256;
        for ($i = 0; $i < 512; ++$i) {
            if ($i < 148 || $i >= 156) {
                $sum += ord($block[$i]);
            }
        }
        if ($checksum !== $sum) {
            throw new ArchiveOpenException('Invalid TAR checksum.');
        }
        $magic = substr($block, 257, 8);
        $gnu = $magic === "ustar  \0";
        if (!$gnu && $magic !== "ustar\00000") {
            throw new ArchiveOpenException('Unsupported TAR magic/version.');
        }
        $unsupported = false;
        $size = 0;
        $fields = [[100, 8], [108, 8], [116, 8], [124, 12], [136, 12], [329, 8], [337, 8]];
        if ($gnu) {
            $fields = [...$fields, [345, 12], [357, 12], [369, 12], [483, 12]];
            // Old GNU sparse/continuation bookkeeping changes payload layout.
            if (trim(substr($block, 381, 102), "\0 ") !== '') {
                $unsupported = true;
            }
        }
        foreach ($fields as [$offset, $width]) {
            $field = substr($block, $offset, $width);
            if ((ord($field[0]) & 128) !== 0) {
                $unsupported = true;
                continue;
            }
            $value = self::number($field, 8);
            if ($gnu && ($offset === 369 || $offset === 483) && $value !== 0) {
                $unsupported = true;
            }
            if ($offset === 124) {
                $size = $value;
            }
        }
        // GNU's tail fields are not a POSIX prefix.
        $name = self::text(substr($block, 0, 100));
        if (!$gnu) {
            $prefix = self::text(substr($block, 345, 155));
            if ($prefix !== '') {
                $name = $prefix . '/' . $name;
            }
        }
        return new self($name, $block[156], $size, $unsupported);
    }
    private static function text(string $field): string
    {
        $end = strpos($field, "\0");
        if ($end === false) {
            return $field;
        }
        if (trim(substr($field, $end), "\0") !== '') {
            throw new ArchiveOpenException('Ambiguous TAR string field.');
        }
        return substr($field, 0, $end);
    }
    public static function number(string $field, int $base): int
    {
        $digits = trim($field, " \0");
        if ($digits === '') {
            return 0;
        }
        $pattern = $base === 8 ? '/^[0-7]+$/D' : '/^[0-9]+$/D';
        if (preg_match($pattern, $digits) !== 1) {
            throw new ArchiveOpenException('Invalid TAR numeric field.');
        }
        $value = 0;
        foreach (str_split($digits) as $digit) {
            $n = ord($digit) - 48;
            if ($value > intdiv(PHP_INT_MAX - $n, $base)) {
                throw new ArchiveOpenException('TAR numeric field overflows.');
            }
            $value = $value * $base + $n;
        }
        return $value;
    }
}
