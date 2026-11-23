# Policy, violations, and errors

This reference covers inspection limits, extraction modes, results, violation codes, and the exceptions an application can receive.

## ArchivePolicy

[`ArchivePolicy`](../../../../src/ArchivePolicy.php) is passed to every `inspect()` or `extract()`
call and defines **maximum allowed values**, not exact expected values.

```php
<?php

use Yaleksandr\ArchiveGuard\ArchivePolicy;

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
    maxCompressionRatio: 100.0,
);
```

| Parameter | What it limits | Allowed value |
| --- | --- | --- |
| `maxArchiveBytes` | Maximum size of the archive file itself | Integer greater than zero |
| `maxEntries` | Maximum number of files and folders inside the archive; for TAR, some format metadata elements are included in the same limit | Integer greater than zero |
| `maxEntryUncompressedBytes` | Maximum size of one file or other element after unpacking | Integer greater than zero |
| `maxTotalUncompressedBytes` | Maximum total volume of unpacked data | Integer greater than zero |
| `maxCompressionRatio` | Additional limit on the ratio of unpacked size to compressed size | `null` or a finite number greater than zero |

The first four parameters have no defaults: the application must choose them explicitly.
`maxCompressionRatio` is optional and defaults to `null`.

Compression ratio checks work differently by format:

- ZIP uses sizes from element metadata;
- TAR.GZ uses the actual amount of data produced by the GZIP decompressor;
- plain TAR does not use this limit.

Invalid constructor values throw the standard `InvalidArgumentException`.

## InspectionResult

[`InspectionResult`](../../../../src/InspectionResult.php) is returned only when the source could be
opened and parsed as a supported archive.

Available methods:

- `format()` — returns [`ArchiveFormat`](../../../../src/ArchiveFormat.php): `zip`, `tar`, or
  `tar.gz`;
- `isAccepted()` — returns `true` when there are no violations;
- `violations()` — returns a list of [`Violation`](../../../../src/Violation.php) objects.

If the file cannot be opened, recognized, or parsed correctly, `ArchiveOpenException` is
thrown instead of returning a result.

## ExtractionOptions

[`ExtractionOptions`](../../../../src/ExtractionOptions.php) is a required argument of `extract()` and has two named factories:

```php
ExtractionOptions::atomic();

ExtractionOptions::merge(
    ExtractionConflictStrategy::Reject,
);
```

`atomic()` publishes into a new destination directory that must not exist beforehand. `merge(...)` applies to an existing directory and requires an [`ExtractionConflictStrategy`](../../../../src/ExtractionConflictStrategy.php):

- `Reject`: an existing regular file is a preflight conflict; no Merge changes are applied;
- `Skip`: keep the existing regular file;
- `Overwrite`: replace an existing regular file with a regular file from the archive.

An existing directory is reused when the archive also contains a directory at the same path. File/directory conflicts, links, and special objects on affected destination paths are rejected under every strategy.

## ExtractionResult

[`ExtractionResult`](../../../../src/ExtractionResult.php) describes changes actually applied:

- `format()`: archive format;
- `filesExtracted()`: new and overwritten files actually applied;
- `directoriesCreated()`: directories actually created;
- `bytesWritten()`: content bytes of applied new and overwritten files;
- `filesSkipped()`: files actually skipped;
- `filesOverwritten()`: existing files actually replaced.

For Atomic, `filesSkipped()` and `filesOverwritten()` are both zero.

## Violation

[`Violation`](../../../../src/Violation.php) has three public properties:

- `code` — a [`ViolationCode`](../../../../src/ViolationCode.php) value intended for programmatic
  handling;
- `message` — a short diagnostic description;
- `entryName` — the file, directory, or other archive element name, or `null` if the violation
  applies to the archive as a whole.

For conditions in code, use `code` rather than comparing the text in `message`.

## ViolationCode

