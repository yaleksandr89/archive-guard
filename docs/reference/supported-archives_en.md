# Supported archives

This reference describes which ZIP, TAR, and TAR.GZ variants the package understands and
which format features it intentionally rejects.

## Format detection

The format is detected from file contents rather than the filename extension:

- ZIP — by ZIP signature;
- TAR.GZ — by GZIP signature;
- TAR — by a valid TAR header or an empty TAR block.

The source must be a readable regular local file. A path containing `://` is rejected.

If the contents cannot be recognized or the archive structure is damaged,
[`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) is thrown.

## ZIP

The package accepts regular ZIP files and directories when they pass the common path and
resource-limit checks.

Additionally:

- symbolic links and special element types are rejected;
- a directory with non-zero data is rejected as an unsupported feature;
- an encrypted element produces the `encrypted_entry` violation;
- the package **does not ask for a password** and does not extract encrypted contents;
- the compression method must be supported by the current `ZipArchive`, otherwise
  `unsupported_compression` is returned;
- when `maxCompressionRatio` is configured, the ratio is evaluated from ZIP metadata.

The compression-ratio check remains an additional heuristic. The actual written volume is
checked again during extraction.

## TAR

Regular files and directories, USTAR headers, and a limited set of GNU/PAX extensions are
supported when needed to determine the effective name, size, and other allowed metadata.

Important details:

- symbolic and hard links are rejected;
- special and sparse elements are rejected;
- a directory with non-zero data is rejected;
- unknown PAX fields and unsupported extensions are rejected;
- conflicting or repeated local extensions are rejected;
- extension metadata elements also count toward element-count and data-size limits.

A damaged header or invalid TAR structure causes `ArchiveOpenException` rather than a normal
policy violation.

`maxCompressionRatio` is not applied to uncompressed TAR.

## TAR.GZ

The data produced by GZIP decompression is checked by the same rules as plain TAR.

When `maxCompressionRatio` is configured, the actual amount of data produced by the GZIP
decompressor is counted. This allows excessive expansion of compressed data to be limited
before files are written to disk.

Additional data after the completed GZIP stream and multiple concatenated GZIP streams are
not supported and are treated as an archive structure error.

## When a format feature is unsupported

The package prefers to reject an unknown or unsupported archive variant instead of trying to
extract it partially.

Depending on the situation, this is either:

- a structured violation such as `encrypted_entry`, `symlink_entry`, or
  `unsupported_feature`;
- `ArchiveOpenException` when the archive structure is damaged and cannot be parsed reliably.

All violation codes are listed in the
[violations reference](policy-and-errors_en.md).

[← Back to README](../readme/README_en.md)
