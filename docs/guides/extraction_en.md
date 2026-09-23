# Controlled extraction

This guide describes extraction with the built-in checks: what must be prepared in advance,
which errors may occur, and what can remain on disk if writing is interrupted.

[`ArchiveGuard::extract()`](../../src/ArchiveGuard.php) first inspects the archive using the
provided policy and starts creating files only after the inspection succeeds.

## Before extraction

Prepare:

- a readable local ZIP, TAR, or TAR.GZ file;
- an [`ArchivePolicy`](../../src/ArchivePolicy.php) with limits suitable for the application;
- a separate empty destination directory.

The destination directory must **already exist and be empty**. The current version does not
unpack an archive over existing files.

If the application uses one shared folder for all extracted archives, create a new subdirectory
inside it for every operation. This keeps different archives separate and allows the library to
check the entire destination before writing begins.

The PHP process needs permission to create files and subdirectories inside that directory.

## Basic scenario

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

// Choose limits that match the real archives and resources of your application.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->extract($archivePath, $destinationPath, $policy);

    echo 'Files: ' . $result->filesExtracted() . PHP_EOL;
    echo 'Directories: ' . $result->directoriesCreated() . PHP_EOL;
    echo 'Bytes written: ' . $result->bytesWritten() . PHP_EOL;
} catch (ArchiveRejectedException $e) {
    // The archive was read, but it did not pass the configured checks.
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException $e) {
    // The source file cannot be opened or parsed correctly.
    echo $e->getMessage() . PHP_EOL;
} catch (ExtractionException $e) {
    // Destination error or a failure that occurred during extraction.
    echo $e->getMessage() . PHP_EOL;
}
```

## What happens before writing

`extract()` always checks the current archive state immediately before writing. This check is
performed regardless of whether `inspect()` was called earlier.

The sequence is:

1. The library opens and inspects the archive.
2. If violations are found,
   [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) is thrown
   before writing has started.
3. The destination directory is checked.
4. Each file is created only if nothing already exists at its path; an existing object is not
   replaced.
5. While writing, the actual size of every file and the total unpacked volume are compared with
   the configured limits and the sizes obtained during inspection. If the archive starts
   producing more data than allowed, extraction stops.
6. TAR and TAR.GZ are read twice: first for inspection, then for extraction. During the second
   pass, the order, path, type, and size of every element are compared with the first inspection.
   If the archive changes between passes, extraction stops.

## Result

[`ExtractionResult`](../../src/ExtractionResult.php) provides:

- `format()` — archive format;
- `filesExtracted()` — number of files created;
- `directoriesCreated()` — number of directories created;
- `bytesWritten()` — number of bytes written to files.

## Errors

Three main groups of errors are possible during `extract()`:

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — the source file
  cannot be opened or parsed correctly;
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — the archive
  is structurally valid but failed limits or checks;
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — a problem with the
  destination directory, target filename, or the write operation itself.

Violation codes available through `ArchiveRejectedException` are listed in the
[errors reference](../reference/policy-and-errors_en.md).

## Partial failure and atomicity

Data is currently written directly into the prepared destination directory. This makes it
possible to check the actual written volume and avoid overwriting existing files, but it also
means the operation **is not atomic**.

If disk space runs out after several files have been created, the process loses write
permission, or another I/O error occurs, files and directories already created remain in place.
The current file may also remain partially written.

A fully atomic approach is technically possible, but it would be a different contract: all
contents would need to be unpacked into a temporary directory first, followed by a separate
final move of the completed result. Such moves behave differently across filesystems and on
Windows, so the current version does not claim atomicity where it cannot provide it.

If the application creates a dedicated directory for one operation and fully owns it, it may
delete that directory after an `ExtractionException` according to its own rules.

## Windows

Windows imposes additional filename restrictions that do not exist in the general ZIP or TAR
formats. Before the first write, the library additionally rejects, for example:

- device names such as `CON`, `NUL`, `PRN`, and similar names;
- characters forbidden by Windows;
- names ending with a dot or space;
- paths that differ only by ASCII case and point to the same location on Windows.

The same archive can therefore pass `inspect()` at the format level but be rejected by
`extract()` while preparing to write on Windows.

## File metadata

An archive can store not only file contents but also permissions, owner, and modification time.
The current version extracts file contents and directory structure but does not apply this
metadata from the archive.

Additional boundaries and recommendations are described in the
[security model](../security-model_en.md).

[← Back to README](../readme/README_en.md)
