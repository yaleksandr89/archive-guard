# Comprobación de archivos

Esta guía explica cómo comprobar un archivo antes de descomprimirlo, cómo leer el resultado y
en qué se diferencia una infracción normal de los límites de un problema del propio archivo.

[`ArchiveGuard::inspect()`](../../../../src/ArchiveGuard.php) no extrae nada al disco. Solo analiza
el archivo y devuelve un [`InspectionResult`](../../../../src/InspectionResult.php).

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

## ZIP con contraseña

Para un ZIP cifrado, pase la contraseña mediante un argumento con nombre:

```php
$result = new ArchiveGuard()->inspect(
    $archivePath,
    $policy,
    password: $password,
);
```

La diferencia entre estos valores es importante:

- `null` indica que no se proporcionó ninguna contraseña;
- `''` indica una cadena vacía proporcionada explícitamente, que no se convierte en `null`;
- cualquier valor proporcionado, incluso `''`, se pasa a `ZipArchive` como contraseña.

Se permite proporcionar una contraseña para un ZIP sin cifrar. TAR y TAR.GZ no tienen cifrado por contraseña integrado; un `password` distinto de `null` provoca `InvalidArgumentException` con `Password is only valid for ZIP archives.`

Los rechazos posibles de entradas cifradas son:

- `encrypted_entry`: entrada cifrada sin contraseña;
- `unsupported_encryption`: se proporcionó contraseña, pero el entorno no admite el método de cifrado;
- `decryption_failed`: no se pudo descifrar o leer el contenido; esto no demuestra que la contraseña sea necesariamente incorrecta.

La contraseña está marcada como parámetro sensible y no debe aparecer en excepciones, infracciones ni mensajes de diagnóstico.

## Límites de comprobación

[`ArchivePolicy`](../../../../src/ArchivePolicy.php) define **límites máximos permitidos**, no valores
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
[referencia de `ArchivePolicy`](../reference/policy-and-errors.md).

El tamaño del archivo de origen se comprueba antes de validar si el formato permite contraseña. Por tanto, `archive_too_large` tiene prioridad sobre un error de contraseña TAR/TAR.GZ.

## Resultado de la comprobación

[`InspectionResult`](../../../../src/InspectionResult.php) ofrece tres métodos principales:

- `format()` — formato detectado: `zip`, `tar` o `tar.gz`;
- `isAccepted()` — indica si el archivo superó todas las comprobaciones;
- `violations()` — causas por las que se rechazó el archivo.

Si `isAccepted()` devuelve `false`, el archivo puede seguir siendo estructuralmente válido.
Por ejemplo, puede contener un archivo demasiado grande o un enlace simbólico que la política
del paquete no permite extraer.

Cada [`Violation`](../../../../src/Violation.php) contiene:

- `code` — código estable de la causa, adecuado para la lógica de la aplicación;
- `message` — descripción de diagnóstico;
- `entryName` — nombre del archivo, directorio u otro elemento si la infracción está asociada
  a uno concreto.

La lista completa de códigos está en la
[referencia de infracciones](../reference/policy-and-errors.md).

## Errores de apertura y estructura

[`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php) no significa que el
archivo no haya superado la política elegida. Significa que no se puede leer correctamente.

Casos principales:

- el archivo no existe, no es un archivo local normal o no se puede leer;
- la ruta suministrada contiene `://`;
- el contenido no se puede reconocer como ZIP, TAR o TAR.GZ;
- ZIP, TAR o GZIP está dañado o tiene una estructura no válida.

En estos casos no se devuelve `InspectionResult`.

Si no se puede leer de forma fiable una entrada ZIP sin cifrar aceptada después de validar sus metadatos, también se produce `ArchiveOpenException`. En cambio, un fallo al descifrar o leer una entrada cifrada con contraseña devuelve un `InspectionResult` rechazado con `decryption_failed`, no `ArchiveOpenException`.

## Qué se comprueba

### Rutas

La biblioteca convierte las barras inversas en `/`, elimina segmentos vacíos y `.`, y rechaza:

- segmentos `..` que permiten subir al directorio padre;
- rutas absolutas, con letra de unidad o rutas UNC;
- nombres con byte NUL;
- duplicados y conflictos tras la normalización.

En Windows, la misma comprobación aplica restricciones deterministas: UTF-8 inválido, caracteres Win32 prohibidos y de control, puntos/espacios finales, dispositivos reservados y colisiones sin distinción entre mayúsculas y minúsculas ASCII.

Las reglas deterministas de nombres se comprueban en `inspect()`. Las condiciones dependientes del directorio de destino concreto y su sistema de archivos se comprueban en `extract()`.

### Tipos de contenido

Se admiten archivos normales y directorios. Los enlaces simbólicos, enlaces duros TAR, objetos especiales y funciones no compatibles del formato se notifican como infracciones.

### Límites de recursos

Se verifican el tamaño del archivo de origen, la cantidad de archivos/directorios y metadatos contabilizados, el tamaño de cada entrada tras descomprimir, el volumen total y, si se configura, la relación de compresión.

### Contenido ZIP

Tras validar los metadatos, las entradas ZIP normales aceptadas se leen por flujo hasta EOF. Los bytes realmente descomprimidos se comparan con los metadatos aceptados. Por tanto, `inspect()` verifica la legibilidad real y no solo el directorio central: los datos dañados no se aceptan simplemente porque sus metadatos parezcan correctos.

### Relación entre `inspect()` y `extract()`

Ambos métodos utilizan las mismas entradas de comprobación del archivo:

```text
archivePath
ArchivePolicy
password
```

Con el mismo contenido, `ArchivePolicy` y `password`, la semántica de aceptación es idéntica. Si `extract()` llega a la comprobación y rechaza el archivo, lanza `ArchiveRejectedException` con el `InspectionResult` correspondiente antes de publicar Atomic o aplicar Merge.

Los errores del espacio de trabajo, destino o bloqueo pueden ocurrir antes. `destinationPath` y `ExtractionOptions` determinan cómo se aplica el resultado, pero no cambian la aceptación del archivo. Los permisos, conflictos Merge, bloqueos, alias de sistema de archivos y cambios concurrentes se verifican solo en `extract()`.

### Particularidades de TAR y TAR.GZ

TAR y TAR.GZ admiten el conjunto implementado de encabezados normales y extensiones GNU/PAX. Consulte la [referencia de formatos](../reference/supported-archives.md).
[← Volver al README](../README.md)
