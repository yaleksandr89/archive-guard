# 安全策略

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/security/policy) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_en.md) | [Español](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_es.md) | **已选择** | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_fr.md) | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_de.md) |

## 支持的版本

安全修复面向当前稳定的 `1.x` 系列发布。

| 版本 | 支持 |
|---|---|
| `1.x` | 是 |

## 哪些情况属于安全漏洞

安全问题包括但不限于：

- 绕过路径校验，从而能够把文件写到目标目录之外；
- 提取本应被包拒绝的符号链接、硬链接、特殊对象或其他元素；
- 绕过 `ArchivePolicy` 限制，使提取在单个元素大小、总数据量或其他已配置限制超出后仍继续；
- 检查过的内容与实际提取内容不一致，从而使未经验证的数据被写入；
- 绕过 Windows 文件名或路径冲突检查，导致数据被写入非预期位置；
- ZIP、TAR 或 TAR.GZ 解析错误造成明确的安全影响，例如发生非预期文件写入；
- 源代码、CI、发布标签、已发布包或供应链其他环节遭到破坏。

## 哪些情况本身不属于漏洞

以下行为属于当前版本明确记录的边界：

- 提取不是原子操作，失败后已经创建的文件可能保留；
- 目标目录不会被锁定以阻止其他进程并发修改；
- 目标目录必须事先存在、为空，并且不能是符号链接；
- 已存在的文件和目录不会被覆盖；
- 不提取加密 ZIP，也不会请求密码；
- 不恢复归档中保存的权限、所有者和时间戳；
- 不支持的格式功能会被拒绝，而不是进行部分处理。

如果实际行为违反了已记录的保证，或者在满足文档使用条件时仍能绕过检查，则仍可能构成安全问题。

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
