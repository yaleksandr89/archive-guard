# Archive Guard

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Farchive--guard-blue.svg?style=flat-square)](https://github.com/yaleksandr89/archive-guard)
[![CI](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml/badge.svg)](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/yaleksandr89/archive-guard.svg?style=flat-square)](https://packagist.org/packages/yaleksandr89/archive-guard)
[![Total Downloads](https://img.shields.io/packagist/dt/yaleksandr89/archive-guard.svg?style=flat-square)](https://packagist.org/packages/yaleksandr89/archive-guard)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

![Archive Guard — проверка и извлечение ZIP, TAR и TAR.GZ для PHP](docs/assets/archive-guard-readme-cover.png)

## Выберите язык

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| **Выбран** | [English](./docs/readme/README_en.md) | [Español](./docs/readme/README_es.md) | [中文](./docs/readme/README_zh.md) | [Français](./docs/readme/README_fr.md) | [Deutsch](./docs/readme/README_de.md) |

Archive Guard — PHP-библиотека для проверки ZIP, TAR и TAR.GZ перед распаковкой
и извлечения файлов с заранее заданными ограничениями.

## Для чего нужен пакет

Если приложение принимает архив от пользователя или внешнего сервиса, перед распаковкой
полезно убедиться, что архив не содержит опасных путей, ссылок и неподдерживаемых элементов,
а его размер и объём распакованных данных укладываются в допустимые для приложения пределы.

Пакет позволяет отдельно проверить архив перед распаковкой или сразу проверить и извлечь
его содержимое. Если архив не проходит проверку, приложение получает конкретную причину.

## Что делает пакет

- определяет формат по содержимому файла, а не по расширению имени;
- проверяет пути внутри архива, коллизии после нормализации и совместимость имён с текущей платформой;
- отклоняет символические и жёсткие ссылки, специальные и неподдерживаемые элементы архива;
- ограничивает максимальный размер архива, количество элементов, размер отдельного файла
  и общий объём данных после распаковки;
- для ZIP проверяет не только метаданные, но и фактическую читаемость содержимого файлов;
- поддерживает проверку и извлечение зашифрованных ZIP с переданным паролем, если метод шифрования поддерживается текущей средой `ZipArchive`/`libzip`;
- извлекает либо атомарной публикацией в новый каталог, либо слиянием в существующий каталог
  со стратегией конфликта `Reject`, `Skip` или `Overwrite`;
- возвращает типизированные результаты и структурированные причины отклонения.

Подробные различия между ZIP, TAR и TAR.GZ описаны в
[справочнике поддерживаемых архивов](docs/reference/supported-archives.md).

## Требования

- PHP `^8.4`;
- `ext-zip`;
- `ext-zlib`.

## Быстрый старт

### Проверка архива

[`ArchiveGuard::inspect()`](src/ArchiveGuard.php) проверяет архив без извлечения файлов.
Ограничения задаются через [`ArchivePolicy`](src/ArchivePolicy.php).

<details>
<summary>Показать пример проверки</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';

// Подберите ограничения под реальные архивы и ресурсы приложения.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$inspection = new ArchiveGuard()->inspect($archivePath, $policy);

if ($inspection->isAccepted()) {
    echo 'Архив прошёл проверку.' . PHP_EOL;
} else {
    foreach ($inspection->violations() as $violation) {
        // code показывает причину отклонения, message — её текстовое описание.
        echo $violation->code->value . ': ' . $violation->message . PHP_EOL;
    }
}
```

</details>

Для зашифрованного ZIP пароль передаётся тем же вызовом:

```php
$inspection = new ArchiveGuard()->inspect(
    $archivePath,
    $policy,
    password: $password,
);
```

В поддерживаемых форматах параметр `password` используется только для ZIP. TAR и TAR.GZ сами по себе не предусматривают встроенного парольного шифрования; если передать для них `password`, метод выбросит `InvalidArgumentException`.

Значения ограничений в примере выбраны только для демонстрации. Подбирать их нужно под размер
архивов, которые действительно ожидает ваше приложение. Подробный сценарий разобран в
[руководстве по `inspect()`](docs/guides/inspection.md).

### Извлечение файлов

[`ArchiveGuard::extract()`](src/ArchiveGuard.php) не использует более ранний результат
`inspect()` как разрешение на применение. Текущий архив повторно проверяется в ходе извлечения,
а режим задаётся явно через [`ExtractionOptions`](src/ExtractionOptions.php).

<details>
<summary>Показать пример атомарного извлечения</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\ExtractionOptions;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/result';

// Финальный каталог назначения для Atomic заранее существовать не должен.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$result = new ArchiveGuard()->extract(
    $archivePath,
    $destinationPath,
    $policy,
    ExtractionOptions::atomic(),
);

echo 'Файлов применено: ' . $result->filesExtracted() . PHP_EOL;
echo 'Каталогов создано: ' . $result->directoriesCreated() . PHP_EOL;
echo 'Записано байт: ' . $result->bytesWritten() . PHP_EOL;
```

</details>

Для слияния в уже существующий каталог используйте `ExtractionOptions::merge(...)`
со стратегией `Reject`, `Skip` или `Overwrite`. Результат более раннего вызова `inspect()`
не используется как разрешение на запись: `extract()` заново использует текущие
`archivePath`, `ArchivePolicy` и `password` для проверки архива и отдельно выполняет проверки,
зависящие от каталога назначения и выбранного режима.

Подробные контракты Atomic, Merge, блокировки и конфликтов описаны в
[руководстве по извлечению](docs/guides/extraction.md).

## Политика проверки

[`ArchivePolicy`](src/ArchivePolicy.php) задаёт верхние пределы, после которых архив
отклоняется:

- максимальный размер файла архива;
- максимальное количество файлов и папок внутри архива; для TAR некоторые служебные элементы формата тоже входят в этот предел;
- максимальный размер одного файла после распаковки;
- максимальный суммарный размер всех распакованных данных;
- необязательный предел отношения распакованного размера к сжатому.

Готовых универсальных значений здесь нет: допустимый архив для загрузки аватара и допустимый
архив с резервной копией будут иметь разные пределы. Все параметры и правила их применения
описаны в [справочнике политики](docs/reference/policy-and-errors.md).

## Нарушения и ошибки

Если архив распознан, но не проходит заданные проверки, [`inspect()`](src/ArchiveGuard.php)
возвращает [`InspectionResult`](src/InspectionResult.php) со списком нарушений.

Исключения используются для другой ситуации:

- [`ArchiveOpenException`](src/Exception/ArchiveOpenException.php) — файл нельзя открыть,
  формат не распознан, структура повреждена или принятое незашифрованное ZIP-содержимое нельзя
  корректно прочитать;
- [`ArchiveRejectedException`](src/Exception/ArchiveRejectedException.php) — проверка архива
  отклонила его; публикация Atomic или применение Merge ещё не начались;
- [`ExtractionException`](src/Exception/ExtractionException.php) — проблема связана с
  каталогом назначения, блокировкой, подготовкой или публикацией результата либо записью в файловую систему.

<details>
<summary>Показать пример обработки ошибок</summary>

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

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$guard = new ArchiveGuard();

try {
    $result = $guard->extract(
        $archivePath,
        $destinationPath,
        $policy,
        ExtractionOptions::atomic(),
    );
} catch (ArchiveRejectedException $e) {
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException $e) {
    echo $e->getMessage() . PHP_EOL;
} catch (ExtractionException $e) {
    echo $e->getMessage() . PHP_EOL;
}
```

</details>

Полный список кодов нарушений и исключений находится в
[справочнике ошибок](docs/reference/policy-and-errors.md).

## Безопасность

Archive Guard проверяет пути и типы элементов, лимиты `ArchivePolicy`, совместимость имён с
текущей платформой и фактическую читаемость ZIP. В режиме Atomic библиотека сама создаёт
соседний временный каталог и публикует готовый результат в новый каталог назначения только
после успешного извлечения и проверок.

Merge предназначен для существующего каталога: архив сначала полностью подготавливается во
временном каталоге, затем пакет проверяет конфликты и применяет выбранную стратегию
`Reject`, `Skip` или `Overwrite`. Оба режима используют кооперативную блокировку Archive Guard.

Подробная модель проверки и применения результата описана в
[модели безопасности](docs/security-model.md).

## Обратная связь

- воспроизводимые ошибки — [GitHub Issues](https://github.com/yaleksandr89/archive-guard/issues);
- вопросы по использованию и идеи — [GitHub Discussions](https://github.com/yaleksandr89/archive-guard/discussions).

---

<p align="center">
  Если пакет оказался полезен, поставьте звезду на GitHub — так его будет проще найти другим разработчикам. 🤘
</p>