| Value | Meaning |
| --- | --- |
| `unsafe_path` | The entry path can escape the permitted directory structure or has an invalid form |
| `path_collision` | Two paths conflict after normalization or platform-specific mapping |
| `platform_incompatible_path` | A path component violates deterministic naming rules of the current platform |
| `symlink_entry` | An archive entry is a symbolic link |
| `hardlink_entry` | A TAR entry is a hard link |
| `special_entry` | A special object, such as a device, was found |
| `archive_too_large` | The archive file exceeds `maxArchiveBytes` |
| `too_many_entries` | The count of files, directories, and counted metadata entries exceeds `maxEntries` |
| `entry_too_large` | One unpacked file or other entry exceeds `maxEntryUncompressedBytes` |
| `total_size_exceeded` | Total unpacked data exceeds `maxTotalUncompressedBytes` |
| `compression_ratio_exceeded` | The configured unpacked-to-compressed size ratio was exceeded |
| `encrypted_entry` | ZIP has an encrypted entry without a supplied password |
| `unsupported_encryption` | The environment does not support the entry encryption method, despite a supplied password |
| `decryption_failed` | Encrypted data could not be decrypted or read |
| `unsupported_compression` | The environment does not support the ZIP compression method |
| `unsupported_feature` | The archive uses an unsupported format feature |

`decryption_failed` means a decryption or encrypted-data read error; `ZipArchive`/`libzip` cannot always identify its exact cause. Not every violation has `entryName`: an overall archive-size failure has no individual entry.

## Exceptions

All package exceptions extend [`ArchiveGuardException`](../../../../src/Exception/ArchiveGuardException.php), itself a `RuntimeException`.

- [`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php): source cannot be opened, format is unknown, structure is corrupt, or accepted unencrypted ZIP data cannot be read reliably. Encrypted-data decryption/read failure with a password instead produces `decryption_failed` (and `ArchiveRejectedException` from `extract()`).
- [`ArchiveRejectedException`](../../../../src/Exception/ArchiveRejectedException.php): archive checks rejected the data before Atomic publication or Merge application. `inspectionResult()` holds the reasons. Some files may already have been created within the temporary workspace before a complete rejection becomes observable.
- [`ExtractionException`](../../../../src/Exception/ExtractionException.php): destination, cooperative lock, staging, preflight, Merge application, Atomic publication, or filesystem I/O error.

`InvalidArgumentException` does not extend `ArchiveGuardException`. It is raised for invalid `ArchivePolicy` values and for a non-null `password` with TAR/TAR.GZ, which have no built-in password encryption.

## How to distinguish a result from an exception

### `inspect()`

- Accepted archives return `InspectionResult` with `isAccepted() === true`.
- Validly inspected but rejected archives return `InspectionResult` with `isAccepted() === false`.
- Unopenable, unparseable, or accepted but unreadable unencrypted ZIP data raises `ArchiveOpenException`.
- `password` is allowed for ZIP; a non-null password for TAR/TAR.GZ raises `InvalidArgumentException`.

### `extract()`

The arguments are:

```text
archivePath
destinationPath
ArchivePolicy
ExtractionOptions
password = null
```

For `archivePath`, `ArchivePolicy`, and `password`, the archive acceptance semantics match `inspect()`.

- Archive rejection raises `ArchiveRejectedException` before Atomic publication or Merge application. Full inspection may occur after a temporary directory has already been created. `inspectionResult()` provides the reasons.
- Source/structure or accepted unencrypted ZIP read failure raises `ArchiveOpenException`.
- Destination, preflight-conflict, or filesystem failure raises `ExtractionException`.
- Success returns `ExtractionResult`.

The source archive-size limit has priority: an archive exceeding `maxArchiveBytes` is rejected before checking password applicability for TAR/TAR.GZ.

Practical examples: [`inspect()`](../guides/inspection.md) and [`extract()`](../guides/extraction.md).

[← Back to README](../README.md)
