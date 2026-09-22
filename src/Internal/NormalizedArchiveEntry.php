<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal;

/** @internal */
final readonly class NormalizedArchiveEntry
{
    public function __construct(public int $index, public string $path, public bool $directory, public int $size, public string $type = '') {}

    public function matches(self $other): bool
    {
        return $this->index === $other->index && $this->path === $other->path && $this->directory === $other->directory && $this->size === $other->size && $this->type === $other->type;
    }
}
