<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Tests\Support\TemporaryWorkspace;
use Yaleksandr\ArchiveGuard\Tests\Support\ZipFixtureFactory;
use Yaleksandr\ArchiveGuard\Violation;
use Yaleksandr\ArchiveGuard\ViolationCode;
use ZipArchive;

final class ZipInspectionTest extends TestCase
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
    /** @return iterable<string, array{int, ViolationCode}> */
    public static function types(): iterable
    {
        yield 'symlink' => [0120777, ViolationCode::SymlinkEntry];
        yield 'fifo' => [0010644, ViolationCode::SpecialEntry];
        yield 'socket' => [0140644, ViolationCode::SpecialEntry];
    }
    #[DataProvider('types')]
    #[TestDox('UNIX-атрибуты ZIP запрещают ссылки и специальные файлы')]
    public function testForbiddenTypes(int $mode, ViolationCode $code): void
    {
        $path = ZipFixtureFactory::create($this->workspace, [['name' => 'entry', 'mode' => $mode]]);
        self::assertContains($code, $this->codes($path, new ArchivePolicy(4096, 5, 100, 100)));
    }
    #[TestDox('Зашифрованная ZIP-запись отклоняется без запроса пароля')]
    public function testEncryption(): void
    {
        if (!ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, true)) {
            self::markTestSkipped('Runtime cannot create AES fixture.');
        }
        $path = ZipFixtureFactory::create($this->workspace, [['name' => 'secret']], true);
        self::assertContains(ViolationCode::EncryptedEntry, $this->codes($path, new ArchivePolicy(4096, 5, 100, 100)));
    }
    #[TestDox('Превышение числа ZIP-записей не запускает проверку их путей')]
    public function testEntryCountStopsInspection(): void
    {
        $path = ZipFixtureFactory::create($this->workspace, [['name' => '../a'], ['name' => '../b']]);
        self::assertSame([ViolationCode::TooManyEntries], $this->codes($path, new ArchivePolicy(4096, 1, 100, 100)));
    }
    #[TestDox('Размеры ZIP ограничиваются на запись и суммарно без повторных суммарных нарушений')]
    public function testPayloadLimits(): void
    {
        $path = ZipFixtureFactory::create($this->workspace, [['name' => 'a'], ['name' => 'b'], ['name' => 'c']]);
        $codes = $this->codes($path, new ArchivePolicy(4096, 3, 2, 4));
        self::assertSame(3, count(array_filter($codes, static fn(ViolationCode $c): bool => $c === ViolationCode::EntryTooLarge)));
        self::assertSame(1, count(array_filter($codes, static fn(ViolationCode $c): bool => $c === ViolationCode::TotalSizeExceeded)));
    }
    #[TestDox('Эвристика сжатия ZIP срабатывает только при включённом пороге')]
    public function testCompressionRatio(): void
    {
        $path = ZipFixtureFactory::create($this->workspace, [['name' => 'a', 'payload' => str_repeat('a', 1000)]]);
        self::assertContains(ViolationCode::CompressionRatioExceeded, $this->codes($path, new ArchivePolicy(4096, 1, 1000, 1000, 2)));
        self::assertSame([], $this->codes($path, new ArchivePolicy(4096, 1, 1000, 1000)));
        $empty = ZipFixtureFactory::create($this->workspace, [['name' => 'empty', 'payload' => '']]);
        self::assertSame([], $this->codes($empty, new ArchivePolicy(4096, 1, 1, 1, 0.1)));
    }
    /** @return list<ViolationCode> */
    private function codes(string $path, ArchivePolicy $policy): array
    {
        return array_map(static fn(Violation $v): ViolationCode => $v->code, new ArchiveGuard()->inspect($path, $policy)->violations());
    }
}
