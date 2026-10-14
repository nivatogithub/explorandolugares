# Advance Admin — implementación inicial

Fecha: 2026-10-14. Repositorio: nivatogithub/explorandolugares. Rama: feature/advance-admin.

No se encontró un plan previo en el repositorio consultado. Este plan materializa el alcance indicado por el propietario. Base: commit 7fde156 (administración segura y destinos). Copia de trabajo independiente; no incorpora cambios sin confirmar del Worker de municipios.

## Implementado

- CRM privado: alta/edición, búsqueda, paginación, notas, procedencia y relación prospecto/en revisión/aliado/archivado; vinculación idempotente de solicitudes por ID sin alterar leads. Importar no concede autorización de campañas.
- Establecimientos: adaptador del catálogo único `businesses`, traducciones españolas y fuentes empresariales; destinos y categorías seleccionados por sus IDs/códigos compartidos. Preserva otros idiomas, canales, medios y estados canónicos. Solo edita negocios no publicados; los publicados admiten vínculo CRM privado. No existe una segunda tabla activa de establecimientos.
- Dashboard: conteos reales, distribución por municipio y estados del correo. Aceptación por proveedor diferenciada de entrega o lectura.
- Plantillas HTML: alta/edición con versiones, asunto y variables name/municipality. Variables escapadas; vista previa en iframe de origen opaco sin scripts, formularios o recursos remotos.
- Campañas: hasta 100 IDs de contactos, deduplicación por correo, autorización explícita documentada, supresión por correo, copia inmutable de HTML/asunto/destinatarios, revisión individual y aprobación de versión por administrador.
- Baja autoservicio: enlace firmado por entrega, confirmación pública con CSRF, exclusión persistente por correo, consentimiento retirado y pendientes omitidos. Las campañas antiguas sin enlace deben recrearse y aprobarse de nuevo.
- Resend en PHP con cURL: endpoint HTTPS fijo, timeout, idempotencia por entrega/contenido y guardado de ID del proveedor. Envío manual individual con confirmación, deshabilitado por defecto. No se enviaron correos reales.
- Sesión/HTTPS/CSRF/throttle existentes; vencimiento por inactividad, consultas parametrizadas, salida escapada, control optimista de versiones para edición, auditoría transaccional de cambios/aprobación y reclamación condicional contra envíos concurrentes.
- Migraciones MySQL/MariaDB y SQLite 007–011: tablas propias de comunicación, referencia opcional del CRM al destino y tabla mínima de vínculo negocio/persona. La herramienta 008 retira la tabla redundante de la primera versión solo si está vacía. No modifica migraciones 003–006 ni el esquema editorial compartido. Ver [contrato de datos y actualización](docs/ADVANCE_ADMIN_MODELO_COMPARTIDO.md).

## Instalación para revisión

1. Revisar y respaldar una base de staging independiente. Aplicar primero el esquema existente de leads; aplicar el esquema territorial/empresarial del módulo de destinos y sus migraciones/importación existentes; aplicar 007 una sola vez y ejecutar `php backend/bin/advance-schema.php --integrar-catalogo` para 008 con el backend detenido, y aplicar las migraciones 009 de valores internos ingleses y 010 de baja autoservicio. No ejecutar el SQL 008 directamente: su herramienta bloquea la operación si hay establecimientos heredados que requieren reconciliación. Esta tarea no aplicó SQL en hosting ni producción. La migración no es reejecutable: revisar tablas parciales si falla DDL de MySQL.
2. Mantener `backend/private/config.php` fuera del directorio público como indica el backend existente. Añadir `resend` siguiendo `backend/config.example.php`; enabled=false inicialmente, public_base_url HTTPS, api_key privada y from como correo real de un dominio verificado exclusivo de Explorando Lugares.
3. Copiar app/advance y public/admin/advance mediante el proceso de empaquetado del backend. El enlace Advance Admin aparece en el panel existente; el módulo usa la misma sesión.
4. Iniciar sesión en `/admin/index.php`, abrir `/admin/advance/index.php`. Crear contactos ficticios en staging, documentar autorización, preparar plantilla y campaña, revisar cada vista previa, aprobar la versión. Habilitar Resend solo tras configurar responsable, instrucciones de baja y remitente real. El botón de envío requiere confirmación individual.

Documentación del proveedor consultada: [envío de correo](https://resend.com/docs/api-reference/emails/send-email) e [idempotencia](https://resend.com/docs/dashboard/emails/idempotency-keys). No confundir idempotencia temporal del proveedor con una autorización para reintentar: la aplicación no reenvía estados aceptada, enviando ni incierta.

## Validación y límites pendientes

Pruebas locales: `php backend/tests/advance/service.php`, `php backend/tests/advance/unsubscribe.php`, `php backend/tests/advance/shared-catalog.php`, `php backend/tests/advance/english-values.php`, `python3 backend/tests/advance/test_http.py`, `python3 backend/tests/integration.py` y lint PHP. SQLite aislada y transporte Resend simulado; ningún contacto o dato de producción.

Pendiente antes de operar campañas comerciales:

- Validar migración, claves foráneas y flujo completo en MySQL/MariaDB de staging; no hay instancia MySQL local confirmada.
- Configurar credenciales y dominio de Resend, responsable/contacto real y política de privacidad/retención. Validar un envío autorizado en staging; no se ha probado el proveedor en vivo.
- Implementar webhooks firmados de rebotes/quejas/entrega y el protocolo de baja de un clic del proveedor. La baja autoservicio por enlace firmado y confirmación POST ya está implementada y probada; no hay métricas de aperturas/clics.
- No hay colas, envío masivo ni cron. Cada envío es manual. `enviando` tras caída del proceso o `incierta` tras error/timeout requiere reconciliación humana en Resend y base; no hay botón de reintento. No suponer fallo de entrega ante timeout.
- Roles múltiples y separación de personas para aprobación/envío siguen pendientes: usa la única cuenta administrativa existente.
- Publicación/edición de negocios ya publicados, gestión de fotos/canales/traducciones adicionales desde este panel, onboarding, publicación, precios, pagos, programación/segmentación avanzada, eliminación/retención automatizada y exportaciones quedan fuera de este incremento. La búsqueda usa nombre y paginación de 25 registros.
- El historial de auditoría registra actor/acción/entidad/fecha, no un historial completo de valores anteriores; consulta restringida administrativa en base, sin visor en este incremento.
- La comprobación de consentimiento ocurre al crear y justo antes de enviar; una baja posterior al inicio de una llamada a Resend no puede cancelar esa llamada.

Los registros empresariales se integran al catálogo compartido; cualquier alta nueva permanece en borrador o archivada. Los contenidos publicados existentes solo se consultan. Todo el trabajo permanece en propuesta privada. La implementación no despliega, publica, confirma alianzas ni contacta prospectos por cuenta del agente. No se utilizaron identidad, contactos ni recursos de Paradigma Solutions.

Ver [estado y siguientes entregas](docs/ADVANCE_ADMIN_ESTADO.md) para el progreso actualizado.

La administración cuenta con [Reportes generales](docs/REPORTES_GENERALES.md), separados del resumen CRM/campañas, con registro manual de periodos y movimientos comerciales y clasificación privada de operadores. Esto no aprueba precios ni habilita cobros.
