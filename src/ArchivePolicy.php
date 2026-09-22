<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

use InvalidArgumentException;

final readonly class ArchivePolicy
{
    public function __construct(
        public int $maxArchiveBytes,
        public int $maxEntries,
        public int $maxEntryUncompressedBytes,
        public int $maxTotalUncompressedBytes,
        public ?float $maxCompressionRatio = null,
    ) {
        if ($maxArchiveBytes <= 0 || $maxEntries <= 0 || $maxEntryUncompressedBytes <= 0 || $maxTotalUncompressedBytes <= 0) {
            throw new InvalidArgumentException('Resource limits must be positive.');
        }
        if ($maxCompressionRatio !== null && (!is_finite($maxCompressionRatio) || $maxCompressionRatio <= 0)) {
            throw new InvalidArgumentException('Compression ratio must be finite and positive.');
        }
    }
}
