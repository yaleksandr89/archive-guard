# Archive Guard

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Farchive--guard-blue.svg?style=flat-square)](https://github.com/yaleksandr89/archive-guard)
[![CI](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml/badge.svg)](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)

![Archive Guard — 用于 PHP 的 ZIP、TAR 和 TAR.GZ 检查与解压](../assets/archive-guard-readme-cover.png)

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../README.md) | [English](./README_en.md) | [Español](./README_es.md) | **已选择** | [Français](./README_fr.md) | [Deutsch](./README_de.md) |

Archive Guard 是一个 PHP 库，用于在解压 ZIP、TAR 和 TAR.GZ 之前进行检查，并按照预先设定的限制提取文件。

## 这个包解决什么问题

如果应用会接收用户或外部服务提供的压缩包，那么在解压前最好先确认其中没有危险路径、链接或不受支持的元素，
并确认压缩包大小和解压后的数据量都处于应用允许的范围内。

这个包既可以在解压前单独检查压缩包，也可以在一次操作中完成检查和提取。
如果压缩包未通过检查，应用会得到明确的原因。

## 这个包会做什么

- 根据文件内容而不是文件扩展名识别格式；
- 检查压缩包中的路径，防止路径逃逸到目标目录之外；
- 拒绝符号链接、硬链接、特殊对象以及不受支持的压缩包元素；
- 限制压缩包最大大小、内部文件和文件夹数量，以及解压后的数据总量；
- 返回结构化的违规列表，便于应用处理；
- 可以通过 `inspect()` 单独检查压缩包，也可以通过 `extract()` 检查并提取；
- 在 Windows 上，还会额外检查文件系统无法安全创建的名称。

ZIP、TAR 和 TAR.GZ 的详细差异请参阅
[支持的压缩包格式](../reference/supported-archives_zh.md)。

## 要求

- PHP `^8.4`;
- `ext-zip`;
- `ext-zlib`.

## 快速开始

### 检查压缩包

[`ArchiveGuard::inspect()`](../../src/ArchiveGuard.php) 只检查压缩包，不会向磁盘提取文件。
限制通过 [`ArchivePolicy`](../../src/ArchivePolicy.php) 配置。

<details>
<summary>查看检查示例</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';

// 请根据应用实际处理的压缩包和可用资源设置限制。
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$inspection = new ArchiveGuard()->inspect($archivePath, $policy);

if ($inspection->isAccepted()) {
    echo '压缩包已通过检查。' . PHP_EOL;
} else {
    foreach ($inspection->violations() as $violation) {
        // code 表示拒绝原因，message 提供对应的文字说明。
        echo $violation->code->value . ': ' . $violation->message . PHP_EOL;
    }
}
```

</details>

以上数值仅用于演示。实际值应根据应用真正预期接收的压缩包大小来设置。
完整检查流程请参阅 [`inspect()` 指南](../guides/inspection_zh.md)。

### 提取文件

[`ArchiveGuard::extract()`](../../src/ArchiveGuard.php) 会在写入文件前立即执行必要检查。
目标目录需要由应用提前创建。

<details>
<summary>查看提取示例</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

// 请根据应用实际处理的压缩包和可用资源设置限制。
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$result = new ArchiveGuard()->extract($archivePath, $destinationPath, $policy);

echo '文件数：' . $result->filesExtracted() . PHP_EOL;
echo '目录数：' . $result->directoriesCreated() . PHP_EOL;
echo '写入字节数：' . $result->bytesWritten() . PHP_EOL;
```

</details>

之前调用 `inspect()` 得到的结果不会被当作写入许可：`extract()` 会在提取前检查文件的当前状态。
目标目录要求和错误行为请参阅[提取指南](../guides/extraction_zh.md)。

## 检查策略

[`ArchivePolicy`](../../src/ArchivePolicy.php) 定义压缩包一旦超过就会被拒绝的上限：

- 压缩包文件的最大大小；
- 压缩包内文件和文件夹的最大数量；对于 TAR，一些格式元数据元素也计入同一限制；
- 单个文件解压后的最大大小；
- 所有解压数据的最大总量；
- 可选的“解压后大小与压缩后大小之比”上限。

这些值不存在通用答案：头像上传可接受的压缩包和备份文件可接受的压缩包需要不同限制。
所有参数及规则请参阅[策略参考](../reference/policy-and-errors_zh.md)。

## 违规与错误

如果压缩包格式能够识别，但未通过配置的检查，
[`inspect()`](../../src/ArchiveGuard.php) 会返回包含违规信息的
[`InspectionResult`](../../src/InspectionResult.php)。

异常用于另一类失败：

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — 文件无法打开、
  格式无法识别或压缩包结构已损坏；
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — 压缩包未通过
  提取前的必要检查，因此尚未开始写入；
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — 问题与目标目录有关，
  或在提取过程中发生。

<details>
<summary>查看错误处理示例</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$guard = new ArchiveGuard();

try {
    $result = $guard->extract($archivePath, $destinationPath, $policy);
} catch (ArchiveRejectedException $e) {
    // 压缩包可以读取，但未通过配置的检查。
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException $e) {
    // 文件无法打开，或压缩包结构无效。
    echo $e->getMessage() . PHP_EOL;
} catch (ExtractionException $e) {
    // 压缩包通过了检查，但无法完成提取。
    echo $e->getMessage() . PHP_EOL;
}
```

</details>

完整的违规代码和异常列表请参阅
[错误参考](../reference/policy-and-errors_zh.md)。

## 安全性与限制

提取时需要注意以下条件：

- **每次提取都需要独立的空目录。** 如果应用使用一个公共目录存放所有压缩包的结果，
  应为每次操作在其中新建一个子目录。当前版本不会把压缩包内容覆盖式解压到已有文件上。
- **不会替换已有文件或目录。** 如果提取过程中目标路径上已经出现对象，操作会报错，
  而不是覆盖它。
- **不会恢复压缩包中的权限、所有者和修改时间。** 会提取文件内容和目录结构，但不会应用
  压缩包中保存的这些元数据。
- **Windows 对文件名有额外限制。** 例如 Windows 不允许 `CON`、`NUL`、以点结尾的名称，
  以及某些包含特殊字符的名称。因此压缩包可能通过通用检查，但在 Windows 上真正开始提取前被拒绝。
- **提取不是原子操作。** 如果写入时磁盘空间耗尽或发生其他 I/O 错误，一部分文件可能已经存在于
  目标目录中。目前没有自动回滚。
- **目标目录不会被锁定以阻止其他进程。** 检查不覆盖其他进程同时修改目标目录内容的情况。
  提取时应使用仅由当前应用访问的独立目录。

这些边界为何存在，以及应用还需要自行负责哪些事情，请参阅
[安全模型](../security-model_zh.md)。

## 反馈

- 可复现的问题 — [GitHub Issues](https://github.com/yaleksandr89/archive-guard/issues);
- 使用问题和建议 — [GitHub Discussions](https://github.com/yaleksandr89/archive-guard/discussions).

---

<p align="center">
  如果这个包对你有帮助，请在 GitHub 上给它一个 Star，方便其他开发者发现它。🤘
</p>
