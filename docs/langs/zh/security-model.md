# 安全模型

本页解释库如何检查不可信归档、Atomic 与 Merge 的保证，以及调用方仍需自行管理的风险。

这里描述的是库的行为，**不是**正式安全审计报告。

## 哪些归档数据被视为潜在危险

归档数据一律视为不可信。检查包括：

- 文件名、路径、规范化后的冲突，以及当前平台确定性的命名限制；
- 条目类型：普通文件、目录、链接或特殊对象；
- 声明的大小和实际读取的大小；
- 解压数据总量和压缩信息；
- ZIP、TAR、TAR.GZ 的格式功能；
- ZIP 的加密方式及已接受内容的实际可读性。

目标目录是另一条信任边界：应用结果时，目录中可能存在先前检查时没有的文件、目录、链接或其他对象。

归档结构即使正确，也可能含有 `../file`、符号链接、过量解压数据或不支持的功能。格式可读不等于内容可安全应用。

## 归档检查的共同接受语义

`inspect()` 和 `extract()` 使用相同的归档检查输入：

```text
archivePath
ArchivePolicy
password
```

对这些输入，两种方法采用相同的接受语义：

- `inspect()` 返回带违规原因的 `InspectionResult`；
- `extract()` 在 Atomic 发布或 Merge 应用前，抛出包含对应 `InspectionResult` 的 `ArchiveRejectedException`。

提取工作区、目标目录或协作锁错误可能在完整检查结果可观察之前出现。`destinationPath` 和 `ExtractionOptions` 仅决定如何将结果应用到文件系统，不改变归档是否被接受。

## `inspect()` 会做什么

[`ArchiveGuard::inspect()`](../../../src/ArchiveGuard.php) 不会将提取内容写入磁盘。它检查：

- 路径规范化与冲突；
- 当前平台确定性的名称限制；
- 归档条目类型；
- [`ArchivePolicy`](../../../src/ArchivePolicy.php) 的限制；
- ZIP、TAR、TAR.GZ 受支持的功能；
- ZIP 的压缩、加密以及已接受普通文件的流式可读性。

Windows 下不兼容组件返回 `platform_incompatible_path`，忽略 ASCII 大小写后的冲突返回 `path_collision`。确定性规则在 `inspect()` 中检查；实际目标文件系统的条件在 `extract()` 中检查。

## ZIP 密码

可选的 `password` 是敏感参数：

- `null` 表示没有密码，`''` 是明确提供的空密码；
- 普通未加密 ZIP 也允许传入密码；
- 没有密码的加密条目产生 `encrypted_entry`；
- 运行环境不支持的加密方法产生 `unsupported_encryption`；
- 解密或读取加密内容失败产生 `decryption_failed`。

`decryption_failed` 表示解密或读取错误；`ZipArchive`/`libzip` 不一定能判断是不是密码错误。密码参数标记为 `#[SensitiveParameter]`，不会保存在 `ExtractionOptions`、`ExtractionResult`、工作区、检查元数据或诊断消息中。

TAR 和 TAR.GZ 本身没有内置密码加密，`password` 必须是 `null`，否则抛出 `InvalidArgumentException`。

## `extract()` 会做什么

[`ArchiveGuard::extract()`](../../../src/ArchiveGuard.php) 要求明确提供 [`ExtractionOptions`](../../../src/ExtractionOptions.php)。应用结果前：

1. 验证源文件并识别格式。
2. 检查 `maxArchiveBytes`。
3. 检查该格式是否允许密码。
4. 初始化 `ExtractionWorkspace`：检查目标、获取协作锁，并在目标父目录创建暂存目录。
5. `ZipExtractor` 或 `TarExtractor` 在暂存目录中完整扫描、提取和验证归档。
6. 发布 Atomic，或预检查冲突并应用 Merge。
7. 清理工作区并释放锁。

目标、暂存目录、锁或工作区错误可能早于完整归档检查结果出现，但不改变两种方法共同的归档接受语义。

对于 TAR/TAR.GZ，会将提取遍历的条目顺序、路径、类型和大小与检查遍历比较；源文件在两次遍历之间变化则停止提取。

## Atomic

`ExtractionOptions::atomic()` 用于**尚不存在**的新最终目录。库自动在其父目录创建暂存目录，完成提取和所有检查后，再用 `rename()` 发布到原先不存在的最终路径。发布前出错时删除暂存目录。

## Merge

`ExtractionOptions::merge(...)` 用于**已存在的真实目录**。库先在暂存区准备完整内容，并在更改目标前检查全部冲突，再为现有普通文件应用策略：

- `Reject`：在应用前拒绝冲突；
- `Skip`：保留现有普通文件；
- `Overwrite`：用准备好的普通文件替换现有普通文件。

两边都是目录则复用现有目录；文件/目录冲突以及受影响路径上的链接与特殊对象被拒绝。

Merge 依次应用更改。文件系统错误抛出 `ExtractionException` 并停止后续更改；**已经应用的更改会保留**，因此 Merge 不是事务性的。操作后删除暂存目录。

## 协作锁

Archive Guard 自动通过 `.archive-guard-locks` 协调库自身针对同一目标的并发操作。这是**协作锁**，不能阻止任意外部进程修改目标文件。

## 资源限制

四项必需的上限分别限制源归档大小、条目数（包括计入的格式元数据）、单个条目解压大小以及总解压量。可选的 `maxCompressionRatio` 提供按格式区分的限制：

- ZIP 使用条目元数据中的大小；
- TAR.GZ 使用解压器实际产生的数据量；
- 普通 TAR 不应用压缩比限制。

对于已接受的 ZIP 条目，还会进行流式读取和实际大小校验，不只依赖元数据。

## 实践建议

- 根据应用实际数据和资源设置 `ArchivePolicy`。
- 将 Atomic 用于新目录，Merge 用于现有目录，并提前决定冲突策略。
- 直接在调用中传入 ZIP 密码，切勿记录到日志。
- 分别处理 `InspectionResult`、`ArchiveOpenException`、`ArchiveRejectedException` 和 `ExtractionException`。
- Merge 要求具体受影响对象的父目录可写，目标的父目录还要允许创建暂存区和锁。`Skip` 对保留的文件本身不需要额外写权限；Windows 覆盖文件时文件本身也必须可写，而 POSIX 上父目录可写就可能替换只读文件。
- 不要允许无关进程同时修改目标。库提取内容和目录结构，但**不会恢复**归档中的权限、所有者和时间戳。Windows 名称还有在检查阶段执行的特殊限制。

详细信息参阅[支持的归档](reference/supported-archives.md)与[提取指南](guides/extraction.md)。

[← 返回 README](README.md)
