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
[`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php) is thrown.

An accepted unencrypted ZIP entry that cannot be read reliably raises `ArchiveOpenException`; encrypted-data decryption or reading failures with a password instead produce `decryption_failed`.

## ZIP

Regular ZIP files and directories are accepted if they pass the common path, resource-limit, compression-method, and actual content-readability checks.

In addition:

- symbolic links and special entry types are rejected;
- directories containing non-zero data are rejected as unsupported features;
- the current `ZipArchive` must support the compression method, otherwise `unsupported_compression` is returned;
- the optional `maxCompressionRatio` uses ZIP entry metadata;
- accepted regular file contents are streamed through EOF and the actual unpacked size is compared with the accepted metadata.

### Encrypted ZIP

The same optional `password` argument is used by `inspect()` and `extract()`:

- an encrypted entry without a password produces `encrypted_entry`;
- an encryption method not supported by the runtime produces `unsupported_encryption`;
- failure to decrypt or read encrypted content produces `decryption_failed`;
- a correct password allows encrypted ZIP files, including archives mixing encrypted and plain entries, if all other checks pass.

A supplied password does not prevent inspection of unencrypted ZIP data. The empty string `''` counts as a supplied password rather than `null`. Actual encryption support depends on `ZipArchive`/`libzip` capabilities in the current runtime.

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

TAR has no built-in password-based encryption. Its `password` argument must be `null`; another value raises `InvalidArgumentException`.

## TAR.GZ

The data produced by GZIP decompression is checked by the same rules as plain TAR.

When `maxCompressionRatio` is configured, the actual amount of data produced by the GZIP
decompressor is counted. This allows excessive expansion of compressed data to be limited
before files are written to disk.

Additional data after the completed GZIP stream and multiple concatenated GZIP streams are
not supported and are treated as an archive structure error.

Like TAR, TAR.GZ has no built-in password encryption. Passing a non-null `password` raises `InvalidArgumentException`.

## Platform path compatibility

The common safe-path checks apply to all supported formats. `inspect()` also enforces deterministic naming restrictions for the current platform.

On Windows, these include:

- valid UTF-8 in path components;
- forbidden Win32 characters and control characters;
- trailing dots or spaces;
- reserved device names;
- ASCII case-insensitive path collisions.

An incompatible name produces `platform_incompatible_path`; a platform-specific collision may produce `path_collision`. These deterministic checks occur in `inspect()`. Destination-specific filesystem checks occur in `extract()`.

## Format and feature errors

If a supported format uses an unsupported feature, inspection returns the corresponding violation code, such as `unsupported_encryption`, `unsupported_compression`, or `unsupported_feature`.

Corrupt archive structure or accepted unencrypted ZIP content that cannot be read reliably raises `ArchiveOpenException`. See all codes in the [violations reference](policy-and-errors.md).

[← Back to README](../README.md)
