# Política, infracciones y errores

Esta referencia describe límites de comprobación, modos de extracción, resultados, códigos de infracción y excepciones.

## ArchivePolicy

[`ArchivePolicy`](../../../../src/ArchivePolicy.php) se pasa en cada llamada a `inspect()` o
`extract()` y define **valores máximos permitidos**, no valores exactos esperados.

```php
<?php

use Yaleksandr\ArchiveGuard\ArchivePolicy;

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
    maxCompressionRatio: 100.0,
);
```

| Parámetro | Qué limita | Valor permitido |
| --- | --- | --- |
| `maxArchiveBytes` | Tamaño máximo del propio archivo | Entero mayor que cero |
| `maxEntries` | Cantidad máxima de archivos y carpetas en el archivo; en TAR, algunos elementos de metadatos del formato entran en el mismo límite | Entero mayor que cero |
| `maxEntryUncompressedBytes` | Tamaño máximo de un archivo u otro elemento después de descomprimir | Entero mayor que cero |
| `maxTotalUncompressedBytes` | Volumen máximo total de datos descomprimidos | Entero mayor que cero |
| `maxCompressionRatio` | Límite adicional de la relación entre tamaño descomprimido y comprimido | `null` o número finito mayor que cero |

Los cuatro primeros parámetros no tienen valores predeterminados: la aplicación debe elegirlos
explícitamente. `maxCompressionRatio` es opcional y su valor predeterminado es `null`.

La comprobación de la relación de compresión funciona de forma distinta según el formato:

- ZIP usa los tamaños de los metadatos del elemento;
- TAR.GZ usa la cantidad real de datos producida por el descompresor GZIP;
- TAR sin comprimir no usa este límite.

Los valores no válidos del constructor lanzan el `InvalidArgumentException` estándar.

## InspectionResult

[`InspectionResult`](../../../../src/InspectionResult.php) solo se devuelve cuando el origen se pudo
abrir e interpretar como un archivo compatible.

Métodos disponibles:

- `format()` — devuelve [`ArchiveFormat`](../../../../src/ArchiveFormat.php): `zip`, `tar` o
  `tar.gz`;
- `isAccepted()` — devuelve `true` cuando no hay infracciones;
- `violations()` — devuelve una lista de objetos [`Violation`](../../../../src/Violation.php).

Si el archivo no se puede abrir, reconocer o interpretar correctamente, se lanza
`ArchiveOpenException` en lugar de devolver un resultado.

## ExtractionOptions

[`ExtractionOptions`](../../../../src/ExtractionOptions.php) es obligatorio en `extract()` y se crea con dos fábricas con nombre:

```php
ExtractionOptions::atomic();

ExtractionOptions::merge(
    ExtractionConflictStrategy::Reject,
);
```

`atomic()` publica en un directorio nuevo que no debe existir antes. `merge(...)` aplica el resultado a un directorio existente y exige una [`ExtractionConflictStrategy`](../../../../src/ExtractionConflictStrategy.php):

- `Reject`: archivo normal existente = conflicto detectado antes de aplicar Merge;
- `Skip`: conservar el archivo normal existente;
- `Overwrite`: sustituirlo por un archivo normal procedente del archivo comprimido.

Se reutiliza el directorio existente si la entrada correspondiente también es un directorio. Los conflictos de tipos archivo/directorio, enlaces y objetos especiales se rechazan con cualquier estrategia.

## ExtractionResult

[`ExtractionResult`](../../../../src/ExtractionResult.php) describe los cambios realmente aplicados:

- `format()`: formato;
- `filesExtracted()`: archivos nuevos y sobrescritos aplicados;
- `directoriesCreated()`: directorios creados;
- `bytesWritten()`: bytes de archivos nuevos y sobrescritos aplicados;
- `filesSkipped()`: archivos realmente omitidos;
- `filesOverwritten()`: archivos existentes sustituidos.

En Atomic, `filesSkipped()` y `filesOverwritten()` siempre valen cero.

## Violation

[`Violation`](../../../../src/Violation.php) contiene tres propiedades públicas:

- `code` — valor de [`ViolationCode`](../../../../src/ViolationCode.php) pensado para el tratamiento
  programático;
- `message` — descripción breve de diagnóstico;
- `entryName` — nombre del archivo, directorio u otro elemento, o `null` si la infracción se
  aplica al archivo completo.

Para condiciones en el código, usa `code` en lugar de comparar el texto de `message`.

