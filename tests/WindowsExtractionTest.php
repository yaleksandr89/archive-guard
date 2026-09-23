<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionConflictStrategy;
use Yaleksandr\ArchiveGuard\ExtractionOptions;
use Yaleksandr\ArchiveGuard\Internal\Extraction\ExtractionWorkspace;
use Yaleksandr\ArchiveGuard\Tests\Support\TarFixtureFactory as Tar;
use Yaleksandr\ArchiveGuard\Tests\Support\TemporaryWorkspace;

#[RequiresOperatingSystemFamily('Windows')]
final class WindowsExtractionTest extends TestCase
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

    /** @return iterable<string, array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'NTFS alternate data stream' => ['dir/file:stream.txt'];
        yield 'reserved device name' => ['CON'];
        yield 'reserved device name with extension' => ['NUL.txt'];
        yield 'invalid Win32 character' => ['bad?.txt'];
        yield 'trailing dot' => ['name.'];
        yield 'trailing space' => ['name '];
    }

    #[DataProvider('invalidNames')]
    #[TestDox('Недопустимое для Windows имя проходит проверку архива, но не извлекается')]
    public function testInvalidWindowsNameIsRejectedBeforeWriting(string $name): void
    {
        $this->assertRejectedBeforeWriting(Tar::record($name, 'payload'));
    }

    #[TestDox('Пути, различающиеся только регистром ASCII, не извлекаются в Windows')]
    public function testAsciiCaseInsensitiveCollisionIsRejectedBeforeWriting(): void
    {
        $this->assertRejectedBeforeWriting(Tar::record('Foo/file.txt', 'first') . Tar::record('foo/file.txt', 'second'));
    }

    #[TestDox('Эквивалентные не-ASCII имена Windows используют одну блокировку')]
    public function testNativeNonAsciiLockEquivalence(): void
    {
        $parent = $this->workspace->directory();
        $upper = $parent . '/Éxtraction';
        $lower = $parent . '/éxtraction';
        if (!@mkdir($upper)) {
            self::markTestSkipped('Runtime cannot create the non-ASCII Windows name fixture.');
        }
        clearstatcache(true, $lower);
        if (!is_dir($lower)) {
            self::markTestSkipped('This Windows filesystem does not equate Éxtraction and éxtraction.');
        }
        rmdir($upper);
        $first = ExtractionWorkspace::atomic($upper);
        try {
            try {
                ExtractionWorkspace::atomic($lower);
                self::fail('Equivalent Windows destination acquired a separate lock.');
            } catch (ExtractionException $e) {
                self::assertStringContainsString('locked', $e->getMessage());
            }
        } finally {
            $first->close();
        }
        $next = ExtractionWorkspace::atomic($lower);
        $next->close();
    }

    #[TestDox('Нативный эквивалент имени пространства блокировок Windows также зарезервирован')]
    public function testNativeReservedNamespaceAlias(): void
    {
        $parent = $this->workspace->directory();
        $namespace = $parent . '/.archive-guard-locks';
        mkdir($namespace);
        $alias = $parent . '/.ARCHIVE-GUARD-LOCKS';
        if (!is_dir($alias)) {
            self::markTestSkipped('This Windows filesystem distinguishes the reserved namespace case spellings.');
        }
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                ExtractionWorkspace::atomic($alias);
                self::fail('Native reserved namespace alias accepted.');
            } catch (ExtractionException $e) {
                self::assertStringContainsString('reserved for archive-guard internal locking', $e->getMessage());
            }
            if ($attempt === 0) {
                rmdir($namespace);
            }
        }
    }

    /** @return iterable<string, array{string, string, ExtractionConflictStrategy}> */
    public static function nativeMergeNames(): iterable
    {
        foreach (ExtractionConflictStrategy::cases() as $strategy) {
            yield 'ASCII/' . $strategy->name => ['FILE.txt', 'file.txt', $strategy];
            yield 'non-ASCII/' . $strategy->name => ['Étage.txt', 'étage.txt', $strategy];
        }
    }

    #[DataProvider('nativeMergeNames')]
    public function testNativeMergeConflict(string $existing, string $archived, ExtractionConflictStrategy $strategy): void
    {
        $destination = $this->workspace->directory() . '/result';
        mkdir($destination);
        file_put_contents($destination . '/' . $existing, 'original');
        if (!is_file($destination . '/' . $archived)) {
            self::markTestSkipped('This Windows filesystem does not alias ' . $existing . ' and ' . $archived . '.');
        }
        $source = $this->workspace->file(Tar::archive(Tar::record('a-new', 'new') . Tar::record($archived, 'replacement')));
        try {
            $result = new ArchiveGuard()->extract($source, $destination, new ArchivePolicy(100000, 20, 10000, 20000), ExtractionOptions::merge($strategy));
            self::assertNotSame(ExtractionConflictStrategy::Reject, $strategy);
            $skip = $strategy === ExtractionConflictStrategy::Skip;
            self::assertSame($skip ? 'original' : 'replacement', file_get_contents($destination . '/' . $existing));
            self::assertSame($skip ? 1 : 2, $result->filesExtracted());
            self::assertSame($skip ? 1 : 0, $result->filesSkipped());
            self::assertSame($skip ? 0 : 1, $result->filesOverwritten());
            self::assertSame($skip ? 3 : 14, $result->bytesWritten());
        } catch (ExtractionException $e) {
            if ($strategy !== ExtractionConflictStrategy::Reject) {
                throw $e;
            }
            self::assertSame('original', file_get_contents($destination . '/' . $existing));
            self::assertFileDoesNotExist($destination . '/a-new');
        }
    }

    public function testMergeReservedNamespaceAlias(): void
    {
        $parent = $this->workspace->directory();
        mkdir($parent . '/.archive-guard-locks');
        $alias = $parent . '/.ARCHIVE-GUARD-LOCKS';
        if (!is_dir($alias)) {
            self::markTestSkipped('This Windows filesystem distinguishes reserved namespace case spellings.');
        }
        try {
            ExtractionWorkspace::merge($alias);
            self::fail('Reserved merge alias accepted.');
        } catch (ExtractionException $e) {
            self::assertStringContainsString('reserved for archive-guard internal locking', $e->getMessage());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function mergeSafetyObjects(): iterable
    {
        yield 'file versus directory' => ['directory'];
        yield 'directory versus file' => ['file'];
        yield 'directory link' => ['directory-link'];
        yield 'file symlink' => ['file-link'];
    }

    #[DataProvider('mergeSafetyObjects')]
    public function testWindowsMergeSafety(string $fixture): void
    {
        $destination = $this->workspace->directory() . '/result';
        mkdir($destination);
        $outside = $this->workspace->directory();
        file_put_contents($outside . '/keep', 'safe');
        $target = $destination . '/z';
        if ($fixture === 'directory') {
            mkdir($target);
        } elseif ($fixture === 'file') {
            file_put_contents($target, 'safe');
        } elseif (!@symlink($fixture === 'directory-link' ? $outside : $outside . '/keep', $target)) {
            self::markTestSkipped('Windows runtime cannot create the ' . $fixture . ' fixture; symlink privileges or Developer Mode required.');
        }
        $nested = $fixture === 'file' || $fixture === 'directory-link';
        $source = $this->workspace->file(Tar::archive(Tar::record('a-new', 'new') . Tar::record($nested ? 'z/keep' : 'z', 'replacement')));
        foreach (ExtractionConflictStrategy::cases() as $strategy) {
            try {
                new ArchiveGuard()->extract($source, $destination, new ArchivePolicy(100000, 20, 10000, 20000), ExtractionOptions::merge($strategy));
                self::fail('Windows merge safety conflict accepted.');
            } catch (ExtractionException) {
                self::assertFileDoesNotExist($destination . '/a-new');
                self::assertSame('safe', file_get_contents($outside . '/keep'));
                self::assertSame(['.', '..', 'z'], scandir($destination));
            }
        }
    }

    public function testReadOnlyOverwriteFailsBeforeOtherWrites(): void
    {
        $destination = $this->workspace->directory() . '/result';
        mkdir($destination);
        $target = $destination . '/conflict.txt';
        file_put_contents($target, 'original');
        chmod($target, 0444);
        try {
            if (is_writable($target)) {
                self::markTestSkipped('Windows runtime cannot observe a read-only overwrite target fixture.');
            }
            $source = $this->workspace->file(Tar::archive(Tar::record('new.txt', 'new') . Tar::record('conflict.txt', 'replacement')));
            try {
                new ArchiveGuard()->extract($source, $destination, new ArchivePolicy(100000, 20, 10000, 20000), ExtractionOptions::merge(ExtractionConflictStrategy::Overwrite));
                self::fail('Read-only Windows overwrite target accepted.');
            } catch (ExtractionException $e) {
                self::assertStringContainsString('overwrite target is not writable', $e->getMessage());
                self::assertSame('original', file_get_contents($target));
                self::assertFileDoesNotExist($destination . '/new.txt');
                self::assertSame([], glob(dirname($destination) . '/.archive-guard-stage-*'));
            }
        } finally {
            chmod($target, 0644);
        }
    }

    private function assertRejectedBeforeWriting(string $records): void
    {
        $source = $this->workspace->file(Tar::archive($records));
        $destination = $this->workspace->directory() . '/result';
        $guard = new ArchiveGuard();
        $policy = new ArchivePolicy(100000, 20, 10000, 20000);

        $inspection = $guard->inspect($source, $policy);
        self::assertSame(ArchiveFormat::Tar, $inspection->format());
        self::assertTrue($inspection->isAccepted());

        try {
            $guard->extract($source, $destination, $policy, ExtractionOptions::atomic());
            self::fail('Windows-incompatible archive extracted.');
        } catch (ExtractionException) {
            self::assertFalse(file_exists($destination));
        }
    }
}
