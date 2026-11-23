# Archive inspection

This guide explains how to inspect an archive before unpacking, how to read the result, and
how a normal limit violation differs from a problem with the archive file itself.

[`ArchiveGuard::inspect()`](../../../../src/ArchiveGuard.php) does not extract anything to disk.
It only analyzes the archive and returns an
[`InspectionResult`](../../../../src/InspectionResult.php).

## Basic scenario

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;

$archivePath = '/path/to/archive.zip';

// Choose limits that match the real archives and resources of your application.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->inspect($archivePath, $policy);

    if ($result->isAccepted()) {
        echo 'Archive passed inspection.' . PHP_EOL;
    } else {
        foreach ($result->violations() as $violation) {
            // code identifies the rejection reason; message contains its text description.
            echo $violation->code->value . ': ' . $violation->message . PHP_EOL;
        }
    }
} catch (ArchiveOpenException $e) {
    echo 'Archive could not be read: ' . $e->getMessage() . PHP_EOL;
}
```

## Password-protected ZIP

Pass the password for an encrypted ZIP as a named argument:

```php
$result = new ArchiveGuard()->inspect(
    $archivePath,
    $policy,
    password: $password,
);
```

Password values are significant:

- `null` means that no password was supplied;
- `''` is an explicitly supplied empty string and is not converted to `null`;
- any supplied value, including `''`, is passed to `ZipArchive` as the password.

A password is allowed even for a plain unencrypted ZIP. TAR and TAR.GZ have no built-in password encryption, so a non-null `password` for either format throws `InvalidArgumentException` with `Password is only valid for ZIP archives.`

Possible violations for encrypted entries are:

- `encrypted_entry`: the entry is encrypted but no password was supplied;
- `unsupported_encryption`: a password was supplied but the runtime does not support that encryption method;
- `decryption_failed`: encrypted contents could not be decrypted or read. This code does not establish that the password itself was incorrect.

The password is marked as a sensitive parameter and must not appear in exceptions, violation messages, or other diagnostics.

## Inspection limits

[`ArchivePolicy`](../../../../src/ArchivePolicy.php) defines **maximum allowed limits**, not exact
expected values:

- `maxArchiveBytes` — maximum size of the archive file itself;
- `maxEntries` — maximum number of files and folders inside the archive; for TAR, some
  format metadata elements are included in the same limit;
- `maxEntryUncompressedBytes` — maximum size of one file after unpacking;
- `maxTotalUncompressedBytes` — maximum total volume of unpacked data;
- `maxCompressionRatio` — optional additional limit on the ratio of unpacked size to
  compressed size.

For example, if `maxArchiveBytes` is `50_000_000`, a 20 MB file does not need to have any
exact size; it simply must not exceed the configured limit.

Detailed rules for every parameter are collected in the
[`ArchivePolicy` reference](../reference/policy-and-errors.md).

The source archive size is checked before checking whether a password is allowed for the format. Thus `archive_too_large` takes precedence over a TAR/TAR.GZ password error.

## Inspection result

[`InspectionResult`](../../../../src/InspectionResult.php) provides three main methods:

- `format()` — detected format: `zip`, `tar`, or `tar.gz`;
- `isAccepted()` — whether the archive passed all checks;
- `violations()` — reasons why the archive was rejected.

If `isAccepted()` returns `false`, the archive itself may still be structurally valid. For
example, it may contain a file that is too large or a symbolic link that the package policy
does not allow to be extracted.

Each [`Violation`](../../../../src/Violation.php) contains:

- `code` — stable reason code suitable for application logic;
- `message` — diagnostic description;
- `entryName` — name of the file, directory, or other archive element if the violation is
  associated with one.

The full list of codes is available in the
[violations reference](../reference/policy-and-errors.md).

## Open and structure errors

[`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php) does not mean the
archive failed the selected policy. It means the archive cannot be read correctly.

Main cases:

- the file does not exist, is not a regular local file, or is not readable;
- the supplied path contains `://`;
- the contents cannot be recognized as ZIP, TAR, or TAR.GZ;
- ZIP, TAR, or GZIP is damaged or has an invalid structure.

In these cases, no `InspectionResult` is returned.

An accepted unencrypted ZIP entry may also fail to be read reliably after metadata validation; this is an `ArchiveOpenException`. By contrast, failure to decrypt or read encrypted content with a password returns a rejected `InspectionResult` containing `decryption_failed`, not `ArchiveOpenException`.

## What is checked

### Paths

The library converts backslashes to `/`, removes empty path segments and `.`, and rejects:

- `..` segments that escape into a parent directory;
- absolute paths, drive-letter paths, and UNC paths;
- names containing a NUL byte;
- duplicates and conflicts after normalization.

On Windows, the same inspection process also enforces deterministic name restrictions: invalid UTF-8, forbidden Win32 and control characters, trailing dots/spaces, reserved device names, and ASCII case-insensitive collisions.

Deterministic platform naming rules are checked by `inspect()` itself. Checks depending on the actual destination directory or its filesystem are performed by `extract()`.

### Content types

Regular files and directories are allowed. Symbolic links, TAR hard links, special objects, and unsupported archive features are reported as violations.

### Resource limits

Inspection enforces archive size, the number of files/directories and counted format metadata entries, each unpacked entry size, total unpacked data volume, and, when set, the compression ratio.

### ZIP contents

After metadata validation, accepted regular ZIP entries are read as streams through EOF. The number of decompressed bytes is checked against the accepted entry metadata. `inspect()` therefore validates actual readability, not just the ZIP central-directory metadata; corrupt data is not accepted merely because its metadata looked correct.

### Relationship between `inspect()` and `extract()`

Both methods use the same archive-check inputs:

```text
archivePath
ArchivePolicy
password
```

For identical archive contents, `ArchivePolicy`, and `password`, the archive acceptance semantics are the same. When extraction reaches the archive checks and they reject it, `extract()` throws `ArchiveRejectedException` with the corresponding `InspectionResult` before Atomic publication or Merge application.

Workspace, destination, and lock errors may occur earlier. `destinationPath` and `ExtractionOptions` affect how the checked result is applied, not whether archive contents are accepted. Permissions, Merge conflicts, lock state, filesystem aliases, and concurrent filesystem changes are checked only during `extract()`.

### TAR and TAR.GZ specifics

TAR and TAR.GZ support the implemented subset of regular headers and GNU/PAX extensions. See the [supported archives reference](../reference/supported-archives.md) for details.
[← Back to README](../README.md)
