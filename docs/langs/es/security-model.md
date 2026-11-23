# Modelo de seguridad

Esta página explica cómo se verifican los archivos no confiables, qué garantías ofrecen Atomic y Merge y qué riesgos debe controlar la aplicación que llama a la biblioteca.

Describe el comportamiento de la biblioteca, **no** los resultados de una auditoría formal de seguridad.

## Qué datos del archivo se consideran potencialmente peligrosos

Los datos del archivo se consideran no confiables. Se comprueban:

- nombres y rutas, conflictos tras normalización y restricciones deterministas de la plataforma;
- tipos de entrada: archivo normal, directorio, enlace u objeto especial;
- tamaños declarados y tamaños realmente leídos;
- volumen total descomprimido e información de compresión;
- funciones ZIP, TAR y TAR.GZ;
- cifrado ZIP y legibilidad real del contenido aceptado.

El destino es otro límite de confianza: al aplicar el resultado pueden existir archivos, directorios, enlaces u objetos que no estaban presentes durante una inspección anterior.

Un archivo puede ser estructuralmente correcto y contener `../file`, un enlace simbólico, datos descomprimidos excesivos o funciones no compatibles. La estructura legible no garantiza la seguridad.

## Semántica común de aceptación del archivo

`inspect()` y `extract()` utilizan las mismas entradas para verificar el archivo:

```text
archivePath
ArchivePolicy
password
```

Ambos métodos aplican la misma semántica de aceptación a esas entradas:

- `inspect()` devuelve `InspectionResult` con las razones de rechazo;
- `extract()` lanza `ArchiveRejectedException` con el `InspectionResult` correspondiente antes de publicar Atomic o aplicar Merge.

Los errores del espacio de trabajo, destino o bloqueo pueden producirse antes de observar la comprobación completa del archivo. `destinationPath` y `ExtractionOptions` solo determinan cómo se aplica el resultado al sistema de archivos, sin cambiar la aceptación del archivo.

## Qué hace `inspect()`

[`ArchiveGuard::inspect()`](../../../src/ArchiveGuard.php) no escribe archivos extraídos en disco. Comprueba:

- normalización de rutas y colisiones;
- reglas deterministas de nombres de la plataforma;
- tipos de entradas;
- límites [`ArchivePolicy`](../../../src/ArchivePolicy.php);
- funciones compatibles de ZIP, TAR y TAR.GZ;
- en ZIP, compresión, cifrado y lectura por flujo del contenido normal aceptado.

En Windows, los componentes inválidos producen `platform_incompatible_path` y las colisiones sin distinción de mayúsculas/minúsculas ASCII producen `path_collision`. Las reglas deterministas se comprueban en `inspect()`; las dependientes del destino concreto en `extract()`.

## Contraseñas ZIP

El `password` opcional es un parámetro sensible:

- `null` indica ausencia de contraseña y `''` una contraseña vacía explícita;
- también se puede dar contraseña para ZIP no cifrados;
- una entrada cifrada sin contraseña produce `encrypted_entry`;
- un método de cifrado no admitido produce `unsupported_encryption`;
- un fallo al descifrar o leer datos cifrados produce `decryption_failed`.

`decryption_failed` es un error de descifrado/lectura; `ZipArchive`/`libzip` no siempre puede determinar si la contraseña era incorrecta. El argumento lleva `#[SensitiveParameter]` y no se guarda en `ExtractionOptions`, `ExtractionResult`, el espacio de trabajo, los metadatos de inspección ni el diagnóstico.

TAR y TAR.GZ no tienen cifrado por contraseña integrado. `password` debe ser `null`; otro valor provoca `InvalidArgumentException`.

## Qué hace `extract()`

[`ArchiveGuard::extract()`](../../../src/ArchiveGuard.php) exige [`ExtractionOptions`](../../../src/ExtractionOptions.php) explícito. Antes de aplicar cambios:

