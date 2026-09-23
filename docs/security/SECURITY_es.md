# Política de seguridad

## Elige un idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/security/policy) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_en.md) | **Seleccionado** | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_zh.md) | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_fr.md) | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/security/SECURITY_de.md) |

## Versiones compatibles

Las correcciones de seguridad se publican para la línea estable actual `1.x`.

| Versión | Soporte |
|---|---|
| `1.x` | Sí |

## Qué se considera una vulnerabilidad

Entre los problemas de seguridad se incluyen, en particular:

- eludir la validación de rutas de forma que permita escribir un archivo fuera del directorio de destino;
- extraer un enlace simbólico, enlace duro, objeto especial u otro elemento que el paquete debería rechazar;
- eludir los límites de `ArchivePolicy` para que la extracción continúe después de superar el tamaño permitido de un elemento, el volumen total de datos u otro límite configurado;
- una diferencia entre el contenido comprobado y el realmente extraído que permita escribir datos no validados;
- eludir las comprobaciones de nombres de Windows o de colisiones de rutas de forma que los datos se escriban en una ubicación inesperada;
- un error al interpretar ZIP, TAR o TAR.GZ con un impacto concreto de seguridad, por ejemplo una escritura de archivo no prevista;
- la compromisión del código fuente, CI, etiquetas de versión, paquetes publicados u otra parte de la cadena de suministro.

## Qué no es una vulnerabilidad por sí solo

El siguiente comportamiento es un límite documentado de la versión actual:

- la extracción no es atómica y algunos archivos ya creados pueden permanecer después de un fallo;
- el directorio de destino no se bloquea frente a modificaciones simultáneas de otro proceso;
- el directorio de destino debe existir de antemano, estar vacío y no ser un enlace simbólico;
- los archivos y directorios existentes no se sobrescriben;
- los ZIP cifrados no se extraen y no se solicita contraseña;
- no se restauran permisos, propietario ni marcas de tiempo almacenadas en el archivo;
- las funciones de formato no compatibles se rechazan en lugar de procesarse parcialmente.

Si el comportamiento real contradice una garantía documentada o permite eludir una comprobación respetando las condiciones de uso documentadas, puede tratarse de un problema de seguridad.

## Cómo informar de una vulnerabilidad

GitHub Private Vulnerability Reporting es el canal preferido cuando está disponible en el repositorio:

1. Abre la pestaña **Security** del repositorio.
2. Ve a **Advisories**.
3. Selecciona **Report a vulnerability**.
4. Envía el informe sin publicar detalles en un Issue público.

Si el formulario privado no está disponible, crea un Issue público mínimo, sin código de explotación ni detalles sensibles, y solicita un canal privado de comunicación.

No publiques antes de que exista una corrección:

- un exploit listo para usar o un archivo que reproduzca directamente un bypass de protección;
- secretos, tokens, credenciales o datos privados reales;
- rutas, contenidos de archivos o registros de producción que contengan información sensible.

## Qué incluir

Siempre que sea posible, indica:

- versión afectada del paquete o SHA del commit;
- versión de PHP y sistema operativo;
- formato del archivo: ZIP, TAR o TAR.GZ;
- impacto de seguridad;
- pasos mínimos de reproducción;
- un archivo sintético mínimo o instrucciones para generarlo;
- comportamiento esperado y real;
- una posible corrección, si se conoce.

Usa datos sintéticos. No adjuntes secretos reales ni archivos privados de usuarios.

## Qué ocurrirá después

El proyecto lo mantiene una sola persona, por lo que no se garantiza un SLA fijo. El informe se revisará cuando sea posible y, si el problema se confirma, se preparará una corrección y una prueba de regresión.

Coordina la divulgación pública con el mantenedor antes de publicar detalles técnicos. No se promete un programa de recompensas por vulnerabilidades.
