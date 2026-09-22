<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Tests\Support\TarFixtureFactory as Tar;
use Yaleksandr\ArchiveGuard\Tests\Support\TemporaryWorkspace;
use Yaleksandr\ArchiveGuard\Tests\Support\ZipFixtureFactory as Zip;
use Yaleksandr\ArchiveGuard\Violation;
use Yaleksandr\ArchiveGuard\ViolationCode;

final class ArchiveGuardInspectionTest extends TestCase
{
    private TemporaryWorkspace $workspace;
    protected function setUp(): void
    {
        $this->workspace = new TemporaryWorkspace();
    }
    protected function tearDown(): void
    {
        $this->workspace->close();
    }

    /** @return iterable<string, array{ArchiveFormat, list<string>, ?ViolationCode}> */
    public static function paths(): iterable
    {
        $cases = [
            'file' => [['ok'], null], 'directory' => [['foo/', 'foo/bar'], null],
            'parent' => [['../x'], ViolationCode::UnsafePath], 'backslash' => [['a\\..\\x'], ViolationCode::UnsafePath],
            'absolute' => [['/x'], ViolationCode::UnsafePath], 'drive' => [['C:\\x'], ViolationCode::UnsafePath],
            'unc' => [['\\\\server\\x'], ViolationCode::UnsafePath], 'device' => [['\\\\?\\C:\\x'], ViolationCode::UnsafePath],
            'root directory' => [['./', './file.txt', './sub/', './sub/file.txt'], null],
            'root aliases' => [['./', '././'], null],
            'empty canonical file' => [['.'], ViolationCode::UnsafePath],
            'absolute directory' => [['/'], ViolationCode::UnsafePath],
            'parent directory' => [['../'], ViolationCode::UnsafePath],
            'relative parent directory' => [['./../'], ViolationCode::UnsafePath],
            'drive directory' => [['C:/'], ViolationCode::UnsafePath],
            'unc directory' => [['//server/share/'], ViolationCode::UnsafePath],
            'device directory' => [['//?/C:/'], ViolationCode::UnsafePath],
            'canonical duplicate' => [['foo//bar', 'foo/./bar'], ViolationCode::PathCollision],
            'separator duplicate' => [['foo\\bar', 'foo/bar'], ViolationCode::PathCollision],
            'directory conflict' => [['foo', 'foo/'], ViolationCode::PathCollision],
            'prefix' => [['foo', 'foo/bar'], ViolationCode::PathCollision],
            'inverse prefix' => [['foo/bar', 'foo'], ViolationCode::PathCollision],
            'implicit directory' => [['foo/bar', 'foo/'], null],
        ];
        foreach ([ArchiveFormat::Zip, ArchiveFormat::Tar, ArchiveFormat::TarGz] as $format) {
            foreach ($cases as $label => [$names, $code]) {
                yield $format->value . ':' . $label => [$format, $names, $code];
            }
        }
    }
    /** @param list<string> $names */
    #[DataProvider('paths')]
    #[TestDox('Формат определяется по содержимому, пути проверяются по общей политике')]
    public function testPaths(ArchiveFormat $format, array $names, ?ViolationCode $expected): void
    {
        if ($format === ArchiveFormat::Zip) {
            $path = Zip::create($this->workspace, array_map(static fn(string $name): array => ['name' => $name, 'payload' => ''], $names));
        } else {
            $records = '';
            foreach ($names as $name) {
                $records .= Tar::record($name, '', str_ends_with($name, '/') ? '5' : '0');
            }
            $bytes = Tar::archive($records);
            $path = $this->workspace->file($format === ArchiveFormat::TarGz ? Tar::gzip($bytes) : $bytes);
        }
        $result = new ArchiveGuard()->inspect($path, new ArchivePolicy(100000, 20, 10000, 20000));
        self::assertSame($format, $result->format());
        self::assertSame($expected === null, $result->isAccepted());
        $codes = array_map(static fn(Violation $v): ViolationCode => $v->code, $result->violations());
        if ($expected === null) {
            self::assertSame([], $codes);
        } else {
            self::assertContains($expected, $codes);
            self::assertSame($names[count($names) - 1], $result->violations()[0]->entryName);
            self::assertNotSame('', $result->violations()[0]->message);
        }
    }
    #[TestDox('Пустые архивы всех поддерживаемых форматов принимаются')]
    public function testEmptyArchives(): void
    {
        foreach ([ArchiveFormat::Zip, ArchiveFormat::Tar, ArchiveFormat::TarGz] as $format) {
            $path = $format === ArchiveFormat::Zip ? Zip::create($this->workspace, []) : $this->workspace->file($format === ArchiveFormat::Tar ? Tar::archive('') : Tar::gzip(Tar::archive('')));
            $result = new ArchiveGuard()->inspect($path, new ArchivePolicy(2048, 1, 1, 1));
            self::assertSame($format, $result->format());
            self::assertTrue($result->isAccepted());
        }
    }
    /** @return iterable<string, array{string}> */
    public static function malformed(): iterable
    {
        yield 'random' => ['not an archive'];
        yield 'gzip not tar' => [Tar::gzip('not tar')];
        yield 'truncated zip' => ["PK\x03\x04bad"];
        yield 'gzip crc' => [substr_replace(Tar::gzip(Tar::archive('')), "\xff\xff\xff\xff", -8, 4)];
        yield 'truncated gzip trailer' => [substr(Tar::gzip(Tar::archive('')), 0, -4)];
        yield 'trailing gzip byte' => [Tar::gzip(Tar::archive('')) . 'x'];
        yield 'concatenated gzip member' => [Tar::gzip(Tar::archive('')) . Tar::gzip(Tar::archive(''))];
    }
    #[DataProvider('malformed')]
    #[TestDox('Нераспознанные и повреждённые архивы вызывают исключение')]
    public function testMalformedContent(string $contents): void
    {
        $this->expectException(ArchiveOpenException::class);
        new ArchiveGuard()->inspect($this->workspace->file($contents), new ArchivePolicy(10000, 10, 1000, 1000));
    }
    #[TestDox('Несуществующий файл, каталог и потоковый URL не являются допустимым источником')]
    public function testInvalidSources(): void
    {
        $path = $this->workspace->file();
        unlink($path);
        foreach ([$path, sys_get_temp_dir(), 'php://memory'] as $source) {
            try {
                new ArchiveGuard()->inspect($source, new ArchivePolicy(1, 1, 1, 1));
                self::fail('Source accepted.');
            } catch (ArchiveOpenException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }
    #[TestDox('Превышение размера источника останавливает проверку до разбора записей')]
    public function testSourceSizeLimit(): void
    {
        foreach ([Zip::create($this->workspace, [['name' => '../bad']]), $this->workspace->file(Tar::archive(Tar::record('../bad'))), $this->workspace->file(Tar::gzip(Tar::archive(Tar::record('../bad'))))] as $path) {
            $result = new ArchiveGuard()->inspect($path, new ArchivePolicy(1, 1, 1, 1));
            self::assertSame([ViolationCode::ArchiveTooLarge], array_map(static fn(Violation $v): ViolationCode => $v->code, $result->violations()));
        }
    }
}