1. Valida el origen y detecta el formato.
2. Comprueba `maxArchiveBytes`.
3. Valida la contraseña según el formato.
4. Inicializa `ExtractionWorkspace`: comprueba el destino, adquiere un bloqueo cooperativo y crea un área temporal en el padre del destino.
5. `ZipExtractor` o `TarExtractor` realiza la comprobación y extracción completas dentro del área temporal.
6. Publica Atomic o comprueba y aplica Merge.
7. Limpia el espacio de trabajo y libera el bloqueo.

Los fallos de destino, bloqueo o área temporal pueden aparecer antes de observar el resultado completo, sin cambiar la semántica común de aceptación.

Para TAR/TAR.GZ se comparan orden, ruta, tipo y tamaño de cada entrada con la pasada de inspección. Si cambia el origen entre pasadas, se detiene la extracción.

## Atomic

`ExtractionOptions::atomic()` requiere un directorio final nuevo que **todavía no exista**. La biblioteca extrae y comprueba todo en un área temporal del padre y publica el resultado con `rename()` en la ruta final antes ausente. Un fallo previo a la publicación elimina el área temporal.

## Merge

`ExtractionOptions::merge(...)` se aplica a un **directorio real existente**. Primero se prepara el archivo completo y se comprueban todos los conflictos. Después, para archivos normales ya presentes:

- `Reject`: rechaza el conflicto antes de aplicar cambios;
- `Skip`: conserva el archivo existente;
- `Overwrite`: sustituye el archivo normal por otro archivo normal preparado.

Se reutilizan directorios coincidentes; se rechazan conflictos archivo/directorio, enlaces y objetos especiales en rutas afectadas.

Merge aplica cambios secuencialmente. Un error del sistema de archivos lanza `ExtractionException` y detiene cambios posteriores; **los cambios ya aplicados permanecen**, por lo que Merge no es transaccional. Después se elimina el área temporal.

## Bloqueo cooperativo

Archive Guard usa automáticamente `.archive-guard-locks` para coordinar sus propias operaciones simultáneas sobre el mismo destino. Es un **bloqueo cooperativo**: no impide que otros procesos arbitrarios modifiquen los archivos.

## Límites de recursos

Cuatro límites obligatorios controlan tamaño del archivo de origen, cantidad de entradas (incluidos algunos metadatos), tamaño de cada entrada descomprimida y volumen total. `maxCompressionRatio` añade una comprobación opcional:

- ZIP usa tamaños de los metadatos;
- TAR.GZ usa el volumen real producido por el descompresor;
- TAR sin comprimir no aplica esta relación.

Las entradas ZIP aceptadas también se leen por flujo y se comprueba su tamaño y legibilidad reales.

## Recomendaciones prácticas

- Ajuste `ArchivePolicy` a los archivos y recursos reales.
- Use Atomic para publicar un directorio nuevo y Merge para uno existente, eligiendo deliberadamente la estrategia de conflictos.
- Pase la contraseña ZIP directamente a la llamada y nunca la registre.
- Trate `InspectionResult`, `ArchiveOpenException`, `ArchiveRejectedException` y `ExtractionException` por separado.
- Para Merge, compruebe los permisos de escritura del padre de cada objeto afectado y del padre del destino para crear el área temporal y el bloqueo. `Skip` no exige permiso de escritura para el archivo que conserva; en Windows, el archivo sobrescrito debe ser escribible y en POSIX puede reemplazarse un archivo de solo lectura si el padre es escribible.
- Evite que otros procesos modifiquen el destino simultáneamente. Se extraen contenidos y directorios, pero **no** se restauran permisos, propietario ni marcas de tiempo del archivo. Los nombres de Windows tienen restricciones adicionales comprobadas durante la inspección.

Más detalles en [formatos compatibles](reference/supported-archives.md) y la [guía de extracción](guides/extraction.md).

[← Volver al README](README.md)
