# 策略、违规与错误

本参考介绍检查限制、提取模式、结果、违规代码和应用可能收到的异常。

## ArchivePolicy

每次调用 `inspect()` 或 `extract()` 都要传入
[`ArchivePolicy`](../../../../src/ArchivePolicy.php)。它定义的是**允许的最大值**，不是精确的期望值。

```php
<?php

use Yaleksandr\ArchiveGuard\ArchivePolicy;

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
    maxCompressionRatio: 100.0,
);
```

| 参数 | 限制内容 | 允许值 |
| --- | --- | --- |
| `maxArchiveBytes` | 归档文件本身的最大大小 | 大于零的整数 |
| `maxEntries` | 归档文件内文件和文件夹的最大数量；对于 TAR，一些格式元数据元素也计入同一限制 | 大于零的整数 |
| `maxEntryUncompressedBytes` | 单个文件或其他元素解压后的最大大小 | 大于零的整数 |
| `maxTotalUncompressedBytes` | 解压数据的最大总量 | 大于零的整数 |
| `maxCompressionRatio` | 解压后大小与压缩后大小之比的额外限制 | `null` 或大于零的有限数值 |

前四个参数没有默认值，应用必须显式选择。`maxCompressionRatio` 可选，默认值为 `null`。

不同格式的压缩比检查方式不同：

- ZIP 使用元素元数据中的大小；
- TAR.GZ 使用 GZIP 解压器实际产生的数据量；
- 普通 TAR 不使用此限制。

构造函数参数无效时会抛出标准 `InvalidArgumentException`。

## InspectionResult

只有在源文件能够打开并解析为受支持归档文件时，才会返回
[`InspectionResult`](../../../../src/InspectionResult.php)。

可用方法：

- `format()` — 返回 [`ArchiveFormat`](../../../../src/ArchiveFormat.php)：`zip`、`tar` 或 `tar.gz`；
- `isAccepted()` — 没有违规时返回 `true`；
- `violations()` — 返回 [`Violation`](../../../../src/Violation.php) 对象列表。

如果文件无法打开、识别或正确解析，则不会返回结果，而是抛出 `ArchiveOpenException`。

## ExtractionOptions

[`ExtractionOptions`](../../../../src/ExtractionOptions.php) 是 `extract()` 的必需参数，通过两个命名工厂创建：

```php
ExtractionOptions::atomic();

ExtractionOptions::merge(
    ExtractionConflictStrategy::Reject,
);
```

`atomic()` 将结果发布到操作前不存在的新目标目录。`merge(...)` 将结果应用到现有目录，必须传入 [`ExtractionConflictStrategy`](../../../../src/ExtractionConflictStrategy.php)：

- `Reject`：在应用前检查阶段拒绝与现有普通文件的冲突；
- `Skip`：保留现有普通文件；
- `Overwrite`：用归档中的普通文件替换现有普通文件。

如果两边同一路径都是目录，则复用现有目录。无论策略如何，文件/目录类型冲突、符号链接和特殊对象都被拒绝。

## ExtractionResult

[`ExtractionResult`](../../../../src/ExtractionResult.php) 表示实际应用的更改：

- `format()`：归档格式；
- `filesExtracted()`：实际应用的新文件及覆盖文件数；
- `directoriesCreated()`：实际创建的目录数；
- `bytesWritten()`：已应用的新文件和覆盖文件的字节数；
- `filesSkipped()`：实际跳过的文件数；
- `filesOverwritten()`：实际替换的现有文件数。

Atomic 中 `filesSkipped()`、`filesOverwritten()` 始终为零。

## Violation

[`Violation`](../../../../src/Violation.php) 有三个公开属性：

- `code` — [`ViolationCode`](../../../../src/ViolationCode.php) 值，用于程序逻辑；
- `message` — 简短的诊断说明；
- `entryName` — 文件、目录或其他归档文件元素名称；如果违规针对整个归档文件，则为 `null`。

代码条件判断应使用 `code`，不要比较 `message` 文本。

## ViolationCode

