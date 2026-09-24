<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\ExtractionOptions;
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
            self::markTestSkipped('Runtime cannot create AES-256 fixture.');
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
    public function testCorruptPayloadWithConsistentMetadataIsRejectedByBothOperations(): void
    {
        $path = ZipFixtureFactory::corruptDeflatePayload($this->workspace);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) === true);
        try {
            $stat = $zip->statIndex(0);
            self::assertIsArray($stat);
            self::assertSame(7000, $stat['size']);
            self::assertSame(ZipArchive::CM_DEFLATE, $stat['comp_method']);
        } finally {
            $zip->close();
        }
        $guard = new ArchiveGuard();
        $policy = new ArchivePolicy(100000, 10, 10000, 10000);
        $destination = $this->workspace->directory() . '/result';
        $message = null;
        foreach ([false, true] as $extract) {
            try {
                if ($extract) {
                    $guard->extract($path, $destination, $policy, ExtractionOptions::atomic());
                } else {
                    $guard->inspect($path, $policy);
                }
                self::fail('Corrupt ZIP payload accepted.');
            } catch (ArchiveOpenException $e) {
                if ($message === null) {
                    $message = $e->getMessage();
                }
                self::assertSame($message, $e->getMessage());
                self::assertStringContainsString('payload', $e->getMessage());
            }
        }
        self::assertFileDoesNotExist($destination);
        self::assertSame([], glob(dirname($destination) . '/.archive-guard-stage-*'));
    }

    public function testPayloadAcrossChunksAndEmptyFile(): void
    {
        $payload = str_repeat('payload', 5000);
        $path = ZipFixtureFactory::create($this->workspace, [
            ['name' => 'large', 'payload' => $payload],
            ['name' => 'empty', 'payload' => ''],
            ['name' => 'directory/'],
        ]);
        $policy = new ArchivePolicy(100000, 10, 40000, 40000);
        $guard = new ArchiveGuard();
        self::assertTrue($guard->inspect($path, $policy)->isAccepted());
        $destination = $this->workspace->directory() . '/result';
        $result = $guard->extract($path, $destination, $policy, ExtractionOptions::atomic());
        self::assertSame($payload, file_get_contents($destination . '/large'));
        self::assertSame('', file_get_contents($destination . '/empty'));
        self::assertSame(strlen($payload), $result->bytesWritten());
        self::assertSame(2, $result->filesExtracted());
        self::assertSame(1, $result->directoriesCreated());
    }

    /** @return list<ViolationCode> */
    private function codes(string $path, ArchivePolicy $policy): array
    {
        return array_map(static fn(Violation $v): ViolationCode => $v->code, new ArchiveGuard()->inspect($path, $policy)->violations());
    }
}
