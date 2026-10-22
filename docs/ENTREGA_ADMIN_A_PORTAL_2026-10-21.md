# Sincronización de administración y portal del cliente

Fecha: 21 de octubre de 2026. Repositorio exclusivo: nivatogithub/explorandolugares.
Origen: conversación W - Implementaciones Admin. Destino: W - Desarrollo de Portal.

## Alcance y estado

El propietario pidió revisar y pasar al worker del portal las decisiones surgidas al recorrer administración. Este documento transmite requisitos, diferencias y contratos; no certifica que el portal ya los implemente. El worker administrativo se limita a visualización y operación. Los cambios siguientes están en trabajo/admin-operacion-visual, rama feature/admin-operacion-visual, candidato ecec4e6; no desplegados ni integrados a main/preprod. No modificar este checkout desde el otro worker. Incorporar cambios por el circuito del repositorio con revisión del delta completo, sin cherry-picks a producción.

El worker del portal está trabajando en trabajo/reparacion-registro-negocios, con un circuito de vistas previas por suscripción, preparación editorial, versiones privadas, aprobación del comerciante y publicación final. No duplicar ese desarrollo ni darlo por terminado por este documento.

## Decisiones y contratos a reconciliar

| Tema | Decisión del propietario / resultado esperado | Estado y responsabilidad |
| --- | --- | --- |
| Cuenta personal | La persona valida el correo. El administrador no autoriza cuentas. Puede gestionar varios negocios. | Portal debe comprobar activación/verificación, acceso y recuperación; administración debe describir el estado sin confundirlo con aprobación del negocio. |
| Asociación | La persona registra/reclama desde su cuenta. No asignación manual rutinaria desde Cuentas. | UI y POST manual de usuarios.php retirados/bloqueados en este candidato. No cambia asociaciones del servicio del portal. |
| Negocio y documentos | Documentación acredita el negocio; su aprobación es distinta de activar la cuenta. | La pantalla administrativa todavía se llama Solicitudes de cuenta y aprueba/asocia con reglas anteriores. Pendiente coordinar servicio y presentación: no basta renombrar botones. |
| Registro/reclamación | Proceso continuo con negocio existente/nuevo, elección de Gratis o suscripción vigente, documentación aplicable, pago cuando corresponda y seguimiento. | Portal: evitar dos entradas que produzcan solicitudes paralelas, estados bloqueados o registros duplicados. Conservar IDs y solicitudes antiguas. |
| Texto libre | No exigir una justificación escrita o nota adicional en el caso ordinario. | En esta rama reviewApplication aún exige 12 caracteres para aprobar. El formulario público mantiene Información para revisar la solicitud. Reconciliar validaciones y pruebas en el worker del portal; ocultar un campo solo dejaría un bloqueo invisible. |
| Disputa | Si otra persona reclama un negocio con responsable existente, revisión manual sin sustituir automáticamente al responsable. | Mantener el caso excepcional separado. La regla anterior del propietario pedía conciliación manual en este caso; no eliminar sus controles al simplificar el caso ordinario. |
| Gratis | Ausencia de suscripción paga significa Gratis, nunca Sin suscripción. | UI aplica fallback; el portal debe garantizar consistencia de altas, asociación y persistencia sin crear duplicados. No modificar ni migrar datos productivos por esta entrega. |
| Origen | Registrado por personas, importado público y antigua alta administrativa son distintos. Una cuenta asociada no cambia la procedencia. | Negocios usa grupos excluyentes por record_origin. Importación y publicación masiva siguen limitadas a public_import, nunca owner_submitted. |
| Publicación | Pendiente de publicación / publicado / rechazado con motivo; pago confirmado no equivale a publicación. | draft se muestra pendiente. archived sigue Retirado y no puede reinterpretarse como rechazado. Falta soporte explícito del rechazo editorial del negocio y motivo; no confundir con rechazo de solicitud o comprobante. |
| Versiones y aprobación | Vista previa real según suscripción, correcciones privadas, preparación editorial, aprobación del comerciante y publicación final. | Portal ya inició este trabajo. Aquí solo existe Ver tarjeta privada con el componente real; no sustituye vista previa de página completa. No sobrescribir directamente la versión publicada desde editores. |
| Canales | Contacto personal en cuenta, canales propios en negocio y contactos sin cuenta para gestión comercial. Historial comercial no se pierde al asociar cuenta. | Requisito pendiente de modelo/presentación coordinados. No copiar contactos ni convertir datos privados de cuenta en públicos o en permiso de marketing. |
| Renovación | Administración revisa planes de pago, vencimientos y recordatorios. Gratis se consulta en Negocios. | Lista paga, fechas, filtros 7/30 días/vencidas y acceso a Campañas implementados aquí. Recordatorio automático y uso del correo del representante pendientes. El acceso actual solo busca canales comerciales habilitados; no completa esa integración. |

