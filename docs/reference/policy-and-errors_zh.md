# 策略、违规与错误

本参考说明检查参数、`inspect()` 的结果、违规代码，以及应用使用本包时可能收到的异常。

## ArchivePolicy

每次调用 `inspect()` 或 `extract()` 都要传入
[`ArchivePolicy`](../../src/ArchivePolicy.php)。它定义的是**允许的最大值**，不是精确的期望值。

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
[`InspectionResult`](../../src/InspectionResult.php)。

可用方法：

- `format()` — 返回 [`ArchiveFormat`](../../src/ArchiveFormat.php)：`zip`、`tar` 或 `tar.gz`；
- `isAccepted()` — 没有违规时返回 `true`；
- `violations()` — 返回 [`Violation`](../../src/Violation.php) 对象列表。

如果文件无法打开、识别或正确解析，则不会返回结果，而是抛出 `ArchiveOpenException`。

## Violation

[`Violation`](../../src/Violation.php) 有三个公开属性：

- `code` — [`ViolationCode`](../../src/ViolationCode.php) 值，用于程序逻辑；
- `message` — 简短的诊断说明；
- `entryName` — 文件、目录或其他归档文件元素名称；如果违规针对整个归档文件，则为 `null`。

代码条件判断应使用 `code`，不要比较 `message` 文本。

## ViolationCode

| 值 | 含义 |
| --- | --- |
| `unsafe_path` | 元素路径可能逃逸到允许的目录结构之外，或路径形式无效 |
| `path_collision` | 两个规范化后的路径相同，或作为文件和目录发生冲突 |
| `symlink_entry` | 归档文件中发现符号链接 |
| `hardlink_entry` | TAR 中发现硬链接 |
| `special_entry` | 发现特殊对象，例如设备 |
| `archive_too_large` | 归档文件文件大小超过 `maxArchiveBytes` |
| `too_many_entries` | 文件、文件夹以及计入限制的格式元数据元素数量超过 `maxEntries` |
| `entry_too_large` | 单个文件或其他元素解压后超过 `maxEntryUncompressedBytes` |
| `total_size_exceeded` | 解压数据总量超过 `maxTotalUncompressedBytes` |
| `compression_ratio_exceeded` | 超过配置的解压大小与压缩大小比例限制 |
| `encrypted_entry` | ZIP 含有加密元素；当前版本不会请求密码 |
| `unsupported_compression` | 当前环境在提取时不支持该 ZIP 压缩方法 |
| `unsupported_feature` | 归档文件使用了本包不支持的格式功能 |

并不是每个违规都有 `entryName`。例如，归档文件总大小超限针对的是整个归档文件，
因此没有单独的元素名称。

## 异常

本包的所有异常都继承自
[`ArchiveGuardException`](../../src/Exception/ArchiveGuardException.php)，后者又继承自
`RuntimeException`。

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — 源文件无法打开、
  格式无法识别或归档文件结构已损坏。
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — 归档文件未通过
  提取前的必要检查。`inspectionResult()` 会返回拒绝原因。
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — 目标目录、目标路径或写入过程出现问题。

`ArchivePolicy` 参数无效时的 `InvalidArgumentException` 是标准 PHP 异常，
不继承 `ArchiveGuardException`。

## 如何区分结果和异常

### `inspect()`

- 如果归档文件可以读取并通过检查，方法返回 `InspectionResult`，且 `isAccepted() === true`。
- 如果归档文件可以读取但发现违规，方法仍返回 `InspectionResult`，但 `isAccepted()` 为 `false`，
  原因位于 `violations()`。
- 如果文件本身无法打开或正确解析，则抛出 `ArchiveOpenException`。

### `extract()`

- 如果归档文件未通过提取前的必要检查，则抛出 `ArchiveRejectedException`。
  可通过 `inspectionResult()` 获取违规列表。
- 如果源文件在提取开始前无法打开或识别，则抛出 `ArchiveOpenException`。
- 如果问题出现在准备目标目录或实际写入时，则抛出 `ExtractionException`。
- 成功时返回 [`ExtractionResult`](../../src/ExtractionResult.php)。

实际示例请参阅
[`inspect()` 指南](../guides/inspection_zh.md)和
[`extract()` 指南](../guides/extraction_zh.md)。

[← 返回 README](../readme/README_zh.md)
