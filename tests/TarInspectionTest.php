<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Tests\Support\TarFixtureFactory as Tar;
use Yaleksandr\ArchiveGuard\Tests\Support\TemporaryWorkspace;
use Yaleksandr\ArchiveGuard\Violation;
use Yaleksandr\ArchiveGuard\ViolationCode;

final class TarInspectionTest extends TestCase
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
    /** @return iterable<string, array{string, ViolationCode}> */
    public static function forbiddenRecords(): iterable
    {
        foreach (['1' => ViolationCode::HardlinkEntry, '2' => ViolationCode::SymlinkEntry, '3' => ViolationCode::SpecialEntry, '4' => ViolationCode::SpecialEntry, '6' => ViolationCode::SpecialEntry, 'S' => ViolationCode::UnsupportedFeature, 'Z' => ViolationCode::UnsupportedFeature] as $type => $code) {
            yield "type:$type" => [Tar::record('entry', '', (string) $type), $code];
        }
        yield 'sparse pax' => [Tar::record('pax', Tar::pax(['GNU.sparse.size' => '10']), 'x') . Tar::record('entry'), ViolationCode::UnsupportedFeature];
        yield 'vendor pax' => [Tar::record('pax', Tar::pax(['SCHILY.xattr.foo' => 'bar']), 'x') . Tar::record('entry'), ViolationCode::UnsupportedFeature];
        foreach (['path', 'size', 'linkpath'] as $key) {
            yield "global:$key" => [Tar::record('pax', Tar::pax([$key => '1']), 'g') . Tar::record('entry'), ViolationCode::UnsupportedFeature];
        }
        yield 'long link symlink' => [Tar::record('long', "target\0", 'K') . Tar::record('link', '', '2'), ViolationCode::SymlinkEntry];
        yield 'long link file' => [Tar::record('long', "target\0", 'K') . Tar::record('file'), ViolationCode::UnsupportedFeature];
        yield 'pax link file' => [Tar::record('pax', Tar::pax(['linkpath' => 'target']), 'x') . Tar::record('file'), ViolationCode::UnsupportedFeature];
        $header = Tar::record('binary');
        yield 'gnu sparse bookkeeping' => [Tar::checksum(substr_replace(Tar::record('file', gnu: true), '1', 482, 1)), ViolationCode::UnsupportedFeature];
        yield 'base256' => [Tar::checksum(substr_replace($header, "\x80" . str_repeat("\0", 11), 124, 12)), ViolationCode::UnsupportedFeature];
        yield 'empty name' => [Tar::record(''), ViolationCode::UnsafePath];
        yield 'duplicate path' => [Tar::record('same') . Tar::record('same'), ViolationCode::PathCollision];
        yield 'nul pax path' => [Tar::record('pax', Tar::pax(['path' => "bad\0path"]), 'x') . Tar::record('file'), ViolationCode::UnsafePath];
    }
    #[DataProvider('forbiddenRecords')]
    #[TestDox('TAR запрещает ссылки, специальные записи и неподдерживаемую семантику')]
    public function testForbiddenRecords(string $records, ViolationCode $code): void
    {
        self::assertContains($code, $this->codes(Tar::archive($records)));
    }
    #[TestDox('GNU long-name, USTAR prefix и PAX path участвуют в проверке логического пути')]
    public function testPathOverrides(): void
    {
        $long = str_repeat('directory/', 15) . 'file';
        self::assertSame([], $this->codes(Tar::archive(Tar::record('long', $long . "\0", 'L', gnu: true) . Tar::record('placeholder', 'abc', gnu: true))));
        foreach ([Tar::record('long', "../bad\0", 'L') . Tar::record('placeholder'), Tar::record('pax', Tar::pax(['path' => '../bad']), 'x') . Tar::record('placeholder'), Tar::record('bad', prefix: '..')] as $records) {
            $result = new ArchiveGuard()->inspect($this->workspace->file(Tar::archive($records)), new ArchivePolicy(100000, 10, 10000, 10000));
            self::assertSame(ViolationCode::UnsafePath, $result->violations()[0]->code);
            self::assertSame('../bad', $result->violations()[0]->entryName);
        }
        self::assertSame([], $this->codes(Tar::archive(Tar::record('pax', Tar::pax(['path' => 'safe/path']), 'x') . Tar::record('../ignored'))));
        self::assertSame([], $this->codes(Tar::archive(Tar::record('file', prefix: 'directory'))));
    }
    /** @return iterable<string, array{string}> */
    public static function directoryLikeRecords(): iterable
    {
        yield 'regular slash' => [Tar::record('foo/')];
        yield 'nul type backslash' => [Tar::record('foo\\', type: "\0")];
        yield 'gnu logical directory' => [Tar::record('long', "foo/\0", 'L') . Tar::record('placeholder')];
        yield 'pax logical directory' => [Tar::record('pax', Tar::pax(['path' => 'foo/']), 'x') . Tar::record('placeholder')];
    }
    #[DataProvider('directoryLikeRecords')]
    #[TestDox('Обычный тип TAR с завершающим разделителем допускает дочерний файл')]
    public function testDirectoryLikeRegularEntries(string $records): void
    {
        self::assertSame([], $this->codes(Tar::archive($records . Tar::record('foo/bar.txt'))));
    }
    #[TestDox('Корневые маркеры обычного типа TAR допускаются, путь без разделителя остаётся файлом')]
    public function testRegularRootAndFileConflict(): void
    {
        self::assertSame([], $this->codes(Tar::archive(Tar::record('./') . Tar::record('.\\', type: "\0") . Tar::record('./file'))));
        self::assertSame([ViolationCode::PathCollision], $this->codes(Tar::archive(Tar::record('foo') . Tar::record('foo/bar.txt'))));
    }
    /** @return iterable<string, array{string, string, ?ViolationCode}> */
    public static function headerCharsets(): iterable
    {
        foreach (['x', 'g'] as $type) {
            yield "$type binary" => [$type, 'BINARY', null];
            yield "$type utf8" => [$type, 'ISO-IR 10646 2000 UTF-8', null];
            yield "$type unsupported" => [$type, 'UTF-16', ViolationCode::UnsupportedFeature];
        }
    }
    #[DataProvider('headerCharsets')]
    #[TestDox('PAX hdrcharset допускает только поддерживаемые кодировки локально и глобально')]
    public function testHeaderCharset(string $type, string $charset, ?ViolationCode $expected): void
    {
        $metadata = ['hdrcharset' => $charset, 'charset' => 'file-data-encoding'];
        if ($type === 'x') {
            $metadata['path'] = 'safe/file';
        }
        $records = Tar::record('pax', Tar::pax($metadata), $type) . Tar::record('safe/file');
        self::assertSame($expected === null ? [] : [$expected], $this->codes(Tar::archive($records)));
    }
    #[TestDox('Поддерживаемый hdrcharset не отменяет проверку опасного логического пути')]
    public function testHeaderCharsetPreservesPathChecks(): void
    {
        foreach (['BINARY', 'ISO-IR 10646 2000 UTF-8'] as $charset) {
            $records = Tar::record('pax', Tar::pax(['hdrcharset' => $charset, 'path' => '../bad']), 'x') . Tar::record('safe');
            self::assertSame([ViolationCode::UnsafePath], $this->codes(Tar::archive($records)));
        }
    }
    #[TestDox('PAX size определяет границу данных и участвует в лимитах')]
    public function testPaxSize(): void
    {
        $pax = Tar::record('pax', Tar::pax(['size' => '513']), 'x');
        $records = $pax . Tar::record('file', str_repeat('a', 513), declaredSize: 0) . Tar::record('next');
        self::assertSame([], $this->codes(Tar::archive($records)));
        self::assertContains(ViolationCode::EntryTooLarge, $this->codes(Tar::archive($records), new ArchivePolicy(10000, 10, 512, 10000)));
    }
    #[TestDox('Безопасная глобальная метаинформация допускается, локальные расширения действуют однократно')]
    public function testExtensionScope(): void
    {
        $records = Tar::record('global', Tar::pax(['comment' => 'safe', 'uid' => '1']), 'g')
            . Tar::record('local', Tar::pax(['path' => 'first', 'mtime' => '1.5']), 'x') . Tar::record('placeholder') . Tar::record('second');
        self::assertSame([], $this->codes(Tar::archive($records)));
    }
    /** @return iterable<string, array{string}> */
    public static function malformedRecords(): iterable
    {
        $header = Tar::record('entry');
        yield 'checksum' => [substr_replace($header, 'X', 0, 1)];
        yield 'version' => [Tar::checksum(substr_replace($header, '99', 263, 2))];
        yield 'negative size' => [Tar::checksum(substr_replace($header, '-0000000001' . "\0", 124, 12))];
        yield 'partial header' => [substr($header, 0, 300)];
        yield 'missing end' => [$header];
        yield 'one zero block' => [$header . str_repeat("\0", 512)];
        yield 'payload truncated' => [Tar::record('entry', declaredSize: 2048) . str_repeat("\0", 1024)];
        yield 'pax missing newline' => [Tar::archive(Tar::record('pax', '9 path=ab', 'x'))];
        yield 'pax missing equals' => [Tar::archive(Tar::record('pax', "7 path\n", 'x'))];
        yield 'bad pax' => [Tar::archive(Tar::record('pax', '99 path=short', 'x'))];
        yield 'duplicate pax' => [Tar::archive(Tar::record('pax', Tar::pax(['path' => 'a']) . Tar::pax(['path' => 'b']), 'x'))];
        yield 'invalid pax size' => [Tar::archive(Tar::record('pax', Tar::pax(['size' => '-1']), 'x'))];
        yield 'overflow pax size' => [Tar::archive(Tar::record('pax', Tar::pax(['size' => '99999999999999999999']), 'x') . Tar::record('file'))];
        yield 'orphan long name' => [Tar::archive(Tar::record('long', "name\0", 'L'))];
        yield 'unterminated long name' => [Tar::archive(Tar::record('long', 'name', 'L') . Tar::record('file'))];
    }
    #[DataProvider('malformedRecords')]
    #[TestDox('Нарушение структуры TAR или расширений вызывает исключение')]
    public function testMalformedRecords(string $contents): void
    {
        $this->expectException(ArchiveOpenException::class);
        $this->codes($contents);
    }
    #[TestDox('Служебные записи TAR учитываются в числе записей и размерах данных')]
    public function testPhysicalRecordLimits(): void
    {
        $metadata = "safe\0";
        $bytes = Tar::archive(Tar::record('long', $metadata, 'L') . Tar::record('entry', 'abc'));
        self::assertSame([ViolationCode::TooManyEntries], $this->codes($bytes, new ArchivePolicy(10000, 1, 100, 100)));
        self::assertContains(ViolationCode::EntryTooLarge, $this->codes($bytes, new ArchivePolicy(10000, 2, 4, 100)));
        self::assertContains(ViolationCode::TotalSizeExceeded, $this->codes($bytes, new ArchivePolicy(10000, 2, 100, 7)));
        self::assertSame([], $this->codes($bytes, new ArchivePolicy(10000, 2, 5, 8)));
        self::assertContains(ViolationCode::EntryTooLarge, $this->codes(Tar::archive(Tar::record('file', 'abcdef')), new ArchivePolicy(10000, 1, 5, 100)));
    }
    #[TestDox('Эвристика TAR.GZ ограничивает расширение, для несжатого TAR она не применяется')]
    public function testGzipRatio(): void
    {
        $bytes = Tar::archive(Tar::record('file', str_repeat('a', 4096)));
        $policy = new ArchivePolicy(10000, 5, 5000, 5000, 2);
        self::assertSame([], $this->codes($bytes, $policy));
        self::assertSame([ViolationCode::CompressionRatioExceeded], $this->codes(Tar::gzip($bytes), $policy));
        self::assertSame([], $this->codes(Tar::gzip($bytes)));
    }
    #[TestDox('Расширение GZIP после конца TAR учитывается в эвристике сжатия')]
    public function testGzipTailExpansion(): void
    {
        $bytes = Tar::gzip(Tar::archive('') . str_repeat("\0", 4 * 1024 * 1024));
        self::assertSame([ViolationCode::CompressionRatioExceeded], $this->codes($bytes, new ArchivePolicy(10000, 1, 1, 1, 200)));
        self::assertSame([], $this->codes($bytes, new ArchivePolicy(10000, 1, 1, 1)));
    }
    #[TestDox('Заголовки, выравнивание и конец TAR входят в расширение GZIP, обычное заполнение допустимо')]
    public function testGzipPaddingRatio(): void
    {
        $bytes = Tar::gzip(str_pad(Tar::archive(Tar::record('file', 'a')), 10240, "\0"));
        self::assertSame([ViolationCode::CompressionRatioExceeded], $this->codes($bytes, new ArchivePolicy(10000, 1, 1, 1, 2)));
        self::assertSame([], $this->codes($bytes, new ArchivePolicy(10000, 1, 1, 1, 1000)));
    }
    #[TestDox('После стандартного конца TAR данные не становятся дополнительными записями')]
    public function testLogicalEnd(): void
    {
        $bytes = Tar::archive(Tar::record('safe')) . Tar::record('../ignored');
        self::assertSame([], $this->codes($bytes));
        self::assertSame([], $this->codes(Tar::gzip($bytes)));
    }
    /** @return list<ViolationCode> */
    private function codes(string $bytes, ?ArchivePolicy $policy = null): array
    {
        $result = new ArchiveGuard()->inspect($this->workspace->file($bytes), $policy ?? new ArchivePolicy(100000, 20, 10000, 20000));
        return array_map(static fn(Violation $v): ViolationCode => $v->code, $result->violations());
    }
}