| 值 | 含义 |
| --- | --- |
| `unsafe_path` | 条目路径可能越过允许的目录结构或形式无效 |
| `path_collision` | 两个路径在规范化或平台映射后发生冲突 |
| `platform_incompatible_path` | 路径组件不符合当前平台确定性的命名规则 |
| `symlink_entry` | 归档条目是符号链接 |
| `hardlink_entry` | TAR 条目是硬链接 |
| `special_entry` | 发现设备等特殊对象 |
| `archive_too_large` | 归档源文件大小超过 `maxArchiveBytes` |
| `too_many_entries` | 文件、目录及计入的元数据条目数超过 `maxEntries` |
| `entry_too_large` | 某个条目解压大小超过 `maxEntryUncompressedBytes` |
| `total_size_exceeded` | 解压数据总量超过 `maxTotalUncompressedBytes` |
| `compression_ratio_exceeded` | 超过设定的解压与压缩大小比值 |
| `encrypted_entry` | ZIP 有加密条目但未提供密码 |
| `unsupported_encryption` | 已提供密码，但运行环境不支持加密方法 |
| `decryption_failed` | 无法解密或读取加密内容 |
| `unsupported_compression` | 运行环境不支持该 ZIP 压缩方法 |
| `unsupported_feature` | 归档使用不支持的格式功能 |

`decryption_failed` 表示解密或读取错误，`ZipArchive`/`libzip` 不一定能确定具体原因。并非所有违规都有 `entryName`：整个归档超出大小上限时没有对应的单个条目。

## 异常

所有包异常继承自 [`ArchiveGuardException`](../../../../src/Exception/ArchiveGuardException.php)，后者继承 `RuntimeException`。

- [`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php)：源文件无法打开、格式无法识别、结构损坏或已接受的未加密 ZIP 内容无法可靠读取。提供密码后加密内容解密/读取失败会产生 `decryption_failed`（在 `extract()` 中为 `ArchiveRejectedException`）。
- [`ArchiveRejectedException`](../../../../src/Exception/ArchiveRejectedException.php)：归档未通过检查，尚未进行 Atomic 发布或 Merge 应用。`inspectionResult()` 提供原因；在最终拒绝可观察之前，暂存目录内可能已经创建了文件。
- [`ExtractionException`](../../../../src/Exception/ExtractionException.php)：目标、协作锁、暂存目录、预检查、Merge 应用、Atomic 发布或文件系统 I/O 错误。

`InvalidArgumentException` 不继承 `ArchiveGuardException`。它用于无效的 `ArchivePolicy` 值，以及对没有内置密码加密的 TAR/TAR.GZ 传入非 `null` 密码。

## 如何区分结果和异常

### `inspect()`

- 检查通过时返回 `isAccepted() === true` 的 `InspectionResult`。
- 检查发现违规时返回 `isAccepted() === false` 的 `InspectionResult`。
- 源文件无法打开或解析，或已接受的未加密 ZIP 内容无法可靠读取，会抛出 `ArchiveOpenException`。
- ZIP 可使用 `password`；TAR/TAR.GZ 非 `null` 密码会抛出 `InvalidArgumentException`。

### `extract()`

参数如下：

```text
archivePath
destinationPath
ArchivePolicy
ExtractionOptions
password = null
```

`archivePath`、`ArchivePolicy` 和 `password` 的归档接受语义与 `inspect()` 相同。

- 拒绝归档时，在 Atomic 发布或 Merge 应用前抛出 `ArchiveRejectedException`。完整检查可能在创建暂存目录后才结束；`inspectionResult()` 提供原因。
- 打开、结构或已接受未加密 ZIP 读取错误产生 `ArchiveOpenException`。
- 目标目录、冲突预检查或文件系统错误产生 `ExtractionException`。
- 成功完成时返回 `ExtractionResult`。

先判断 `maxArchiveBytes`：过大归档在检查 TAR/TAR.GZ 密码是否合法前被拒绝。

示例见 [`inspect()`](../guides/inspection.md) 与 [`extract()`](../guides/extraction.md)。

[← 返回 README](../README.md)
