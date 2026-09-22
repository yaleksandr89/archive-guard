<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests\Support;

use RuntimeException;

final class TemporaryWorkspace
{
    /** @var list<string> */
    private array $files = [];
    public function file(string $contents = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'archive-guard-');
        if ($path === false) {
            throw new RuntimeException('Cannot create test fixture.');
        }
        $this->files[] = $path;
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Cannot write test fixture.');
        }
        return $path;
    }
    public function close(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->files = [];
    }
}
