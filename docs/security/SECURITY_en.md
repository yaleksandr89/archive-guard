# Security Policy

## Choose a language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/security/policy) | **Selected** | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_es.md) | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_zh.md) | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_fr.md) | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_de.md) |

## Supported versions

Security fixes are released for the current stable `1.x` line.

| Version | Supported |
|---|---|
| `1.x` | Yes |

## What counts as a vulnerability

Security issues include, in particular:

- bypassing path validation in a way that allows a file to be written outside the destination directory;
- extracting a symbolic link, hard link, special object, or another element that the package is expected to reject;
- bypassing `ArchivePolicy` limits so extraction continues after an allowed per-entry size, total data volume, or another configured limit has been exceeded;
- a mismatch between inspected and actually extracted content that allows unvalidated data to be written;
- bypassing Windows filename or path-collision checks in a way that writes data to an unexpected location;
- a ZIP, TAR, or TAR.GZ parsing flaw with a concrete security impact, such as an unintended file write;
- compromise of source code, CI, release tags, published packages, or another part of the supply chain.

## What is not a vulnerability by itself

The following behavior is a documented boundary of the current version:

- extraction is not atomic, and files already created may remain after a failure;
- the destination directory is not locked against concurrent modification by another process;
- the destination directory must already exist, be empty, and not be a symbolic link;
- existing files and directories are not overwritten;
- encrypted ZIP archives are not extracted and no password is requested;
- permissions, owner, and timestamps stored in an archive are not restored;
- unsupported format features are rejected instead of being partially processed.

If actual behavior violates a documented guarantee or allows a check to be bypassed while the documented usage conditions are satisfied, it may still be a security issue.

## Reporting a vulnerability

GitHub Private Vulnerability Reporting is the preferred channel when it is available for the repository:

1. Open the repository **Security** tab.
2. Go to **Advisories**.
3. Select **Report a vulnerability**.
4. Submit the report without publishing details in a public Issue.

If the private form is unavailable, open a minimal public Issue without exploit code or sensitive details and request a private communication channel.

Do not publish before a fix is available:

- a ready-to-run exploit or archive that directly reproduces a protection bypass;
- real secrets, tokens, credentials, or private data;
- production paths, file contents, or logs containing sensitive information.

## What to include

When possible, include:

- affected package version or commit SHA;
- PHP version and operating system;
- archive format: ZIP, TAR, or TAR.GZ;
- security impact;
- minimal reproduction steps;
- a minimal synthetic archive or instructions for building one;
- expected and actual behavior;
- a possible fix, if known.

Use synthetic data. Do not attach real secrets or private user files.

## What happens next

The project is maintained by one author, so no fixed SLA is guaranteed. The report will be reviewed as time permits, and a confirmed issue will be followed by a fix and regression coverage.

Please coordinate public disclosure with the maintainer before publishing technical details. No bug bounty program is promised.
