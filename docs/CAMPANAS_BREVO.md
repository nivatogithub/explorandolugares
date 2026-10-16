# Campañas de negocios con Brevo

Implementado el 15 de octubre de 2026 en PHP/MySQL. Entrada: Administración → Campañas por correo. No utiliza las tablas de personas/contactos de la rama independiente Advance Admin ni crea negocios nuevos.

## Uso

1. En **Conexión con Brevo**, guardar una llave API, nombre, correo verificado, dirección del remitente y correo de respuesta opcional. Habilitar la conexión cuando estén listos. La configuración no ejecuta llamadas al proveedor.
2. En **Revisar correos**, consultar negocio, correo y fuente. Registrar la autorización disponible con una referencia y confirmación explícita. Los canales investigados llegan pendientes; encontrar un correo público no registra consentimiento. Una exclusión no se elimina al reimportar canales ni mediante una edición normal.
3. Crear una campaña: nombre interno, asunto, mensaje de texto y enlace HTTPS opcional. Seleccionar un correo por negocio, hasta 300. Si varios negocios comparten un correo, se conserva su asociación y se incluye una sola vez.
4. Revisar el mensaje, el remitente y los destinatarios. Aprobar esa versión. La aprobación no transfiere datos a Brevo ni envía mensajes.
5. Autorizar **Preparar en Brevo, sin enviar**: crea una carpeta y lista exclusivas de esa campaña, añade solo los correos seleccionados y prepara un borrador de marketing. La preparación continúa por pasos pequeños; recargar permite consultar el estado. Los contactos existentes solo reciben la pertenencia a la lista: no se reescriben atributos ni se eliminan bloqueos. Un correo bloqueado por Brevo detiene la preparación.
6. Revisar de nuevo y confirmar **ENVIAR**. Antes de solicitar el envío, se comprueban los permisos locales, el remitente, el contenido aprobado y el contenido, remitente y lista reales de Brevo. Una lista modificada que incluya otros correos no se envía. La aceptación de la API se registra separadamente de las entregas.
7. **Actualizar resultados** consulta el estado y estadísticas de Brevo: envíos, entregas, aperturas, clics, bajas, rebotes y quejas. Aperturas/clics no son ventas ni ingresos atribuidos.

El HTML se produce a partir de texto escapado. Incluye el marcador de baja nativo de Brevo; el proveedor gestiona la página de baja y las exclusiones. El sistema conserva los bloqueos del proveedor al preparar o comprobar la lista. No utiliza la API transaccional para campañas comerciales.

## Datos y seguridad

Migración aditiva `029-business-email-campaigns`: permisos por correo normalizado, campañas con audiencia/remitente/contenido inmutables y auditoría. Los canales y sus fuentes permanecen en `business_research_contacts`; los negocios mantienen sus IDs. Los permisos no se inventan ni se copian como consentimiento a las observaciones históricas.

La llave se guarda cifrada mediante AES-256-GCM en `private/brevo/settings.json`, con modo 0600, usando la llave de la aplicación; no se incluye en HTML, exportaciones ni repositorio. No modifica `private/config.php`. Respaldar/coordinar la llave de aplicación cuando se planifique su rotación; una rotación invalida el descifrado y exige reconfigurar Brevo. El campo vacío conserva la llave; deshabilitar la conexión conserva los datos sin permitir llamadas API.

Rutas administrativas autenticadas, expiración de sesión, CSRF, no-store y noindex. API fijada a `https://api.brevo.com/v3`, sin redirecciones. Errores del proveedor no exponen cuerpos ni credenciales. Preparaciones/envíos se reclaman mediante una actualización atómica. El estado incierto impide repetir automáticamente una escritura cuyo resultado no se conoce. Si un proceso se interrumpe con `busy=1`, requiere revisión, no desbloqueo automático.

## Límites comprobados y pendientes de operación

La modalidad gratuita de Brevo anuncia 300 envíos diarios por cuenta. El panel limita la audiencia de una campaña a 300; Brevo aplica el cupo real restante, también compartido con envíos hechos fuera del portal. No se promete entrega de toda campaña el mismo día.

La conexión real exige cuenta de Brevo, llave y remitente/dominio verificado. Se probaron contratos API mediante transporte simulado; no se creó contenido ni se enviaron correos en una cuenta real del proveedor durante el desarrollo. El primer envío real necesita una autorización concreta del propietario sobre mensaje y destinatarios.

Un estado incierto se reconcilia en Brevo con los IDs conservados en la campaña y su auditoría; no se reintenta desde el panel. Si no llegó un ID por un fallo de red, buscar la carpeta/campaña por el identificador `EL <ID>` o `Explorando Lugares · <ID>`. Cancelar en el portal impide continuar aquí, pero conserva los borradores del proveedor y no revierte correos ya iniciados. Solo usuarios autorizados deben acceder a Brevo; una edición directa de listas/campañas fuera del panel puede desalinear la revisión local. No hay reenvío automático, tareas programadas ni webhooks en esta entrega; las estadísticas se consultan manualmente. Las bajas se aplican en Brevo y se comprueban antes del envío; su importación masiva al historial local no está implementada.

## Validación

`backend/tests/business-email-campaigns.php`: autorización, deduplicación, IDs originales, mensaje escapado, baja nativa, invalidación por remitente, preparación sin envío, exclusión local/proveedor, no reactivación de contactos, lista de proveedor alterada, errores inciertos, envío único y cifrado de configuración.

`backend/tests/admin-pages.integration.py`: rutas privadas, navegación, CSRF, correos importados pendientes, borrador, escape, bloqueo sin conexión y guardado privado de configuración. Base de pruebas aislada y cero llamadas reales a Brevo.

## Fuentes oficiales consultadas

- [Crear campañas](https://developers.brevo.com/reference/create-email-campaign).
- [Enviar una campaña](https://developers.brevo.com/reference/send-email-campaign-now).
- [Crear contactos](https://developers.brevo.com/reference/create-contact).
- [Baja en campañas](https://help.brevo.com/hc/en-us/articles/209553645-Insert-a-custom-unsubscribe-link-in-your-emails).
- [Límite gratuito](https://help.brevo.com/hc/es/articles/208580669-FAQ-Cu%C3%A1les-son-los-l%C3%ADmites-del-plan-Gratis).
- [Requisitos de las listas de contactos](https://help.brevo.com/hc/en-us/articles/213405965-Build-a-legitimate-contacts-database-for-optimal-deliverability-and-compliance).
