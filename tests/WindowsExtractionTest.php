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
use Yaleksandr\ArchiveGuard\ExtractionMode;
use Yaleksandr\ArchiveGuard\ExtractionOptions;
use Yaleksandr\ArchiveGuard\Internal\Extraction\AtomicExtractionWorkspace;
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
        $first = new AtomicExtractionWorkspace($upper);
        try {
            try {
                new AtomicExtractionWorkspace($lower);
                self::fail('Equivalent Windows destination acquired a separate lock.');
            } catch (ExtractionException $e) {
                self::assertStringContainsString('locked', $e->getMessage());
            }
        } finally {
            $first->close();
        }
        $next = new AtomicExtractionWorkspace($lower);
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
                new AtomicExtractionWorkspace($alias);
                self::fail('Native reserved namespace alias accepted.');
            } catch (ExtractionException $e) {
                self::assertStringContainsString('reserved for archive-guard internal locking', $e->getMessage());
            }
            if ($attempt === 0) {
                rmdir($namespace);
            }
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
            $guard->extract($source, $destination, $policy, new ExtractionOptions(ExtractionMode::Atomic));
            self::fail('Windows-incompatible archive extracted.');
        } catch (ExtractionException) {
            self::assertFalse(file_exists($destination));
        }
    }
}
