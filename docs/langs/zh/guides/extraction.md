# 受控提取

本指南介绍两种明确选择的提取模式：**Atomic** 将完整结果发布到新目录；**Merge** 将归档内容应用到已经存在的目录。

[`ArchiveGuard::extract()`](../../../../src/ArchiveGuard.php) 不会把先前的 `inspect()` 结果视为写入许可。提取时会重新检查当前归档，模式由 [`ExtractionOptions`](../../../../src/ExtractionOptions.php) 明确指定。

## 提取前

请准备：

- 可读取的本地 ZIP、TAR 或 TAR.GZ 文件；
- 根据应用资源选择限制的 [`ArchivePolicy`](../../../../src/ArchivePolicy.php)；
- 明确选择 Atomic 或 Merge 的 [`ExtractionOptions`](../../../../src/ExtractionOptions.php)；
- 满足所选模式要求的目标路径。

对于 `ExtractionOptions::atomic()`，最终目标目录**必须尚不存在**。库会自动在目标的父目录下创建暂存目录，无须手动创建。父目录必须允许创建暂存目录和协作锁目录。

对于 `ExtractionOptions::merge(...)`，目标则必须是**已存在、可读取的真实目录**，不能是符号链接。归档会先完整提取并验证到目标父目录下自动创建的暂存目录，再应用到现有目标目录。

## 基本场景：Atomic

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;
use Yaleksandr\ArchiveGuard\ExtractionOptions;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/result';

// 根据应用实际处理的归档和资源选择限制。
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->extract(
        $archivePath,
        $destinationPath,
        $policy,
        ExtractionOptions::atomic(),
    );

    echo '已应用文件：' . $result->filesExtracted() . PHP_EOL;
    echo '已创建目录：' . $result->directoriesCreated() . PHP_EOL;
    echo '写入字节数：' . $result->bytesWritten() . PHP_EOL;
} catch (ArchiveRejectedException $e) {
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException | ExtractionException $e) {
    echo $e->getMessage() . PHP_EOL;
}
```

Atomic 在目标父目录下自动创建暂存目录。完成全部提取与检查后，使用 `rename()` 将其发布到原先不存在的最终目标路径。

## Merge 与冲突策略

使用 `ExtractionOptions::merge()` 将归档应用到现有目录：

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\ExtractionConflictStrategy;
use Yaleksandr\ArchiveGuard\ExtractionOptions;

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$result = new ArchiveGuard()->extract(
    '/path/to/archive.zip',
    '/path/to/existing-directory',
    $policy,
    ExtractionOptions::merge(ExtractionConflictStrategy::Reject),
);
```

归档文件与目标中**已有普通文件**冲突时，有三种策略：

- `Reject`：在应用前的预检查阶段拒绝冲突，不开始修改 Merge 目标；
- `Skip`：保留现有文件，跳过暂存文件；
- `Overwrite`：用暂存的普通文件替换现有普通文件。

目录有不同的规则：如果归档和目标在同一路径上都是目录，则复用现有目录。文件与目录之间任意方向的类型冲突都属于错误。受影响路径上的符号链接及特殊对象也会被拒绝。

`Overwrite` 只允许普通文件替换普通文件，绝不允许替换目录、链接或其他对象类型。

## 应用更改前的处理

`extract()` 会重新检查当前的 `archivePath`、`ArchivePolicy` 和 `password`，不会使用此前的 `inspect()` 结果作为写入许可。

整体顺序如下：

1. 验证来源，并根据文件内容识别格式。
2. 检查 `maxArchiveBytes`。
3. 检查该格式是否允许传入 `password`。
4. 为目标取得 Archive Guard 协作锁。
5. 在目标父目录下创建暂存目录。
6. 在暂存目录中完整提取并验证归档。
7. Atomic 将完整暂存目录发布到先前不存在的最终目标路径。
8. Merge 先检查全部冲突，再依照选定策略逐项应用文件。
9. 清理暂存目录并释放锁。

ZIP 内容通过共用的流式读取机制处理：读取过程中检查实际解压字节数，不会把整个条目加载到内存。

TAR 与 TAR.GZ 使用单独的提取遍历。每个条目的顺序、路径、类型和大小都与检查结果比较；如果两次遍历之间源文件发生变化，提取将停止。

## ZIP 密码

通过单独的命名参数传入密码：

```php
$result = new ArchiveGuard()->extract(
    $archivePath,
    $destinationPath,
    $policy,
    ExtractionOptions::atomic(),
    password: $password,
);
```

