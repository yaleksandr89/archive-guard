# Archive inspection

This guide explains how to inspect an archive before unpacking, how to read the result, and
how a normal limit violation differs from a problem with the archive file itself.

[`ArchiveGuard::inspect()`](../../src/ArchiveGuard.php) does not extract anything to disk.
It only analyzes the archive and returns an
[`InspectionResult`](../../src/InspectionResult.php).

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

## Inspection limits

[`ArchivePolicy`](../../src/ArchivePolicy.php) defines **maximum allowed limits**, not exact
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
[`ArchivePolicy` reference](../reference/policy-and-errors_en.md).

## Inspection result

[`InspectionResult`](../../src/InspectionResult.php) provides three main methods:

- `format()` — detected format: `zip`, `tar`, or `tar.gz`;
- `isAccepted()` — whether the archive passed all checks;
- `violations()` — reasons why the archive was rejected.

If `isAccepted()` returns `false`, the archive itself may still be structurally valid. For
example, it may contain a file that is too large or a symbolic link that the package policy
does not allow to be extracted.

Each [`Violation`](../../src/Violation.php) contains:

- `code` — stable reason code suitable for application logic;
- `message` — diagnostic description;
- `entryName` — name of the file, directory, or other archive element if the violation is
  associated with one.

The full list of codes is available in the
[violations reference](../reference/policy-and-errors_en.md).

## Open and structure errors

[`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) does not mean the
archive failed the selected policy. It means the archive cannot be read correctly.

Main cases:

- the file does not exist, is not a regular local file, or is not readable;
- the supplied path contains `://`;
- the contents cannot be recognized as ZIP, TAR, or TAR.GZ;
- ZIP, TAR, or GZIP is damaged or has an invalid structure.

In these cases, no `InspectionResult` is returned.

## What is checked

### Paths

The library converts backslashes to `/`, removes empty segments and `.`, and rejects:

- `..` segments that can escape to a parent directory;
- absolute paths;
- drive-letter paths;
- UNC paths;
- names containing a NUL byte;
- path duplicates and conflicts after normalization.

### Content types

Regular files and directories are allowed. Symbolic links, TAR hard links, special objects,
and unsupported format features are returned as violations.

### Resource limits

The following are checked:

- archive file size;
- number of files, folders, and counted format metadata elements;
- size of one file after unpacking;
- total unpacked data volume;
- when configured, the ratio of compressed to unpacked size.

### ZIP, TAR, and TAR.GZ specifics

For ZIP, encryption and the compression method are checked as well.

If a ZIP contains an encrypted element, `inspect()` returns the `encrypted_entry` violation
and `isAccepted()` is `false`. The current package version does not ask for a password and
does not extract encrypted contents.

TAR and TAR.GZ support only the implemented set of regular headers and GNU/PAX extensions.
Details are available in the
[supported archives reference](../reference/supported-archives_en.md).

[← Back to README](../readme/README_en.md)
