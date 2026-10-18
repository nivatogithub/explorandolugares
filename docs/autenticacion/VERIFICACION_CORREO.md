# Activación por correo — F04

Las altas nuevas personales y las iniciadas desde registro de negocio quedan `pending_activation`. Se conserva solicitud de negocio pero no sesión autenticada. Un correo confirmado activa la cuenta; no aprueba ni publica establecimientos.

Aplicar migración `035-account-email-verifications.{mysql,sqlite}.sql` antes de habilitar altas. Registrar ruta idéntica local/producción `/cuenta/verificar-correo/` hacia `business/verify-email.php`. El servicio falla cerrado si falta almacenamiento o correo: nunca activa por una falla de envío.

Tokens aleatorios de 256 bits, hash SHA-256 almacenado, vencimiento 24 h y un uso. Cada reenvío revoca enlaces previos; máximo tres tokens por cuenta cada 15 minutos y límites adicionales por IP/correo en la ruta pública. Las respuestas de reenvío no revelan si existe el correo. Un envío fallido revoca el token.

En local solo con `EL_PREPROD_LOCAL=1`, configuración `local_test` y host loopback se escribe en `private/mailbox.json`, con bloqueo, permisos0600 y máximo50 mensajes. No se envían correos. Producción reutiliza transporte transaccional Brevo y configuración `account_mail` de recuperación, con origen HTTPS canónico configurado. No usa Host para enlaces productivos ni inscribe a campañas. Hace falta configurar remitente/proveedor antes de publicar esta funcionalidad.

Compatibilidad: cuentas históricas `active` con `email_verified_at=NULL` conservan acceso; no se falsifica fecha de verificación. Las nuevas siempre pendientes y el autenticador actual rechaza pending. Un futuro endurecimiento de legacy requiere campaña de verificación y tratamiento explícito de administradores para no bloquearlos.

Pruebas aisladas: `php backend/tests/account-email-verification.php`. Cubren rechazo de acceso pendiente, token alterado/vencido/reutilizado, revocación previa, límite de reenvío, cuenta deshabilitada, legacy sin falsa verificación y URL/carga transaccional. Falta validar integración HTTP/UI en paquete ensamblado por coordinador; este worker no actualiza contenedor ni producción.
