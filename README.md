# Archive Guard

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Farchive--guard-blue.svg?style=flat-square)](https://github.com/yaleksandr89/archive-guard)
[![CI](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml/badge.svg)](https://github.com/yaleksandr89/archive-guard/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)

![OAuth2 Yandex — Yandex ID provider client for league/oauth2-client](docs/assets/archive-guard-readme-cover.png)

Archive Guard — PHP-библиотека для проверки ZIP, TAR и TAR.GZ перед распаковкой
и извлечения файлов с заранее заданными ограничениями.

## Для чего нужен пакет

Если приложение принимает архив от пользователя или внешнего сервиса, перед распаковкой
полезно убедиться, что архив не содержит опасных путей, ссылок и неподдерживаемых записей,
а его размер и объём распакованных данных укладываются в допустимые для приложения пределы.

Пакет позволяет отдельно проверить архив перед распаковкой или сразу проверить и извлечь
его содержимое. Если архив не проходит проверку, приложение получает конкретную причину.

## Что делает пакет

- определяет формат по содержимому файла, а не по расширению имени;
- проверяет пути внутри архива и не допускает выход за каталог назначения;
- отклоняет символические и жёсткие ссылки, специальные и неподдерживаемые записи;
- ограничивает максимальный размер архива, количество файлов и папок внутри него и общий объём данных после распаковки;
- возвращает структурированный список нарушений, который можно обработать в приложении;
- позволяет отдельно проверить архив через `inspect()` или проверить и извлечь его через `extract()`;
- в Windows дополнительно проверяет имена, которые файловая система не сможет создать безопасно.

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

Значения в примере выбраны только для демонстрации. Подбирать их нужно под размер архивов,
которые действительно ожидает ваше приложение. Подробный сценарий проверки разобран в
[руководстве по `inspect()`](docs/guides/inspection.md).

### Извлечение файлов

[`ArchiveGuard::extract()`](src/ArchiveGuard.php) выполняет обязательную проверку
непосредственно перед записью файлов. Каталог назначения приложение создаёт заранее.

<details>
<summary>Показать пример извлечения</summary>

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

// Подберите ограничения под реальные архивы и ресурсы приложения.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$result = new ArchiveGuard()->extract($archivePath, $destinationPath, $policy);

echo 'Файлов: ' . $result->filesExtracted() . PHP_EOL;
echo 'Каталогов: ' . $result->directoriesCreated() . PHP_EOL;
echo 'Записано байт: ' . $result->bytesWritten() . PHP_EOL;
```

</details>

Результат более раннего вызова `inspect()` не используется как разрешение на запись:
`extract()` проверяет текущее состояние файла непосредственно перед извлечением.
Требования к каталогу назначения и поведение при ошибке описаны в
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
  формат не распознан или структура архива повреждена;
- [`ArchiveRejectedException`](src/Exception/ArchiveRejectedException.php) — архив не прошёл
  обязательную проверку перед извлечением, поэтому запись не началась;
- [`ExtractionException`](src/Exception/ExtractionException.php) — проблема связана с
  каталогом назначения или возникла уже во время извлечения.

<details>
<summary>Показать пример обработки ошибок</summary>

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
    // Архив прочитан, но не прошёл заданные проверки.
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException $e) {
    // Файл нельзя открыть или архив имеет некорректную структуру.
    echo $e->getMessage() . PHP_EOL;
} catch (ExtractionException $e) {
    // Архив прошёл проверку, но извлечение выполнить не удалось.
    echo $e->getMessage() . PHP_EOL;
}
```

</details>

Полный список кодов нарушений и исключений находится в
[справочнике ошибок](docs/reference/policy-and-errors.md).

## Безопасность и ограничения

При извлечении важно учитывать несколько условий:

- **Для каждой распаковки нужен отдельный пустой каталог.** Если приложение использует одну
  общую папку для всех архивов, создавайте внутри неё новый подкаталог для каждой операции.
  Текущая версия не распаковывает архив поверх уже существующих файлов.
- **Существующий файл или каталог не заменяется.** Если во время извлечения по нужному пути
  уже появился объект, операция завершается ошибкой вместо перезаписи.
- **Права доступа, владелец и время изменения из архива не переносятся.** Извлекаются
  содержимое файлов и структура каталогов; сохранённые в архиве метаданные не применяются.
- **В Windows есть дополнительные ограничения имён.** Например, Windows не позволяет
  создать `CON`, `NUL`, имя с завершающей точкой или некоторые имена со специальными
  символами. Поэтому архив может пройти общую проверку, но быть отклонён непосредственно
  перед извлечением на Windows.
- **Извлечение не атомарно.** Если во время записи закончится место на диске или произойдёт
  другая ошибка ввода-вывода, часть файлов может уже находиться в каталоге назначения.
  Автоматического отката сейчас нет.
- **Каталог не блокируется от других процессов.** Проверки не рассчитаны на ситуацию,
  когда другой процесс одновременно меняет содержимое каталога назначения. Для распаковки
  используйте отдельный каталог, доступный только вашему приложению на время операции.

Почему выбраны именно такие границы и что приложение должно контролировать самостоятельно,
подробнее описано в [модели безопасности](docs/security-model.md).

## Обратная связь

- воспроизводимые ошибки — [GitHub Issues](https://github.com/yaleksandr89/archive-guard/issues);
- вопросы по использованию и идеи — [GitHub Discussions](https://github.com/yaleksandr89/archive-guard/discussions).

---

<p align="center">
  Если пакет оказался полезен, поставьте звезду на GitHub — так его будет проще найти другим разработчикам. 🤘
</p>
