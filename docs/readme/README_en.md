# Archive Guard

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Farchive--guard-blue.svg?style=flat-square)](https://github.com/yaleksandr89/archive-guard)
[![CI](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml/badge.svg)](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)

![Archive Guard — ZIP, TAR and TAR.GZ inspection and extraction for PHP](../assets/archive-guard-readme-cover.png)

## Choose a language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../README.md) | **Selected** | [Español](./README_es.md) | [中文](./README_zh.md) | [Français](./README_fr.md) | [Deutsch](./README_de.md) |

Archive Guard is a PHP library for inspecting ZIP, TAR, and TAR.GZ archives before unpacking
and extracting files with predefined limits.

## Why use this package

If an application receives an archive from a user or an external service, it is useful to
check before unpacking that the archive does not contain unsafe paths, links, or unsupported
elements, and that its size and unpacked data volume stay within the application's limits.

The package lets you inspect an archive separately before unpacking, or inspect and extract
its contents in one operation. If the archive fails inspection, the application receives a
specific reason.

## What the package does

- detects the format from file contents rather than the filename extension;
- validates paths inside the archive and prevents escaping the destination directory;
- rejects symbolic links, hard links, special objects, and unsupported archive elements;
- limits the maximum archive size, the number of files and folders inside it, and the total
  volume of data after unpacking;
- returns a structured list of violations that the application can process;
- lets you inspect an archive with `inspect()` or inspect and extract it with `extract()`;
- on Windows, additionally validates names that the filesystem cannot create safely.

Detailed differences between ZIP, TAR, and TAR.GZ are described in the
[supported archives reference](../reference/supported-archives_en.md).

## Requirements

- PHP `^8.4`;
- `ext-zip`;
- `ext-zlib`.

## Quick start

### Inspect an archive

[`ArchiveGuard::inspect()`](../../src/ArchiveGuard.php) inspects an archive without extracting
files. Limits are configured through [`ArchivePolicy`](../../src/ArchivePolicy.php).

<details>
<summary>Show inspection example</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';

// Choose limits that match the real archives and resources of your application.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$inspection = new ArchiveGuard()->inspect($archivePath, $policy);

if ($inspection->isAccepted()) {
    echo 'Archive passed inspection.' . PHP_EOL;
} else {
    foreach ($inspection->violations() as $violation) {
        // code identifies the rejection reason; message contains its text description.
        echo $violation->code->value . ': ' . $violation->message . PHP_EOL;
    }
}
```

</details>

The values above are only for demonstration. Choose them according to the archive sizes your
application actually expects. The inspection flow is described in detail in the
[`inspect()` guide](../guides/inspection_en.md).

### Extract files

[`ArchiveGuard::extract()`](../../src/ArchiveGuard.php) performs the required inspection
immediately before writing files. The application creates the destination directory in advance.

<details>
<summary>Show extraction example</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

// Choose limits that match the real archives and resources of your application.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$result = new ArchiveGuard()->extract($archivePath, $destinationPath, $policy);

echo 'Files: ' . $result->filesExtracted() . PHP_EOL;
echo 'Directories: ' . $result->directoriesCreated() . PHP_EOL;
echo 'Bytes written: ' . $result->bytesWritten() . PHP_EOL;
```

</details>

The result of an earlier `inspect()` call is not used as permission to write: `extract()`
checks the current state of the file immediately before extraction. Destination requirements
and failure behavior are described in the
[extraction guide](../guides/extraction_en.md).

## Inspection policy

[`ArchivePolicy`](../../src/ArchivePolicy.php) defines upper limits beyond which an archive
is rejected:

- maximum archive file size;
- maximum number of files and folders inside the archive; for TAR, some format metadata
  elements are included in the same limit;
- maximum size of one file after unpacking;
- maximum total size of all unpacked data;
- optional limit on the ratio of unpacked size to compressed size.

There are no universal values: an acceptable archive for avatar uploads and an acceptable
backup archive will need different limits. All parameters and their rules are described in the
[policy reference](../reference/policy-and-errors_en.md).

## Violations and errors

If an archive is recognized but does not pass the configured checks,
[`inspect()`](../../src/ArchiveGuard.php) returns an
[`InspectionResult`](../../src/InspectionResult.php) with violations.

Exceptions are used for a different kind of failure:

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — the file cannot be
  opened, the format is not recognized, or the archive structure is damaged;
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — the archive
  failed the required inspection before extraction, so writing did not begin;
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — the problem is related
  to the destination directory or occurred while extracting.

<details>
<summary>Show error handling example</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$guard = new ArchiveGuard();

try {
    $result = $guard->extract($archivePath, $destinationPath, $policy);
} catch (ArchiveRejectedException $e) {
    // The archive was read, but it did not pass the configured checks.
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException $e) {
    // The file cannot be opened or the archive has an invalid structure.
    echo $e->getMessage() . PHP_EOL;
} catch (ExtractionException $e) {
    // The archive passed inspection, but extraction could not be completed.
    echo $e->getMessage() . PHP_EOL;
}
```

</details>

The full list of violation codes and exceptions is available in the
[errors reference](../reference/policy-and-errors_en.md).

## Security and limitations

Several conditions are important during extraction:

- **Each extraction needs a separate empty directory.** If the application uses one shared
  folder for all archives, create a new subdirectory inside it for each operation. The current
  version does not unpack an archive over existing files.
- **An existing file or directory is not replaced.** If an object appears at the required path
  during extraction, the operation fails instead of overwriting it.
- **Permissions, owner, and modification time from the archive are not restored.** File
  contents and directory structure are extracted; metadata stored in the archive is not applied.
- **Windows has additional filename restrictions.** For example, Windows does not allow
  `CON`, `NUL`, names ending with a dot, or some names containing special characters.
  An archive may therefore pass the general inspection but be rejected immediately before
  extraction on Windows.
- **Extraction is not atomic.** If the disk fills up while writing or another I/O error occurs,
  some files may already exist in the destination directory. There is currently no automatic
  rollback.
- **The destination directory is not locked against other processes.** The checks do not cover
  a situation where another process changes the destination contents at the same time. Use a
  separate directory that only your application can access during extraction.

The reasons for these boundaries and the responsibilities left to the application are described
in the [security model](../security-model_en.md).

## Feedback

- reproducible bugs — [GitHub Issues](https://github.com/yaleksandr89/archive-guard/issues);
- usage questions and ideas — [GitHub Discussions](https://github.com/yaleksandr89/archive-guard/discussions).

---

<p align="center">
  If the package was useful, give it a star on GitHub so other developers can find it more easily. 🤘
</p>
