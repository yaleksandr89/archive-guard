# Contributing

## Choose a language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/blob/master/.github/CONTRIBUTING.md) | **Selected** | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/es/CONTRIBUTING.md) | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/zh/CONTRIBUTING.md) | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/fr/CONTRIBUTING.md) | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/de/CONTRIBUTING.md) |

Thank you for improving Archive Guard. Changes here affect untrusted archive handling and filesystem writes, so a small scope, verifiable behavior, and explicit security boundaries matter.

## Before you start

- Report reproducible bugs through GitHub Issues.
- Usage questions and ideas can be discussed in GitHub Discussions.
- Report security problems according to the [security policy](https://github.com/yaleksandr89/archive-guard/security/policy) without publishing exploit code or sensitive details.
- Discuss large public API, archive-format, extraction-model, or security-boundary changes in an Issue or Discussion first.

## Package contract

- The package remains a framework-agnostic library for PHP `>=8.4 <8.6`.
- Supported ZIP, TAR, and TAR.GZ formats are detected from contents.
- The main public operations are `ArchiveGuard::inspect()` and `ArchiveGuard::extract()`.
- `ArchivePolicy` supplies resource limits.
- `inspect()` and `extract()` use the same archive acceptance semantics for `archivePath`, `ArchivePolicy`, and optional ZIP `password`.
- Unsafe paths, normalized collisions, platform-incompatible names, symbolic and hard links, special objects, and unsupported features must be rejected.
- For ZIP, `inspect()` checks streaming readability of accepted contents, not metadata alone.
- `extract()` requires explicit `ExtractionOptions`: `atomic()` for a new previously absent destination or `merge(strategy)` for an existing directory.
- Atomic automatically creates staging in the destination's parent, fully prepares the result, and publishes the complete result with `rename()`.
- Merge prepares staging, preflights conflicts, then applies `Reject`, `Skip`, or `Overwrite`.
- `Overwrite` only replaces a regular file with another regular file. Type conflicts, links, and special objects are rejected.
- Archive Guard operations on the same destination coordinate through `.archive-guard-locks`; unrelated external writers are not excluded.
- ZIP can be inspected and extracted with an optional password. Never include the original password in results, state, or diagnostics. TAR and TAR.GZ do not have built-in password encryption.
- TAR and TAR.GZ compare the inspection pass with the separate extraction pass.
- Do not add framework integrations, storage, background jobs, automatic retries/fallbacks, telemetry, or unrelated features.

See the [security model](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/en/security-model.md) for details.

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
fix: исправить проверку размера TAR
feat: добавить поддержку пароля ZIP
docs: уточнить модель безопасности
test: добавить регрессию для коллизии путей
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

- Add tests for concrete behavior or regressions, not assertion counts.
- Use only synthetic ZIP/TAR/TAR.GZ archives and temporary directories.
- For password tests use only synthetic values and verify that they are never exposed in diagnostics.
- Do not include real user archives, private files, tokens, or credentials.
- Changes to paths, encryption, entry types, limits, Atomic/Merge, or filesystem application must test the affected security boundary.
- When behavior depends on Windows or libzip capabilities, preserve capability-aware tests and Windows CI coverage as appropriate.
- Do not weaken validation merely to accept a problematic archive; first establish a safe public contract.

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