## Aclaraciones pendientes sin inventar reglas

- El propietario inicialmente describió el alta/reclamación Gratis como simple y sin autorización administrativa rutinaria. Después aclaró que la cuenta se verifica por correo y que el negocio se valida con documentación y queda pendiente de aprobar. Separar asociación, activación de cuenta, verificación documental y publicación para reconciliar ambos criterios. No introducir aprobación de cuenta ni asumir que toda asociación requiere soporte manual.
- El propietario mencionó tres suscripciones pagas; el catálogo y conversaciones históricas pueden tener otra cantidad. Leer configuración vigente y confirmar cualquier discrepancia antes de eliminar modalidades o fijar cantidades en formularios. No crear planes ni cambiar precios desde esta entrega.
- La bandeja antigua de reclamaciones sigue accesible desde Solicitudes de cuenta; quitarla del menú no ha retirado el endpoint público antiguo reclamar-negocio.php. Revisar enlaces y POST existentes antes de unificar o redirigir, conservando solicitudes, adjuntos y trazabilidad.

## Evidencia concreta en el candidato administrativo

- backend/public/admin/usuarios.php: asociación manual bloqueada; consulta de vínculos conservada.
- backend/public/admin/negocios.php y partials/business-operations.php: alta manual bloqueada, listado único y segmentado, sin vistas duplicadas de atención/revisión.
- backend/public/admin/reclamaciones.php y solicitudes-cuenta.php: histórico bajo la sección de solicitudes; servicio y reglas de aprobación sin cambiar.
- backend/app/BusinessAccountPortalService.php: auto_claim_on_activation y disputed_existing existentes; reviewApplication exige nota y evidencia. Verificar contra la rama más reciente del portal antes de actuar: este checkout puede quedar atrás.
- backend/public/admin/suscripciones.php: bandeja de planes pagos y vigencias; gestión directa por ID sigue disponible para compatibilidad, incluso un negocio Gratis que llega desde Negocios. No se han cambiado ciclos de vida ni pagos.
- backend/public/admin/campanas.php: selección de destinatarios por canal autorizado, filtros, formato fijo y reutilización de mensajes. No biblioteca/editor HTML personalizado todavía. La cuenta del representante no se usa automáticamente como audiencia.

## Criterios de aceptación para el portal y la integración

1. Verificar correo activa únicamente la cuenta, sin publicar ni dar por revisados documentos/pagos.
2. Registro/reclamación crea o reutiliza el negocio correcto y la relación correcta; reintentos no duplican cuenta, negocio, membresía o suscripción.
3. No se necesita un operador que escoja manualmente cuenta y negocio para completar el caso ordinario.
4. Caso ordinario no falla por falta de nota libre; documentos y decisiones mantienen su trazabilidad estructurada. Disputa conserva al responsable actual hasta su resolución.
5. Gratis se identifica sin vencimiento artificial; planes de pago conservan precio, vigencia y confirmación de pago por separado de la publicación.
6. Negocio rechazado conserva motivo visible para su responsable; no se pierde la última versión aprobada/publicada.
7. Vista previa privada representa el contenido y nivel reales; acceso por otra cuenta o visitante queda bloqueado. Cambios no aprobados no salen en sitio público ni sitemap.
8. Contactos personales permanecen privados. Contactos comerciales conservan procedencia, exclusiones y permisos aunque se asocie una cuenta.
9. Renovación usa la suscripción y destinatario correctos; no enviar mensajes por el simple hecho de revisar una ficha, ni duplicar recordatorios al reintentar.
10. Integración revisa contratos entre ambos workers, rutas antiguas, estados y migraciones MySQL/SQLite sin tocar producción ni resetear datos/estadísticas. Coordinar números de migración antes de reservarlos; este candidato administrativo no añadió migraciones.

## Validación realizada aquí

Regresión HTTP administrativa en SQLite descartable, pruebas de campañas, actualización masiva, tema administrativo y sintaxis PHP. Se probaron bloqueos de alta/asociación manual, separación de orígenes, filtros de vencimientos y reutilización de mensajes sin copiar aprobaciones. No certifica aún Apache/MariaDB de hosting ni el nuevo circuito editorial del otro worker. La sesión local 8046 usa datos sintéticos; no es producción. Antes de publicar, cumplir AGENTS y revisión/aprobación del commit exacto de preprod.
