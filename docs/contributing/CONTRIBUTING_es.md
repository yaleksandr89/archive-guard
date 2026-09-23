# Participar en el desarrollo

## Elige un idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/blob/master/.github/CONTRIBUTING.md) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_en.md) | **Seleccionado** | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_zh.md) | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_fr.md) | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/contributing/CONTRIBUTING_de.md) |

Gracias por querer mejorar Archive Guard. Los cambios aquí afectan al tratamiento de archivos no confiables y a la escritura en el sistema de archivos, por lo que importan un alcance pequeño, un comportamiento verificable y límites de seguridad explícitos.

## Antes de empezar

- Informa de errores reproducibles mediante GitHub Issues.
- Las preguntas de uso y las ideas pueden discutirse en GitHub Discussions.
- Informa de problemas de seguridad siguiendo la [política de seguridad](https://github.com/yaleksandr89/archive-guard/security/policy), sin publicar código de explotación ni detalles sensibles.
- Los cambios grandes en la API pública, los formatos, el modelo de extracción o los límites de seguridad deben discutirse primero en un Issue o Discussion.

## Contrato del paquete

- El paquete sigue siendo una biblioteca independiente de frameworks para PHP `^8.4`.
- Los formatos compatibles se detectan por el contenido: ZIP, TAR y TAR.GZ.
- Las operaciones públicas principales son `ArchiveGuard::inspect()` y `ArchiveGuard::extract()`.
- Los límites de recursos se configuran mediante `ArchivePolicy`.
- Deben rechazarse las rutas inseguras, los enlaces simbólicos y duros, los objetos especiales y las funciones de formato no compatibles.
- `extract()` realiza la comprobación obligatoria antes de escribir, no sobrescribe archivos existentes y controla el volumen real de datos escritos.
- Para TAR y TAR.GZ, el resultado de la primera pasada se compara con la pasada de extracción.
- El directorio de destino debe existir de antemano, estar vacío y no ser un enlace simbólico.
- La versión actual no promete extracción atómica, bloqueo del directorio, extracción de ZIP cifrados ni restauración de metadatos del sistema de archivos guardados en el archivo.
- No añadas bundles/service providers de frameworks, almacenamiento, trabajos en segundo plano, retry/fallback automáticos, telemetría ni funciones no relacionadas.

Los límites detallados se describen en el [modelo de seguridad](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security-model_es.md).

## Ramas

Usa un nombre corto que describa el cambio, por ejemplo:

```text
fix/tar-size-validation
feat/zip-password
docs/security-policy
```

## Commits

Usa Conventional Commits con una descripción breve:

```text
fix: corregir la validación del tamaño TAR
feat: añadir soporte de contraseña ZIP
docs: aclarar el modelo de seguridad
test: añadir una regresión de colisión de rutas
```

Cada commit debe contener un único cambio coherente, sin refactorizaciones no relacionadas.

## Comprobaciones locales

Instala las dependencias y ejecuta el conjunto completo:

```shell
composer install
composer check
```

También están disponibles comprobaciones específicas:

```shell
composer test
composer analyse
composer cs:check
```

`composer coverage` es un informe de diagnóstico y no es obligatorio para cada cambio.

## Pruebas y fixtures

- Añade pruebas para un comportamiento concreto o una regresión, no para aumentar el número de assertions.
- Usa únicamente fixtures ZIP/TAR/TAR.GZ sintéticos y directorios temporales.
- No añadas archivos reales de usuarios, ficheros privados, tokens ni credenciales.
- Los cambios en rutas, tipos de elementos, límites o extracción deben incluir pruebas de la frontera de seguridad afectada.
- Si el comportamiento depende de Windows, conserva o añade cobertura en CI de Windows cuando corresponda.
- No debilites una comprobación solo para aceptar un archivo problemático; define primero el contrato público seguro.

## Pull Request

En la descripción del Pull Request indica:

- el problema y el cambio implementado;
- el impacto en la API pública y la compatibilidad;
- la frontera de seguridad/sistema de archivos afectada;
- las pruebas añadidas o actualizadas;
- las comprobaciones ejecutadas;
- los cambios de documentación y la sincronización de traducciones cuando cambió el comportamiento público.

Antes de enviar, comprueba:

- que el diff no contiene cambios no relacionados;
- que `git diff --check` pasa;
- que `composer check` o un conjunto justificado de comprobaciones relevantes pasa;
- que no se han añadido `vendor/`, `composer.lock`, `.build/`, archivos reales con datos privados ni otros artefactos locales;
- que la API pública y la documentación coinciden con el comportamiento real;
- que los detalles de seguridad no se revelan prematuramente en un Issue o Pull Request público.
