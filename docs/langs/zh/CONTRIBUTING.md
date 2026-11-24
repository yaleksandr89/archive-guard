# 参与开发

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/blob/master/.github/CONTRIBUTING.md) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/en/CONTRIBUTING.md) | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/es/CONTRIBUTING.md) | **已选择** | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/fr/CONTRIBUTING.md) | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/de/CONTRIBUTING.md) |

感谢你改进 Archive Guard。这里的更改会影响对不可信归档文件的处理和文件系统写入，因此应保持范围清晰、行为可验证，并明确安全边界。

## 开始之前

- 可复现的错误请通过 GitHub Issues 报告。
- 使用问题和想法可以在 GitHub Discussions 中讨论。
- 安全问题请按照[安全策略](https://github.com/yaleksandr89/archive-guard/security/policy)报告，不要公开 exploit 代码或敏感细节。
- 对公共 API、归档格式、提取模型或安全边界的大改动，请先在 Issue 或 Discussion 中讨论。

## 包的契约

- 本包是面向 PHP `>=8.4 <8.6` 的框架无关库。
- 根据内容识别 ZIP、TAR 和 TAR.GZ。
- 主要公共操作为 `ArchiveGuard::inspect()` 和 `ArchiveGuard::extract()`。
- 资源限制通过 `ArchivePolicy` 设置。
- `inspect()` 和 `extract()` 对 `archivePath`、`ArchivePolicy` 与可选 ZIP `password` 采用相同的归档接受语义。
- 拒绝危险路径、规范化冲突、平台不兼容名称、符号链接和硬链接、特殊对象以及不支持的格式功能。
- 对 ZIP，`inspect()` 会流式读取已接受内容，不只检查元数据。
- `extract()` 必须明确传入 `ExtractionOptions`：`atomic()` 用于尚不存在的新目标，`merge(strategy)` 用于现有目录。
- Atomic 自动在目标父目录创建暂存区，完成准备和检查后用 `rename()` 发布完整结果。
- Merge 先完整准备暂存内容、预检查冲突，再应用 `Reject`、`Skip` 或 `Overwrite`。
- `Overwrite` 只能用普通文件替换普通文件；拒绝类型冲突、链接和特殊对象。
- Archive Guard 针对同一目标的操作通过 `.archive-guard-locks` 协调，不排除任意外部写入者。
- ZIP 可使用可选密码进行检查和提取；原始密码不得进入状态、结果或诊断。TAR/TAR.GZ 没有内置密码加密。
- TAR/TAR.GZ 将检查遍历与单独的提取遍历进行比较。
- 不要添加框架集成、存储、后台任务、自动重试/回退、遥测或无关功能。

详细边界参见[安全模型](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/zh/security-model.md)。

## 分支

使用能简要说明目的的分支名，例如：

```text
fix/tar-size-validation
feat/zip-password
docs/security-policy
```

## Commit

使用 Conventional Commits，并保持描述简洁：

```text
fix: исправить проверку размера TAR
feat: добавить поддержку пароля ZIP
docs: уточнить модель безопасности
test: добавить регрессию для коллизии путей
```

每个 commit 应只包含一个连贯的改动，不要夹带无关重构。

## 本地检查

安装依赖并运行完整检查：

```shell
composer install
composer check
```

也可以运行针对性检查：

```shell
composer test
composer analyse
composer cs:check
```

`composer coverage` 是诊断报告，不要求每次改动都运行。

## 测试与 fixtures

- 为实际行为或回归添加测试，不要只追求断言数量。
- 仅使用合成 ZIP/TAR/TAR.GZ 归档和临时目录。
- 密码测试仅使用合成值，并验证诊断中不会泄露密码。
- 不要提交真实用户归档、私有文件、令牌或凭据。
- 修改路径检查、加密、条目类型、限制、Atomic/Merge 或文件系统应用时，必须测试受影响的安全边界。
- 如果行为依赖 Windows 或 libzip 能力，应按实际环境保留能力感知测试与适用的 Windows CI 测试。
- 不要为接受某个有问题的归档而削弱验证；先定义安全的公共契约。

## Pull Request

Pull Request 描述中请说明：

- 问题和实现的更改；
- 对公共 API 和向后兼容性的影响；
- 受影响的安全/文件系统边界；
- 新增或更新的测试；
- 已执行的检查；
- 如果公共行为发生变化，相关文档和翻译是否已同步。

提交前请确认：

- diff 中没有无关更改；
- `git diff --check` 通过；
- `composer check` 或经过说明的相关检查通过；
- 没有提交 `vendor/`、`composer.lock`、`.build/`、包含私有数据的真实归档或其他本地工件；
- 公共 API 和文档与实际行为一致；
- 安全细节没有在公开 Issue 或 Pull Request 中被过早披露。
