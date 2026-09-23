# Extracción controlada

Esta guía describe la extracción con las comprobaciones integradas: qué hay que preparar,
qué errores pueden ocurrir y qué puede quedar en el disco si la escritura se interrumpe.

[`ArchiveGuard::extract()`](../../src/ArchiveGuard.php) comprueba primero el archivo con la
política indicada y solo empieza a crear archivos después de superar la comprobación.

## Antes de extraer

Prepara:

- un archivo local ZIP, TAR o TAR.GZ que pueda leerse;
- una [`ArchivePolicy`](../../src/ArchivePolicy.php) con límites adecuados para la aplicación;
- un directorio de destino vacío e independiente.

El directorio de destino debe **existir de antemano y estar vacío**. La versión actual no
descomprime sobre archivos ya existentes.

Si la aplicación usa una carpeta común para todas las extracciones, crea dentro de ella un
subdirectorio nuevo para cada operación. Así no se mezcla el contenido de archivos distintos
y la biblioteca puede comprobar todo el destino antes de comenzar a escribir.

El proceso PHP necesita permisos para crear archivos y subdirectorios dentro de ese directorio.

## Escenario básico

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;
use Yaleksandr\ArchiveGuard\Exception\ArchiveRejectedException;
use Yaleksandr\ArchiveGuard\Exception\ExtractionException;

$archivePath = '/path/to/archive.zip';
$destinationPath = '/path/to/empty-directory';

// Elige límites acordes con los archivos reales y los recursos de tu aplicación.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->extract($archivePath, $destinationPath, $policy);

    echo 'Archivos: ' . $result->filesExtracted() . PHP_EOL;
    echo 'Directorios: ' . $result->directoriesCreated() . PHP_EOL;
    echo 'Bytes escritos: ' . $result->bytesWritten() . PHP_EOL;
} catch (ArchiveRejectedException $e) {
    // El archivo se leyó, pero no superó las comprobaciones configuradas.
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException $e) {
    // El archivo de origen no se puede abrir o interpretar correctamente.
    echo $e->getMessage() . PHP_EOL;
} catch (ExtractionException $e) {
    // Error de destino o fallo durante la extracción.
    echo $e->getMessage() . PHP_EOL;
}
```

## Qué ocurre antes de escribir

`extract()` siempre comprueba el estado actual del archivo inmediatamente antes de escribir.
Esta comprobación se realiza aunque `inspect()` se haya llamado antes.

La secuencia es la siguiente:

1. La biblioteca abre y comprueba el archivo.
2. Si se encuentran infracciones, se lanza
   [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) antes de
   iniciar la escritura.
3. Se comprueba el directorio de destino.
4. Cada archivo se crea solo si todavía no existe nada en su ruta; un objeto existente no se
   sustituye.
5. Durante la escritura, el tamaño real de cada archivo y el volumen total descomprimido se
   comparan con los límites configurados y con los tamaños obtenidos durante la comprobación.
   Si el archivo empieza a producir más datos de los permitidos, la extracción se detiene.
6. TAR y TAR.GZ se leen dos veces: primero para comprobarlos y después para extraerlos. En la
   segunda pasada, el orden, la ruta, el tipo y el tamaño de cada elemento se comparan con la
   primera comprobación. Si el archivo cambia entre ambas pasadas, la extracción se detiene.

## Resultado

[`ExtractionResult`](../../src/ExtractionResult.php) ofrece:

- `format()` — formato del archivo;
- `filesExtracted()` — cantidad de archivos creados;
- `directoriesCreated()` — cantidad de directorios creados;
- `bytesWritten()` — cantidad de bytes escritos en archivos.

## Errores

Durante `extract()` pueden aparecer tres grupos principales de errores:

- [`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) — el archivo de
  origen no se puede abrir o interpretar correctamente;
- [`ArchiveRejectedException`](../../src/Exception/ArchiveRejectedException.php) — el archivo
  es estructuralmente válido, pero no superó los límites o comprobaciones;
- [`ExtractionException`](../../src/Exception/ExtractionException.php) — problema con el
  directorio de destino, el nombre objetivo o la propia escritura en disco.

Los códigos disponibles a través de `ArchiveRejectedException` se enumeran en la
[referencia de errores](../reference/policy-and-errors_es.md).

## Fallo parcial y atomicidad

Actualmente los datos se escriben directamente en el directorio de destino preparado. Esto
permite comprobar el volumen realmente escrito y evitar sobrescribir archivos existentes,
pero significa que la operación **no es atómica**.

Si se agota el espacio en disco después de crear varios archivos, el proceso pierde permisos
de escritura o se produce otro error de entrada/salida, los archivos y directorios ya creados
permanecerán. El archivo actual también puede quedar escrito parcialmente.

Un enfoque completamente atómico es técnicamente posible, pero supondría otro contrato: habría
que descomprimir primero todo el contenido en un directorio temporal y después realizar un
traslado final del resultado completo. Ese traslado se comporta de forma distinta según el
sistema de archivos y en Windows, por lo que la versión actual no promete atomicidad donde no
puede garantizarla.

Si la aplicación crea un directorio específico para una operación y tiene control total sobre
él, puede eliminarlo después de `ExtractionException` según sus propias reglas.

## Windows

Windows impone restricciones adicionales sobre los nombres que no existen en los formatos ZIP
o TAR generales. Antes de la primera escritura, la biblioteca también rechaza, por ejemplo:

- nombres de dispositivo como `CON`, `NUL`, `PRN` y similares;
- caracteres prohibidos por Windows;
- nombres que terminan en punto o espacio;
- rutas que solo difieren en mayúsculas/minúsculas ASCII y que Windows considera la misma ruta.

Por tanto, un mismo archivo puede superar `inspect()` a nivel de formato y ser rechazado por
`extract()` al preparar la escritura en Windows.

## Metadatos de archivos

Un archivo comprimido puede guardar no solo el contenido, sino también permisos, propietario
y fecha de modificación. La versión actual extrae el contenido y la estructura de directorios,
pero no aplica esos metadatos del archivo.

Los límites y recomendaciones adicionales se describen en el
[modelo de seguridad](../security-model_es.md).

[← Volver al README](../readme/README_es.md)
