# Управляемое извлечение

`ArchiveGuard::extract()` проверяет архив по политике, затем записывает допустимые файлы в подготовленный пустой каталог и возвращает `ExtractionResult`.

## Перед извлечением

- Источник должен быть читаемым обычным локальным файлом, а его содержимое — поддерживаемым архивом.
- Каталог назначения должен уже существовать, разрешаться в каталог, быть пустым и доступным для чтения. Его конечный путь не должен быть символической ссылкой. Для успешного извлечения процессу также нужны права на создание каталогов и файлов внутри назначения.
- Передайте `ArchivePolicy` с лимитами, подходящими для приложения; готовой политики по умолчанию нет.

## Базовый сценарий

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

$policy = new ArchivePolicy(50_000_000, 1_000, 10_000_000, 100_000_000);
$guard = new ArchiveGuard();

try {
    $result = $guard->extract($archivePath, $destinationPath, $policy);
    echo $result->format()->value . ': ' . $result->filesExtracted() . ' файлов, '
        . $result->directoriesCreated() . ' каталогов, '
        . $result->bytesWritten() . ' байт' . PHP_EOL;
} catch (ArchiveRejectedException $e) {
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException | ExtractionException $e) {
    echo $e->getMessage() . PHP_EOL;
}
```

Лимиты в примере условные. `$destinationPath` обозначает заранее созданный пустой каталог.

## Повторная проверка архива

`extract()` самостоятельно проверяет источник по переданной политике. Предыдущий `InspectionResult` не служит разрешением на извлечение. Отклонение архива при проверке происходит до записи в каталог назначения. После успешной проверки необходимые подкаталоги создаются по мере извлечения, а файлы открываются без перезаписи существующих целей. Фактически записанные байты сверяются с принятой метаинформацией и лимитами политики.

## Результат

`format()` сообщает формат; `filesExtracted()` — число созданных файлов; `directoriesCreated()` — число созданных каталогов; `bytesWritten()` — число записанных байтов.

## Ошибки

- `ArchiveRejectedException` означает отклонение архива по политике или проверкам записей; `inspectionResult()` содержит нарушения.
- `ArchiveOpenException` означает недоступный, нераспознанный или структурно повреждённый источник до начала извлечения.
- `ExtractionException` означает неподходящий каталог назначения, несовместимое целевое имя или сбой во время извлечения. Ошибка источника во время повторного прохода TAR тоже может быть обёрнута в это исключение.

## Частичный сбой

Извлечение не атомарно. При сбое во время записи уже созданные каталоги, готовые файлы и частично записанный текущий файл могут остаться; автоматического отката нет. Приложение должно учитывать это при обработке ошибки.

## Windows

Перед записью дополнительно проверяются имена целей: недопустимые символы, зарезервированные имена устройств, завершающая точка или пробел и коллизии путей, различающихся только ASCII-регистром. Поэтому архив может пройти `inspect()`, но получить `ExtractionException` при `extract()` в Windows.

## Метаданные

Права доступа, владелец и временные метки из архива не восстанавливаются.

## См. также

[Модель безопасности](../security-model.md) · [Политика и ошибки](../reference/policy-and-errors.md) · [Поддерживаемые архивы](../reference/supported-archives.md) · [README](../../README.md)
