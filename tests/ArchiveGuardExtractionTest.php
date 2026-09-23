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
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionMode;
use Yaleksandr\ArchiveGuard\ExtractionOptions;
use Yaleksandr\ArchiveGuard\Tests\Support\TarFixtureFactory as Tar;
use Yaleksandr\ArchiveGuard\Tests\Support\TemporaryWorkspace;
use Yaleksandr\ArchiveGuard\Tests\Support\ZipFixtureFactory as Zip;
use Yaleksandr\ArchiveGuard\ViolationCode;

final class ArchiveGuardExtractionTest extends TestCase
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

    /** @return iterable<string, array{ArchiveFormat}> */
    public static function formats(): iterable
    {
        foreach (ArchiveFormat::cases() as $format) {
            yield $format->value => [$format];
        }
    }

    #[DataProvider('formats')]
    #[TestDox('Вложенные файлы извлекаются по содержимому архива с точными счётчиками')]
    public function testNestedExtraction(ArchiveFormat $format): void
    {
        $path = $this->source($format, false);
        $destination = $this->workspace->directory() . '/result';
        $result = new ArchiveGuard()->extract($path, $destination, $this->policy(), new ExtractionOptions(ExtractionMode::Atomic));
        self::assertSame($format, $result->format());
        self::assertSame(2, $result->filesExtracted());
        self::assertSame(2, $result->directoriesCreated());
        self::assertSame(5, $result->bytesWritten());
        self::assertSame('abc', file_get_contents($destination . '/a/b/one.txt'));
        self::assertSame('de', file_get_contents($destination . '/a/two.txt'));
    }

    #[DataProvider('formats')]
    #[TestDox('Пустой архив публикует пустой конечный каталог')]
    public function testEmptyExtraction(ArchiveFormat $format): void
    {
        $destination = $this->workspace->directory() . '/result';
        $result = new ArchiveGuard()->extract($this->source($format, true), $destination, $this->policy(), new ExtractionOptions(ExtractionMode::Atomic));
        self::assertSame($format, $result->format());
        self::assertSame(0, $result->filesExtracted());
        self::assertSame(0, $result->directoriesCreated());
        self::assertSame(0, $result->bytesWritten());
        self::assertSame(['.', '..'], scandir($destination));
    }

    #[DataProvider('formats')]
    #[TestDox('Недопустимая или занятая папка назначения не изменяется')]
    public function testDestinationPreflight(ArchiveFormat $format): void
    {
        $source = $this->source($format, false);
        $guard = new ArchiveGuard();
        $file = $this->workspace->file('existing');
        $empty = $this->workspace->directory();
        $nonempty = $this->workspace->directory();
        file_put_contents($nonempty . '/keep', 'keep');
        foreach ([$file . '/result', $nonempty . '/missing/result', $file, $nonempty, $empty, '', '.', '..', '//server/share/result', '\\\\server\\share\\result', 'php://memory', 'file://' . $nonempty . '/result', $nonempty . "\0bad"] as $destination) {
            try {
                $guard->extract($source, $destination, $this->policy(), new ExtractionOptions(ExtractionMode::Atomic));
                self::fail('Invalid destination accepted.');
            } catch (ExtractionException) {
                self::assertSame('keep', file_get_contents($nonempty . '/keep'));
                self::assertSame('existing', file_get_contents($file));
                self::assertSame(['.', '..'], scandir($empty));
            }
        }
    }

    #[TestDox('Ссылка в конечном пути назначения отклоняется')]
    public function testDestinationSymlink(): void
    {
        $real = $this->workspace->directory();
        $link = $this->workspace->directory() . '/link';
        if (!@symlink($real, $link)) {
            self::markTestSkipped('Runtime cannot create symlink fixture.');
        }
        $this->expectException(ExtractionException::class);
        new ArchiveGuard()->extract($this->source(ArchiveFormat::Zip, false), $link, $this->policy(), new ExtractionOptions(ExtractionMode::Atomic));
    }

    #[DataProvider('formats')]
    #[TestDox('Отклонённый архив не публикует назначение и сохраняет результат проверки')]
    public function testRejectedArchive(ArchiveFormat $format): void
    {
        $source = $format === ArchiveFormat::Zip
            ? Zip::create($this->workspace, [['name' => '../bad']])
            : $this->workspace->file($format === ArchiveFormat::Tar ? Tar::archive(Tar::record('../bad')) : Tar::gzip(Tar::archive(Tar::record('../bad'))));
        $destination = $this->workspace->directory() . '/result';
        try {
            new ArchiveGuard()->extract($source, $destination, $this->policy(), new ExtractionOptions(ExtractionMode::Atomic));
            self::fail('Unsafe archive accepted.');
        } catch (ArchiveRejectedException $e) {
            self::assertSame(ViolationCode::UnsafePath, $e->inspectionResult()->violations()[0]->code);
            self::assertSame($format, $e->inspectionResult()->format());
            self::assertFalse(file_exists($destination));
            self::assertSame([], glob(dirname($destination) . '/.archive-guard-stage-*'));
        }
    }

    #[TestDox('Превышение размера источника возвращает отклонение до записи')]
    public function testSourceLimitRejection(): void
    {
        $destination = $this->workspace->directory() . '/result';
        try {
            new ArchiveGuard()->extract($this->source(ArchiveFormat::Zip, false), $destination, new ArchivePolicy(1, 10, 100, 100), new ExtractionOptions(ExtractionMode::Atomic));
            self::fail('Oversize source accepted.');
        } catch (ArchiveRejectedException $e) {
            self::assertSame(ViolationCode::ArchiveTooLarge, $e->inspectionResult()->violations()[0]->code);
            self::assertFalse(file_exists($destination));
        }
    }

    #[TestDox('Имя внутреннего пространства блокировок зарезервировано, остальные имена с префиксом разрешены')]
    public function testReservedLockNamespace(): void
    {
        $parent = $this->workspace->directory();
        $guard = new ArchiveGuard();
        $source = $this->source(ArchiveFormat::Zip, false);
        $options = new ExtractionOptions(ExtractionMode::Atomic);
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $guard->extract($source, $parent . '/.archive-guard-locks', $this->policy(), $options);
                self::fail('Reserved namespace accepted.');
            } catch (ExtractionException $e) {
                self::assertStringContainsString('reserved for archive-guard internal locking', $e->getMessage());
            }
            if ($attempt === 0) {
                self::assertFalse(file_exists($parent . '/.archive-guard-locks'));
                $guard->extract($source, $parent . '/.archive-guard-other', $this->policy(), $options);
            }
        }
        self::assertSame('abc', file_get_contents($parent . '/.archive-guard-other/a/b/one.txt'));
        self::assertSame([], glob($parent . '/.archive-guard-stage-*'));
    }

    private function source(ArchiveFormat $format, bool $empty): string
    {
        if ($format === ArchiveFormat::Zip) {
            return Zip::create($this->workspace, $empty ? [] : [['name' => 'a/b/one.txt', 'payload' => 'abc'], ['name' => 'a/two.txt', 'payload' => 'de'], ['name' => 'a/', 'payload' => '']]);
        }
        $records = $empty ? '' : Tar::record('./', '', '5') . Tar::record('a/b/one.txt', 'abc') . Tar::record('a/two.txt', 'de') . Tar::record('a/', '', '5');
        $bytes = Tar::archive($records);
        return $this->workspace->file($format === ArchiveFormat::TarGz ? Tar::gzip($bytes) : $bytes);
    }

    private function policy(): ArchivePolicy
    {
        return new ArchivePolicy(100000, 20, 10000, 20000);
    }
}
