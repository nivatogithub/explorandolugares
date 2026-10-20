# Reparación del proceso de registro y administración

Rama de trabajo: `reparacion/registro-negocios`.
Base observada: `origin/main`, `bc5fc07fa0ed58bd642632cb6795df672746d9b8`, 20 de octubre de 2026.
Este documento no demuestra paridad de esa base con producción.

## Alcance construido del primer candidato (E1–E3)

- Guardado de estados con resultado real, filtros conservados, página acotada y selectores cortos nativos. Guardar no envía correo.
- Recursos administrativos versionados por su contenido desde el paquete.
- Detalle privado por tipo/ID para consultas y solicitudes de cuenta; evidencia enviada separada de ficha actual; documentos e imágenes mediante rutas protegidas.
- Vínculo informativo explícito entre consulta y ficha, confirmación por ID, actor y fecha. Relación muchos-a-muchos: una consulta puede tratar varios negocios y una ficha recibir varias consultas. No crea permisos ni certifica representación.
- Atención a comercios según solicitudes, vínculos confirmados o comprobantes; catálogo conserva todas las fichas; revisión general incluye borradores de todos los orígenes y solicitudes sin ficha.
- Bloqueos de publicación consultan las reglas usadas por la propia acción, sin duplicarlas.
- Predicado compartido de categorías y demás filtros para listado, conteo, paginación y todos los filtrados; incluye categorías secundarias.
- Lote restringido a importaciones Gratis sin representante. Confirmación de sesión ligada a versiones; versión comprobada dentro de la transacción de publicación.

## Datos

La migración aditiva **047-lead-business-links** añade únicamente `lead_business_links`: claves foráneas, pareja única, índice por negocio, actor y fecha. No hace backfill por nombre/correo y no cambia fichas, cuentas, suscripciones ni estadísticas. Variantes SQLite/MySQL registradas en mantenimiento.

No se consultó el caso real Casa Hotel Ruta ni se repararon datos privados. Su causa continúa **no comprobada**. No se modificó producción, la base persistente de preprod ni la propuesta de navegación.

## Verificación y límites

Pruebas con SQLite desechable: estados guardado/sin cambios/ausente, vínculos idempotentes, conservación de actor y relaciones previas, rechazo de IDs ausentes, filtros por categoría secundaria; regresión HTTP administrativa ampliada con autorización, 404, CSRF, escape y ausencia de publicación/asignación por vincular; regresiones de suscripción y prorrateo.

Antes de aprobar E6 faltan revisión visual móvil/teclado del paquete final, ensayo aislado de MariaDB y revisión de concurrencia entre procesos. No declarar este desarrollo listo para producción por el solo paso de SQLite. No se autorizó merge a preprod ni despliegue.

## Entregas siguientes del plan

- E4: alta/reclamación Gratis con documento privado y declaración, asociación inicial automática, duplicados bajo concurrencia y disputa solo si existe otro responsable. No se ha modificado todavía ese flujo público. Precisar publicación inmediata del Gratis y política documental antes de cerrar el recorrido.
- E5: borrador de respuesta individual, vista previa/versionado, confirmación explícita y transporte idempotente simulado. No está implementado ni se enviaron mensajes reales.
- E3/E6 por cerrar: contexto de retorno de todas las acciones y validación visual del detalle (la evidencia estructurada sigue mostrándose en formato técnico). Los filtros de cuenta, suscripción, origen, categoría, lugar y verificación ya están disponibles en las colas; el catálogo también separa cuenta y suscripción.

El plan original permanece como referencia sin editar. La rama no debe presentarse como ejecución completa de E0–E6.
