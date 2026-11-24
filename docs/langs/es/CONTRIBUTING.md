# Participar en el desarrollo

## Elige un idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/blob/master/.github/CONTRIBUTING.md) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/en/CONTRIBUTING.md) | **Seleccionado** | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/zh/CONTRIBUTING.md) | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/fr/CONTRIBUTING.md) | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/de/CONTRIBUTING.md) |

Gracias por querer mejorar Archive Guard. Los cambios aquí afectan al tratamiento de archivos no confiables y a la escritura en el sistema de archivos, por lo que importan un alcance pequeño, un comportamiento verificable y límites de seguridad explícitos.

## Antes de empezar

- Informa de errores reproducibles mediante GitHub Issues.
- Las preguntas de uso y las ideas pueden discutirse en GitHub Discussions.
- Informa de problemas de seguridad siguiendo la [política de seguridad](https://github.com/yaleksandr89/archive-guard/security/policy), sin publicar código de explotación ni detalles sensibles.
- Los cambios grandes en la API pública, los formatos, el modelo de extracción o los límites de seguridad deben discutirse primero en un Issue o Discussion.

## Contrato del paquete

- El paquete sigue siendo una biblioteca independiente de frameworks para PHP `>=8.4 <8.6`.
- ZIP, TAR y TAR.GZ se detectan por el contenido.
- Las operaciones públicas principales son `ArchiveGuard::inspect()` y `ArchiveGuard::extract()`.
- Los límites se configuran con `ArchivePolicy`.
- `inspect()` y `extract()` comparten la semántica de aceptación para `archivePath`, `ArchivePolicy` y el `password` ZIP opcional.
- Se rechazan rutas peligrosas, colisiones normalizadas, nombres incompatibles con la plataforma, enlaces simbólicos/duros, objetos especiales y funciones no compatibles.
- Para ZIP, `inspect()` comprueba la legibilidad real por flujo, no solo los metadatos.
- `extract()` requiere `ExtractionOptions`: `atomic()` para un destino nuevo inexistente o `merge(strategy)` para uno existente.
- Atomic crea automáticamente un área temporal en el padre del destino, prepara el resultado completo y lo publica con `rename()`.
- Merge prepara todo en temporal, comprueba conflictos y aplica `Reject`, `Skip` u `Overwrite`.
- `Overwrite` solo sustituye archivo normal por archivo normal. Se rechazan conflictos de tipos, enlaces y objetos especiales.
- Operaciones de Archive Guard sobre el mismo destino se coordinan con `.archive-guard-locks`; no se bloquean escritores externos arbitrarios.
- ZIP admite contraseña opcional para comprobar y extraer. Nunca se incorpora la contraseña original a resultados, estado ni diagnóstico. TAR/TAR.GZ no incluyen cifrado por contraseña.
- TAR/TAR.GZ comparan la inspección con la pasada independiente de extracción.
- No añada integraciones de frameworks, almacenamiento, tareas en segundo plano, reintentos/fallback automáticos, telemetría ni funciones ajenas al alcance.

Consulte el [modelo de seguridad](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/es/security-model.md).

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
fix: исправить проверку размера TAR
feat: добавить поддержку пароля ZIP
docs: уточнить модель безопасности
test: добавить регрессию для коллизии путей
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

- Añada pruebas de comportamientos concretos o regresiones, no para aumentar las aserciones.
- Use solo archivos ZIP/TAR/TAR.GZ sintéticos y directorios temporales.
- Use contraseñas sintéticas y compruebe que nunca aparecen en diagnósticos.
- No incluya archivos de usuarios, ficheros privados, tokens ni credenciales reales.
- Los cambios de rutas, cifrado, tipos de entradas, límites, Atomic/Merge o aplicación al sistema de archivos deben probar la frontera de seguridad afectada.
- Si el comportamiento depende de Windows o libzip, conserve pruebas que tengan en cuenta las capacidades y la cobertura CI de Windows cuando corresponda.
- No debilite la validación para aceptar un archivo problemático; defina primero el contrato público seguro.

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
