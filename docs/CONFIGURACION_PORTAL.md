# Configuración del portal

Menú de administración → Sistema → Configuración del portal (`/admin/configuracion.php`). Solo sesión administrativa vigente, con CSRF para guardar.

Privacidad editable: responsable real, correo de atención, criterio/plazo de conservación en español e inglés. No elegir plazos por defecto, ni presentar el texto como borrado automatizado. Esta pantalla no modifica la política estática editorial ni acredita entrega de correo.

Los valores efectivos parten de `private/config.php`; si existe `private/portal-settings.json`, se superponen exclusivamente los cuatro campos permitidos de privacidad. Archivo privado0600, reemplazo atómico, validación antes de guardar y metadatos de última modificación/cuenta. Nunca se puede cambiar acceso, llaves, DSN ni contraseñas mediante este formulario. Un archivo ilegible/inválido detiene el servicio con error controlado; recuperar el JSON privado válido desde respaldo.

No versionar ni empaquetar `portal-settings.json`. El archivo sobrevive a la actualización del contenedor porque la carpeta privada es persistente. En otro PC se introducen nuevamente los datos reales del ambiente. Los cambios locales no se transfieren automáticamente al hosting; guardar los aprobados en producción después de desplegar el código.

Brevo: acceso directo en Sistema → Conexión con Brevo y desde Configuración del portal, usa el formulario existente de campañas y su archivo cifrado. Guardar conexión no envía correos. Correo de privacidad, remitente transaccional y respuesta de campañas son conceptos distintos. La configuración de transporte transaccional aún se habilita desde `account_mail` del archivo privado; el buzón local sigue activo en preproducción. No afirmar envío real por guardar una API.

Comprobación: `php backend/tests/portal-settings.php`, verificación de permisos HTTP incluida en la suite de panel, y revisión visual del candidato local.

## Activación en producción desde preproducción

El pase conserva `private/config.php` y sus credenciales. `private/production-runtime-settings.json` permite únicamente `business_accounts_enabled`, `unified_admin_only` y `account_mail` (enabled, use_brevo_connection, public_origin HTTPS). Se carga mediante `ProductionRuntimeSettings`; rechaza DSN, claves, `local_test` y campos arbitrarios. No activa accesos administrativos sin contraseña. La cuenta administradora real usa el hash administrativo existente y el rol explícito `administrator`, sin copiar cuentas locales.

La conexión Brevo se vuelve a cifrar con la clave de aplicación del hosting; copiar el cifrado local directamente no es válido. La privacidad existente en producción se conserva cuando no hay una edición local real. Los medios de pago se configuran en producción por administración: el QR, cuenta y llave de simulación no se trasladan.

El contenido autorizado de Jericó, Cartagena, Bogotá, Medellín y Santa Marta está en `content/release-places-businesses.json`; sus imágenes/procedencia se inventarían en `backend/app/content/release-media-manifest.json`. La herramienta privada `ProductionContentTransfer` remapea identificadores por lugar, imagen y negocio, conserva cuentas/pagos/CRM existentes y no importa las cinco fichas técnicas. Se ensaya dos veces en una base aislada con `preprod/probar-traslado-produccion.php` antes del pase. El paquete de datos queda privado, bajo app/content, sin un importador público permanente.
