<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\ExtractionOptions;
use Yaleksandr\ArchiveGuard\Tests\Support\TarFixtureFactory as Tar;
use Yaleksandr\ArchiveGuard\Tests\Support\TemporaryWorkspace;
use Yaleksandr\ArchiveGuard\ViolationCode;

final class TarExtractionTest extends TestCase
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

    /** @return iterable<string, array{bool}> */
    public static function compression(): iterable
    {
        yield 'tar' => [false];
        yield 'tar.gz' => [true];
    }

    #[DataProvider('compression')]
    #[TestDox('GNU и PAX задают путь извлечения, а корневой маркер не создаёт каталог')]
    public function testEffectivePaths(bool $gzip): void
    {
        $long = str_repeat('nested/', 15) . 'gnu.txt';
        $records = Tar::record('./', '', '5')
            . Tar::record('long', $long . "\0", 'L', gnu: true) . Tar::record('ignored', 'G', gnu: true)
            . Tar::record('pax', Tar::pax(['path' => 'pax/sub/file.txt']), 'x') . Tar::record('../ignored', 'P');
        $bytes = Tar::archive($records);
        $source = $this->workspace->file($gzip ? Tar::gzip($bytes) : $bytes);
        $destination = $this->workspace->directory() . '/result';
        $result = new ArchiveGuard()->extract($source, $destination, $this->policy(), ExtractionOptions::atomic());
        self::assertSame($gzip ? ArchiveFormat::TarGz : ArchiveFormat::Tar, $result->format());
        self::assertSame('G', file_get_contents($destination . '/' . $long));
        self::assertSame('P', file_get_contents($destination . '/pax/sub/file.txt'));
        self::assertSame(2, $result->filesExtracted());
        self::assertSame(17, $result->directoriesCreated());
        self::assertSame(2, $result->bytesWritten());
    }

    /** @return iterable<string, array{string, ViolationCode}> */
    public static function rejectedRecords(): iterable
    {
        yield 'symlink' => [Tar::record('link', '', '2'), ViolationCode::SymlinkEntry];
        yield 'hardlink' => [Tar::record('link', '', '1'), ViolationCode::HardlinkEntry];
        yield 'special' => [Tar::record('device', '', '3'), ViolationCode::SpecialEntry];
        yield 'directory payload' => [Tar::record('dir', 'x', '5'), ViolationCode::UnsupportedFeature];
        yield 'regular slash payload' => [Tar::record('dir/', 'x'), ViolationCode::UnsupportedFeature];
        yield 'regular backslash payload' => [Tar::record('dir\\', 'x'), ViolationCode::UnsupportedFeature];
    }

    #[DataProvider('rejectedRecords')]
    #[TestDox('Недопустимый тип или данные каталога отклоняются проверкой до записи')]
    public function testRejectedRecords(string $record, ViolationCode $code): void
    {
        $source = $this->workspace->file(Tar::archive(Tar::record('good', 'ok') . $record));
        $destination = $this->workspace->directory() . '/result';
        $guard = new ArchiveGuard();
        self::assertContains($code, array_map(static fn($v) => $v->code, $guard->inspect($source, $this->policy())->violations()));
        try {
            $guard->extract($source, $destination, $this->policy(), ExtractionOptions::atomic());
            self::fail('Rejected archive extracted.');
        } catch (ArchiveRejectedException $e) {
            self::assertContains($code, array_map(static fn($v) => $v->code, $e->inspectionResult()->violations()));
            self::assertFalse(file_exists($destination));
        }
    }

    private function policy(): ArchivePolicy
    {
        return new ArchivePolicy(100000, 20, 10000, 20000);
    }
}
