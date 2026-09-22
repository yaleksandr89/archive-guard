# Политика, нарушения и ошибки

## ArchivePolicy

`ArchivePolicy` задаётся при каждом вызове `inspect()` или `extract()`:

```text
ArchivePolicy::__construct(
    int $maxArchiveBytes,
    int $maxEntries,
    int $maxEntryUncompressedBytes,
    int $maxTotalUncompressedBytes,
    ?float $maxCompressionRatio = null,
)
```

| Параметр | Тип | Назначение | Требование |
| --- | --- | --- | --- |
| `maxArchiveBytes` | `int` | Максимальный размер файла архива в байтах | Обязателен, больше нуля |
| `maxEntries` | `int` | Максимальное число записей | Обязателен, больше нуля |
| `maxEntryUncompressedBytes` | `int` | Максимальный распакованный размер одной записи в байтах | Обязателен, больше нуля |
| `maxTotalUncompressedBytes` | `int` | Максимальная сумма распакованных размеров в байтах | Обязателен, больше нуля |
| `maxCompressionRatio` | `?float` | Необязательный порог отношения сжатия | `null` либо конечное число больше нуля |

Для четырёх ресурсных лимитов значений по умолчанию нет. Недопустимые значения конструктора вызывают `InvalidArgumentException`. Порог отношения сжатия по умолчанию равен `null`, то есть соответствующая эвристика выключена. Указанный порог не является универсально безопасным значением; выбирайте его для своего сценария.

## InspectionResult

`format(): ArchiveFormat` возвращает `zip`, `tar` или `tar.gz`. `isAccepted(): bool` истинно, если нарушений нет. `violations(): array` возвращает список объектов `Violation`. При ошибке открытия или структуры архива результат не создаётся: вызывается `ArchiveOpenException`.

## Violation

Публичные свойства `Violation`: `code: ViolationCode`, `message: string`, `entryName: ?string`. `entryName` может быть `null` для нарушения, не связанного с конкретной записью. Для программной обработки используйте `code`; `message` служит диагностическим описанием.

## ViolationCode

| Значение | Смысл |
| --- | --- |
| `unsafe_path` | Небезопасный путь записи |
| `path_collision` | Совпадение нормализованных путей или конфликт файла и каталога |
| `symlink_entry` | Символическая ссылка |
| `hardlink_entry` | Жёсткая ссылка TAR |
| `special_entry` | Специальный объект, например устройство |
| `archive_too_large` | Файл архива превышает `maxArchiveBytes` |
| `too_many_entries` | Число записей превышает `maxEntries` |
| `entry_too_large` | Распакованный размер записи превышает `maxEntryUncompressedBytes` |
| `total_size_exceeded` | Сумма распакованных размеров превышает `maxTotalUncompressedBytes` |
| `compression_ratio_exceeded` | Превышен заданный порог отношения сжатия |
| `encrypted_entry` | Зашифрованная запись ZIP |
| `unsupported_compression` | Метод сжатия ZIP не поддерживается для распаковки |
| `unsupported_feature` | Неподдерживаемая возможность архива или записи |

Не каждый код привязан к имени записи. Например, `archive_too_large`, `too_many_entries` и некоторые нарушения возможностей формата могут иметь `entryName === null`.

## Исключения

| Класс | Наследование | Когда возникает |
| --- | --- | --- |
| `ArchiveGuardException` | `RuntimeException` | Общий базовый класс исключений пакета |
| `ArchiveOpenException` | `ArchiveGuardException` | Источник нельзя открыть, распознать или корректно разобрать |
| `ArchiveRejectedException` | `ArchiveGuardException` | `extract()` отклонил архив при проверке политики или записей до записи |
| `ExtractionException` | `ArchiveGuardException` | Ошибка каталога назначения, целевого пути или выполнения извлечения |

`ArchiveRejectedException::inspectionResult(): InspectionResult` возвращает результат с нарушениями. Во время выполнения извлечения, в том числе при повторном проходе TAR, может возникнуть `ExtractionException`. `InvalidArgumentException` при неверных параметрах `ArchivePolicy` относится к стандартным исключениям PHP и не наследует `ArchiveGuardException`.

## Как различать результат и исключение

При `inspect()` сначала обрабатывайте `ArchiveOpenException`; получив `InspectionResult`, проверяйте `isAccepted()` и `violations()`. При `extract()` отдельно обрабатывайте отклонение архива через `ArchiveRejectedException`, ошибки открытия через `ArchiveOpenException` и ошибки назначения или записи через `ExtractionException`. Не считайте все неудачи результатом с `isAccepted() === false`.

## См. также

[Проверка архива](../guides/inspection.md) · [Управляемое извлечение](../guides/extraction.md) · [Модель безопасности](../security-model.md) · [README](../../README.md)
