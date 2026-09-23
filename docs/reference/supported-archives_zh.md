# 支持的归档格式

本参考说明本包能够处理哪些 ZIP、TAR 和 TAR.GZ 变体，以及哪些格式功能会被主动拒绝。

## 格式识别

格式根据文件内容而不是文件扩展名识别：

- ZIP — 根据 ZIP 签名；
- TAR.GZ — 根据 GZIP 签名；
- TAR — 根据有效的 TAR 头部或空 TAR 块。

源必须是可读取的普通本地文件。包含 `://` 的路径会被拒绝。

如果内容无法识别或归档文件结构已损坏，会抛出
[`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php)。

## ZIP

普通 ZIP 文件和目录在通过通用路径检查与资源限制后可以被接受。

此外：

- 符号链接和特殊元素类型会被拒绝；
- 带有非零数据的目录会作为不受支持的功能被拒绝；
- 加密元素会产生 `encrypted_entry` 违规；
- 本包**不会请求密码**，也不会提取加密内容；
- 压缩方法必须由当前 `ZipArchive` 支持，否则返回 `unsupported_compression`；
- 配置 `maxCompressionRatio` 后，会根据 ZIP 元数据评估压缩比。

压缩比检查只是额外的启发式保护。提取时仍会再次检查实际写入的数据量。

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

## TAR.GZ

GZIP 解压后得到的数据按照与普通 TAR 相同的规则检查。

如果配置了 `maxCompressionRatio`，会统计 GZIP 解压器实际产生的数据量。
这样可以在向磁盘写文件前限制压缩数据的过度膨胀。

完整 GZIP 流结束后的额外数据以及多个拼接的 GZIP 流都不受支持，
并被视为归档文件结构错误。

## 当格式功能不受支持时

本包更倾向于拒绝未知或不受支持的归档文件变体，而不是尝试部分提取。

根据具体情况，会得到：

- 结构化违规，例如 `encrypted_entry`、`symlink_entry` 或 `unsupported_feature`；
- 如果归档文件结构已损坏且无法可靠解析，则抛出 `ArchiveOpenException`。

所有违规代码请参阅[违规参考](policy-and-errors_zh.md)。

[← 返回 README](../readme/README_zh.md)
