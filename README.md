# Archive Guard

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Farchive--guard-blue.svg?style=flat-square)](https://github.com/yaleksandr89/archive-guard)
[![CI](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml/badge.svg)](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)

Archive Guard — PHP-библиотека для проверки и контролируемого извлечения недоверенных архивов ZIP, TAR и TAR.GZ с лимитами ресурсов и отклонением опасных или неподдерживаемых записей.

## Для чего нужен пакет

Когда приложение получает архив из внешнего источника, пакет помогает проверить пути, типы записей и размеры данных до записи содержимого в каталог назначения.

## Что делает пакет

- Определяет формат по содержимому файла, а не по расширению имени.
- Нормализует пути и выявляет совпадения, конфликты файлов и каталогов.
- Отклоняет ссылки, специальные записи и неподдерживаемые возможности архивов.
- Применяет лимиты размера архива, числа записей и распакованных данных.
- Возвращает структурированные нарушения проверки.
- Повторно проверяет архив и извлекает файлы в пустой каталог без перезаписи целей.
- Перед записью в Windows дополнительно проверяет совместимость имён и коллизии путей, различающихся только ASCII-регистром.

## Требования

- PHP `^8.4`
- `ext-zip`
- `ext-zlib`

## Быстрый старт

### Проверка архива

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$guard = new ArchiveGuard();
$inspection = $guard->inspect($archivePath, $policy);

if (!$inspection->isAccepted()) {
    foreach ($inspection->violations() as $violation) {
        echo $violation->code->value;
        if ($violation->entryName !== null) {
            echo ': ' . $violation->entryName;
        }
        echo PHP_EOL;
    }
}
```

Лимиты в примере условные; приложение должно выбирать их под свой сценарий и ожидаемые архивы. Обработка ошибок открытия описана в [руководстве по проверке](docs/guides/inspection.md).

### Управляемое извлечение

Каталог назначения должен уже существовать и быть пустым. Используйте политику с лимитами, выбранными для вашего приложения:

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$result = (new ArchiveGuard())->extract($archivePath, $destinationPath, $policy);

echo $result->filesExtracted() . ' файлов, '
    . $result->directoriesCreated() . ' каталогов, '
    . $result->bytesWritten() . ' байт' . PHP_EOL;
```

`extract()` выполняет проверку архива заново по переданной политике. Результат предыдущего вызова `inspect()` не разрешает извлечение.

## Политика проверки

`ArchivePolicy` требует четыре положительных лимита: размер файла архива, число записей, размер одной распакованной записи и суммарный распакованный размер. Порог отношения сжатия можно задать дополнительно; это эвристика, а не универсальная гарантия. Параметры описаны в [справочнике политики](docs/reference/policy-and-errors.md).

## Нарушения и ошибки

При отклонении по политике `inspect()` возвращает `InspectionResult` с нарушениями. Недоступный, нераспознанный или структурно повреждённый источник вызывает `ArchiveOpenException`. При извлечении отклонение архива вызывает `ArchiveRejectedException`, а проблема каталога назначения или выполнения извлечения — `ExtractionException`. См. [справочник нарушений и ошибок](docs/reference/policy-and-errors.md).

## Безопасность и ограничения

- Каталог назначения должен существовать, быть пустым, а его конечный компонент не должен быть символической ссылкой; существующие цели не перезаписываются.
- Права, владелец и временные метки архива не восстанавливаются.
- После сбоя уже созданные каталоги, готовые файлы и частично записанный текущий файл могут остаться: отката нет.
- В Windows при извлечении действуют дополнительные проверки имён и коллизий путей, различающихся только ASCII-регистром; архив может пройти `inspect()`, но не пройти `extract()`.
- Защита от враждебного одновременного изменения файловой системы не гарантируется.
- Порог отношения сжатия — ограниченная эвристика, а не полная защита от архивов с большим расширением данных.

Подробности: [модель безопасности](docs/security-model.md).

## Документация

- [Проверка архива](docs/guides/inspection.md)
- [Управляемое извлечение](docs/guides/extraction.md)
- [Политика, нарушения и ошибки](docs/reference/policy-and-errors.md)
- [Поддерживаемые архивы](docs/reference/supported-archives.md)
- [Модель безопасности](docs/security-model.md)

## Обратная связь

О воспроизводимых ошибках сообщайте в [GitHub Issues](https://github.com/yaleksandr89/archive-guard/issues).

---

<p align="center">
  Если пакет оказался полезен, поставьте звезду на GitHub — так его будет проще найти другим разработчикам. 🤘
</p>
