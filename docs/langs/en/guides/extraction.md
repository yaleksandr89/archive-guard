# Controlled extraction

This guide describes two explicit extraction modes: **Atomic**, which publishes an entirely new directory, and **Merge**, which applies an archive to an existing directory.

[`ArchiveGuard::extract()`](../../../../src/ArchiveGuard.php) does not treat an earlier `inspect()` result as authorization to apply changes. It checks the current archive again during extraction, and the mode is selected through [`ExtractionOptions`](../../../../src/ExtractionOptions.php).

## Before extraction

Prepare:

- a readable local ZIP, TAR, or TAR.GZ file;
- an [`ArchivePolicy`](../../../../src/ArchivePolicy.php) with limits appropriate to your application;
- [`ExtractionOptions`](../../../../src/ExtractionOptions.php) explicitly selecting Atomic or Merge;
- a destination path that meets the requirements of that mode.

For `ExtractionOptions::atomic()`, the final destination directory **must not exist**. The library creates a staging directory inside the destination's parent directory automatically. Do not create the staging directory yourself. The parent must permit creation of both the staging directory and the cooperative-lock directory.

For `ExtractionOptions::merge(...)`, the destination must instead be an **existing, readable, real directory**, not a symbolic link. The archive is completely prepared in an automatically created staging directory in the destination's parent, and only then applied to the existing destination.

## Basic scenario: Atomic

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionOptions;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/result';

// Select limits appropriate for the archives and resources of your application.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->extract(
        $archivePath,
        $destinationPath,
        $policy,
        ExtractionOptions::atomic(),
    );

    echo 'Files applied: ' . $result->filesExtracted() . PHP_EOL;
    echo 'Directories created: ' . $result->directoriesCreated() . PHP_EOL;
    echo 'Bytes written: ' . $result->bytesWritten() . PHP_EOL;
} catch (ArchiveRejectedException $e) {
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException | ExtractionException $e) {
    echo $e->getMessage() . PHP_EOL;
}
```

Atomic uses an automatically created staging directory in the destination's parent. After complete extraction and successful checks, `rename()` publishes that directory at the previously absent final destination path.

## Merge and conflict strategies

Use `ExtractionOptions::merge()` to apply an archive to an existing directory:

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\ExtractionConflictStrategy;
use Yaleksandr\ArchiveGuard\ExtractionOptions;

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$result = new ArchiveGuard()->extract(
    '/path/to/archive.zip',
    '/path/to/existing-directory',
    $policy,
    ExtractionOptions::merge(ExtractionConflictStrategy::Reject),
);
```

Three strategies handle a conflict between an archive file and an existing **regular file**:

- `Reject`: reject the conflict during preflight, before Merge starts applying changes;
- `Skip`: keep the existing file and do not apply the staged file;
- `Overwrite`: replace the existing regular file with a staged regular file.

Directories follow a different rule: when the archive and destination both contain a directory at the same path, the existing directory is reused. File/directory type conflicts in either direction are always errors. Symbolic links and special objects on any affected destination path are rejected as well.

`Overwrite` only replaces a regular file with another regular file. It never authorizes replacing directories, links, or other object types.

## What happens before application

`extract()` checks the current `archivePath`, `ArchivePolicy`, and `password` again; it never relies on a preceding `inspect()` call as permission to write.

The overall sequence is:

1. Validate the source and detect the format from its contents.
2. Enforce `maxArchiveBytes`.
3. Validate whether a password is allowed for this archive format.
4. Acquire an Archive Guard cooperative lock for the destination.
5. Create a staging directory inside the destination's parent directory.
6. Fully extract and validate the archive in that directory.
7. With Atomic, publish the complete staging directory at a previously absent final path.
8. With Merge, preflight all conflicts, then apply files according to the selected conflict strategy.
9. Clean up the staging directory and release the lock.

ZIP content is processed through a shared streaming mechanism. Its actual decompressed volume is checked while reading without loading an entire entry into memory.

TAR and TAR.GZ use a separate extraction pass. Entry order, path, type, and size are compared with the inspection result; extraction stops if the source changes between passes.

## ZIP password

Pass the password as a separate named argument:

