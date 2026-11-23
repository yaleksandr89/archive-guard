# Extracción controlada

Esta guía describe dos modos explícitos: **Atomic**, que publica un directorio nuevo completo, y **Merge**, que aplica el archivo a un directorio existente.

[`ArchiveGuard::extract()`](../../../../src/ArchiveGuard.php) no considera el resultado anterior de `inspect()` una autorización para aplicar cambios. Vuelve a comprobar el archivo durante la operación; el modo se selecciona con [`ExtractionOptions`](../../../../src/ExtractionOptions.php).

## Antes de extraer

Prepare lo siguiente:

- un archivo ZIP, TAR o TAR.GZ local y legible;
- una [`ArchivePolicy`](../../../../src/ArchivePolicy.php) con límites adecuados para la aplicación;
- [`ExtractionOptions`](../../../../src/ExtractionOptions.php) que elija explícitamente Atomic o Merge;
- una ruta de destino que cumpla las condiciones del modo elegido.

Para `ExtractionOptions::atomic()`, el directorio final **no debe existir**. La biblioteca crea automáticamente un directorio temporal en el directorio padre del destino; no es necesario crearlo manualmente. El padre debe permitir crear tanto ese directorio como el directorio de bloqueos cooperativos.

Para `ExtractionOptions::merge(...)`, el destino debe ser un **directorio real, legible y existente**, no un enlace simbólico. La biblioteca prepara el contenido completo en un directorio temporal creado automáticamente en el padre y solo después lo aplica al destino.

## Escenario básico: Atomic

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

// Elija límites adecuados para los archivos y recursos reales de su aplicación.
$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

try {
    $result = new ArchiveGuard()->extract(
        $archivePath,
        $destinationPath,
        $policy,
        ExtractionOptions::atomic(),
    );

    echo 'Archivos aplicados: ' . $result->filesExtracted() . PHP_EOL;
    echo 'Directorios creados: ' . $result->directoriesCreated() . PHP_EOL;
    echo 'Bytes escritos: ' . $result->bytesWritten() . PHP_EOL;
} catch (ArchiveRejectedException $e) {
    foreach ($e->inspectionResult()->violations() as $violation) {
        echo $violation->code->value . PHP_EOL;
    }
} catch (ArchiveOpenException | ExtractionException $e) {
    echo $e->getMessage() . PHP_EOL;
}
```

Atomic prepara y verifica todo en un directorio temporal del padre. Tras completar la extracción y las comprobaciones, `rename()` lo publica en la ruta final que anteriormente no existía.

## Merge y estrategias de conflicto

Para aplicar un archivo a un directorio existente, use `ExtractionOptions::merge()`:

```php
<?php

use Yaleksandr\ArchiveGuard\ArchiveGuard;
use Yaleksandr\ArchiveGuard\ArchivePolicy;
use Yaleksandr\ArchiveGuard\ExtractionConflictStrategy;
use Yaleksandr\ArchiveGuard\ExtractionOptions;

$policy = new ArchivePolicy(
    maxArchiveBytes: 50_000_000,
    maxEntries: 1_000,
    maxEntryUncompressedBytes: 10_000_000,
    maxTotalUncompressedBytes: 100_000_000,
);

