# 归档检查

本指南说明如何在解压前检查归档文件、如何读取结果，以及普通的限制违规与归档文件本身错误之间的区别。

[`ArchiveGuard::inspect()`](../../../../src/ArchiveGuard.php) 不会向磁盘提取任何内容。
它只分析归档文件并返回 [`InspectionResult`](../../../../src/InspectionResult.php)。

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

## 带密码的 ZIP

对于加密 ZIP，通过命名参数传入密码：

```php
$result = new ArchiveGuard()->inspect(
    $archivePath,
    $policy,
    password: $password,
);
```

这些值具有不同含义：

- `null` 表示没有传入密码；
- `''` 是明确传入的空字符串，不会被转换为 `null`；
- 所有已传入的值（包括 `''`）都会作为密码交给 `ZipArchive`。

普通未加密 ZIP 也允许传入密码。TAR 和 TAR.GZ 本身没有内置密码加密；为它们传入非 `null` 的 `password` 会抛出 `InvalidArgumentException`，消息为 `Password is only valid for ZIP archives.`。

加密条目可能产生以下违规：

- `encrypted_entry`：条目已加密但未提供密码；
- `unsupported_encryption`：提供了密码，但运行环境不支持该加密方法；
- `decryption_failed`：无法解密或读取加密数据；不表示原因必然是密码输错。

密码是敏感参数，不应出现在异常、违规信息或其他诊断消息中。

## 检查限制

[`ArchivePolicy`](../../../../src/ArchivePolicy.php) 定义的是**允许的最大值**，而不是必须精确匹配的期望值：

- `maxArchiveBytes` — 归档文件本身的最大大小；
- `maxEntries` — 归档文件内文件和文件夹的最大数量；对于 TAR，一些格式元数据元素也计入同一限制；
- `maxEntryUncompressedBytes` — 单个文件解压后的最大大小；
- `maxTotalUncompressedBytes` — 解压数据的最大总量；
- `maxCompressionRatio` — 可选的“解压后大小与压缩后大小之比”额外上限。

例如，如果 `maxArchiveBytes` 为 `50_000_000`，20 MB 的文件并不需要精确等于某个值，
只需要不超过设定的上限。

每个参数的详细规则请参阅
[`ArchivePolicy` 参考](../reference/policy-and-errors.md)。

先检查源归档的大小，再检查其格式是否允许密码，因此 `archive_too_large` 优先于 TAR/TAR.GZ 的密码参数错误。

## 检查结果

[`InspectionResult`](../../../../src/InspectionResult.php) 提供三个主要方法：

- `format()` — 检测到的格式：`zip`、`tar` 或 `tar.gz`；
- `isAccepted()` — 归档文件是否通过全部检查；
- `violations()` — 归档文件被拒绝的原因列表。

即使 `isAccepted()` 返回 `false`，归档文件本身仍可能在结构上有效。例如，它可能包含过大的文件，
或者包含策略不允许提取的符号链接。

每个 [`Violation`](../../../../src/Violation.php) 包含：

- `code` — 稳定的原因代码，适合在应用逻辑中使用；
- `message` — 诊断说明；
- `entryName` — 如果违规与具体元素有关，则为对应文件、目录或其他元素的名称。

完整代码列表请参阅[违规参考](../reference/policy-and-errors.md)。

## 打开和结构错误

[`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php) 并不表示归档文件违反了所选策略，
而是表示无法正确读取归档文件。

主要情况：

- 文件不存在、不是普通本地文件或不可读；
- 传入路径包含 `://`；
- 内容无法识别为 ZIP、TAR 或 TAR.GZ；
- ZIP、TAR 或 GZIP 已损坏或结构无效。

这些情况下不会返回 `InspectionResult`。

即使元数据已通过验证，接受的未加密 ZIP 条目也可能无法可靠读取，此时会抛出 `ArchiveOpenException`。提供密码后无法解密或读取加密内容时，则返回含 `decryption_failed` 的拒绝状态 `InspectionResult`，而不是该异常。

## 具体检查内容

### 路径

库将反斜杠转换为 `/`，去掉空路径段和 `.`，并拒绝：

- 可进入父目录的 `..` 段；
- 绝对路径、带驱动器字母的路径和 UNC 路径；
- 包含 NUL 字节的名称；
- 规范化后的重复及冲突路径。

在 Windows 上，同一检查流程还执行确定性名称限制：无效 UTF-8、禁用的 Win32 字符和控制字符、尾部点/空格、保留设备名，以及忽略 ASCII 大小写的路径冲突。

平台名称的确定性规则已在 `inspect()` 检查。依赖具体目标目录及文件系统的条件在 `extract()` 检查。

### 内容类型

允许普通文件和目录。符号链接、TAR 硬链接、特殊对象和不受支持的格式功能会作为违规返回。

### 资源限制

检查源文件大小、文件和目录及计入的格式元数据条目数、单个条目的解压大小、解压总量，以及配置时的压缩比。

### ZIP 内容

通过元数据验证的普通 ZIP 条目会以流式方式读取到 EOF，将实际解压字节数与已接受的元数据比较。因此 `inspect()` 不仅检查 ZIP 中央目录，也验证实际可读性；不能仅凭看似正确的元数据接受损坏内容。

### `inspect()` 与 `extract()` 的关系

两种方法使用相同的归档检查输入：

```text
archivePath
ArchivePolicy
password
```

对于相同归档内容、`ArchivePolicy` 和 `password`，归档接受语义一致。如果 `extract()` 执行到归档检查时发现违规，会在 Atomic 发布或 Merge 应用之前抛出包含对应 `InspectionResult` 的 `ArchiveRejectedException`。

工作区、目标或锁错误可能更早出现。`destinationPath` 和 `ExtractionOptions` 决定如何将结果应用到文件系统，不改变归档内容是否被接受。权限、Merge 冲突、锁状态、文件系统别名及并发变化只在 `extract()` 检查。

### TAR 和 TAR.GZ 特点

TAR 和 TAR.GZ 仅支持已实现的普通头部及 GNU/PAX 扩展子集。详见[支持的归档参考](../reference/supported-archives.md)。
[← 返回 README](../README.md)
