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
[`ArchiveOpenException`](../../src/Exception/ArchiveOpenException.php).

## ZIP

El paquete acepta archivos y directorios ZIP normales cuando superan las comprobaciones comunes
de rutas y límites de recursos.

Además:

- se rechazan enlaces simbólicos y tipos especiales de elementos;
- un directorio con datos distintos de cero se rechaza como función no compatible;
- un elemento cifrado produce la infracción `encrypted_entry`;
- el paquete **no solicita contraseña** ni extrae contenido cifrado;
- el método de compresión debe ser compatible con el `ZipArchive` actual; de lo contrario se
  devuelve `unsupported_compression`;
- si se configura `maxCompressionRatio`, la relación se evalúa a partir de los metadatos ZIP.

La comprobación de la relación de compresión sigue siendo una heurística adicional. El volumen
real escrito se vuelve a controlar durante la extracción.

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

## TAR.GZ

Los datos obtenidos tras descomprimir GZIP se comprueban con las mismas reglas que TAR normal.

Si se configura `maxCompressionRatio`, se contabiliza la cantidad real de datos producida por
el descompresor GZIP. Así se puede limitar una expansión excesiva de datos comprimidos antes
de escribir archivos en disco.

No se admiten datos adicionales después del flujo GZIP terminado ni varios flujos GZIP
concatenados; se consideran un error de estructura del archivo.

## Cuando una función del formato no es compatible

El paquete prefiere rechazar una variante desconocida o no compatible en lugar de intentar
extraerla parcialmente.

Según la situación, será:

- una infracción estructurada como `encrypted_entry`, `symlink_entry` o
  `unsupported_feature`;
- `ArchiveOpenException` si la estructura está dañada y no se puede interpretar de forma
  fiable.

Todos los códigos de infracción se enumeran en la
[referencia de infracciones](policy-and-errors_es.md).

[← Volver al README](../readme/README_es.md)
