# Preparación de migraciones de auditoría 2

Comprobación local: 20 de octubre de 2026. No aplicada a producción en esta tarea.

- 045: crea `business_service_sections` para las secciones opcionales de Premium por negocio e idioma.
- 046: crea `business_page_aliases` para URL personalizada e historial de alias.

Ambas son aditivas: `CREATE TABLE IF NOT EXISTS`, sin UPDATE, DELETE, DROP ni cambios de columnas existentes. No requieren rellenar datos para las cuentas o negocios actuales. Sin secciones ni alias, se conserva el funcionamiento anterior. Las claves foráneas apuntan a `businesses`; requieren comprobar su motor y tipo de ID en producción antes de aplicar. El borrado en cascada solo actúa cuando se borra explícitamente el negocio correspondiente.

Validado en MariaDB local mediante `preprod/probar-reclamacion-premium.php`, en una base temporal independiente: instalación de migraciones, persistencia de secciones y alias, bloqueo entre conexiones concurrentes, reintento, una sola asociación ganadora, disputa de reclamación, reversión ante fallo de inserción y nueva comprobación del propietario al verificar el correo. La base temporal se elimina al terminar. No se envían correos ni pagos ni se alteran estadísticas reales.

Antes de publicar: esperar a que termine el despliegue independiente de cobertura/mapa; reconstruir el paquete desde su nueva base confirmada; comprobar esquema y registro de migraciones remoto, respaldo de base de datos y validación de restauración aislada; aplicar solamente las migraciones pendientes; comprobar tablas, claves y rutas comerciales. No restaurar una base antigua sobre producción como reversión de código: conservar cuentas, pagos y estadísticas posteriores. Si se revierte código, mantener estas tablas aditivas con sus datos.

Mapa y cobertura no forman parte de la aceptación de esta entrega comercial. La verificación mediante API de WhatsApp/Instagram queda aplazada por decisión del propietario; no afirmar que esos canales están verificados.

La suite HTTP envía `X-EL-Local-Regression: 1` para omitir límites exclusivamente si coinciden configuración privada `local_test=true`, variable del servidor `EL_PREPROD_LOCAL=1` y host local. Las peticiones normales y producción conservan sus límites; el encabezado por sí solo no los desactiva. No se borran contadores. Validación: `php backend/tests/local-regression-mode.php`. Las pruebas específicas del mecanismo antiabuso deben omitir ese encabezado.
