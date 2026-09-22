<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Internal\Inspection;

use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Internal\Path\PathCanonicalizer;
use Yaleksandr\ArchiveGuard\Internal\Path\PathRegistry;
use Yaleksandr\ArchiveGuard\Violation;
use Yaleksandr\ArchiveGuard\ViolationCode;

/** @internal */
final class InspectionAccumulator
{
    /** @var list<Violation> */
    public array $violations = [];
    public int $total = 0;
    private bool $totalExceeded = false;
    private PathRegistry $registry;

    public function __construct(private readonly ArchivePolicy $policy)
    {
        $this->registry = new PathRegistry();
    }
    public function add(ViolationCode $code, ?string $name = null): void
    {
        $this->violations[] = new Violation($code, str_replace('_', ' ', $code->value) . '.', $name);
    }
    public function path(string $name, bool $directory): void
    {
        $canonical = new PathCanonicalizer()->canonicalize($name);
        if ($canonical === null || ($canonical === '' && !$directory)) {
            $this->add(ViolationCode::UnsafePath, $name);
        } elseif ($canonical !== '' && !$this->registry->register($canonical, $directory)) {
            $this->add(ViolationCode::PathCollision, $name);
        }
    }
    public function payload(int $size, ?string $name): bool
    {
        $ok = true;
        if ($size > $this->policy->maxEntryUncompressedBytes) {
            $this->add(ViolationCode::EntryTooLarge, $name);
            $ok = false;
        }
        if ($size > $this->policy->maxTotalUncompressedBytes - $this->total) {
            if (!$this->totalExceeded) {
                $this->add(ViolationCode::TotalSizeExceeded, $name);
            }
            $this->totalExceeded = true;
            $ok = false;
        } else {
            $this->total += $size;
        }
        return $ok;
    }
}
