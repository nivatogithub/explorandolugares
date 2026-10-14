# Advance Admin — implementación inicial

Fecha: 2026-10-14. Repositorio: nivatogithub/explorandolugares. Rama: feature/advance-admin.

No se encontró un plan previo en el repositorio consultado. Este plan materializa el alcance indicado por el propietario. Base: commit 7fde156 (administración segura y destinos). Copia de trabajo independiente; no incorpora cambios sin confirmar del Worker de municipios.

## Implementado

- CRM privado: alta/edición, búsqueda, paginación, notas, procedencia y relación prospecto/en revisión/aliado/archivado; vinculación idempotente de solicitudes por ID sin alterar leads. Importar no concede autorización de campañas.
- Establecimientos: alta/edición y archivo privados, responsable por ID, municipio, categorías compartidas, descripción y evidencia. Verificación, operación y estado privado separados. No publica ni modifica el directorio del otro Worker.
- Dashboard: conteos reales, distribución por municipio y estados del correo. Aceptación por proveedor diferenciada de entrega o lectura.
- Plantillas HTML: alta/edición con versiones, asunto y variables nombre/municipio. Variables escapadas; vista previa en iframe de origen opaco sin scripts, formularios o recursos remotos.
- Campañas: hasta 100 IDs de contactos, deduplicación por correo, autorización explícita documentada, supresión por correo, copia inmutable de HTML/asunto/destinatarios, revisión individual y aprobación de versión por administrador.
- Resend en PHP con cURL: endpoint HTTPS fijo, timeout, idempotencia por entrega/contenido y guardado de ID del proveedor. Envío manual individual con confirmación, deshabilitado por defecto. No se enviaron correos reales.
- Sesión/HTTPS/CSRF/throttle existentes; vencimiento por inactividad, consultas parametrizadas, salida escapada, control optimista de versiones para edición, auditoría transaccional de cambios/aprobación y reclamación condicional contra envíos concurrentes.
- Migraciones independientes MySQL/MariaDB y SQLite `007-advance-admin.*.sql`; sin alterar tablas o migraciones de destinos.

## Instalación para revisión

1. Revisar y respaldar una base de staging independiente. Aplicar primero el esquema existente de leads; aplicar `backend/database/migrations/007-advance-admin.mysql.sql` una sola vez mediante el procedimiento administrativo de migraciones. Esta tarea no aplicó SQL en hosting ni producción. La migración no es reejecutable: revisar tablas parciales si falla DDL de MySQL.
2. Mantener `backend/private/config.php` fuera del directorio público como indica el backend existente. Añadir `resend` siguiendo `backend/config.example.php`; enabled=false inicialmente, api_key privada y from como correo real de un dominio verificado exclusivo de Explorando Lugares.
3. Copiar app/advance y public/admin/advance mediante el proceso de empaquetado del backend. El enlace Advance Admin aparece en el panel existente; el módulo usa la misma sesión.
4. Iniciar sesión en `/admin/index.php`, abrir `/admin/advance/index.php`. Crear contactos ficticios en staging, documentar autorización, preparar plantilla y campaña, revisar cada vista previa, aprobar la versión. Habilitar Resend solo tras configurar responsable, instrucciones de baja y remitente real. El botón de envío requiere confirmación individual.

Documentación del proveedor consultada: [envío de correo](https://resend.com/docs/api-reference/emails/send-email) e [idempotencia](https://resend.com/docs/dashboard/emails/idempotency-keys). No confundir idempotencia temporal del proveedor con una autorización para reintentar: la aplicación no reenvía estados aceptada, enviando ni incierta.

## Validación y límites pendientes

Pruebas locales: `php backend/tests/advance/service.php`, `python3 backend/tests/advance/test_http.py`, `python3 backend/tests/integration.py` y lint PHP. SQLite aislada y transporte Resend simulado; ningún contacto o dato de producción.

Pendiente antes de operar campañas comerciales:

- Validar migración, claves foráneas y flujo completo en MySQL/MariaDB de staging; no hay instancia MySQL local confirmada.
- Configurar credenciales y dominio de Resend, responsable/contacto real y política de privacidad/retención. Validar un envío autorizado en staging; no se ha probado el proveedor en vivo.
- Implementar baja autoservicio y webhooks firmados de rebotes/quejas/entrega. En este incremento la baja se registra manualmente marcando el correo excluido en CRM; la plantilla debe explicar el canal real para solicitarla. No hay métricas de aperturas/clics.
- No hay colas, envío masivo ni cron. Cada envío es manual. `enviando` tras caída del proceso o `incierta` tras error/timeout requiere reconciliación humana en Resend y base; no hay botón de reintento. No suponer fallo de entrega ante timeout.
- Roles múltiples y separación de personas para aprobación/envío siguen pendientes: usa la única cuenta administrativa existente.
- Integración pública de establecimientos, onboarding, publicación, precios, pagos, programación/segmentación avanzada, eliminación/retención automatizada y exportaciones quedan fuera de este incremento. La búsqueda usa nombre y paginación de 25 registros.
- El historial de auditoría registra actor/acción/entidad/fecha, no un historial completo de valores anteriores; consulta restringida administrativa en base, sin visor en este incremento.
- La comprobación de consentimiento ocurre al crear y justo antes de enviar; una baja posterior al inicio de una llamada a Resend no puede cancelar esa llamada.

Todo permanece en propuesta privada. La implementación no despliega, publica, confirma alianzas ni contacta prospectos por cuenta del agente. No se utilizaron identidad, contactos ni recursos de Paradigma Solutions.
