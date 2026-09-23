<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\ExtractionOptions;
use Yaleksandr\ArchiveGuard\Tests\Support\TemporaryWorkspace;
use Yaleksandr\ArchiveGuard\Tests\Support\ZipFixtureFactory as Zip;
use Yaleksandr\ArchiveGuard\ViolationCode;

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
}
