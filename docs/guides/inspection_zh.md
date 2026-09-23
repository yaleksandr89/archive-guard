# 归档检查

本指南说明如何在解压前检查归档文件、如何读取结果，以及普通的限制违规与归档文件本身错误之间的区别。

[`ArchiveGuard::inspect()`](../../src/ArchiveGuard.php) 不会向磁盘提取任何内容。
它只分析归档文件并返回 [`InspectionResult`](../../src/InspectionResult.php)。

## 基本场景

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;

$archivePath = '/path/to/archive.zip';

// 请根据应用实际处理的归档文件和可用资源设置限制。
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->inspect($archivePath, $policy);

    if ($result->isAccepted()) {
        echo '归档文件已通过检查。' . PHP_EOL;
    } else {
        foreach ($result->violations() as $violation) {
            // code 表示拒绝原因，message 提供对应的文字说明。
            echo $violation->code->value . ': ' . $violation->message . PHP_EOL;
        }
    }
} catch (ArchiveOpenException $e) {
    echo '无法读取归档文件：' . $e->getMessage() . PHP_EOL;
}
```

## 检查限制

[`ArchivePolicy`](../../src/ArchivePolicy.php) 定义的是**允许的最大值**，而不是必须精确匹配的期望值：

- `maxArchiveBytes` — 归档文件本身的最大大小；
- `maxEntries` — 归档文件内文件和文件夹的最大数量；对于 TAR，一些格式元数据元素也计入同一限制；
- `maxEntryUncompressedBytes` — 单个文件解压后的最大大小；
- `maxTotalUncompressedBytes` — 解压数据的最大总量；
- `maxCompressionRatio` — 可选的“解压后大小与压缩后大小之比”额外上限。

例如，如果 `maxArchiveBytes` 为 `50_000_000`，20 MB 的文件并不需要精确等于某个值，
只需要不超过设定的上限。

每个参数的详细规则请参阅
[`ArchivePolicy` 参考](../reference/policy-and-errors_zh.md)。

## 检查结果

[`InspectionResult`](../../src/InspectionResult.php) 提供三个主要方法：

- `format()` — 检测到的格式：`zip`、`tar` 或 `tar.gz`；
- `isAccepted()` — 归档文件是否通过全部检查；
- `violations()` — 归档文件被拒绝的原因列表。

即使 `isAccepted()` 返回 `false`，归档文件本身仍可能在结构上有效。例如，它可能包含过大的文件，
或者包含策略不允许提取的符号链接。

每个 [`Violation`](../../src/Violation.php) 包含：

- `code` — 稳定的原因代码，适合在应用逻辑中使用；
- `message` — 诊断说明；
- `entryName` — 如果违规与具体元素有关，则为对应文件、目录或其他元素的名称。

完整代码列表请参阅[违规参考](../reference/policy-and-errors_zh.md)。

## 打开和结构错误

[`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) 并不表示归档文件违反了所选策略，
而是表示无法正确读取归档文件。

主要情况：

- 文件不存在、不是普通本地文件或不可读；
- 传入路径包含 `://`；
- 内容无法识别为 ZIP、TAR 或 TAR.GZ；
- ZIP、TAR 或 GZIP 已损坏或结构无效。

这些情况下不会返回 `InspectionResult`。

## 具体检查内容

### 路径

库会把反斜杠转换为 `/`，删除空路径段和 `.`，并拒绝：

- 可能跳到父目录的 `..`；
- 绝对路径；
- 带盘符的路径；
- UNC 路径；
- 包含 NUL 字节的名称；
- 规范化后的重复路径和路径冲突。

### 内容类型

允许普通文件和目录。符号链接、TAR 硬链接、特殊对象和不受支持的格式功能会作为违规返回。

### 资源限制

会检查：

- 归档文件大小；
- 文件、文件夹以及计入限制的格式元数据元素数量；
- 单个文件解压后的大小；
- 解压数据总量；
- 如果配置了，则检查压缩大小与解压大小的比例。

### ZIP、TAR 和 TAR.GZ 的特点

对于 ZIP，还会检查加密和压缩方法。

如果 ZIP 中包含加密元素，`inspect()` 会返回 `encrypted_entry` 违规，
`isAccepted()` 为 `false`。当前版本不会请求密码，也不会提取加密内容。

TAR 和 TAR.GZ 仅支持已实现的普通头部以及 GNU/PAX 扩展集合。
详情请参阅[支持的归档格式](../reference/supported-archives_zh.md)。

[← 返回 README](../readme/README_zh.md)
