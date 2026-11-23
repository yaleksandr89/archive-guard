# 支持的归档格式

本参考说明本包能够处理哪些 ZIP、TAR 和 TAR.GZ 变体，以及哪些格式功能会被主动拒绝。

## 格式识别

格式根据文件内容而不是文件扩展名识别：

- ZIP — 根据 ZIP 签名；
- TAR.GZ — 根据 GZIP 签名；
- TAR — 根据有效的 TAR 头部或空 TAR 块。

源必须是可读取的普通本地文件。包含 `://` 的路径会被拒绝。

如果内容无法识别或归档文件结构已损坏，会抛出
[`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php)。

如果已接受的未加密 ZIP 条目无法可靠读取，会抛出 `ArchiveOpenException`；提供密码后加密内容的解密或读取失败则返回 `decryption_failed`。

## ZIP

普通 ZIP 文件和目录通过路径、资源限制、压缩方法及实际内容可读性检查后才被接受。

其他规则：

- 拒绝符号链接及特殊条目类型；
- 目录条目包含非零数据会被视为不支持的功能；
- 压缩方法必须受到当前 `ZipArchive` 支持，否则返回 `unsupported_compression`；
- 配置 `maxCompressionRatio` 时根据 ZIP 条目元数据计算压缩比；
- 已接受的普通文件条目会流式读取到 EOF，并用实际解压大小校验元数据。

### 加密 ZIP

`inspect()` 与 `extract()` 使用相同的可选 `password` 参数：

- 加密条目未提供密码时返回 `encrypted_entry`；
- 当前运行环境不支持加密方法时返回 `unsupported_encryption`；
- 无法解密或读取加密内容时返回 `decryption_failed`；
- 密码正确且其他检查通过时，允许加密 ZIP，包括普通条目与加密条目混合的归档。

传入密码不影响普通未加密 ZIP 的检查。空字符串 `''` 视为已提供密码，不等同于 `null`。实际加密方法支持程度取决于当前环境的 `ZipArchive`/`libzip`。

## TAR

支持普通文件和目录、USTAR 头部，以及用于正确确定名称、大小和其他允许元数据的有限 GNU/PAX 扩展集合。

需要注意：

- 符号链接和硬链接会被拒绝；
- 特殊和稀疏元素会被拒绝；
- 带有非零数据的目录会被拒绝；
- 未知 PAX 字段和不受支持的扩展会被拒绝；
- 冲突或重复的本地扩展会被拒绝；
- 扩展元数据元素也计入元素数量和数据大小限制。

损坏的头部或无效的 TAR 结构会导致 `ArchiveOpenException`，而不是普通策略违规。

未压缩 TAR 不使用 `maxCompressionRatio`。

TAR 本身没有内置的密码加密，`password` 必须为 `null`；其他值会抛出 `InvalidArgumentException`。

## TAR.GZ

GZIP 解压后得到的数据按照与普通 TAR 相同的规则检查。

如果配置了 `maxCompressionRatio`，会统计 GZIP 解压器实际产生的数据量。
这样可以在向磁盘写文件前限制压缩数据的过度膨胀。

完整 GZIP 流结束后的额外数据以及多个拼接的 GZIP 流都不受支持，
并被视为归档文件结构错误。

与 TAR 一样，TAR.GZ 不提供内置密码加密。非 `null` 的 `password` 会抛出 `InvalidArgumentException`。

## 平台路径兼容性

所有支持的格式均应用通用安全路径规则。`inspect()` 还执行当前平台确定性的名称限制。

Windows 下包括：

- 路径组件有效 UTF-8；
- 禁止的 Win32 字符与控制字符；
- 末尾的点或空格；
- 保留设备名；
- 忽略 ASCII 大小写的路径冲突。

不兼容的名称返回 `platform_incompatible_path`；平台路径冲突可能返回 `path_collision`。这些确定性规则在 `inspect()` 检查；依赖具体目标文件系统的检查在 `extract()` 执行。

## 格式和功能错误

支持的格式若使用当前实现不支持的功能，检查会返回对应违规代码，例如 `unsupported_encryption`、`unsupported_compression` 或 `unsupported_feature`。

归档结构损坏或已接受的未加密 ZIP 内容无法可靠读取时抛出 `ArchiveOpenException`。所有代码见[违规参考](policy-and-errors.md)。

[← 返回 README](../README.md)
