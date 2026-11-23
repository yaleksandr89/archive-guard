# 安全策略

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/security/policy) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/en/SECURITY.md) | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/es/SECURITY.md) | **已选择** | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/fr/SECURITY.md) | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/de/SECURITY.md) |

## 支持的版本

安全修复面向当前稳定的 `1.x` 系列发布。

| 版本 | 支持 |
|---|---|
| `1.x` | 是 |

## 哪些情况属于安全漏洞

安全问题包括：

- 绕过路径检查，将文件写入预期目标目录以外；
- 提取本应拒绝的符号链接、硬链接、特殊对象或其他条目；
- 绕过 `ArchivePolicy`，应用超出设定限制的数据；
- 实际应用的内容与经过验证的归档内容不一致；
- 绕过平台确定性名称或路径冲突检查，写入意外位置；
- 破坏 Atomic 保证，在必需检查结束之前把最终目录暴露为已成功发布；
- 绕过 Merge 的冲突预检查或所选冲突策略，替换本不应替换的对象；
- 让遵守协议的某个 Archive Guard 操作绕过同一目标的另一个操作的协作锁；
- 通过异常、违规信息、结果、状态或诊断泄露 ZIP 密码；
- ZIP、TAR 或 TAR.GZ 解析/读取错误造成具体安全影响；
- 源代码、CI、发布标签、软件包或其他供应链环节遭到破坏。

## 安全模型边界

Atomic 将完整准备的结果发布到新目录。Merge 先在暂存目录准备全部结果并预检查冲突，然后执行选定策略。针对同一目标的并发 Archive Guard 操作通过 `.archive-guard-locks` 协调。

支持哪些 ZIP 加密方法取决于当前 `ZipArchive`/`libzip` 环境。绕过上述检查、应用模式或密码保护的行为均应视为潜在漏洞。

## 如何报告漏洞

如果仓库提供 GitHub Private Vulnerability Reporting，请优先使用：

1. 打开仓库的 **Security** 标签页。
2. 进入 **Advisories**。
3. 选择 **Report a vulnerability**。
4. 提交报告，不要在公开 Issue 中披露细节。

如果私密表单不可用，请创建一个最小化的公开 Issue，不要包含利用代码或敏感细节，并请求私密沟通渠道。

修复发布前请不要公开：

- 可直接运行的 exploit，或能够立即复现保护绕过的归档文件；
- 真实的秘密、token、凭据或私有数据；
- 含有敏感信息的生产环境路径、文件内容或日志。

## 报告中应包含什么

如有可能，请提供：

- 提取模式是 Atomic 还是 Merge，以及适用时的冲突策略；
- 是否使用 ZIP 密码，但**不要发送真实密码**；
- 受影响的包版本或 commit SHA；
- PHP 版本和操作系统；
- 归档格式：ZIP、TAR 或 TAR.GZ；
- 安全影响；
- 最小复现步骤；
- 最小化的合成归档文件，或其构建方法；
- 预期行为和实际行为；
- 如果已知，可提供可能的修复方案。

请使用合成数据，不要附带真实秘密或用户私有文件。

## 后续处理

项目由一名作者维护，因此不保证固定 SLA。报告会在条件允许时进行检查；确认问题后，将准备修复和回归测试。

在公开技术细节前，请与维护者协调披露时间。项目不承诺提供漏洞奖励计划。
