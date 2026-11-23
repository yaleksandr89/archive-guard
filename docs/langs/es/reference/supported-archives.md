# Archivos compatibles

Esta referencia describe qué variantes de ZIP, TAR y TAR.GZ entiende el paquete y qué
funciones del formato rechaza de forma intencionada.

## Detección del formato

El formato se detecta por el contenido del archivo y no por la extensión del nombre:

- ZIP — por la firma ZIP;
- TAR.GZ — por la firma GZIP;
- TAR — por una cabecera TAR válida o un bloque TAR vacío.

El origen debe ser un archivo local normal, legible. Se rechaza una ruta que contenga `://`.

Si el contenido no se puede reconocer o la estructura está dañada, se lanza
[`ArchiveOpenException`](../../../../src/Exception/ArchiveOpenException.php).

Si una entrada ZIP sin cifrar ya aceptada no se puede leer de forma fiable, se produce `ArchiveOpenException`. En cambio, un fallo de descifrado/lectura del contenido cifrado con contraseña produce `decryption_failed`.

## ZIP

Se aceptan archivos normales y directorios ZIP si superan las comprobaciones de rutas, límites de recursos, método de compresión y legibilidad real del contenido.

Además:

- se rechazan enlaces simbólicos y tipos especiales;
- un directorio con datos no vacíos se rechaza por función no compatible;
- el `ZipArchive` actual debe admitir el método de compresión, o se devuelve `unsupported_compression`;
- `maxCompressionRatio`, si está configurado, utiliza metadatos de las entradas ZIP;
- las entradas normales aceptadas se leen por flujo hasta EOF y se compara el tamaño descomprimido real con los metadatos aceptados.

### ZIP cifrados

`inspect()` y `extract()` usan el mismo argumento opcional `password`:

- una entrada cifrada sin contraseña devuelve `encrypted_entry`;
- un método de cifrado no admitido por el entorno devuelve `unsupported_encryption`;
- un fallo al descifrar o leer contenido cifrado devuelve `decryption_failed`;
- una contraseña válida permite un ZIP cifrado o mixto con entradas cifradas y normales si supera las demás comprobaciones.

Una contraseña también permite comprobar ZIP sin cifrar. La cadena vacía `''` cuenta como contraseña proporcionada, no como `null`. La compatibilidad real depende de `ZipArchive`/`libzip` en el entorno actual.

## TAR

Se admiten archivos y directorios normales, cabeceras USTAR y un conjunto limitado de
extensiones GNU/PAX necesario para determinar correctamente el nombre, el tamaño y otros
metadatos permitidos.

Aspectos importantes:

- se rechazan enlaces simbólicos y duros;
- se rechazan elementos especiales y dispersos;
- se rechaza un directorio con datos distintos de cero;
- se rechazan campos PAX desconocidos y extensiones no compatibles;
- se rechazan extensiones locales repetidas o en conflicto;
- los elementos de metadatos de las extensiones también cuentan para los límites de cantidad
  de elementos y de volumen de datos.

Una cabecera dañada o una estructura TAR no válida produce `ArchiveOpenException` y no una
infracción normal de la política.

`maxCompressionRatio` no se aplica a TAR sin comprimir.

TAR no incluye cifrado por contraseña integrado. Su `password` debe ser `null`; otro valor provoca `InvalidArgumentException`.

## TAR.GZ

Los datos obtenidos tras descomprimir GZIP se comprueban con las mismas reglas que TAR normal.

Si se configura `maxCompressionRatio`, se contabiliza la cantidad real de datos producida por
el descompresor GZIP. Así se puede limitar una expansión excesiva de datos comprimidos antes
de escribir archivos en disco.

No se admiten datos adicionales después del flujo GZIP terminado ni varios flujos GZIP
concatenados; se consideran un error de estructura del archivo.

Como TAR, TAR.GZ no tiene cifrado por contraseña integrado. Un `password` distinto de `null` provoca `InvalidArgumentException`.

## Compatibilidad de rutas con la plataforma

Las comprobaciones generales de rutas seguras se aplican a todos los formatos. `inspect()` también comprueba las reglas deterministas de nombres de la plataforma actual.

En Windows se incluyen:

- componentes con UTF-8 válido;
- caracteres Win32 prohibidos y caracteres de control;
- puntos o espacios finales;
- nombres de dispositivos reservados;
- colisiones de rutas ignorando mayúsculas/minúsculas ASCII.

Un nombre incompatible produce `platform_incompatible_path`; una colisión puede producir `path_collision`. Estas comprobaciones se hacen en `inspect()`; las dependientes del destino concreto se hacen en `extract()`.

## Errores de formato y funciones

Si un formato compatible utiliza una función que esta implementación no puede procesar, la inspección devuelve un código como `unsupported_encryption`, `unsupported_compression` o `unsupported_feature`.

Una estructura dañada o un contenido ZIP sin cifrar aceptado pero ilegible provoca `ArchiveOpenException`. La [referencia de infracciones](policy-and-errors.md) enumera todos los códigos.

[← Volver al README](../README.md)
