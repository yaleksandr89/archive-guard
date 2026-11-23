# Security model

This page describes how the package checks untrusted archives, the guarantees of Atomic and Merge, and the risks the calling application must still control.

This describes library behavior, **not** the findings of a formal security audit.

## Which archive data is considered potentially unsafe

Archive input is untrusted. The checks cover:

- file names and paths, plus collisions after normalization and deterministic restrictions of the current platform;
- entry types: regular file, directory, link, or special object;
- declared sizes and data sizes actually read;
- total unpacked volume and compression data;
- ZIP, TAR, and TAR.GZ features;
- ZIP encryption and actual readability of accepted contents.

The destination is a separate trust boundary: files, directories, links, or other objects may already be present when the result is applied, even if they were absent at an earlier inspection.

An archive may be structurally valid but still contain `../file`, a symbolic link, excessive unpacked data, or an unsupported feature. These are separate from the structure being readable.

## Common archive acceptance semantics

`inspect()` and `extract()` check the same archive inputs:

```text
archivePath
ArchivePolicy
password
```

For these inputs both methods apply the same archive acceptance semantics:

- `inspect()` returns an `InspectionResult` with violation reasons;
- `extract()` raises `ArchiveRejectedException` with the corresponding `InspectionResult` before Atomic publication or Merge application.

Errors associated with the extraction workspace, destination, or cooperative lock may occur before full archive acceptance becomes observable. `destinationPath` and `ExtractionOptions` only specify how to apply an accepted result to the filesystem; they do not change whether the archive is accepted.

## What `inspect()` does

[`ArchiveGuard::inspect()`](../../../src/ArchiveGuard.php) does not write extracted files to disk. It checks:

- path normalization and collisions;
- deterministic naming restrictions of the current platform;
- types of archive entries;
- [`ArchivePolicy`](../../../src/ArchivePolicy.php) limits;
- supported ZIP, TAR, and TAR.GZ features;
- for ZIP, compression, encryption, and streaming readability of accepted regular files.

On Windows, invalid components yield `platform_incompatible_path`, while ASCII case-insensitive collisions yield `path_collision`. Deterministic naming checks occur in `inspect()`; conditions specific to the actual destination and filesystem occur in `extract()`.

## ZIP passwords

The optional `password` is a sensitive parameter:

- `null` means no password; `''` means an explicitly supplied empty password;
- a password may also be supplied for plain ZIP archives;
- encrypted entries without a password produce `encrypted_entry`;
- encryption methods unsupported by the runtime produce `unsupported_encryption`;
- failure to decrypt or read encrypted data produces `decryption_failed`.

`decryption_failed` signifies a decryption/read error; `ZipArchive`/`libzip` cannot always pinpoint whether the password is wrong. The password has `#[SensitiveParameter]` and is not saved in `ExtractionOptions`, `ExtractionResult`, workspace state, inspection metadata, or diagnostic messages.

TAR and TAR.GZ have no built-in password encryption. Their `password` must be `null`; any other value raises `InvalidArgumentException`.

## What `extract()` does

[`ArchiveGuard::extract()`](../../../src/ArchiveGuard.php) requires explicit [`ExtractionOptions`](../../../src/ExtractionOptions.php). Before applying results it:

1. Validates the source and detects its format.
2. Checks `maxArchiveBytes`.
3. Checks password applicability for that format.
4. Initializes `ExtractionWorkspace`: validates the destination, acquires a cooperative lock, and creates staging inside the destination's parent.
5. Uses `ZipExtractor` or `TarExtractor` to scan, extract, and validate the whole archive in staging.
6. Publishes Atomic or preflights and applies Merge.
7. Cleans the workspace and releases the lock.

Destination, staging, lock, or workspace failures can occur before the full archive result is observable, without changing the common archive acceptance rules.

For TAR/TAR.GZ, order, path, type, and size of entries are checked against the inspection pass. Changes to the source between passes stop extraction.

## Atomic

`ExtractionOptions::atomic()` targets a new final directory that **must not exist**. The library automatically extracts into staging within its parent, validates everything, then publishes it with `rename()` at the previously absent final path. Failure before publication removes staging.

## Merge

`ExtractionOptions::merge(...)` targets an **existing real directory**. The archive is fully prepared in staging, all destination conflicts are checked before changes, and then the selected strategy applies to existing regular files:

- `Reject`: conflict is rejected before application;
- `Skip`: existing regular file remains;
- `Overwrite`: existing regular file is replaced by a staged regular file.

Matching directories are reused. File/directory conflicts, links, and special objects on affected paths are rejected.

Merge applies changes sequentially. A filesystem error during application raises `ExtractionException` and stops later changes; **already applied changes remain**, so Merge is not transactional. Staging is removed after the operation.

## Cooperative lock

Archive Guard automatically uses `.archive-guard-locks` to coordinate its own concurrent operations on the same destination. This is a **cooperative lock**: it does not stop arbitrary external processes from modifying destination files.

## Resource limits

Four required maximums constrain source archive size, number of entries (including counted metadata entries), size of an unpacked entry, and total unpacked volume. Optional `maxCompressionRatio` adds format-specific protection:

- ZIP uses entry metadata sizes;
- TAR.GZ uses the decompressor's actual output volume;
- plain TAR does not apply the ratio.

Accepted ZIP entries are also streamed and checked for actual readability and size, not just their metadata.

## Practical recommendations

- Choose `ArchivePolicy` limits for real application data and available resources.
- Use Atomic when publishing into a new directory; use Merge for an existing directory, with a deliberate conflict strategy.
- Pass a ZIP password directly to the call and never log it.
- Handle `InspectionResult`, `ArchiveOpenException`, `ArchiveRejectedException`, and `ExtractionException` separately.
- Ensure writable permissions on the specific parent paths Merge will modify and on the destination's parent for staging and lock creation. For `Skip`, a preserved file does not require write permission solely for that conflict; on Windows, files overwritten by `Overwrite` must themselves be writable. On POSIX, a writable parent can permit replacing a read-only file.
- Do not allow unrelated processes to modify the destination concurrently. The library extracts contents and directory structure but does **not** restore archive permissions, owner, or timestamps. Windows has extra name restrictions checked during inspection.

See the [supported archive formats](reference/supported-archives.md) and [extraction guide](guides/extraction.md) for details.

[← Back to README](README.md)
