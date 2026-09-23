# 参与开发

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/blob/master/.github/CONTRIBUTING.md) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_en.md) | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_es.md) | **已选择** | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_fr.md) | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_de.md) |

感谢你改进 Archive Guard。这里的更改会影响对不可信归档文件的处理和文件系统写入，因此应保持范围清晰、行为可验证，并明确安全边界。

## 开始之前

- 可复现的错误请通过 GitHub Issues 报告。
- 使用问题和想法可以在 GitHub Discussions 中讨论。
- 安全问题请按照[安全策略](https://github.com/yaleksandr89/archive-guard/security/policy)报告，不要公开 exploit 代码或敏感细节。
- 对公共 API、归档格式、提取模型或安全边界的大改动，请先在 Issue 或 Discussion 中讨论。

## 包的契约

- 本包保持为面向 PHP `^8.4` 的 framework-agnostic 库。
- 支持的格式根据内容识别：ZIP、TAR 和 TAR.GZ。
- 主要公共操作是 `ArchiveGuard::inspect()` 和 `ArchiveGuard::extract()`。
- 资源限制通过 `ArchivePolicy` 配置。
- 危险路径、符号链接、硬链接、特殊对象以及不支持的格式功能必须被拒绝。
- `extract()` 会在写入前执行必要检查，不覆盖已有文件，并限制实际写入的数据量。
- 对 TAR 和 TAR.GZ，会将第一次检查结果与提取阶段的第二次读取进行比较。
- 目标目录必须事先存在、为空，并且不能是符号链接。
- 当前版本不承诺原子提取、目标目录锁定、加密 ZIP 提取，也不会恢复归档中的文件系统元数据。
- 不要加入 framework bundle/service provider、存储、后台任务、自动 retry/fallback、遥测或与当前任务无关的功能。

详细边界请参阅[安全模型](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security-model_zh.md)。

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
fix: 修正 TAR 大小校验
feat: 增加 ZIP 密码支持
docs: 澄清安全模型
test: 增加路径冲突回归测试
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

- 为具体行为或回归添加测试，不要只追求 assertion 数量。
- 只使用合成的 ZIP/TAR/TAR.GZ fixtures 和临时目录。
- 不要提交真实用户归档、私有文件、token 或凭据。
- 路径校验、元素类型、限制或提取行为的更改，应覆盖相应安全边界。
- 如果行为与 Windows 有关，应在适用时保留或增加 Windows CI 覆盖。
- 不要为了接受某一个问题归档而削弱校验；应先定义安全的公共契约。

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
