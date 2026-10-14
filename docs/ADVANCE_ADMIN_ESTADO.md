# Estado de Advance Admin

Actualizado el 14 de octubre de 2026. Repositorio `nivatogithub/explorandolugares`, rama `feature/advance-admin`. Sin despliegue ni envíos reales.

| Área | Estado verificado | Siguiente paso |
| --- | --- | --- |
| CRM | Alta/edición, solicitudes vinculadas, notas, consentimiento y exclusión | Mejorar selección/segmentación de contactos |
| Dashboard | Datos reales de CRM, catálogo y correo | Confirmaciones de entrega vía webhook |
| Establecimientos | Catálogo único businesses; conserva IDs, idiomas, canales, fuentes y medios | Flujo editorial para edición pública; coordinación con Worker de destinos |
| Plantillas | HTML, variables inglesas, versiones y vista previa aislada | Plantillas de comunicación revisadas con responsable real |
| Campañas | Snapshot, destinatarios deduplicados, aprobación de versión y envío individual | Mejorar selección visual y cancelación; colas después de reglas operativas |
| Resend | Adaptador PHP probado con transporte simulado | Webhooks firmados; configurar dominio/credenciales y probar un envío autorizado |
| Baja autoservicio | Implementada y probada por HTTP; enlace firmado, confirmación POST y exclusión persistente | Desplegar en staging y comprobar URL pública HTTPS |
| MySQL/MariaDB | Migraciones preparadas; pruebas actuales con SQLite | Validar instalación y actualizaciones en una instancia aislada MySQL/MariaDB |
| Seguridad | Sesión existente, CSRF, versiones y auditoría | Roles múltiples, visor de auditoría y proceso de recuperación |

## Incremento: baja autoservicio

La migración 010 agrega `unsubscribe_url` al snapshot de cada entrega y `advance_email_suppressions` para conservar la exclusión por correo aunque aparezcan nuevos contactos. No duplica entidades del catálogo territorial/empresarial.

Configurar `public_base_url` con el origen HTTPS y prefijo público real del sitio, además de `app_key` estable. El enlace se genera desde esa configuración, nunca desde el encabezado Host. Cada entrega nueva incorpora su propio enlace firmado en el HTML antes de la revisión y aprobación. No contiene el correo en la URL ni muestra datos del CRM.

`GET /api/unsubscribe.php?token=...` muestra una confirmación sin efectos. La confirmación POST usa token firmado y CSRF de sesión; no requiere iniciar sesión administrativa. Registra una exclusión persistente por correo, retira el consentimiento y marca los pendientes como `skipped`. Repetir la baja no duplica auditoría ni altera entregas aceptadas o inciertas. El envío y la creación de campañas comprueban la exclusión persistente. La edición del CRM no puede reactivar un correo dado de baja; un futuro flujo de nueva autorización deberá definirse por separado.

No implementa todavía el protocolo de baja de un clic de los proveedores. Un envío que ya comenzó puede llegar; una baja no cancela una llamada a Resend en curso. El enlace permanece válido mientras se conserve la entrega y no cambie `app_key`: planificar cualquier rotación para no invalidar enlaces antiguos.

Las campañas previas que no tienen enlace no se reescriben después de aprobarse. Para enviarlas hay que crear una nueva campaña con enlace y volver a revisar/aprobar. Aplicar 010 en staging con respaldo y backend detenido antes de usar el código nuevo. La migración no es reejecutable; revisar esquema si hubo una ejecución parcial de DDL MySQL.

## Pruebas de este incremento

- `php backend/tests/advance/unsubscribe.php`: firmas alteradas, otra clave, GET lógico sin modificación, múltiples contactos, idempotencia, exclusión persistente, bloqueo de reactivación y preservación de snapshots.
- `python3 backend/tests/advance/test_http.py`: acceso privado, CSRF, catálogo único, creación/aprobación, envío deshabilitado y baja pública confirmada sin sesión administrativa.
- Pruebas de servicio, catálogo compartido, migración de valores ingleses y lint PHP.

## Orden de continuación

1. Webhooks de Resend con firmas, deduplicación de eventos y tratamiento de rebotes/quejas sin reenvíos automáticos.
2. Instalación y migraciones reproducibles en MySQL/MariaDB aislado.
3. Selección de contactos/plantillas desde el panel y visor de auditoría.
4. Staging: configurar datos reales, revisar plantillas y autorizar un envío de prueba. El despliegue y los envíos reales necesitan la autorización concreta del propietario.

## Entorno local de revisión

El propietario autorizó usar su Mac como entorno de pruebas. Se preparó un panel persistente, con datos ficticios y envíos deshabilitados, accesible únicamente desde el equipo. Ver [acceso y reinicio](ADVANCE_ADMIN_LOCAL.md). Este entorno usa SQLite; la validación específica de MySQL/MariaDB sigue pendiente.
