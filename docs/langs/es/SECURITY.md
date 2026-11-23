# Política de seguridad

## Elige un idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/archive-guard/security/policy) | [English](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/en/SECURITY.md) | **Seleccionado** | [中文](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/zh/SECURITY.md) | [Français](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/fr/SECURITY.md) | [Deutsch](https://github.com/yaleksandr89/archive-guard/blob/master/docs/langs/de/SECURITY.md) |

## Versiones compatibles

Las correcciones de seguridad se publican para la línea estable actual `1.x`.

| Versión | Soporte |
|---|---|
| `1.x` | Sí |

## Qué se considera una vulnerabilidad

Los problemas de seguridad incluyen, entre otros:

- eludir la validación de rutas y escribir fuera del destino esperado;
- extraer enlaces simbólicos o duros, objetos especiales u otros elementos que deban rechazarse;
- eludir `ArchivePolicy` y aplicar datos por encima del límite configurado;
- aplicar contenido distinto del que se verificó;
- eludir reglas deterministas de nombres de plataforma o colisiones de rutas, escribiendo en un lugar inesperado;
- violar el contrato Atomic exponiendo un directorio final como publicado antes de terminar las comprobaciones;
- eludir la comprobación previa Merge o la estrategia de conflictos y sustituir objetos no previstos;
- permitir que una operación Archive Guard que cumple el protocolo evite el bloqueo cooperativo de otra operación sobre el mismo destino;
- revelar la contraseña ZIP en una excepción, infracción, resultado, estado o diagnóstico;
- fallos de análisis/lectura ZIP, TAR o TAR.GZ con impacto concreto en la seguridad;
- comprometer el código, CI, etiquetas de lanzamiento, paquetes u otros elementos de la cadena de suministro.

## Límites del modelo de seguridad

Atomic publica el resultado completo previamente preparado en un directorio nuevo. Merge prepara todo en un directorio temporal, comprueba conflictos y aplica la estrategia seleccionada. Las operaciones Archive Guard simultáneas sobre el mismo destino se coordinan mediante `.archive-guard-locks`.

La compatibilidad de cifrado ZIP depende del `ZipArchive`/`libzip` del entorno actual. La elusión de comprobaciones, modos de aplicación o protección de contraseñas debe considerarse una posible vulnerabilidad.

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

- modo de extracción: Atomic o Merge, y estrategia de conflictos cuando corresponda;
- si se utiliza contraseña ZIP, **sin enviar la contraseña real**;
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