`null` 表示未提供密码；空字符串 `''` 仍视为已提供的密码，不会转换为 `null`。

普通未加密 ZIP 也允许传入密码。加密条目未提供密码时返回 `encrypted_entry`；当前环境不支持加密方式时返回 `unsupported_encryption`；无法解密或读取加密内容时返回 `decryption_failed`。

`decryption_failed` 表示解密或读取失败。`ZipArchive`/`libzip` 不一定能区分密码错误与其他加密数据读取错误。

TAR 和 TAR.GZ 格式本身没有内置的密码加密。对于这两种格式，`password` 必须是 `null`，其他值会抛出 `InvalidArgumentException`。

ZIP 密码被标记为敏感参数，不会保存在 `ExtractionOptions`、`ExtractionResult`、工作区状态或诊断消息中。

## 结果

[`ExtractionResult`](../../../../src/ExtractionResult.php) 描述实际应用的更改：

- `format()`：归档格式；
- `filesExtracted()`：实际应用到目标的新文件和被覆盖文件数；
- `directoriesCreated()`：实际创建的目录数；
- `bytesWritten()`：已应用的新文件和覆盖文件的内容字节数；
- `filesSkipped()`：通过 `Skip` 实际跳过的文件数；
- `filesOverwritten()`：通过 `Overwrite` 实际替换的现有文件数。

Atomic 模式下，`filesSkipped()` 与 `filesOverwritten()` 始终为零。

## 错误

`extract()` 的主要错误分为三类：

- [`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php)：源文件无法打开或可靠解析，或已接受的未加密 ZIP 内容无法可靠读取；
- [`ArchiveRejectedException`](../../../../src/Exception/ArchiveRejectedException.php)：归档未通过检查；`inspectionResult()` 提供原因，此时尚未执行 Atomic 发布或 Merge 应用；
- [`ExtractionException`](../../../../src/Exception/ExtractionException.php)：目标目录、锁、暂存目录、预检查、Merge 应用、Atomic 发布或文件系统 I/O 出错。

为 TAR/TAR.GZ 提供非 `null` 密码会另外抛出 `InvalidArgumentException`。违规代码参见[错误参考](../reference/policy-and-errors.md)。

## Atomic 与 Merge 的应用过程

### Atomic

Atomic 在目标父目录自动创建暂存目录，并在其中完整提取、验证归档。所有检查成功后，`rename()` 将其发布为新的最终目标目录。发布前若出错，操作将停止并删除暂存目录。

### Merge

Merge 用于已有目录。库先在暂存目录完整准备归档并预检查全部冲突，然后根据 `Reject`、`Skip` 或 `Overwrite` 策略依次应用更改。

应用过程中发生文件系统错误会抛出 `ExtractionException`，并停止后续更改。**已经应用的更改仍保留在目标中**。操作结束后暂存目录会被删除。Merge **不是事务性操作**。

### 协作锁

两种模式都通过 `.archive-guard-locks` 协调针对同一目标目录的并发 Archive Guard 操作。锁由库自动创建和使用。协作锁不能阻止任意外部进程写入。

## Merge 访问权限

不能简单要求“整个目标目录均可写”来概括 Merge 权限：

- 使用 `Skip` 时，对于保持不变的冲突文件，不需要仅为该文件获得写入权限。
- 创建新文件或目录、执行 `Overwrite` 时，必须能够写入**具体目标对象的父目录**。
- 在 Windows 上，被覆盖的文件本身还必须可写。
- 在 POSIX 上，如果父目录可写，即使原文件只读也可以替换。
- 如果不需要直接在 Merge 根目录下写入，该根目录可以是只读的。

目标目录的父目录还必须允许创建自动管理的暂存目录和协作锁空间。

归档元数据可能包含权限、所有者和时间戳。Archive Guard 提取文件内容与目录结构，但不会应用归档中保存的这些元数据。

## Windows

检查归档时就会应用当前平台的确定性名称兼容规则。在 Windows 上，拒绝的情况包括：

- 路径组件包含无效 UTF-8；
- 禁止的 Win32 字符和控制字符；
- 名称以点或空格结尾；
- 保留设备名 `CON`、`NUL`、`PRN`、`AUX`、`COM1...`、`LPT1...`；
- 仅 ASCII 字母大小写不同的路径冲突。

依赖具体目标目录及其文件系统的检查在 `extract()` 阶段执行。

详情请参阅[安全模型](../security-model.md)。

[← 返回 README](../README.md)
