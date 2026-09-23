# 受控提取

本指南介绍带有内置检查的提取流程：需要提前准备什么、可能出现哪些错误，以及写入中断时磁盘上可能留下什么。

[`ArchiveGuard::extract()`](../../src/ArchiveGuard.php) 会先按照传入策略检查压缩包，
只有检查成功后才开始创建文件。

## 提取前

请准备：

- 可读取的本地 ZIP、TAR 或 TAR.GZ 文件；
- 一个设置了适合应用限制的 [`ArchivePolicy`](../../src/ArchivePolicy.php)；
- 一个独立的空目标目录。

目标目录必须**提前存在并且为空**。当前版本不会把压缩包覆盖式解压到已有文件上。

如果应用使用一个公共目录保存所有提取结果，请为每次操作在其中创建新的子目录。
这样不同压缩包的内容不会混在一起，库也可以在开始写入前完整检查目标目录。

PHP 进程需要有权在该目录中创建文件和子目录。

## 基本场景

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

// 请根据应用实际处理的压缩包和可用资源设置限制。
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->extract($archivePath, $destinationPath, $policy);

    echo '文件数：' . $result->filesExtracted() . PHP_EOL;
    echo '目录数：' . $result->directoriesCreated() . PHP_EOL;
    echo '写入字节数：' . $result->bytesWritten() . PHP_EOL;
} catch (ArchiveRejectedException $e) {
    // 压缩包可以读取，但未通过配置的检查。
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException $e) {
    // 源文件无法打开或无法正确解析。
    echo $e->getMessage() . PHP_EOL;
} catch (ExtractionException $e) {
    // 目标目录错误，或提取过程中发生失败。
    echo $e->getMessage() . PHP_EOL;
}
```

## 写入前会发生什么

`extract()` 总会在真正写入前检查压缩包的当前状态，无论之前是否调用过 `inspect()`。

流程如下：

1. 库打开并检查压缩包。
2. 如果发现违规，会在尚未开始写入时抛出
   [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php)。
3. 检查目标目录。
4. 只有目标路径上还不存在任何对象时才创建文件；已有对象不会被替换。
5. 写入时，会把每个文件的实际大小和解压数据总量与配置的限制以及检查阶段得到的大小比较。
   如果压缩包开始产生超过允许范围的数据，提取会停止。
6. TAR 和 TAR.GZ 会读取两次：第一次用于检查，第二次用于提取。第二次读取时会把每个元素的顺序、
   路径、类型和大小与第一次检查结果比较。如果两次之间压缩包发生变化，提取会停止。

## 结果

[`ExtractionResult`](../../src/ExtractionResult.php) 提供：

- `format()` — 压缩包格式；
- `filesExtracted()` — 创建的文件数量；
- `directoriesCreated()` — 创建的目录数量；
- `bytesWritten()` — 写入文件的字节数。

## 错误

`extract()` 过程中主要可能出现三类错误：

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — 源文件无法打开或无法正确解析；
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — 压缩包结构有效，
  但没有通过限制或检查；
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — 目标目录、目标文件名或磁盘写入本身出现问题。

`ArchiveRejectedException` 中可用的违规代码列在
[错误参考](../reference/policy-and-errors_zh.md)中。

## 部分失败与原子性

当前数据会直接写入准备好的目标目录。这样可以检查实际写入量并避免覆盖已有文件，
但也意味着操作**不是原子的**。

如果已经创建了几个文件后磁盘空间耗尽、进程失去写权限或发生其他 I/O 错误，
已创建的文件和目录会保留下来。当前正在写入的文件也可能只写入了一部分。

完全原子的方案在技术上可行，但会是另一套契约：需要先把全部内容解压到临时目录，
然后再单独进行一次最终移动。不同文件系统和 Windows 对这类移动的行为并不相同，
因此当前版本不会声称提供无法保证的原子性。

如果应用为一次操作专门创建了一个目录并完全拥有它，那么可以在
`ExtractionException` 后按照自己的规则删除该目录。

## Windows

Windows 对文件名有一些 ZIP 或 TAR 通用格式本身没有的额外限制。
第一次写入前，库还会拒绝例如：

- `CON`、`NUL`、`PRN` 等设备名称；
- Windows 禁止的字符；
- 以点或空格结尾的名称；
- 仅 ASCII 大小写不同、但 Windows 会视为同一路径的路径。

因此同一个压缩包可能在格式层面通过 `inspect()`，却在 Windows 上准备写入时被 `extract()` 拒绝。

## 文件元数据

压缩包除了文件内容外，还可以保存权限、所有者和修改时间。
当前版本会提取文件内容和目录结构，但不会应用压缩包中的这些元数据。

其他边界和建议请参阅[安全模型](../security-model_zh.md)。

[← 返回 README](../readme/README_zh.md)
