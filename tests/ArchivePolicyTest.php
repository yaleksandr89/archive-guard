<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Tests\Support\TemporaryWorkspace;
use Yaleksandr\ArchiveGuard\Tests\Support\ZipFixtureFactory;

final class ArchivePolicyTest extends TestCase
{
    /** @return iterable<string, array{int, int, int, int, ?float}> */
    public static function invalidPolicies(): iterable
    {
        foreach (range(0, 3) as $index) {
            foreach ([0, -1] as $bad) {
                $args = [1, 1, 1, 1, null];
                $args[$index] = $bad;
                yield "$index:$bad" => $args;
            }
        }
        foreach ([0.0, -1.0, INF, -INF, NAN] as $index => $ratio) {
            yield "ratio:$index" => [1, 1, 1, 1, $ratio];
        }
    }
    #[DataProvider('invalidPolicies')]
    #[TestDox('Политика отклоняет неположительные лимиты и некорректное отношение сжатия')]
    public function testInvalidLimits(int $archive, int $entries, int $entry, int $total, ?float $ratio): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ArchivePolicy($archive, $entries, $entry, $total, $ratio);
    }
    #[TestDox('Допустима политика с лимитом записи выше суммарного лимита и отключённой эвристикой')]
    public function testIndependentLimitsAndDisabledRatio(): void
    {
        $workspace = new TemporaryWorkspace();
        try {
            $path = ZipFixtureFactory::create($workspace, [['name' => 'ok', 'payload' => 'abc']]);
            self::assertTrue(new ArchiveGuard()->inspect($path, new ArchivePolicy(4096, 1, 100, 3))->isAccepted());
        } finally {
            $workspace->close();
        }
    }
}