## ViolationCode

| Valor | Significado |
| --- | --- |
| `unsafe_path` | La ruta puede escapar de la estructura permitida o tiene una forma inválida |
| `path_collision` | Dos rutas entran en conflicto tras normalización o comparación de plataforma |
| `platform_incompatible_path` | Un componente incumple las reglas deterministas de nombres de la plataforma |
| `symlink_entry` | Se encontró un enlace simbólico |
| `hardlink_entry` | Se encontró un enlace duro TAR |
| `special_entry` | Se encontró un objeto especial, como un dispositivo |
| `archive_too_large` | El archivo de origen supera `maxArchiveBytes` |
| `too_many_entries` | Archivos, directorios y metadatos contabilizados superan `maxEntries` |
| `entry_too_large` | Una entrada descomprimida supera `maxEntryUncompressedBytes` |
| `total_size_exceeded` | El volumen total descomprimido supera `maxTotalUncompressedBytes` |
| `compression_ratio_exceeded` | Se superó el límite de relación descomprimido/comprimido |
| `encrypted_entry` | Hay una entrada ZIP cifrada pero no se proporcionó contraseña |
| `unsupported_encryption` | El entorno no admite el método de cifrado pese a que se proporcionó contraseña |
| `decryption_failed` | No se pudo descifrar o leer el contenido cifrado |
| `unsupported_compression` | El entorno no admite el método de compresión ZIP |
| `unsupported_feature` | El formato contiene una función no compatible |

`decryption_failed` es un error de descifrado o lectura; `ZipArchive`/`libzip` no siempre puede determinar la causa. No todas las infracciones tienen `entryName`: el tamaño total del archivo no corresponde a una entrada individual.

## Excepciones

Todas las excepciones del paquete heredan de [`ArchiveGuardException`](../../../../src/Exception/ArchiveGuardException.php), que hereda de `RuntimeException`.

- [`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php): no se puede abrir o reconocer el origen, la estructura está dañada o no se puede leer una entrada ZIP sin cifrar ya aceptada. Un fallo de descifrado/lectura de contenido cifrado con contraseña produce `decryption_failed` (y `ArchiveRejectedException` en `extract()`).
- [`ArchiveRejectedException`](../../../../src/Exception/ArchiveRejectedException.php): el archivo no pasó la comprobación antes de publicar Atomic o aplicar Merge. `inspectionResult()` contiene los motivos. Pueden existir archivos temporales creados antes de que se detecte el rechazo completo.
- [`ExtractionException`](../../../../src/Exception/ExtractionException.php): fallo de destino, bloqueo cooperativo, área temporal, comprobación previa, aplicación Merge, publicación Atomic o E/S del sistema de archivos.

`InvalidArgumentException` no hereda de `ArchiveGuardException`; se usa con valores inválidos de `ArchivePolicy` y con contraseña no nula para TAR/TAR.GZ, que no admiten cifrado integrado por contraseña.

## Cómo distinguir un resultado de una excepción

### `inspect()`

- Si se acepta el archivo, devuelve `InspectionResult` con `isAccepted() === true`.
- Si se detectan infracciones, devuelve `InspectionResult` con `isAccepted() === false`.
- Un origen no legible, mal estructurado o un contenido ZIP sin cifrar aceptado pero ilegible provoca `ArchiveOpenException`.
- Se admite `password` para ZIP; para TAR/TAR.GZ una contraseña no nula provoca `InvalidArgumentException`.

### `extract()`

Argumentos:

```text
archivePath
destinationPath
ArchivePolicy
ExtractionOptions
password = null
```

`archivePath`, `ArchivePolicy` y `password` siguen la misma semántica de aceptación que `inspect()`.

- Si se rechaza el archivo, lanza `ArchiveRejectedException` antes de publicar Atomic o aplicar Merge. La comprobación puede terminar después de crear el área temporal; `inspectionResult()` aporta los motivos.
- Los errores de apertura/estructura o lectura ZIP sin cifrar aceptada producen `ArchiveOpenException`.
- Los errores de destino, conflicto previo o sistema de archivos producen `ExtractionException`.
- Al completar la operación, devuelve `ExtractionResult`.

El exceso de `maxArchiveBytes` se rechaza antes de validar si TAR/TAR.GZ permite contraseña.

Ejemplos: [`inspect()`](../guides/inspection.md) y [`extract()`](../guides/extraction.md).

[← Volver al README](../README.md)
