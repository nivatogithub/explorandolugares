# Modelo definitivo: cuentas, negocios y canales

Preparado el 15 de octubre de 2026 según la última definición del propietario. Sustituye las propuestas previas de una entidad de personas y de varios contactos/cargos por negocio. No se desplegó esta actualización en esta tarea.

## Regla de producto

La cuenta es el perfil de una persona: nombre, correo, teléfono y acceso. No se crea una tabla `people`, un registro de contactos personales ni canales personales separados.

Una cuenta puede administrar varios negocios. Un negocio reclamado tiene una sola cuenta responsable activa. Un negocio importado desde fuentes públicas puede no tener cuenta; la investigación no fabrica responsables ni credenciales.

Tener una cuenta no significa haber pagado: la suscripción mantiene su propio estado. La cuenta se asocia al negocio después de aprobar la solicitud de representación, con los controles documentales existentes.

## Canales del negocio

Los correos, teléfonos, WhatsApp, redes y sitios encontrados se asocian al negocio existente. El canal puede pertenecer a recepción, reservas o administración, incluso si utiliza Gmail o Hotmail. El proveedor del correo no determina si el canal es personal o empresarial.

Para dirigir una comunicación puede guardarse un texto opcional **A la atención de** (`recipient_label`): un área o un nombre conocido. Si no se conoce, queda vacío y la comunicación se dirige al negocio. Ese texto no crea una persona, cuenta ni permisos y no acredita una identidad o representación.

La procedencia, fecha de consulta, estado de evidencia y restricciones de uso de la investigación se mantienen. Una misma dirección o número puede tener varias evidencias de fuentes diferentes.

## Esquema mínimo

- `accounts`: mantiene el nombre declarado, correo de acceso/comunicación, autenticación y estado. Se añade únicamente `contact_phone`.
- `businesses`: conserva el único registro de cada establecimiento y sus IDs.
- `business_account_memberships`: conserva la asociación autorizada y limita a una cuenta activa por negocio; una cuenta puede estar asociada a varios negocios. Las asociaciones históricas revocadas pueden conservarse.
- `business_contacts`: nombre histórico de los canales de ficha, conservado por compatibilidad.
- `business_research_contacts`: canales investigados con fuentes y controles privados. Se añade únicamente `recipient_label`.

Los permisos técnicos actuales de las cuentas no son cargos de CRM. No se añaden contactos principales/suplentes, empleados, relaciones persona–negocio ni tablas de varios cargos.

## Migración 028

`028-accounts-business-channels` añade dos campos y la restricción de una cuenta responsable activa por negocio. No crea tablas de personas ni elimina datos existentes. La unicidad utiliza una columna generada e índice único en MySQL/MariaDB y un índice parcial equivalente en SQLite.

Si ya existen varios responsables activos para un negocio, la migración se detiene para revisión; no escoge uno ni borra asociaciones. La anterior migración propuesta `028-people-business-contacts` se retiró del código antes del despliegue registrado en esta tarea. No se ejecutan instrucciones para borrar posibles tablas personales en bases ya existentes.

## Flujo comercial

1. **Prospecto sin cuenta:** ficha del negocio y canales investigados; destinatario opcional si se conoce. La publicidad o invitación se prepara para el negocio por un canal permitido, sin inventar una persona.
2. **Responsable que se registra:** se guardan su nombre, correo y teléfono en `accounts` junto con la solicitud de representación. No obtiene permisos del negocio hasta aprobarse.
3. **Solicitud aprobada:** se asocia esa cuenta como única responsable activa del negocio. La persistencia del teléfono y la aprobación forman parte de la misma transacción.
4. **Participación y suscripción:** la cuenta identifica al responsable; la suscripción determina el producto y su estado comercial. La plataforma puede necesitar comunicarse con un cliente para servicio o renovación, además de contactar prospectos.

Los canales públicos investigados no se transforman en cuentas. Los datos de una cuenta no se convierten automáticamente en canales públicos del negocio. No se conceden permisos comerciales ni se envían comunicaciones al cargar información.

## Administración e importación

**Canales y responsable → Consultar negocios** muestra el negocio, sus canales investigados y, si existe, la cuenta responsable. La búsqueda admite negocio, destinatario, correo y teléfono. En cada registro investigado puede editarse **A la atención de**. En el bloque de cuenta puede actualizarse el teléfono del responsable; el cambio se verá desde todos sus negocios.

**Carga masiva de canales** conserva la asociación por nombre y destino. No requiere exportar IDs. El servidor resuelve el ID existente y exige coincidencia única; tolera mayúsculas, tildes, espacios y puntuación. No adivina errores o coincidencias ambiguas.

El contrato `business-channels-import-v1` usa `businesses[].name` y `businesses[].channels`; cada canal puede incluir `recipient_label`. Los paquetes anteriores `business-research-contacts-v1` siguen siendo compatibles. Reimportar sin destinatario no borra uno ya anotado ni cambia exclusiones. Un destinatario diferente exige revisión antes de sobrescribir desde una importación.

La exportación privada para actualización masiva incluye el destinatario una vez habilitada 028, para permitir editarlo por ID con vista previa y auditoría. No aparece en la ficha pública.

## Validación y límites

Pruebas locales de SQLite y HTTP: ausencia de tablas de personas, investigación sin cuentas nuevas, edición privada de destinatarios, cuenta personal con varios negocios, rechazo de otra cuenta activa para el mismo negocio, aprobación transaccional, búsqueda, compatibilidad, publicación intacta y vista de escritorio/móvil.

El paquete de Jericó mantiene 199 negocios y 285 registros de canal/fuente para 99 negocios; no crea cuentas ni cambia la respuesta pública.

La migración MySQL/MariaDB está preparada, sin aplicar ni validar en producción por esta tarea. Desplegar conjuntamente migración, servicios, pantalla y recursos. No desplegar únicamente la pantalla ni ejecutar automáticamente una eliminación de tablas de propuestas anteriores.
