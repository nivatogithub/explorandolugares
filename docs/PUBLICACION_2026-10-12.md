# Primera publicación informativa

Fecha: 12 de octubre de 2026. Dominio: https://explorandolugares.com/.

El propietario autorizó publicar la versión actual con el aviso del formulario no disponible. La llave fue desbloqueada por el propietario en su Terminal; no se compartió su frase de acceso.

## Resultado

- Raíz pública: `public_html/explorandolugares.com`, exclusiva del portal.
- Versión: `20261012T150545Z-c3f457`.
- 172 archivos, aproximadamente 39.6 MiB. Subida y cotejo SHA-256 descargando nuevamente cada archivo antes de activar.
- Portada, índice, formulario informativo, inglés y 42 páginas de municipio verificadas mediante HTTPS. En total 48 rutas públicas respondieron correctamente, incluyendo CSS y una fotografía.
- Formulario y administración deshabilitados: sus endpoints devuelven HTTP 503 porque no existe configuración privada. No se habilitó base de datos, no se configuraron credenciales de administración y no se recibieron solicitudes de prueba.
- Se conserva `noindex,nofollow`, de acuerdo con la publicación de la versión tal como estaba. Esto permite compartirla, sin presentar el lanzamiento SEO como terminado.

## Respaldo y separación

La raíz estaba vacía salvo `.well-known/acme-challenge` y `cgi-bin`, sin archivos. Se descargó respaldo completo antes de modificarla. El despliegue conservó la estructura de esas carpetas auxiliares.

Backend privado, configuración futura, versiones y respaldos se ubican en la carpeta exclusiva `explorandolugares/` fuera de `public_html`. Se creó una carpeta `app` vacía para preparar la migración inicial con el mecanismo de respaldo de la pareja sitio/app. La activación guardó la carpeta pública previa y ese `app` vacío como `previous-public` y `previous-app` en la versión remota.

No se modificaron carpetas ni despliegues de otros sitios. Solo se compartió el acceso técnico autorizado al hosting. La configuración real permanece en `.local/`, excluida de Git.

El diario local, manifiesto, comprobaciones HTTP y respaldo descargado están en `.local/deploy/`. No se suben a GitHub porque pueden contener rutas y configuración operativa.

## Pendientes

Confirmar la solución definitiva para recibir formularios, configurar base de datos y privacidad si se utiliza el backend preparado, revisar visualmente la composición en navegador y decidir cuándo retirar `noindex`. Esta sesión verificó contenido y acceso HTTP/HTTPS, no una inspección visual en navegador.