```php
$result = new ArchiveGuard()->extract(
    $archivePath,
    $destinationPath,
    $policy,
    ExtractionOptions::atomic(),
    password: $password,
);
```

`null` means that no password was provided. An empty string `''` is still a supplied password; it is not normalized to `null`.

A supplied password is permitted for a plain ZIP. An encrypted entry without a password produces `encrypted_entry`; an encryption method unsupported by the current environment produces `unsupported_encryption`; failure to decrypt or read encrypted data produces `decryption_failed`.

`decryption_failed` means decryption or reading failed. `ZipArchive`/`libzip` cannot always distinguish an incorrect password from another encrypted-data read error.

TAR and TAR.GZ have no built-in password-based encryption. Their `password` argument must be `null`; any other value raises `InvalidArgumentException`.

The ZIP password is marked as sensitive and is not kept in `ExtractionOptions`, `ExtractionResult`, workspace state, or diagnostic messages.

## Result

[`ExtractionResult`](../../../../src/ExtractionResult.php) describes the changes actually applied:

- `format()`: archive format;
- `filesExtracted()`: new and overwritten files actually applied to the destination;
- `directoriesCreated()`: directories actually created;
- `bytesWritten()`: content bytes of new and overwritten files actually applied;
- `filesSkipped()`: files actually skipped by `Skip`;
- `filesOverwritten()`: existing files actually replaced by `Overwrite`.

For Atomic, `filesSkipped()` and `filesOverwritten()` are always zero.

## Errors

There are three main error groups for `extract()`:

- [`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php): the source cannot be opened or reliably parsed, or accepted unencrypted ZIP content cannot be read reliably;
- [`ArchiveRejectedException`](../../../../src/Exception/ArchiveRejectedException.php): the archive failed an archive check. `inspectionResult()` contains the reasons; Atomic publication or Merge application has not started;
- [`ExtractionException`](../../../../src/Exception/ExtractionException.php): a destination, lock, staging, preflight, Merge application, Atomic publication, or filesystem I/O failure.

Passing a non-null password for TAR or TAR.GZ raises `InvalidArgumentException` separately. See the [errors reference](../reference/policy-and-errors.md) for violation codes.

## How Atomic and Merge are applied

### Atomic

Atomic extracts and validates the complete result in a staging directory automatically created within the destination's parent. Once all checks succeed, `rename()` publishes it at a new final destination path. Failure before publication ends the operation and removes the staging directory.

### Merge

Merge targets an existing directory. The complete archive is prepared in staging and all conflicts are checked before destination changes begin. The selected `Reject`, `Skip`, or `Overwrite` strategy is then applied sequentially.

A filesystem error during application raises `ExtractionException` and stops later changes. **Changes already applied remain** in the destination. Staging is removed when Merge finishes. Merge is **not transactional**.

### Cooperative lock

Both modes use `.archive-guard-locks` to coordinate concurrent Archive Guard operations on the same destination. The library creates and uses the lock automatically. This cooperative mechanism does not lock out arbitrary external writers.

## Merge access permissions

Merge permission requirements cannot be reduced to “the entire destination directory must be writable”:

- With `Skip`, no additional write permission is needed solely for a conflicting file that will not be changed.
- Creating a new file or directory, or using `Overwrite`, requires write access to the **parent of the specific object** being modified.
- On Windows, an overwritten file itself must additionally be writable.
- On POSIX, a read-only file can be replaced if its parent directory is writable.
- The Merge root may be read-only when changes require no direct writes in that root.

The parent of the destination directory must also permit the automatically created staging and cooperative-lock space.

Archive metadata can include permissions, ownership, and timestamps. Archive Guard extracts file contents and directory structure but does not apply those stored metadata values.

## Windows

Deterministic compatibility rules for the current platform are already checked during archive inspection. On Windows these reject, for example:

- invalid UTF-8 in a path component;
- forbidden Win32 characters and control characters;
- names ending in a dot or space;
- reserved device names such as `CON`, `NUL`, `PRN`, `AUX`, `COM1...`, `LPT1...`;
- ASCII case-insensitive path collisions.

Checks that depend on the specific destination directory and its filesystem are performed by `extract()`.

Further details are in the [security model](../security-model.md).

[← Back to README](../README.md)
