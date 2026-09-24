<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use SensitiveParameter;
use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\ExtractionConflictStrategy;
use Yaleksandr\ArchiveGuard\ExtractionOptions;
use Yaleksandr\ArchiveGuard\Internal\Extraction\ZipExtractor;
use Yaleksandr\ArchiveGuard\Internal\Inspection\ZipInspector;
use Yaleksandr\ArchiveGuard\Internal\Zip\ZipPayloadReader;
use Yaleksandr\ArchiveGuard\Tests\Support\TemporaryWorkspace;
use Yaleksandr\ArchiveGuard\Tests\Support\ZipFixtureFactory as Zip;
use Yaleksandr\ArchiveGuard\ViolationCode;
use ZipArchive;

final class ZipExtractionTest extends TestCase
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

    /** @return iterable<string, array{list<array{name: string, payload?: string, mode?: int}>, ViolationCode}> */
    public static function rejectedEntries(): iterable
    {
        yield 'symlink' => [[['name' => 'link', 'mode' => 0120777]], ViolationCode::SymlinkEntry];
        yield 'special' => [[['name' => 'device', 'mode' => 0020644]], ViolationCode::SpecialEntry];
        yield 'directory payload' => [[['name' => 'dir', 'payload' => 'x', 'mode' => 0040755]], ViolationCode::UnsupportedFeature];
    }

    /** @param list<array{name: string, payload?: string, mode?: int}> $entries */
    #[DataProvider('rejectedEntries')]
    #[TestDox('Ссылки, специальные записи и каталог с данными отклоняются до записи')]
    public function testRejectedEntries(array $entries, ViolationCode $code): void
    {
        $path = Zip::create($this->workspace, $entries);
        $destination = $this->workspace->directory() . '/result';
        $guard = new ArchiveGuard();
        self::assertSame($code, $guard->inspect($path, new ArchivePolicy(10000, 10, 1000, 1000))->violations()[0]->code);
        try {
            $guard->extract($path, $destination, new ArchivePolicy(10000, 10, 1000, 1000), ExtractionOptions::atomic());
            self::fail('Rejected entry extracted.');
        } catch (ArchiveRejectedException $e) {
            self::assertContains($code, array_map(static fn($v) => $v->code, $e->inspectionResult()->violations()));
            self::assertFalse(file_exists($destination));
        }
    }

    public function testPasswordApiContract(): void
    {
        foreach (['inspect' => 2, 'extract' => 4] as $method => $position) {
            $parameter = new ReflectionMethod(ArchiveGuard::class, $method)->getParameters()[$position];
            self::assertSame('password', $parameter->getName());
            self::assertSame('?string', (string) $parameter->getType());
            self::assertTrue($parameter->isDefaultValueAvailable());
            self::assertNull($parameter->getDefaultValue());
            self::assertCount(1, $parameter->getAttributes(SensitiveParameter::class));
        }
        foreach ([
            [ArchiveGuard::class, 'validatePasswordFormat', 1],
            [ZipExtractor::class, 'extract', 3],
            [ZipInspector::class, 'inspect', 4],
            [ZipPayloadReader::class, 'read', 2],
        ] as [$class, $method, $position]) {
            self::assertCount(1, new ReflectionMethod($class, $method)->getParameters()[$position]->getAttributes(SensitiveParameter::class));
        }
    }

    public function testEncryptedWithoutPasswordNeverPublishes(): void
    {
        $path = $this->encrypted([['name' => 'secret.txt', 'payload' => 'hidden']]);
        $inspection = new ArchiveGuard()->inspect($path, $this->policy());
        self::assertSame(ViolationCode::EncryptedEntry, $inspection->violations()[0]->code);
        foreach ([false, true] as $merge) {
            [$destination, $options] = $this->destination($merge);
            try {
                new ArchiveGuard()->extract($path, $destination, $this->policy(), $options);
                self::fail('Encrypted ZIP accepted without password.');
            } catch (ArchiveRejectedException $e) {
                self::assertEquals($inspection, $e->inspectionResult());
                $this->assertUnpublished($destination, $merge);
            }
        }
    }

    public function testCorrectPasswordAtomicAndMerge(): void
    {
        $path = $this->encrypted([['name' => 'secret.txt', 'payload' => 'hidden']]);
        self::assertTrue(new ArchiveGuard()->inspect($path, $this->policy(), password: 'secret')->isAccepted());
        foreach ([false, true] as $merge) {
            [$destination, $options] = $this->destination($merge);
            $result = new ArchiveGuard()->extract($path, $destination, $this->policy(), $options, password: 'secret');
            self::assertSame('hidden', file_get_contents($destination . '/secret.txt'));
            self::assertSame(1, $result->filesExtracted());
            self::assertSame(0, $result->directoriesCreated());
            self::assertSame(6, $result->bytesWritten());
            if ($merge) {
                self::assertSame('keep', file_get_contents($destination . '/keep.txt'));
            }
            self::assertSame([], glob(dirname($destination) . '/.archive-guard-stage-*'));
        }
    }

    /** @return iterable<string, array{string}> */
    public static function wrongPasswords(): iterable
    {
        yield 'wrong password' => ['wrong-password-marker'];
        yield 'explicit empty password' => [''];
    }

    #[DataProvider('wrongPasswords')]
    public function testWrongPasswordMixedArchiveCannotPartiallyPublish(#[SensitiveParameter] string $password): void
    {
        $path = $this->encrypted([
            ['name' => 'plain.txt', 'payload' => 'visible', 'encrypted' => false],
            ['name' => 'secret.txt', 'payload' => 'hidden', 'encrypted' => true],
        ]);
        $inspection = new ArchiveGuard()->inspect($path, $this->policy(), password: $password);
        self::assertSame(ViolationCode::DecryptionFailed, $inspection->violations()[0]->code);
        self::assertSame('decryption_failed', $inspection->violations()[0]->code->value);
        foreach ($inspection->violations() as $violation) {
            self::assertStringNotContainsString('wrong-password-marker', $violation->message);
            self::assertStringNotContainsString('secret', $violation->message);
        }
        foreach ([false, true] as $merge) {
            [$destination, $options] = $this->destination($merge);
            try {
                new ArchiveGuard()->extract($path, $destination, $this->policy(), $options, password: $password);
                self::fail('Wrong password accepted.');
            } catch (ArchiveRejectedException $e) {
                self::assertEquals($inspection, $e->inspectionResult());
                self::assertStringNotContainsString('wrong-password-marker', $e->getMessage());
                self::assertStringNotContainsString('secret', $e->getMessage());
                $this->assertUnpublished($destination, $merge);
            }
            // A failed extraction must release the cooperative lock.
            $plain = Zip::create($this->workspace, [['name' => 'retry.txt', 'payload' => 'ok']]);
            new ArchiveGuard()->extract($plain, $destination, $this->policy(), $options);
            self::assertSame('ok', file_get_contents($destination . '/retry.txt'));
        }
    }

    public function testMixedArchiveWithCorrectPasswordAndPlainZipWithPassword(): void
    {
        $path = $this->encrypted([
            ['name' => 'plain.txt', 'payload' => 'visible', 'encrypted' => false],
            ['name' => 'secret.txt', 'payload' => 'hidden', 'encrypted' => true],
        ]);
        self::assertTrue(new ArchiveGuard()->inspect($path, $this->policy(), password: 'secret')->isAccepted());
        $destination = $this->workspace->directory() . '/mixed';
        $result = new ArchiveGuard()->extract($path, $destination, $this->policy(), ExtractionOptions::atomic(), password: 'secret');
        self::assertSame('visible', file_get_contents($destination . '/plain.txt'));
        self::assertSame('hidden', file_get_contents($destination . '/secret.txt'));
        self::assertSame(2, $result->filesExtracted());
        self::assertSame(13, $result->bytesWritten());
        $plain = Zip::create($this->workspace, [['name' => 'plain.txt', 'payload' => 'ok']]);
        self::assertTrue(new ArchiveGuard()->inspect($plain, $this->policy(), password: 'unused')->isAccepted());
        self::assertTrue(new ArchiveGuard()->inspect($plain, $this->policy(), password: '')->isAccepted());
        $plainDestination = $this->workspace->directory() . '/plain';
        new ArchiveGuard()->extract($plain, $plainDestination, $this->policy(), ExtractionOptions::atomic(), password: 'unused');
        self::assertSame('ok', file_get_contents($plainDestination . '/plain.txt'));
        $emptyPasswordDestination = $this->workspace->directory() . '/plain-empty-password';
        new ArchiveGuard()->extract($plain, $emptyPasswordDestination, $this->policy(), ExtractionOptions::atomic(), password: '');
        self::assertSame('ok', file_get_contents($emptyPasswordDestination . '/plain.txt'));
    }

    public function testPasswordDoesNotBypassUnsafePath(): void
    {
        $path = $this->encrypted([['name' => '../escape.txt', 'payload' => 'bad']]);
        $inspection = new ArchiveGuard()->inspect($path, $this->policy(), password: 'secret');
        self::assertSame(ViolationCode::UnsafePath, $inspection->violations()[0]->code);
        $destination = $this->workspace->directory() . '/result';
        try {
            new ArchiveGuard()->extract($path, $destination, $this->policy(), ExtractionOptions::atomic(), password: 'secret');
            self::fail('Unsafe encrypted entry extracted.');
        } catch (ArchiveRejectedException $e) {
            self::assertEquals($inspection, $e->inspectionResult());
            self::assertFileDoesNotExist($destination);
        }
    }

    public function testEmptyPasswordWhenRuntimeSupportsFixture(): void
    {
        $path = null;
        foreach ([ZipArchive::EM_AES_256, ZipArchive::EM_AES_192, ZipArchive::EM_AES_128, ZipArchive::EM_TRAD_PKWARE] as $method) {
            if (!ZipArchive::isEncryptionMethodSupported($method, true) || !ZipArchive::isEncryptionMethodSupported($method, false)) {
                continue;
            }
            try {
                $candidate = Zip::create($this->workspace, [['name' => 'empty.txt', 'payload' => 'empty']], true, '', $method);
            } catch (RuntimeException) {
                continue;
            }
            $zip = new ZipArchive();
            self::assertTrue($zip->open($candidate, ZipArchive::RDONLY) === true);
            try {
                if (!$zip->setPassword('')) {
                    continue;
                }
                $stream = @$zip->getStreamIndex(0);
                if ($stream === false) {
                    continue;
                }
                $contents = @stream_get_contents($stream);
                fclose($stream);
                if ($contents === 'empty') {
                    $path = $candidate;
                    break;
                }
            } finally {
                $zip->close();
            }
        }
        if ($path === null) {
            self::markTestSkipped('Runtime cannot create and decrypt an empty-password encrypted ZIP fixture.');
        }
        self::assertTrue(new ArchiveGuard()->inspect($path, $this->policy(), password: '')->isAccepted());
        self::assertSame(ViolationCode::EncryptedEntry, new ArchiveGuard()->inspect($path, $this->policy(), password: null)->violations()[0]->code);
        $destination = $this->workspace->directory() . '/empty-password';
        new ArchiveGuard()->extract($path, $destination, $this->policy(), ExtractionOptions::atomic(), password: '');
        self::assertSame('empty', file_get_contents($destination . '/empty.txt'));
        try {
            new ArchiveGuard()->extract($path, $this->workspace->directory() . '/no-password', $this->policy(), ExtractionOptions::atomic(), password: null);
            self::fail('Encrypted ZIP accepted with null password.');
        } catch (ArchiveRejectedException $e) {
            self::assertContains(ViolationCode::EncryptedEntry, array_map(static fn($v) => $v->code, $e->inspectionResult()->violations()));
        }
    }

    public function testUnsupportedDecryptionIsRejectedBeforePublication(): void
    {
        foreach ([ZipArchive::EM_TRAD_PKWARE, ZipArchive::EM_AES_128, ZipArchive::EM_AES_192, ZipArchive::EM_AES_256] as $method) {
            if (!ZipArchive::isEncryptionMethodSupported($method, true) || ZipArchive::isEncryptionMethodSupported($method, false)) {
                continue;
            }
            try {
                $path = Zip::create($this->workspace, [['name' => 'secret.txt']], true, 'secret', $method);
            } catch (RuntimeException) {
                continue;
            }
            $inspection = new ArchiveGuard()->inspect($path, $this->policy(), password: 'secret');
            self::assertSame(ViolationCode::UnsupportedEncryption, $inspection->violations()[0]->code);
            $destination = $this->workspace->directory() . '/unsupported';
            try {
                new ArchiveGuard()->extract($path, $destination, $this->policy(), ExtractionOptions::atomic(), password: 'secret');
                self::fail('Unsupported encryption accepted.');
            } catch (ArchiveRejectedException $e) {
                self::assertEquals($inspection, $e->inspectionResult());
                self::assertFileDoesNotExist($destination);
                self::assertSame([], glob(dirname($destination) . '/.archive-guard-stage-*'));
            }
            return;
        }
        self::markTestSkipped('Runtime has no creatable encryption method unsupported for decryption.');
    }

    /** @param list<array{name: string, payload?: string, encrypted?: bool}> $entries */
    private function encrypted(array $entries): string
    {
        return Zip::create($this->workspace, $entries, true, 'secret', $this->encryptionMethod());
    }

    private function encryptionMethod(): int
    {
        foreach ([ZipArchive::EM_AES_256, ZipArchive::EM_AES_192, ZipArchive::EM_AES_128, ZipArchive::EM_TRAD_PKWARE] as $method) {
            if (ZipArchive::isEncryptionMethodSupported($method, true) && ZipArchive::isEncryptionMethodSupported($method, false)) {
                return $method;
            }
        }
        self::markTestSkipped('Runtime has no method supporting ZIP encryption and decryption.');
    }

    /** @return array{string, ExtractionOptions} */
    private function destination(bool $merge): array
    {
        $destination = $this->workspace->directory() . '/result';
        if ($merge) {
            mkdir($destination);
            file_put_contents($destination . '/keep.txt', 'keep');
        }
        return [$destination, $merge ? ExtractionOptions::merge(ExtractionConflictStrategy::Reject) : ExtractionOptions::atomic()];
    }

    private function assertUnpublished(string $destination, bool $merge): void
    {
        if ($merge) {
            self::assertSame(['.', '..', 'keep.txt'], scandir($destination));
            self::assertSame('keep', file_get_contents($destination . '/keep.txt'));
        } else {
            self::assertFileDoesNotExist($destination);
        }
        self::assertSame([], glob(dirname($destination) . '/.archive-guard-stage-*'));
    }

    private function policy(): ArchivePolicy
    {
        return new ArchivePolicy(100000, 20, 10000, 20000);
    }
}
