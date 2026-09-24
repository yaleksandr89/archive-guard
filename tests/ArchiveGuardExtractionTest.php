<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionConflictStrategy;
use Yaleksandr\ArchiveGuard\ExtractionMode;
use Yaleksandr\ArchiveGuard\ExtractionOptions;
use Yaleksandr\ArchiveGuard\Internal\Extraction\ExtractionWorkspace;
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
        $result = new ArchiveGuard()->extract($path, $destination, $this->policy(), ExtractionOptions::atomic());
        self::assertSame($format, $result->format());
        self::assertSame(2, $result->filesExtracted());
        self::assertSame(2, $result->directoriesCreated());
        self::assertSame(5, $result->bytesWritten());
        self::assertSame(0, $result->filesSkipped());
        self::assertSame(0, $result->filesOverwritten());
        self::assertSame('abc', file_get_contents($destination . '/a/b/one.txt'));
        self::assertSame('de', file_get_contents($destination . '/a/two.txt'));
    }

    #[DataProvider('formats')]
    #[TestDox('Пустой архив публикует пустой конечный каталог')]
    public function testEmptyExtraction(ArchiveFormat $format): void
    {
        $destination = $this->workspace->directory() . '/result';
        $result = new ArchiveGuard()->extract($this->source($format, true), $destination, $this->policy(), ExtractionOptions::atomic());
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
                $guard->extract($source, $destination, $this->policy(), ExtractionOptions::atomic());
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
        new ArchiveGuard()->extract($this->source(ArchiveFormat::Zip, false), $link, $this->policy(), ExtractionOptions::atomic());
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
            new ArchiveGuard()->extract($source, $destination, $this->policy(), ExtractionOptions::atomic());
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
            new ArchiveGuard()->extract($this->source(ArchiveFormat::Zip, false), $destination, new ArchivePolicy(1, 10, 100, 100), ExtractionOptions::atomic());
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
        $options = ExtractionOptions::atomic();
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

    public function testOptionsState(): void
    {
        self::assertTrue(new ReflectionMethod(ExtractionOptions::class, '__construct')->isPrivate());
        self::assertSame(ExtractionMode::Atomic, ExtractionOptions::atomic()->mode());
        self::assertNull(ExtractionOptions::atomic()->conflictStrategy());
        foreach (ExtractionConflictStrategy::cases() as $strategy) {
            $options = ExtractionOptions::merge($strategy);
            self::assertSame(ExtractionMode::Merge, $options->mode());
            self::assertSame($strategy, $options->conflictStrategy());
        }
    }

    /** @return iterable<string, array{ArchiveFormat, ExtractionConflictStrategy}> */
    public static function mergeCases(): iterable
    {
        foreach (ArchiveFormat::cases() as $format) {
            foreach (ExtractionConflictStrategy::cases() as $strategy) {
                yield $format->value . '/' . $strategy->name => [$format, $strategy];
            }
        }
    }

    #[DataProvider('mergeCases')]
    public function testMergeSuccess(ArchiveFormat $format, ExtractionConflictStrategy $strategy): void
    {
        $destination = $this->workspace->directory() . '/result';
        mkdir($destination);
        mkdir($destination . '/a');
        file_put_contents($destination . '/unrelated', 'keep');
        $result = new ArchiveGuard()->extract($this->source($format, false), $destination, $this->policy(), ExtractionOptions::merge($strategy));
        self::assertSame($format, $result->format());
        self::assertSame(2, $result->filesExtracted());
        self::assertSame(1, $result->directoriesCreated());
        self::assertSame(5, $result->bytesWritten());
        self::assertSame(0, $result->filesSkipped());
        self::assertSame(0, $result->filesOverwritten());
        self::assertSame('abc', file_get_contents($destination . '/a/b/one.txt'));
        self::assertSame('de', file_get_contents($destination . '/a/two.txt'));
        self::assertSame('keep', file_get_contents($destination . '/unrelated'));
        self::assertSame([], glob(dirname($destination) . '/.archive-guard-stage-*'));
    }

    #[DataProvider('mergeCases')]
    public function testMergeFileConflict(ArchiveFormat $format, ExtractionConflictStrategy $strategy): void
    {
        $destination = $this->workspace->directory() . '/result';
        mkdir($destination);
        mkdir($destination . '/a');
        file_put_contents($destination . '/a/two.txt', 'original');
        file_put_contents($destination . '/unrelated', 'keep');
        try {
            $result = new ArchiveGuard()->extract($this->source($format, false), $destination, $this->policy(), ExtractionOptions::merge($strategy));
            self::assertNotSame(ExtractionConflictStrategy::Reject, $strategy);
            $skip = $strategy === ExtractionConflictStrategy::Skip;
            self::assertSame($skip ? 'original' : 'de', file_get_contents($destination . '/a/two.txt'));
            self::assertSame('abc', file_get_contents($destination . '/a/b/one.txt'));
            self::assertSame($skip ? 1 : 2, $result->filesExtracted());
            self::assertSame(1, $result->directoriesCreated());
            self::assertSame($skip ? 3 : 5, $result->bytesWritten());
            self::assertSame($skip ? 1 : 0, $result->filesSkipped());
            self::assertSame($skip ? 0 : 1, $result->filesOverwritten());
        } catch (ExtractionException $e) {
            if ($strategy !== ExtractionConflictStrategy::Reject) {
                throw $e;
            }
            self::assertStringContainsString('conflicts', $e->getMessage());
            self::assertSame(['.', '..', 'two.txt'], scandir($destination . '/a'));
            self::assertSame('original', file_get_contents($destination . '/a/two.txt'));
        }
        self::assertSame('keep', file_get_contents($destination . '/unrelated'));
        self::assertSame([], glob(dirname($destination) . '/.archive-guard-stage-*'));
        $next = ExtractionWorkspace::merge($destination);
        $next->close();
    }

    /** @return iterable<string, array{ArchiveFormat, ExtractionConflictStrategy, string}> */
    public static function unsafeMergeCases(): iterable
    {
        foreach (self::mergeCases() as $name => [$format, $strategy]) {
            foreach (['file-to-directory', 'directory-to-file', 'file-link', 'directory-link', 'dangling-link', 'fifo'] as $fixture) {
                yield $name . '/' . $fixture => [$format, $strategy, $fixture];
            }
        }
    }

    #[DataProvider('unsafeMergeCases')]
    public function testMergeUnsafeTargets(ArchiveFormat $format, ExtractionConflictStrategy $strategy, string $fixture): void
    {
        $destination = $this->workspace->directory() . '/result';
        mkdir($destination);
        mkdir($destination . '/a');
        $outside = $this->workspace->directory();
        file_put_contents($outside . '/keep', 'safe');
        $target = $destination . '/a/two.txt';
        if ($fixture === 'file-to-directory') {
            mkdir($target);
        } elseif ($fixture === 'directory-to-file') {
            $target = $destination . '/a/b';
            file_put_contents($target, 'safe');
        } elseif ($fixture === 'fifo') {
            if (!function_exists('posix_mkfifo') || !@posix_mkfifo($target, 0600)) {
                self::markTestSkipped('Runtime cannot create a FIFO target fixture.');
            }
        } else {
            $referent = $outside . '/keep';
            if ($fixture === 'directory-link') {
                $target = $destination . '/a/b';
                $referent = $outside;
            } elseif ($fixture === 'dangling-link') {
                $referent = $outside . '/missing';
            }
            if (!@symlink($referent, $target)) {
                self::markTestSkipped('Runtime cannot create the affected-path symlink fixture.');
            }
        }
        $before = scandir($destination . '/a');
        try {
            new ArchiveGuard()->extract($this->source($format, false), $destination, $this->policy(), ExtractionOptions::merge($strategy));
            self::fail('Unsafe merge conflict accepted.');
        } catch (ExtractionException) {
            self::assertSame($before, scandir($destination . '/a'));
            self::assertFileDoesNotExist($destination . '/a/b/one.txt');
            self::assertSame(['.', '..', 'keep'], scandir($outside));
            self::assertSame('safe', file_get_contents($outside . '/keep'));
            self::assertSame([], glob(dirname($destination) . '/.archive-guard-stage-*'));
        }
    }

    #[DataProvider('formats')]
    public function testMergeEmptyArchiveAndInvalidRoots(ArchiveFormat $format): void
    {
        $destination = $this->workspace->directory();
        file_put_contents($destination . '/keep', 'safe');
        $source = $this->source($format, true);
        $guard = new ArchiveGuard();
        $options = ExtractionOptions::merge(ExtractionConflictStrategy::Reject);
        $result = $guard->extract($source, $destination, $this->policy(), $options);
        self::assertSame(0, $result->filesExtracted());
        self::assertSame(0, $result->directoriesCreated());
        self::assertSame(0, $result->bytesWritten());
        foreach ([$destination . '/absent', $destination . '/keep', $destination . '/.archive-guard-locks'] as $invalid) {
            try {
                $guard->extract($source, $invalid, $this->policy(), $options);
                self::fail('Invalid merge root accepted.');
            } catch (ExtractionException) {
                self::assertSame('safe', file_get_contents($destination . '/keep'));
            }
        }
        $link = $destination . '/link';

        if (!@symlink($destination, $link)) {
            self::markTestSkipped('Runtime cannot create the merge root symlink fixture.');
        }

        $this->expectException(ExtractionException::class);

        $guard->extract(
            $source,
            $link,
            $this->policy(),
            $options,
        );
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testMergeOverwriteReadOnlyFileUnderWritableParent(): void
    {
        $destination = $this->workspace->directory() . '/result';
        mkdir($destination);
        $target = $destination . '/conflict.txt';
        file_put_contents($target, 'original');
        chmod($target, 0444);
        try {
            if (is_writable($target)) {
                self::markTestSkipped('Runtime can write to the read-only file fixture.');
            }
            self::assertTrue(is_writable($destination));
            $source = Zip::create($this->workspace, [
                ['name' => 'new.txt', 'payload' => 'new'],
                ['name' => 'conflict.txt', 'payload' => 'replacement'],
            ]);
            $result = new ArchiveGuard()->extract($source, $destination, $this->policy(), ExtractionOptions::merge(ExtractionConflictStrategy::Overwrite));
            self::assertSame('replacement', file_get_contents($target));
            self::assertSame('new', file_get_contents($destination . '/new.txt'));
            self::assertSame(2, $result->filesExtracted());
            self::assertSame(0, $result->directoriesCreated());
            self::assertSame(14, $result->bytesWritten());
            self::assertSame(0, $result->filesSkipped());
            self::assertSame(1, $result->filesOverwritten());
        } finally {
            chmod($target, 0644);
        }
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testMergeSkipInReadOnlyTraversableDirectory(): void
    {
        $destination = $this->workspace->directory() . '/result';
        mkdir($destination);
        $nested = $destination . '/existing';
        mkdir($nested);
        $target = $nested . '/conflict.txt';
        file_put_contents($target, 'original');
        chmod($nested, 0555);
        try {
            if (is_writable($nested)) {
                self::markTestSkipped('Runtime can write to the read-only directory fixture.');
            }
            self::assertTrue(is_readable($nested));
            self::assertTrue(is_executable($nested));
            $source = Zip::create($this->workspace, [['name' => 'existing/conflict.txt', 'payload' => 'replacement']]);
            $result = new ArchiveGuard()->extract($source, $destination, $this->policy(), ExtractionOptions::merge(ExtractionConflictStrategy::Skip));
            self::assertSame('original', file_get_contents($target));
            self::assertSame(1, $result->filesSkipped());
            self::assertSame(0, $result->filesExtracted());
            self::assertSame(0, $result->directoriesCreated());
            self::assertSame(0, $result->bytesWritten());
            self::assertSame(0, $result->filesOverwritten());
        } finally {
            chmod($nested, 0755);
        }
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testMergeSkipInReadOnlyRoot(): void
    {
        $parent = $this->workspace->directory();
        $destination = $parent . '/result';
        mkdir($destination);
        $target = $destination . '/conflict.txt';
        file_put_contents($target, 'original');
        chmod($destination, 0555);
        try {
            if (is_writable($destination)) {
                self::markTestSkipped('Runtime can write to the read-only Merge root fixture.');
            }
            self::assertTrue(is_readable($destination));
            self::assertTrue(is_executable($destination));
            $source = Zip::create($this->workspace, [['name' => 'conflict.txt', 'payload' => 'replacement']]);
            $result = new ArchiveGuard()->extract($source, $destination, $this->policy(), ExtractionOptions::merge(ExtractionConflictStrategy::Skip));
            self::assertSame('original', file_get_contents($target));
            self::assertSame(1, $result->filesSkipped());
            self::assertSame(0, $result->filesExtracted());
            self::assertSame(0, $result->directoriesCreated());
            self::assertSame(0, $result->bytesWritten());
            self::assertSame(0, $result->filesOverwritten());
            self::assertSame([], glob($parent . '/.archive-guard-stage-*'));
        } finally {
            chmod($destination, 0755);
        }
    }

    /** @return iterable<string, array{ArchiveFormat, bool}> */
    public static function tarPasswordCases(): iterable
    {
        foreach ([ArchiveFormat::Tar, ArchiveFormat::TarGz] as $format) {
            foreach ([false, true] as $merge) {
                yield $format->value . '/' . ($merge ? 'merge' : 'atomic') => [$format, $merge];
            }
        }
    }

    #[DataProvider('tarPasswordCases')]
    public function testZipPasswordRejectedForTarBeforeWorkspace(ArchiveFormat $format, bool $merge): void
    {
        $source = $this->source($format, false);
        $destination = $this->workspace->directory() . '/result';
        if ($merge) {
            mkdir($destination);
            file_put_contents($destination . '/keep.txt', 'keep');
        }
        try {
            new ArchiveGuard()->extract($source, $destination, $this->policy(), $merge ? ExtractionOptions::merge(ExtractionConflictStrategy::Reject) : ExtractionOptions::atomic(), zipPassword: 'tar-secret-marker');
            self::fail('ZIP password accepted for TAR.');
        } catch (ExtractionException $e) {
            self::assertStringNotContainsString('tar-secret-marker', $e->getMessage());
            if ($merge) {
                self::assertSame(['.', '..', 'keep.txt'], scandir($destination));
                self::assertSame('keep', file_get_contents($destination . '/keep.txt'));
            } else {
                self::assertFileDoesNotExist($destination);
            }
            self::assertFileDoesNotExist(dirname($destination) . '/.archive-guard-locks');
            self::assertSame([], glob(dirname($destination) . '/.archive-guard-stage-*'));
        }
    }

    public function testArchiveSizePrecedesInvalidTarPassword(): void
    {
        $source = $this->source(ArchiveFormat::Tar, false);
        try {
            new ArchiveGuard()->extract($source, $this->workspace->directory() . '/result', new ArchivePolicy(1, 20, 10000, 20000), ExtractionOptions::atomic(), zipPassword: 'secret');
            self::fail('Oversized TAR accepted.');
        } catch (ArchiveRejectedException $e) {
            self::assertSame(ViolationCode::ArchiveTooLarge, $e->inspectionResult()->violations()[0]->code);
        }
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
