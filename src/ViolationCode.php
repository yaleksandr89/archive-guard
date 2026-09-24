<?php

declare(strict_types=1);

namespace Yaleksandr\ArchiveGuard;

enum ViolationCode: string
{
    case UnsafePath = 'unsafe_path';
    case PathCollision = 'path_collision';
    case PlatformIncompatiblePath = 'platform_incompatible_path';
    case SymlinkEntry = 'symlink_entry';
    case HardlinkEntry = 'hardlink_entry';
    case SpecialEntry = 'special_entry';
    case ArchiveTooLarge = 'archive_too_large';
    case TooManyEntries = 'too_many_entries';
    case EntryTooLarge = 'entry_too_large';
    case TotalSizeExceeded = 'total_size_exceeded';
    case CompressionRatioExceeded = 'compression_ratio_exceeded';
    case EncryptedEntry = 'encrypted_entry';
    case UnsupportedEncryption = 'unsupported_encryption';
    case DecryptionFailed = 'decryption_failed';
    case UnsupportedCompression = 'unsupported_compression';
    case UnsupportedFeature = 'unsupported_feature';
}
