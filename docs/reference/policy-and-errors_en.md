# Policy, violations, and errors

This reference describes inspection parameters, the `inspect()` result, violation codes, and
exceptions that an application may receive when using the package.

## ArchivePolicy

[`ArchivePolicy`](../../src/ArchivePolicy.php) is passed to every `inspect()` or `extract()`
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

[`InspectionResult`](../../src/InspectionResult.php) is returned only when the source could be
opened and parsed as a supported archive.

Available methods:

- `format()` — returns [`ArchiveFormat`](../../src/ArchiveFormat.php): `zip`, `tar`, or
  `tar.gz`;
- `isAccepted()` — returns `true` when there are no violations;
- `violations()` — returns a list of [`Violation`](../../src/Violation.php) objects.

If the file cannot be opened, recognized, or parsed correctly, `ArchiveOpenException` is
thrown instead of returning a result.

## Violation

[`Violation`](../../src/Violation.php) has three public properties:

- `code` — a [`ViolationCode`](../../src/ViolationCode.php) value intended for programmatic
  handling;
- `message` — a short diagnostic description;
- `entryName` — the file, directory, or other archive element name, or `null` if the violation
  applies to the archive as a whole.

For conditions in code, use `code` rather than comparing the text in `message`.

## ViolationCode

| Value | Meaning |
| --- | --- |
| `unsafe_path` | The element path may escape the allowed directory structure or has an invalid form |
| `path_collision` | Two normalized paths are equal or conflict as a file and directory |
| `symlink_entry` | A symbolic link was found in the archive |
| `hardlink_entry` | A hard link was found in TAR |
| `special_entry` | A special object, such as a device, was found |
| `archive_too_large` | The archive file size exceeds `maxArchiveBytes` |
| `too_many_entries` | The number of files, folders, and counted format metadata elements exceeds `maxEntries` |
| `entry_too_large` | One file or other element exceeds `maxEntryUncompressedBytes` after unpacking |
| `total_size_exceeded` | The total unpacked data volume exceeds `maxTotalUncompressedBytes` |
| `compression_ratio_exceeded` | The configured ratio of unpacked size to compressed size was exceeded |
| `encrypted_entry` | ZIP contains an encrypted element; the current version does not ask for a password |
| `unsupported_compression` | The ZIP compression method is not supported by the environment for extraction |
| `unsupported_feature` | The archive uses a format feature that the package does not support |

`entryName` is not present for every violation. For example, exceeding the total archive file
size applies to the archive as a whole, so there is no individual element name.

## Exceptions

All package exceptions extend
[`ArchiveGuardException`](../../src/Exception/ArchiveGuardException.php), which in turn
extends `RuntimeException`.

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — the source file
  cannot be opened, the format is not recognized, or the archive structure is damaged.
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — the archive
  failed the required inspection before extraction. `inspectionResult()` returns the reasons.
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — a problem with the
  destination directory, target path, or write operation.

`InvalidArgumentException` for invalid `ArchivePolicy` values is a standard PHP exception and
does not extend `ArchiveGuardException`.

## How to distinguish a result from an exception

### `inspect()`

- If the archive can be read and passes inspection, the method returns `InspectionResult` with
  `isAccepted() === true`.
- If the archive can be read but violations are found, the method still returns
  `InspectionResult`, but `isAccepted()` is `false` and the reasons are available from
  `violations()`.
- If the file itself cannot be opened or parsed correctly, `ArchiveOpenException` is thrown.

### `extract()`

- If the archive fails the required inspection before extraction,
  `ArchiveRejectedException` is thrown. The violations are available through
  `inspectionResult()`.
- If the source file cannot be opened or recognized before extraction begins,
  `ArchiveOpenException` is thrown.
- If the problem occurs while preparing the destination or while writing,
  `ExtractionException` is thrown.
- On success, [`ExtractionResult`](../../src/ExtractionResult.php) is returned.

Practical examples are available in the
[`inspect()` guide](../guides/inspection_en.md) and
[`extract()` guide](../guides/extraction_en.md).

[← Back to README](../readme/README_en.md)
