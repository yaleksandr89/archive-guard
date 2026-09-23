<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\Internal\Extraction\AtomicExtractionWorkspace;
use Yaleksandr\ArchiveGuard\Tests\Support\TemporaryWorkspace;

final class AtomicExtractionWorkspaceTest extends TestCase
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

    #[TestDox('Промежуточная папка создаётся в разрешённом родителе и становится назначением только после публикации')]
    public function testSiblingStagingAndPublish(): void
    {
        $parent = $this->workspace->directory();
        mkdir($parent . '/nested');
        $final = $parent . '/result';
        $atomic = new AtomicExtractionWorkspace($parent . '/nested/../result');
        try {
            $staging = $atomic->stagingPath();
            self::assertSame(realpath($parent), dirname($staging));
            self::assertNotSame($final, $staging);
            self::assertFalse(file_exists($final));
            mkdir($staging . '/dir');
            file_put_contents($staging . '/dir/file', 'complete');
            $atomic->publish();
            self::assertFalse(file_exists($staging));
            self::assertSame('complete', file_get_contents($final . '/dir/file'));
        } finally {
            $atomic->close();
        }
    }

    #[TestDox('Отмена удаляет только созданное промежуточное дерево')]
    public function testAbortRemovesOnlyStaging(): void
    {
        $parent = $this->workspace->directory();
        file_put_contents($parent . '/keep', 'untouched');
        $atomic = new AtomicExtractionWorkspace($parent . '/result');
        $staging = $atomic->stagingPath();
        mkdir($staging . '/nested');
        file_put_contents($staging . '/nested/partial', 'partial');
        $atomic->close();
        $atomic->close();
        self::assertFalse(file_exists($staging));
        self::assertFalse(file_exists($parent . '/result'));
        self::assertSame('untouched', file_get_contents($parent . '/keep'));
    }

    #[TestDox('Одинаковое назначение блокируется без ожидания, после освобождения доступно снова')]
    public function testLockContentionAndRelease(): void
    {
        $parent = $this->workspace->directory();
        $first = new AtomicExtractionWorkspace($parent . '/result');
        try {
            try {
                new AtomicExtractionWorkspace($parent . '/./result');
                self::fail('Concurrent workspace acquired the same destination.');
            } catch (ExtractionException $e) {
                self::assertStringContainsString('locked', $e->getMessage());
            }
            $staging = $first->stagingPath();
            self::assertDirectoryExists($staging);
        } finally {
            $first->close();
        }
        self::assertFalse(file_exists($staging));
        $second = new AtomicExtractionWorkspace($parent . '/result');
        try {
            self::assertNotSame($staging, $second->stagingPath());
            self::assertDirectoryExists($second->stagingPath());
        } finally {
            $second->close();
        }
    }

    #[TestDox('Разные имена назначения не разделяют блокировку')]
    public function testDifferentDestinationsCanBeHeldTogether(): void
    {
        $parent = $this->workspace->directory();
        $first = new AtomicExtractionWorkspace($parent . '/first');
        try {
            $second = new AtomicExtractionWorkspace($parent . '/second');
            try {
                self::assertNotSame($first->stagingPath(), $second->stagingPath());
                self::assertDirectoryExists($first->stagingPath());
                self::assertDirectoryExists($second->stagingPath());
            } finally {
                $second->close();
            }
        } finally {
            $first->close();
        }
    }

    #[TestDox('Назначение, появившееся до публикации, сохраняется, промежуточные файлы удаляются')]
    public function testPublishRechecksDestination(): void
    {
        $parent = $this->workspace->directory();
        $final = $parent . '/result';
        $atomic = new AtomicExtractionWorkspace($final);
        $staging = $atomic->stagingPath();
        file_put_contents($staging . '/file', 'extracted');
        // Model a non-cooperating writer before the final check, not inside its rename gap.
        mkdir($final);
        file_put_contents($final . '/keep', 'external');
        try {
            $atomic->publish();
            self::fail('Existing destination replaced.');
        } catch (ExtractionException) {
            self::assertSame('external', file_get_contents($final . '/keep'));
        } finally {
            $atomic->close();
        }
        self::assertFalse(file_exists($staging));
        self::assertFalse(file_exists($final . '/file'));
    }

    #[TestDox('Висячая ссылка, появившаяся перед публикацией, не заменяется')]
    public function testPublishRejectsDanglingSymlink(): void
    {
        $parent = $this->workspace->directory();
        $final = $parent . '/result';
        $atomic = new AtomicExtractionWorkspace($final);
        $staging = $atomic->stagingPath();
        try {
            if (!@symlink($parent . '/missing', $final)) {
                self::markTestSkipped('Runtime cannot create symlink fixture.');
            }
            try {
                $atomic->publish();
                self::fail('Dangling destination link replaced.');
            } catch (ExtractionException) {
                self::assertTrue(is_link($final));
            }
        } finally {
            $atomic->close();
        }
        self::assertFalse(file_exists($staging));
    }

    #[TestDox('Очистка удаляет ссылки внутри промежуточной папки, не затрагивая их цели')]
    public function testCleanupDoesNotFollowSymlinks(): void
    {
        $outside = $this->workspace->directory();
        file_put_contents($outside . '/keep', 'safe');
        $atomic = new AtomicExtractionWorkspace($this->workspace->directory() . '/result');
        $staging = $atomic->stagingPath();
        try {
            if (!@symlink($outside, $staging . '/directory-link')
                || !@symlink($outside . '/keep', $staging . '/file-link')
                || !@symlink($outside . '/missing', $staging . '/dangling-link')) {
                self::markTestSkipped('Runtime cannot create symlink fixture.');
            }
        } finally {
            $atomic->close();
        }
        self::assertFalse(file_exists($staging));
        self::assertSame('safe', file_get_contents($outside . '/keep'));
        self::assertSame(['.', '..', 'keep'], scandir($outside));
    }

    #[TestDox('Ссылка вместо корня промежуточной папки удаляется без обхода цели')]
    public function testCleanupDoesNotFollowReplacedStagingRoot(): void
    {
        $outside = $this->workspace->directory();
        file_put_contents($outside . '/keep', 'safe');
        $atomic = new AtomicExtractionWorkspace($this->workspace->directory() . '/result');
        $staging = $atomic->stagingPath();
        try {
            rmdir($staging);
            if (!@symlink($outside, $staging)) {
                self::markTestSkipped('Runtime cannot create symlink fixture.');
            }
        } finally {
            $atomic->close();
        }
        self::assertFalse(is_link($staging));
        self::assertFalse(file_exists($staging));
        self::assertSame('safe', file_get_contents($outside . '/keep'));
    }

    #[TestDox('Ошибка переименования не публикует результат и не удерживает блокировку после закрытия')]
    public function testRenameFailureReleasesLockOnClose(): void
    {
        $final = $this->workspace->directory() . '/result';
        $atomic = new AtomicExtractionWorkspace($final);
        // Deterministically make rename fail without changing the final destination.
        rmdir($atomic->stagingPath());
        try {
            $atomic->publish();
            self::fail('Missing staging directory published.');
        } catch (ExtractionException $e) {
            self::assertSame('Cannot publish extracted directory.', $e->getMessage());
        } finally {
            $atomic->close();
        }
        self::assertFalse(file_exists($final));
        $next = new AtomicExtractionWorkspace($final);
        try {
            self::assertDirectoryExists($next->stagingPath());
        } finally {
            $next->close();
        }
    }

    #[RequiresOperatingSystemFamily('Linux')]
    #[TestDox('Неудачная очистка не скрывает исходную ошибку публикации')]
    public function testCleanupFailurePreservesPublishFailure(): void
    {
        $final = $this->workspace->directory() . '/result';
        $atomic = new AtomicExtractionWorkspace($final);
        $blocked = $atomic->stagingPath() . '/blocked';
        mkdir($blocked);
        file_put_contents($blocked . '/partial', 'partial');
        mkdir($final);
        chmod($blocked, 0000);
        try {
            if (is_readable($blocked)) {
                self::markTestSkipped('Runtime can bypass fixture permissions.');
            }
            $failure = null;
            try {
                try {
                    $atomic->publish();
                    self::fail('Existing destination replaced.');
                } catch (ExtractionException $e) {
                    $failure = $e;
                    throw $e;
                } finally {
                    $atomic->close($failure);
                }
            } catch (ExtractionException $e) {
                self::assertSame($failure, $e);
                self::assertSame('Final destination must not exist.', $e->getMessage());
            }
            self::assertDirectoryExists($blocked);
            self::assertSame(['.', '..'], scandir($final));
            rmdir($final);
            $next = new AtomicExtractionWorkspace($final);
            $next->close();
        } finally {
            chmod($blocked, 0700);
            $atomic->close();
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function cleanupModes(): iterable
    {
        yield 'explicit close' => [false];
        yield 'destructor' => [true];
    }

    #[RequiresOperatingSystemFamily('Linux')]
    #[DataProvider('cleanupModes')]
    #[TestDox('Ошибка очистки сообщается явно, подавляется деструктором и освобождает блокировку')]
    public function testCleanupFailureWithoutPrimaryFailure(bool $destructor): void
    {
        $final = $this->workspace->directory() . '/result';
        $atomic = new AtomicExtractionWorkspace($final);
        $blocked = $atomic->stagingPath() . '/blocked';
        mkdir($blocked);
        file_put_contents($blocked . '/partial', 'partial');
        chmod($blocked, 0000);
        try {
            if (is_readable($blocked)) {
                self::markTestSkipped('Runtime can bypass fixture permissions.');
            }
            if ($destructor) {
                unset($atomic);
            } else {
                try {
                    $atomic->close();
                    self::fail('Cleanup failure was hidden.');
                } catch (ExtractionException $e) {
                    self::assertSame('Cannot completely remove staging directory.', $e->getMessage());
                }
            }
            self::assertDirectoryExists($blocked);
            $next = new AtomicExtractionWorkspace($final);
            $next->close();
            self::assertFalse(file_exists($final));
        } finally {
            chmod($blocked, 0700);
            if (isset($atomic)) {
                $atomic->close();
            }
        }
    }

    /** @return iterable<string, array{string, bool}> */
    public static function unsafeLocks(): iterable
    {
        yield 'namespace file' => ['namespace-file', false];
        yield 'namespace symlink' => ['namespace-link', true];
        yield 'lock directory' => ['entry-directory', false];
        yield 'lock symlink' => ['entry-link', true];
    }

    #[DataProvider('unsafeLocks')]
    #[TestDox('Несовместимые объекты и ссылки в пространстве блокировок отклоняются без изменения целей')]
    public function testUnsafeLockObjects(string $fixture, bool $link): void
    {
        $parent = $this->workspace->directory();
        $outside = $this->workspace->directory();
        file_put_contents($outside . '/keep', 'safe');
        $namespace = $parent . '/.archive-guard-locks';
        $target = $namespace;
        if (str_starts_with($fixture, 'entry-')) {
            mkdir($namespace);
            $target .= '/result';
        }
        if ($link) {
            $referent = $fixture === 'namespace-link' ? $outside : $outside . '/keep';
            if (!@symlink($referent, $target)) {
                self::markTestSkipped('Runtime cannot create symlink fixture.');
            }
        } elseif ($fixture === 'namespace-file') {
            file_put_contents($target, 'keep');
        } else {
            mkdir($target);
        }
        try {
            new AtomicExtractionWorkspace($parent . '/result');
            self::fail('Unsafe lock object accepted.');
        } catch (ExtractionException $e) {
            self::assertStringContainsString($fixture === 'namespace-file' || $fixture === 'namespace-link' ? 'namespace' : 'lock path', $e->getMessage());
        }
        self::assertSame('safe', file_get_contents($outside . '/keep'));
        self::assertFalse(file_exists($parent . '/result'));
        self::assertSame([], glob($parent . '/.archive-guard-stage-*'));
        if ($link) {
            self::assertTrue(is_link($target));
        } elseif ($fixture === 'namespace-file') {
            self::assertSame('keep', file_get_contents($target));
        } else {
            self::assertDirectoryExists($target);
        }
    }

    #[TestDox('Блокировка сохраняет исходное имя без удлинения компонента и остаётся после закрытия')]
    public function testPersistentNativeLockName(): void
    {
        $parent = $this->workspace->directory();
        $name = str_repeat('a', 255);
        if (!@mkdir($parent . '/' . $name)) {
            self::markTestSkipped('Filesystem cannot create the 255-character destination fixture.');
        }
        rmdir($parent . '/' . $name);
        $atomic = new AtomicExtractionWorkspace($parent . '/' . $name);
        $atomic->close();
        $lockPath = $parent . '/.archive-guard-locks/' . $name;
        self::assertSame('', file_get_contents($lockPath));
        $again = new AtomicExtractionWorkspace($parent . '/' . $name);
        $again->close();
        self::assertSame([$name], array_values(array_diff(scandir($parent . '/.archive-guard-locks') ?: [], ['.', '..'])));
    }
}
