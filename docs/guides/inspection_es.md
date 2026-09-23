# Comprobación de archivos

Esta guía explica cómo comprobar un archivo antes de descomprimirlo, cómo leer el resultado y
en qué se diferencia una infracción normal de los límites de un problema del propio archivo.

[`ArchiveGuard::inspect()`](../../src/ArchiveGuard.php) no extrae nada al disco. Solo analiza
el archivo y devuelve un [`InspectionResult`](../../src/InspectionResult.php).

## Escenario básico

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\Exception\ArchiveOpenException;

$archivePath = '/path/to/archive.zip';

// Elige límites acordes con los archivos reales y los recursos de tu aplicación.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->inspect($archivePath, $policy);

    if ($result->isAccepted()) {
        echo 'El archivo superó la comprobación.' . PHP_EOL;
    } else {
        foreach ($result->violations() as $violation) {
            // code identifica la causa del rechazo; message contiene su descripción textual.
            echo $violation->code->value . ': ' . $violation->message . PHP_EOL;
        }
    }
} catch (ArchiveOpenException $e) {
    echo 'No se pudo leer el archivo: ' . $e->getMessage() . PHP_EOL;
}
```

## Límites de comprobación

[`ArchivePolicy`](../../src/ArchivePolicy.php) define **límites máximos permitidos**, no valores
exactos esperados:

- `maxArchiveBytes` — tamaño máximo del propio archivo;
- `maxEntries` — cantidad máxima de archivos y carpetas en su interior; en TAR, algunos
  elementos de metadatos del formato también cuentan dentro de este límite;
- `maxEntryUncompressedBytes` — tamaño máximo de un archivo tras la descompresión;
- `maxTotalUncompressedBytes` — volumen máximo total de datos descomprimidos;
- `maxCompressionRatio` — límite adicional opcional para la relación entre tamaño
  descomprimido y comprimido.

Por ejemplo, si `maxArchiveBytes` es `50_000_000`, un archivo de 20 MB no tiene que tener un
tamaño exacto: simplemente no debe superar el límite configurado.

Las reglas detalladas de cada parámetro están en la
[referencia de `ArchivePolicy`](../reference/policy-and-errors_es.md).

## Resultado de la comprobación

[`InspectionResult`](../../src/InspectionResult.php) ofrece tres métodos principales:

- `format()` — formato detectado: `zip`, `tar` o `tar.gz`;
- `isAccepted()` — indica si el archivo superó todas las comprobaciones;
- `violations()` — causas por las que se rechazó el archivo.

Si `isAccepted()` devuelve `false`, el archivo puede seguir siendo estructuralmente válido.
Por ejemplo, puede contener un archivo demasiado grande o un enlace simbólico que la política
del paquete no permite extraer.

Cada [`Violation`](../../src/Violation.php) contiene:

- `code` — código estable de la causa, adecuado para la lógica de la aplicación;
- `message` — descripción de diagnóstico;
- `entryName` — nombre del archivo, directorio u otro elemento si la infracción está asociada
  a uno concreto.

La lista completa de códigos está en la
[referencia de infracciones](../reference/policy-and-errors_es.md).

## Errores de apertura y estructura

[`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php) no significa que el
archivo no haya superado la política elegida. Significa que no se puede leer correctamente.

Casos principales:

- el archivo no existe, no es un archivo local normal o no se puede leer;
- la ruta suministrada contiene `://`;
- el contenido no se puede reconocer como ZIP, TAR o TAR.GZ;
- ZIP, TAR o GZIP está dañado o tiene una estructura no válida.

En estos casos no se devuelve `InspectionResult`.

## Qué se comprueba

### Rutas

La biblioteca convierte las barras inversas a `/`, elimina segmentos vacíos y `.`, y rechaza:

- `..`, que permitiría subir al directorio padre;
- rutas absolutas;
- rutas con letra de unidad;
- rutas UNC;
- nombres con byte NUL;
- duplicados y conflictos de rutas tras la normalización.

### Tipos de contenido

Se permiten archivos normales y directorios. Los enlaces simbólicos, enlaces duros de TAR,
objetos especiales y funciones del formato no compatibles se devuelven como infracciones.

### Límites de recursos

Se comprueban:

- tamaño del archivo;
- cantidad de archivos, carpetas y elementos de metadatos del formato contabilizados;
- tamaño de un archivo tras la descompresión;
- volumen total de datos descomprimidos;
- cuando está configurada, la relación entre tamaño comprimido y descomprimido.

### Particularidades de ZIP, TAR y TAR.GZ

En ZIP también se comprueban el cifrado y el método de compresión.

Si un ZIP contiene un elemento cifrado, `inspect()` devuelve la infracción `encrypted_entry`
y `isAccepted()` será `false`. La versión actual del paquete no solicita contraseña ni extrae
contenido cifrado.

TAR y TAR.GZ solo admiten el conjunto implementado de cabeceras normales y extensiones
GNU/PAX. Los detalles están en la
[referencia de archivos compatibles](../reference/supported-archives_es.md).

[← Volver al README](../readme/README_es.md)
