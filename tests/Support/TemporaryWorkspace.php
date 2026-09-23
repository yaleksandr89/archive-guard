<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class TemporaryWorkspace
{
    /** @var list<string> */
    private array $files = [];
    /** @var list<string> */
    private array $directories = [];
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
    public function directory(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'archive-guard-');
        if ($path === false || !unlink($path) || !mkdir($path)) {
            throw new RuntimeException('Cannot create test directory.');
        }
        $this->directories[] = $path;
        return $path;
    }
    public function close(): void
    {
        foreach ($this->files as $file) {
            if (is_link($file)) {
                $this->removeLink($file);
            } elseif (is_file($file)) {
                unlink($file);
            }
        }
        foreach (array_reverse($this->directories) as $directory) {
            if (is_link($directory)) {
                $this->removeLink($directory);
                continue;
            }
            if (!is_dir($directory)) {
                continue;
            }
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($items as $item) {
                if (!$item instanceof SplFileInfo) {
                    throw new RuntimeException('Unexpected test fixture entry.');
                }
                $path = $item->getPathname();
                if ($item->isLink()) {
                    $this->removeLink($path);
                    continue;
                }
                if ($item->isDir()) {
                    rmdir($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($directory);
        }
        $this->files = [];
        $this->directories = [];
    }

    private function removeLink(string $path): void
    {
        if (@unlink($path)) {
            return;
        }
        // Only known links reach this fallback; rmdir removes the Windows
        // directory-link representation without traversing its target.
        if (PHP_OS_FAMILY === 'Windows' && @rmdir($path)) {
            return;
        }
        throw new RuntimeException('Cannot remove test fixture link: ' . $path);
    }
}