$result = new ArchiveGuard()->extract(
    '/path/to/archive.zip',
    '/path/to/existing-directory',
    $policy,
    ExtractionOptions::merge(ExtractionConflictStrategy::Reject),
);
```

Tres estrategias resuelven conflictos entre un archivo del ZIP/TAR y un **archivo normal existente**:

- `Reject`: error durante la comprobación previa, antes de aplicar cambios de Merge;
- `Skip`: conservar el archivo existente y omitir el preparado;
- `Overwrite`: sustituir el archivo normal existente por el archivo normal preparado.

Los directorios siguen otra regla: si ambos lados contienen un directorio en la misma ruta, se reutiliza el existente. Un conflicto entre archivo y directorio, en cualquiera de los dos sentidos, siempre es un error. También se rechazan los enlaces simbólicos y los objetos especiales en las rutas afectadas del destino.

`Overwrite` solo permite sustituir un archivo normal por otro archivo normal; nunca autoriza sustituir directorios, enlaces u otros tipos de objeto.

## Qué ocurre antes de aplicar los cambios

`extract()` vuelve a comprobar `archivePath`, `ArchivePolicy` y `password`, sin usar un `inspect()` anterior como permiso para escribir.

La secuencia general es:

1. Validar el origen y detectar el formato según su contenido.
2. Comprobar `maxArchiveBytes`.
3. Comprobar si el formato permite el argumento `password`.
4. Adquirir el bloqueo cooperativo de Archive Guard para el destino.
5. Crear un directorio temporal dentro del padre del destino.
6. Extraer y verificar todo el contenido dentro de ese directorio.
7. Para Atomic, publicar el directorio completo en una ruta final que no existía.
8. Para Merge, comprobar previamente todos los conflictos y aplicar los archivos según la estrategia seleccionada.
9. Limpiar el directorio temporal y liberar el bloqueo.

ZIP usa un mecanismo común de lectura por flujo que comprueba el volumen real al leer y no carga una entrada completa en memoria.

TAR y TAR.GZ usan una pasada de extracción independiente. El orden, la ruta, el tipo y el tamaño de cada entrada se comparan con el resultado de la comprobación; si el origen cambia entre pasadas, la extracción se detiene.

## Contraseña ZIP

Pase la contraseña como argumento con nombre:

```php
$result = new ArchiveGuard()->extract(
    $archivePath,
    $destinationPath,
    $policy,
    ExtractionOptions::atomic(),
    password: $password,
);
```

`null` significa que no se proporcionó contraseña. La cadena vacía `''` es una contraseña proporcionada y no se convierte en `null`.

Una contraseña también es válida para un ZIP sin cifrar. Una entrada cifrada sin contraseña devuelve `encrypted_entry`; un método no admitido por el entorno actual devuelve `unsupported_encryption`; un fallo al descifrar o leer contenido cifrado devuelve `decryption_failed`.

`decryption_failed` indica un fallo de descifrado o lectura. `ZipArchive`/`libzip` no siempre puede distinguir una contraseña incorrecta de otro error de lectura del contenido cifrado.

TAR y TAR.GZ no tienen cifrado integrado mediante contraseña. Para ellos `password` debe ser `null`; cualquier otro valor provoca `InvalidArgumentException`.

La contraseña ZIP se marca como parámetro sensible y no se guarda en `ExtractionOptions`, `ExtractionResult`, el espacio de trabajo ni los mensajes de diagnóstico.

## Resultado

[`ExtractionResult`](../../../../src/ExtractionResult.php) describe los cambios realmente aplicados:

- `format()`: formato del archivo;
- `filesExtracted()`: archivos nuevos y sobrescritos aplicados realmente al destino;
- `directoriesCreated()`: directorios creados realmente;
- `bytesWritten()`: bytes de contenido de los archivos nuevos y sobrescritos aplicados;
- `filesSkipped()`: archivos realmente omitidos mediante `Skip`;
- `filesOverwritten()`: archivos existentes realmente sustituidos mediante `Overwrite`.

En Atomic, `filesSkipped()` y `filesOverwritten()` siempre son cero.

## Errores

Durante `extract()` pueden aparecer tres grupos principales:

- [`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php): no se puede abrir o analizar de forma fiable el origen, o no se puede leer contenido ZIP sin cifrar que ya se había aceptado;
- [`ArchiveRejectedException`](../../../../src/Exception/ArchiveRejectedException.php): el archivo no supera la comprobación. `inspectionResult()` contiene los motivos; aún no se ha iniciado la publicación Atomic ni la aplicación Merge;
- [`ExtractionException`](../../../../src/Exception/ExtractionException.php): problema del destino, bloqueo, directorio temporal, comprobación previa, aplicación Merge, publicación Atomic o E/S del sistema de archivos.

Una contraseña no nula para TAR o TAR.GZ provoca por separado `InvalidArgumentException`. Consulte los códigos en la [referencia de errores](../reference/policy-and-errors.md).

## Cómo se aplican Atomic y Merge

### Atomic

Atomic extrae y verifica completamente el resultado en un directorio temporal creado en el padre del destino. Tras superar las comprobaciones, `rename()` lo publica en un directorio final nuevo. Si ocurre un error antes de la publicación, la operación termina y elimina el directorio temporal.

### Merge

Merge funciona con un directorio existente. Primero prepara todo el archivo en el área temporal y comprueba los conflictos; después aplica sucesivamente la estrategia `Reject`, `Skip` u `Overwrite`.

Un error de E/S durante la aplicación provoca `ExtractionException` y detiene los cambios posteriores. **Los cambios ya aplicados permanecen** en el destino. El directorio temporal se elimina al terminar. Merge **no es transaccional**.

### Bloqueo cooperativo

Ambos modos usan `.archive-guard-locks` para coordinar operaciones simultáneas de Archive Guard sobre un mismo destino. La biblioteca crea y utiliza automáticamente el bloqueo. Este mecanismo cooperativo no impide las escrituras de procesos externos arbitrarios.

## Permisos de acceso para Merge

Los permisos de Merge no se reducen a exigir que «todo el destino sea escribible»:

- Con `Skip`, si el archivo en conflicto no se modifica, no se necesita permiso de escritura solo para ese archivo.
- Para crear un archivo o directorio, o usar `Overwrite`, debe poder escribirse en el **padre del objeto concreto** afectado.
- En Windows, el archivo que se sustituye también debe permitir escritura.
- En POSIX, un archivo de solo lectura puede sustituirse si su directorio padre es escribible.
- La raíz de Merge puede ser de solo lectura si los cambios no requieren escribir directamente en ella.

El directorio padre del destino también debe permitir crear el área temporal y los bloqueos cooperativos.

Los metadatos del archivo pueden contener permisos, propietario y fechas. Archive Guard extrae contenido y directorios, pero no aplica esos valores almacenados.

## Windows

Las reglas deterministas de nombres de la plataforma actual se comprueban durante la inspección del archivo. En Windows se rechazan, por ejemplo:

- UTF-8 no válido en un componente de ruta;
- caracteres Win32 prohibidos y caracteres de control;
- nombres terminados en punto o espacio;
- dispositivos reservados `CON`, `NUL`, `PRN`, `AUX`, `COM1...`, `LPT1...`;
- colisiones de rutas que solo difieren por mayúsculas y minúsculas ASCII.

Las comprobaciones dependientes del directorio de destino y su sistema de archivos se ejecutan en `extract()`.

Encontrará más detalles en el [modelo de seguridad](../security-model.md).

[← Volver al README](../README.md)
