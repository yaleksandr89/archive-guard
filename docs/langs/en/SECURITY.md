# Security Policy

## Choose a language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/security/policy) | **Selected** | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/es/SECURITY.md) | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/zh/SECURITY.md) | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/fr/SECURITY.md) | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/de/SECURITY.md) |

## Supported versions

Security fixes are released for the current stable `1.x` line.

| Version | Supported |
|---|---|
| `1.x` | Yes |

## What counts as a vulnerability

Security issues include, in particular:

- bypassing path checks and writing outside the expected destination directory;
- extracting symbolic or hard links, special objects, or other entries the package must reject;
- bypassing `ArchivePolicy` so data beyond configured limits can be applied;
- applying contents that differ from the verified archive contents;
- bypassing deterministic platform naming or path-collision checks and writing to an unexpected location;
- violating the Atomic guarantee by exposing a final directory as successfully published before required checks finish;
- bypassing Merge preflight or the selected conflict strategy so that an unexpected object is replaced;
- allowing one Archive Guard operation that follows the protocol to bypass another such operation's cooperative lock for the same destination;
- leaking a ZIP password through an exception, violation, result, state, or diagnostic message;
- ZIP/TAR/TAR.GZ parsing or reading faults with concrete security impact;
- compromise of source code, CI, release tags, the package, or another supply-chain component.

## Security model boundaries

Atomic publishes a completely prepared result into a new directory. Merge first prepares the complete result in staging and preflights conflicts, then applies the chosen strategy. Concurrent Archive Guard operations targeting the same directory coordinate through `.archive-guard-locks`.

Supported ZIP encryption methods depend on the current `ZipArchive`/`libzip` environment. Bypassing the documented checks, application modes, or password handling should be treated as a potential vulnerability.

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

- extraction mode: Atomic or Merge, and the conflict strategy if applicable;
- whether a ZIP password is involved, **without sending the secret**;
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
