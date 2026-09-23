# Security model

This page explains which common archive-handling problems the package protects against, which
checks it performs, and which risks still need to be controlled by the application.

This describes library behavior, not the result of a formal security audit.

## Which archive data is considered potentially unsafe

Data coming from the archive itself is treated as potentially unsafe. The package checks:

- file names and paths;
- content type: regular file, directory, link, or special object;
- declared file sizes;
- total unpacked data volume;
- compression information;
- additional ZIP, TAR, and TAR.GZ format features.

The reason is simple: an archive can be structurally valid but still contain a path such as
`../file`, a symbolic link, a huge unpacked file, or a format feature the application does not
expect to handle.

## What `inspect()` does

[`ArchiveGuard::inspect()`](../src/ArchiveGuard.php) inspects an archive without extracting
files.

The inspection includes:

- path normalization and validation;
- detection of conflicting paths;
- checking the type of each element: file, directory, link, or special object;
- limits from [`ArchivePolicy`](../src/ArchivePolicy.php);
- ZIP-, TAR-, and TAR.GZ-specific checks.

If an archive is structurally valid but violates a rule, the application receives an
[`InspectionResult`](../src/InspectionResult.php) with the reasons. If the file itself cannot
be read or parsed reliably, `ArchiveOpenException` is thrown.

## What `extract()` does before writing

[`ArchiveGuard::extract()`](../src/ArchiveGuard.php) always checks the current archive state
immediately before extraction. A previously returned `InspectionResult` is not treated as
permission to write.

The package then:

1. ensures that the destination directory already exists and is empty;
2. checks that the destination directory itself is not a symbolic link;
3. does not overwrite existing files;
4. compares the actual size of every file and the total unpacked volume with the configured
   limits and the sizes obtained during inspection. If more data appears than was allowed,
   extraction stops;
5. reads TAR and TAR.GZ a second time for extraction. The order, path, type, and size of each
   element are compared with the first inspection; if the archive changes between passes,
   extraction stops.

The application creates the destination directory, not the library. The current version works
only with a separate empty directory. If an application stores extraction results in one shared
folder, it must create a new empty subdirectory inside it for every operation.

## Resource limits

Four required limits constrain:

- source archive file size;
- number of files, folders, and counted format metadata elements;
- size of one file or other element after unpacking;
- total unpacked data volume.

Optional `maxCompressionRatio` adds another check against excessive expansion of compressed
data:

- ZIP uses metadata sizes;
- TAR.GZ uses the actual amount of data produced by the decompressor;
- plain TAR does not use this limit.

This is an additional heuristic, not a replacement for the main size limits.

## Windows specifics

Some names are valid inside ZIP or TAR but cannot be safely created on Windows.

Before writing, the package additionally rejects, for example:

- reserved device names such as `CON`, `NUL`, `PRN`, and similar names;
- characters not allowed by Windows;
- names ending with a dot or space;
- paths that differ only by ASCII case when Windows would treat them as the same path.

Therefore, a general `inspect()` call can accept such an archive, while `extract()` on Windows
stops with `ExtractionException` before the first write.

## Limitations and why they exist

### Extraction is not atomic

Files are written directly into the prepared destination directory. If the operation stops
after several files were written successfully, those files remain on disk.

A fully atomic scheme would require unpacking everything into a temporary directory first and
then replacing or moving the complete result in one final action. Such a move has different
constraints across filesystems and on Windows and would require a separate public contract.
The current version therefore does not claim atomicity.

### The directory is not locked against other processes

The current version does not place a system lock on the destination directory. If another
process modifies its contents at the same time, the library checks do not guarantee protection
against those changes.

Use a separate directory that only your application can access during extraction.

### Permissions and owner from the archive are not restored

An archive can contain its own permissions, owner, and timestamps. The current version extracts
file contents and directory structure but does not apply those values from the archive.

### Not every ZIP or TAR feature is supported

If the library cannot understand a format feature reliably enough, it rejects the archive
instead of guessing how to handle it. For example, the current version does not extract
encrypted ZIP elements.

The exact limitations are listed in the
[supported archives reference](reference/supported-archives_en.md).

## Practical recommendations

- choose `ArchivePolicy` limits that match the real archive sizes of your application;
- use a separate empty subdirectory for each extraction operation;
- do not allow untrusted processes to modify that directory at the same time;
- handle inspection violations, open errors, and write errors separately;
- if the application created a temporary directory and fully owns it, it may delete that
  directory after a failed extraction according to its own rules.

[← Back to README](readme/README_en.md)
