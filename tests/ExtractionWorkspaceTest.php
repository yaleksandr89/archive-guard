<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\ArchiveGuard\ArchiveFormat;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionConflictStrategy;
use Yaleksandr\ArchiveGuard\Internal\Extraction\ExtractionWorkspace;
use Yaleksandr\ArchiveGuard\Internal\Extraction\MergeExtractionPublisher;
use Yaleksandr\ArchiveGuard\Tests\Support\TemporaryWorkspace;

final class ExtractionWorkspaceTest extends TestCase
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
        $atomic = ExtractionWorkspace::atomic($parent . '/nested/../result');
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
        $atomic = ExtractionWorkspace::atomic($parent . '/result');
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
        $first = ExtractionWorkspace::atomic($parent . '/result');
        try {
            try {
                ExtractionWorkspace::atomic($parent . '/./result');
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
        $second = ExtractionWorkspace::atomic($parent . '/result');
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
        $first = ExtractionWorkspace::atomic($parent . '/first');
        try {
            $second = ExtractionWorkspace::atomic($parent . '/second');
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
        $atomic = ExtractionWorkspace::atomic($final);
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
        $atomic = ExtractionWorkspace::atomic($final);
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
        $atomic = ExtractionWorkspace::atomic($this->workspace->directory() . '/result');
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
        $atomic = ExtractionWorkspace::atomic($this->workspace->directory() . '/result');
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
        $atomic = ExtractionWorkspace::atomic($final);
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
        $next = ExtractionWorkspace::atomic($final);
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
        $atomic = ExtractionWorkspace::atomic($final);
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
            $next = ExtractionWorkspace::atomic($final);
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
        $atomic = ExtractionWorkspace::atomic($final);
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
            $next = ExtractionWorkspace::atomic($final);
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
            ExtractionWorkspace::atomic($parent . '/result');
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
        $atomic = ExtractionWorkspace::atomic($parent . '/' . $name);
        $atomic->close();
        $lockPath = $parent . '/.archive-guard-locks/' . $name;
        self::assertSame('', file_get_contents($lockPath));
        $again = ExtractionWorkspace::atomic($parent . '/' . $name);
        $again->close();
        self::assertSame([$name], array_values(array_diff(scandir($parent . '/.archive-guard-locks') ?: [], ['.', '..'])));
    }
    public function testMergeLockAndRootIdentity(): void
    {
        $parent = $this->workspace->directory();
        $destination = $parent . '/result';
        mkdir($destination);
        $merge = ExtractionWorkspace::merge($destination);
        try {
            try {
                ExtractionWorkspace::merge($parent . '/./result');
                self::fail('Second merge acquired the held lock.');
            } catch (ExtractionException $e) {
                self::assertStringContainsString('locked', $e->getMessage());
            }
            try {
                $merge->publish();
                self::fail('Merge published staging wholesale.');
            } catch (ExtractionException $e) {
                self::assertStringContainsString('Only Atomic', $e->getMessage());
            }
            rename($destination, $parent . '/original');
            mkdir($destination);
            try {
                $merge->mergeDestination();
                self::fail('Replaced root accepted.');
            } catch (ExtractionException $e) {
                self::assertStringContainsString('root changed', $e->getMessage());
            }
        } finally {
            $merge->close();
        }
        $next = ExtractionWorkspace::merge($destination);
        $next->close();
    }

    /** @return iterable<string, array{string}> */
    public static function mergeMutations(): iterable
    {
        yield 'new target appeared' => ['appeared'];
        yield 'overwrite target became directory' => ['directory'];
        yield 'parent became link' => ['parent-link'];
        yield 'staged source disappeared' => ['missing-source'];
    }

    #[DataProvider('mergeMutations')]
    public function testApplyRechecksStateAndKeepsCompletedWrites(string $mutation): void
    {
        $parent = $this->workspace->directory();
        $destination = $parent . '/result';
        mkdir($destination);
        mkdir($destination . '/z');
        $outside = $this->workspace->directory();
        file_put_contents($outside . '/keep', 'safe');
        if ($mutation === 'directory') {
            file_put_contents($destination . '/z/file', 'old');
        }
        $merge = ExtractionWorkspace::merge($destination);
        $stage = $merge->stagingPath();
        file_put_contents($stage . '/a', 'applied');
        mkdir($stage . '/z');
        file_put_contents($stage . '/z/file', 'new');
        $publisher = new MergeExtractionPublisher($merge, ExtractionConflictStrategy::Overwrite);
        $publisher->preflight();
        if ($mutation === 'appeared') {
            file_put_contents($destination . '/z/file', 'external');
        } elseif ($mutation === 'directory') {
            unlink($destination . '/z/file');
            mkdir($destination . '/z/file');
        } elseif ($mutation === 'parent-link') {
            rmdir($destination . '/z');
            if (!@symlink($outside, $destination . '/z')) {
                $merge->close();
                self::markTestSkipped('Runtime cannot create a directory link for apply recheck.');
            }
        } else {
            unlink($stage . '/z/file');
        }
        $failure = null;
        try {
            $publisher->apply(ArchiveFormat::Tar);
            self::fail('Changed merge state accepted.');
        } catch (ExtractionException $e) {
            $failure = $e;
            self::assertSame('applied', file_get_contents($destination . '/a'));
            self::assertSame('safe', file_get_contents($outside . '/keep'));
            self::assertFileDoesNotExist($outside . '/file');
            if ($mutation === 'appeared') {
                self::assertSame('external', file_get_contents($destination . '/z/file'));
            } elseif ($mutation === 'directory') {
                self::assertDirectoryExists($destination . '/z/file');
            }
        } finally {
            $merge->close($failure);
        }
        self::assertDirectoryDoesNotExist($stage);
        $next = ExtractionWorkspace::merge($destination);
        $next->close();
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testMergeApplyFailureSurvivesCleanupFailure(): void
    {
        $destination = $this->workspace->directory() . '/result';
        mkdir($destination);
        $merge = ExtractionWorkspace::merge($destination);
        $stage = $merge->stagingPath();
        file_put_contents($stage . '/a', 'applied');
        file_put_contents($stage . '/b', 'fail');
        $publisher = new MergeExtractionPublisher($merge, ExtractionConflictStrategy::Reject);
        $publisher->preflight();
        unlink($stage . '/b');
        mkdir($stage . '/blocked');
        file_put_contents($stage . '/blocked/keep', 'blocked');
        chmod($stage . '/blocked', 0000);
        try {
            if (is_readable($stage . '/blocked')) {
                self::markTestSkipped('Runtime can bypass cleanup fixture permissions.');
            }
            $failure = null;
            try {
                try {
                    $publisher->apply(ArchiveFormat::Tar);
                    self::fail('Missing source applied.');
                } catch (ExtractionException $e) {
                    $failure = $e;
                    throw $e;
                } finally {
                    $merge->close($failure);
                }
            } catch (ExtractionException $e) {
                self::assertSame($failure, $e);
                self::assertSame('Staged object changed after merge preflight.', $e->getMessage());
            }
            self::assertSame('applied', file_get_contents($destination . '/a'));
            self::assertDirectoryExists($stage . '/blocked');
            $next = ExtractionWorkspace::merge($destination);
            $next->close();
        } finally {
            chmod($stage . '/blocked', 0700);
            $merge->close();
        }
    }

    public function testPreflightRejectsUnexpectedStagingObject(): void
    {
        $destination = $this->workspace->directory() . '/result';
        mkdir($destination);
        $merge = ExtractionWorkspace::merge($destination);
        $outside = $this->workspace->file('safe');
        try {
            file_put_contents($merge->stagingPath() . '/a', 'new');
            if (!@symlink($outside, $merge->stagingPath() . '/z')) {
                self::markTestSkipped('Runtime cannot create staging link fixture.');
            }
            $publisher = new MergeExtractionPublisher($merge, ExtractionConflictStrategy::Overwrite);
            try {
                $publisher->preflight();
                self::fail('Unexpected staging link accepted.');
            } catch (ExtractionException) {
                self::assertSame(['.', '..'], scandir($destination));
            }
            try {
                $publisher->apply(ArchiveFormat::Zip);
                self::fail('Partial preflight plan applied.');
            } catch (ExtractionException $e) {
                self::assertStringContainsString('completed preflight', $e->getMessage());
            }
        } finally {
            $merge->close();
        }
        self::assertSame('safe', file_get_contents($outside));
    }

}
