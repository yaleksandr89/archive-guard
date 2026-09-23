# Política, infracciones y errores

Esta referencia describe los parámetros de comprobación, el resultado de `inspect()`, los
códigos de infracción y las excepciones que puede recibir una aplicación al usar el paquete.

## ArchivePolicy

[`ArchivePolicy`](../../src/ArchivePolicy.php) se pasa en cada llamada a `inspect()` o
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
| `maxArchiveBytes` | Tamaño máximo del propio archivo comprimido | Entero mayor que cero |
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

[`InspectionResult`](../../src/InspectionResult.php) solo se devuelve cuando el origen se pudo
abrir e interpretar como un archivo compatible.

Métodos disponibles:

- `format()` — devuelve [`ArchiveFormat`](../../src/ArchiveFormat.php): `zip`, `tar` o
  `tar.gz`;
- `isAccepted()` — devuelve `true` cuando no hay infracciones;
- `violations()` — devuelve una lista de objetos [`Violation`](../../src/Violation.php).

Si el archivo no se puede abrir, reconocer o interpretar correctamente, se lanza
`ArchiveOpenException` en lugar de devolver un resultado.

## Violation

[`Violation`](../../src/Violation.php) contiene tres propiedades públicas:

- `code` — valor de [`ViolationCode`](../../src/ViolationCode.php) pensado para el tratamiento
  programático;
- `message` — descripción breve de diagnóstico;
- `entryName` — nombre del archivo, directorio u otro elemento, o `null` si la infracción se
  aplica al archivo completo.

Para condiciones en el código, usa `code` en lugar de comparar el texto de `message`.

## ViolationCode

| Valor | Significado |
| --- | --- |
| `unsafe_path` | La ruta del elemento puede salir de la estructura de directorios permitida o tiene una forma no válida |
| `path_collision` | Dos rutas normalizadas coinciden o entran en conflicto como archivo y directorio |
| `symlink_entry` | Se encontró un enlace simbólico en el archivo |
| `hardlink_entry` | Se encontró un enlace duro en TAR |
| `special_entry` | Se encontró un objeto especial, por ejemplo un dispositivo |
| `archive_too_large` | El tamaño del archivo comprimido supera `maxArchiveBytes` |
| `too_many_entries` | La cantidad de archivos, carpetas y elementos de metadatos contabilizados supera `maxEntries` |
| `entry_too_large` | Un archivo u otro elemento supera `maxEntryUncompressedBytes` después de descomprimir |
| `total_size_exceeded` | El volumen total descomprimido supera `maxTotalUncompressedBytes` |
| `compression_ratio_exceeded` | Se superó la relación configurada entre tamaño descomprimido y comprimido |
| `encrypted_entry` | ZIP contiene un elemento cifrado; la versión actual no solicita contraseña |
| `unsupported_compression` | El método de compresión ZIP no es compatible con el entorno para la extracción |
| `unsupported_feature` | El archivo usa una función del formato que el paquete no admite |

`entryName` no está disponible para todas las infracciones. Por ejemplo, superar el tamaño
total del archivo afecta al archivo completo, así que no existe el nombre de un elemento
individual.

## Excepciones

Todas las excepciones del paquete heredan de
[`ArchiveGuardException`](../../src/Exception/ArchiveGuardException.php), que a su vez hereda
de `RuntimeException`.

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — no se puede abrir
  el archivo de origen, no se reconoce el formato o la estructura está dañada.
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — el archivo
  no superó la comprobación obligatoria antes de extraer. `inspectionResult()` devuelve las
  causas.
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — problema con el
  directorio de destino, la ruta objetivo o la escritura.

`InvalidArgumentException` por valores no válidos de `ArchivePolicy` es una excepción estándar
de PHP y no hereda de `ArchiveGuardException`.

## Cómo distinguir un resultado de una excepción

### `inspect()`

- Si el archivo se puede leer y supera la comprobación, el método devuelve `InspectionResult`
  con `isAccepted() === true`.
- Si el archivo se puede leer pero se encuentran infracciones, el método también devuelve
  `InspectionResult`, pero `isAccepted()` será `false` y las causas estarán en `violations()`.
- Si el propio archivo no se puede abrir o interpretar correctamente, se lanza
  `ArchiveOpenException`.

### `extract()`

- Si el archivo no supera la comprobación obligatoria antes de extraer, se lanza
  `ArchiveRejectedException`. Las infracciones se obtienen mediante `inspectionResult()`.
- Si el archivo de origen no se puede abrir o reconocer antes de comenzar la extracción, se
  lanza `ArchiveOpenException`.
- Si el problema aparece al preparar el destino o durante la escritura, se lanza
  `ExtractionException`.
- Si todo termina correctamente, se devuelve
  [`ExtractionResult`](../../src/ExtractionResult.php).

Hay ejemplos prácticos en la
[guía de `inspect()`](../guides/inspection_es.md) y en la
[guía de `extract()`](../guides/extraction_es.md).

[← Volver al README](../readme/README_es.md)
