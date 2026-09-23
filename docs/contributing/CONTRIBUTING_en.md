# Contributing

## Choose a language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/blob/master/.github/CONTRIBUTING.md) | **Selected** | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_es.md) | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_zh.md) | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_fr.md) | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_de.md) |

Thank you for improving Archive Guard. Changes here affect untrusted archive handling and filesystem writes, so a small scope, verifiable behavior, and explicit security boundaries matter.

## Before you start

- Report reproducible bugs through GitHub Issues.
- Usage questions and ideas can be discussed in GitHub Discussions.
- Report security problems according to the [security policy](https://github.com/yaleksandr89/archive-guard/security/policy) without publishing exploit code or sensitive details.
- Discuss large public API, archive-format, extraction-model, or security-boundary changes in an Issue or Discussion first.

## Package contract

- The package remains a framework-agnostic library for PHP `^8.4`.
- Supported formats are detected from content: ZIP, TAR, and TAR.GZ.
- The main public operations are `ArchiveGuard::inspect()` and `ArchiveGuard::extract()`.
- Resource limits are configured through `ArchivePolicy`.
- Unsafe paths, symbolic and hard links, special objects, and unsupported format features must be rejected.
- `extract()` performs the required inspection before writing, does not overwrite existing files, and enforces actual written-data limits.
- For TAR and TAR.GZ, the first-pass result is compared with the extraction pass.
- The destination directory must already exist, be empty, and not be a symbolic link.
- The current version does not promise atomic extraction, destination locking, encrypted ZIP extraction, or restoration of archive filesystem metadata.
- Do not add framework bundles/service providers, storage, background jobs, automatic retry/fallback behavior, telemetry, or unrelated features.

Detailed boundaries are documented in the [security model](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security-model_en.md).

## Branches

Use a short name that describes the change, for example:

```text
fix/tar-size-validation
feat/zip-password
docs/security-policy
```

## Commits

Use Conventional Commits with a concise description:

```text
fix: correct TAR size validation
feat: add ZIP password support
docs: clarify the security model
test: add path-collision regression
```

Keep each commit focused on one coherent change without unrelated refactoring.

## Local checks

Install dependencies and run the full check set:

```shell
composer install
composer check
```

Targeted checks are also available:

```shell
composer test
composer analyse
composer cs:check
```

`composer coverage` is a diagnostic report and is not required for every change.

## Tests and fixtures

- Add tests for concrete behavior or a regression, not for assertion count.
- Use only synthetic ZIP/TAR/TAR.GZ fixtures and temporary directories.
- Do not add real user archives, private files, tokens, or credentials.
- Changes to path validation, entry types, limits, or extraction should include tests for the affected security boundary.
- If behavior is Windows-specific, preserve or add Windows CI coverage where appropriate.
- Do not weaken validation merely to accept one problematic archive; define the safe public contract first.

## Pull Request

In the Pull Request description, include:

- the problem and the implemented change;
- public API and backward-compatibility impact;
- the affected security/filesystem boundary;
- added or updated tests;
- checks that were run;
- documentation changes and translation synchronization when public behavior changed.

Before submitting, make sure:

- the diff contains no unrelated changes;
- `git diff --check` passes;
- `composer check` or a justified set of relevant checks passes;
- `vendor/`, `composer.lock`, `.build/`, real archives containing private data, and other local artifacts are not committed;
- public API and documentation match actual behavior;
- security details are not disclosed prematurely in a public Issue or Pull Request.
