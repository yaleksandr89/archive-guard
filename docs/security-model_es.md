# Modelo de seguridad

Esta página explica frente a qué problemas habituales al trabajar con archivos comprimidos
protege el paquete, qué comprobaciones realiza y qué riesgos debe seguir controlando la
aplicación.

Describe el comportamiento de la biblioteca, no el resultado de una auditoría formal de
seguridad.

## Qué datos del archivo se consideran potencialmente peligrosos

Los datos procedentes del propio archivo se consideran potencialmente peligrosos. Se
comprueban:

- nombres y rutas de archivos;
- tipo de contenido: archivo normal, directorio, enlace u objeto especial;
- tamaños declarados de los archivos;
- volumen total de datos descomprimidos;
- información sobre la compresión;
- funciones adicionales de los formatos ZIP, TAR y TAR.GZ.

La razón es sencilla: un archivo puede ser estructuralmente válido y aun así contener una ruta
como `../file`, un enlace simbólico, un archivo enorme al descomprimir o una función del formato
que la aplicación no espera procesar.

## Qué hace `inspect()`

[`ArchiveGuard::inspect()`](../src/ArchiveGuard.php) comprueba el archivo sin extraer archivos.

La comprobación incluye:

- normalización y validación de rutas;
- detección de rutas en conflicto;
- comprobación del tipo de cada elemento: archivo, directorio, enlace u objeto especial;
- límites de [`ArchivePolicy`](../src/ArchivePolicy.php);
- comprobaciones específicas de ZIP, TAR y TAR.GZ.

Si el archivo es estructuralmente válido pero incumple una regla, la aplicación recibe un
[`InspectionResult`](../src/InspectionResult.php) con las causas. Si el propio archivo no se
puede leer o interpretar de forma fiable, se lanza `ArchiveOpenException`.

## Qué hace `extract()` antes de escribir

[`ArchiveGuard::extract()`](../src/ArchiveGuard.php) siempre comprueba el estado actual del
archivo justo antes de extraer. Un `InspectionResult` obtenido anteriormente no se considera
un permiso para escribir.

Después, el paquete:

1. comprueba que el directorio de destino ya existe y está vacío;
2. comprueba que el propio directorio de destino no es un enlace simbólico;
3. no sobrescribe archivos existentes;
4. compara el tamaño real de cada archivo y el volumen total descomprimido con los límites
   configurados y los tamaños obtenidos durante la comprobación. Si aparecen más datos de los
   permitidos, la extracción se detiene;
5. lee TAR y TAR.GZ una segunda vez para extraerlos. El orden, la ruta, el tipo y el tamaño de
   cada elemento se comparan con la primera comprobación; si el archivo cambia entre ambas
   pasadas, la extracción se detiene.

La aplicación crea el directorio de destino, no la biblioteca. La versión actual funciona solo
con un directorio vacío independiente. Si una aplicación guarda los resultados en una carpeta
común, debe crear dentro un subdirectorio vacío nuevo para cada operación.

## Límites de recursos

Cuatro límites obligatorios restringen:

- tamaño del archivo de origen;
- cantidad de archivos, carpetas y elementos de metadatos del formato contabilizados;
- tamaño de un archivo u otro elemento tras la descompresión;
- volumen total de datos descomprimidos.

El `maxCompressionRatio` opcional añade otra comprobación frente a una expansión excesiva de
datos comprimidos:

- ZIP usa tamaños de los metadatos;
- TAR.GZ usa la cantidad real de datos producida por el descompresor;
- TAR sin comprimir no usa este límite.

Es una heurística adicional, no un sustituto de los límites principales de tamaño.

## Particularidades de Windows

Algunos nombres son válidos dentro de ZIP o TAR, pero no se pueden crear de forma segura en
Windows.

Antes de escribir, el paquete también rechaza, por ejemplo:

- nombres de dispositivo reservados como `CON`, `NUL`, `PRN` y similares;
- caracteres no permitidos por Windows;
- nombres que terminan en punto o espacio;
- rutas que solo difieren en mayúsculas/minúsculas ASCII cuando Windows las trataría como la
  misma ruta.

Por eso `inspect()` puede aceptar el archivo en la comprobación general, mientras que
`extract()` en Windows se detendrá con `ExtractionException` antes de la primera escritura.

## Limitaciones y por qué existen

### La extracción no es atómica

Los archivos se escriben directamente en el directorio de destino preparado. Si la operación
se interrumpe después de escribir correctamente varios archivos, esos archivos permanecen en
el disco.

Un esquema completamente atómico exigiría descomprimir primero todo en un directorio temporal
y después sustituir o mover el resultado completo en una única acción final. Ese traslado tiene
limitaciones distintas entre sistemas de archivos y en Windows y requeriría un contrato público
separado. Por eso la versión actual no promete atomicidad.

### El directorio no se bloquea frente a otros procesos

La versión actual no aplica un bloqueo del sistema sobre el directorio de destino. Si otro
proceso modifica su contenido al mismo tiempo, las comprobaciones de la biblioteca no
garantizan protección frente a esos cambios.

Usa un directorio independiente al que solo tenga acceso tu aplicación durante la extracción.

### No se restauran permisos ni propietario del archivo

Un archivo comprimido puede contener sus propios permisos, propietario y marcas de tiempo.
La versión actual extrae el contenido y la estructura de directorios, pero no aplica esos
valores procedentes del archivo.

### No se admiten todas las funciones de ZIP o TAR

Si la biblioteca no puede interpretar una función del formato de forma suficientemente fiable,
rechaza el archivo en vez de intentar adivinar cómo tratarlo. Por ejemplo, la versión actual no
extrae elementos ZIP cifrados.

La lista exacta de limitaciones está en la
[referencia de archivos compatibles](reference/supported-archives_es.md).

## Recomendaciones prácticas

- elige límites de `ArchivePolicy` acordes con el tamaño real de los archivos de tu aplicación;
- usa un subdirectorio vacío independiente para cada operación de extracción;
- no permitas que procesos no confiables modifiquen ese directorio al mismo tiempo;
- gestiona por separado las infracciones, los errores de apertura y los errores de escritura;
- si la aplicación creó un directorio temporal y tiene control total sobre él, puede eliminarlo
  después de una extracción fallida según sus propias reglas.

[← Volver al README](readme/README_es.md)
