<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Tar;

use InflateContext;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;

/** @internal */
final class TarReader
{
    /** @var resource */
    private $stream;
    private ?InflateContext $inflate = null;
    private string $buffer = '';
    private bool $ended = false;
    private int $compressedRead = 0;
    private int|float $decompressedBytes = 0;
    private bool $ratioExceeded = false;

    public function __construct(string $path, bool $gzip, private readonly int $sourceBytes, private readonly ?float $maxCompressionRatio)
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            throw new ArchiveOpenException('Cannot open TAR stream.');
        }
        $this->stream = $stream;
        if ($gzip) {
            $inflate = inflate_init(ZLIB_ENCODING_GZIP);
            if ($inflate === false) {
                fclose($stream);
                throw new ArchiveOpenException('Cannot initialize GZIP decoder.');
            }
            $this->inflate = $inflate;
        }
    }
    public function close(): void
    {
        fclose($this->stream);
    }
    public function ratioExceeded(): bool
    {
        return $this->ratioExceeded;
    }
    /** Returns null when the optional GZIP expansion limit stops reading. */
    public function read(int $length): ?string
    {
        while (strlen($this->buffer) < $length && !$this->ended && !$this->ratioExceeded) {
            $this->fill();
        }
        if ($this->ratioExceeded) {
            return null;
        }
        if (strlen($this->buffer) < $length) {
            throw new ArchiveOpenException('Truncated TAR stream.');
        }
        $result = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);
        return $result;
    }
    public function consume(int $length): bool
    {
        while ($length > 0) {
            $chunk = min(8192, $length);
            if ($this->read($chunk) === null) {
                return false;
            }
            $length -= $chunk;
        }
        return true;
    }
    // Validate the GZIP trailer; discard bytes after the logical TAR end.
    public function finish(): void
    {
        if ($this->inflate === null) {
            return;
        }
        while (!$this->ended && !$this->ratioExceeded) {
            $this->buffer = '';
            $this->fill();
        }
        $this->buffer = '';
    }
    private function fill(): void
    {
        $data = fread($this->stream, $this->inflate === null ? 8192 : 512);
        if ($data === false) {
            throw new ArchiveOpenException('Cannot read TAR stream.');
        }
        if ($this->inflate === null) {
            $this->buffer .= $data;
            $this->ended = feof($this->stream);
            return;
        }
        if ($data === '') {
            throw new ArchiveOpenException('Truncated GZIP stream.');
        }
        $this->compressedRead += strlen($data);
        $output = @inflate_add($this->inflate, $data, ZLIB_SYNC_FLUSH);
        if ($output === false) {
            throw new ArchiveOpenException('Malformed GZIP stream.');
        }
        // Count decoder output, including read-ahead, framing and post-EOA bytes.
        // Integer overflow promotes the counter to float for this heuristic.
        $this->decompressedBytes += strlen($output);
        if ($this->maxCompressionRatio !== null && $this->decompressedBytes / $this->sourceBytes > $this->maxCompressionRatio) {
            $this->ratioExceeded = true;
            $this->buffer = '';
            return;
        }
        $this->buffer .= $output;
        if (inflate_get_status($this->inflate) === ZLIB_STREAM_END) {
            if (inflate_get_read_len($this->inflate) !== $this->compressedRead || fread($this->stream, 1) !== '') {
                throw new ArchiveOpenException('Trailing or concatenated GZIP data is unsupported.');
            }
            $this->ended = true;
        }
    }
}
