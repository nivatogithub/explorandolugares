# Reparación visual y operativa de la administración

Fecha: 20 de octubre de 2026. Rama: `feature/admin-operacion-visual`. Base: main `bc5fc07`.

## Alcance implementado

- Solicitudes: selectores de estado nativos, Guardar estado con contexto accesible, avisos reales de guardado/sin cambios, 404 para ID inexistente, protección frente a una modificación entre lectura y escritura, filtros conservados y página ajustada al rango válido. Si una fila sale del filtro, se informa. Guardar no envía correo.
- Detalle privado de participación o solicitud de cuenta: `/admin/solicitud-detalle.php?tipo=participation|account&id=...`. Solo consulta, validación de tipo/ID, sesión administrativa, 404/405/403, salida escapada. Se muestran datos conservados, referencias/documentos protegidos y plan solicitado; se separan ficha actual, membresía activa y suscripción registrada. No reconstruye evidencia histórica ni verifica titularidad.
- Listados por tarea sobre los mismos IDs: Catálogo y cobertura, Atención a comercios, Revisión y pendientes. Búsqueda, lugar, categorías, publicación, origen registrado, plan registrado, verificación y cuenta asociada; conteos distintos, paginación, filtros persistentes y limpieza explícita.
- Atención incluye fichas con solicitudes de cuenta documentadas, y solicitudes pendientes sin ficha. Es una definición explícita y acotada: no inventa vínculos con leads ni relaciones comerciales históricas que no estén registradas en esas solicitudes. Cuenta asociada se filtra aparte.
- Revisión general incluye borradores de cualquier origen y fichas con solicitudes pendientes, además de solicitudes pendientes sin ficha. Informa el siguiente paso; no afirma elegibilidad completa para publicar. Los validadores existentes mantienen la decisión de cada operación.
- Las solicitudes sin ficha se incluyen sin filtros que necesitan ficha: publicación, origen, verificación, plan registrado y cuenta asociada. No se confunde la cuenta autora con una membresía sobre una ficha inexistente ni un plan solicitado con uno contratado.
- El lote existente conserva sus reglas y servicio; pasa a llamarse «Publicar importaciones Gratis sin representante». Se unificó el predicado de listado y selección masiva, incluyendo categorías secundarias y duplicados. La nueva revisión general no amplía la publicación masiva.
- Recursos CSS compartidos y JavaScript modificado con versiones por contenido. La selección de filas de solicitudes usa un archivo externo compatible con la CSP vigente, sin habilitar scripts inline. Menú compacto desplegable en móvil; conserva navegación completa si JavaScript no está disponible.

## Fuera de alcance

No se modificaron `BusinessAccountPortalService`, `AutomaticBusinessClaim`, `BusinessSubscriptionAdminService`, el registro público ni las reglas de creación, asociación, reclamación, documentos, Gratis y publicación. No se añadieron tablas, migraciones, respuestas por correo ni vínculos nuevos. No se consultó el caso real ni se repararon datos. Producción, configuración privada y estadísticas permanecen sin tocar.

## Validación

- `php backend/tests/admin-business-operations.php`: filtros compartidos (categoría secundaria/duplicados), filas únicas por negocio, pendientes de distintos orígenes, separación cuenta/plan y funciones de presentación sin mutación.
- `python3 backend/tests/admin-pages.integration.py`: regresión HTTP administrativa completa, cuentas y suscripciones existentes; nuevos detalles protegidos, solicitudes sin ficha, borradores owner_submitted y snapshots de tablas para comprobar que consultar no modifica asociaciones/negocios/suscripciones. SQLite aislada; transporte externo bloqueado.
- `python3 backend/tests/integration.py`: solicitudes, CSRF, ID ausente, aviso de salida del filtro, página válida, escape de detalle y acceso no autorizado.
- `php backend/tests/business-crm.php`, `business-account-portal.php`, `business-subscriptions.php`: regresiones existentes sin cambiar servicios de dominio.
- `python3 backend/tests/admin-theme.py`, sintaxis PHP de páginas administrativas y auxiliares nuevos; sintaxis de selectores y scripts administrativos; `git diff --check`.
- Paquete completo preparado para hosting en `.local/revision-admin-operativa`. Revisión real en el navegador del paquete local, con datos ficticios: selector nativo, alineación al extremo de tabla, filtros y menú compacto en viewport móvil, restaurando luego el tamaño original. Capturas fuera del código, en `entregables/admin-operacion-visual-2026-10-20` del proyecto local.

## Revisión local

Servidor de revisión aislado en `http://127.0.0.1:8046/admin/index.php`. Es auxiliar; no sustituye preprod. La base se copió de fixtures de la regresión, nunca de producción. Registro público deshabilitado y funciones de transporte externas bloqueadas. Configuración y datos quedan ignorados por Git.

## Pendiente antes de publicar

Integrar el candidato en preprod conforme a AGENTS, reconciliar main, revisar el delta completo del paquete y validar allí Apache/MariaDB. Completar comprobación cruzada Safari/Firefox y tecnologías de asistencia; la revisión de navegador realizada no certifica todos los navegadores. Revisar visualmente con el propietario y obtener aprobación del commit exacto antes del despliegue. Ningún cambio a los flujos de negocio se incluye en este candidato.

## Ajustes del 21 de octubre de 2026

- Gratis es la modalidad por defecto en presentación y filtros aunque no haya una fila histórica explícita de suscripción. No se crean ni migran filas desde estas pantallas; garantizar la fila física en altas pertenece al flujo de negocio, fuera de este worker.
- Se muestra Pendiente de publicación para `draft`. `archived` conserva Retirado: no se transforma en Rechazado sin una decisión editorial registrada. El rechazo editorial de negocio con motivo obligatorio queda pendiente del soporte de flujo correspondiente. El detalle sí muestra la nota registrada cuando una solicitud de cuenta está rechazada; no confundir ese rechazo con el del negocio.
- Una ficha `public_import` sin membresía se describe como importada por el portal sin responsable. Una creación administrativa se identifica como tal. Una solicitud aprobada o una ficha `owner_submitted` sin cuenta activa se marca para revisar la asociación; no se inventa que provino de una importación.
- Una sola entrada Negocios en el menú. Dentro: Listado de negocios, Solicitudes y seguimiento y las herramientas existentes de crear/importar/publicar importaciones.
- Ver tarjeta abre una vista privada del negocio real, utilizando el mismo componente y permisos de contacto del directorio. No modifica el plan ni publica. Editar datos y Gestionar plan y publicación describen los destinos administrativos de los otros botones. La vista previa privada de la página completa sigue pendiente y se informa en pantalla; no se presenta contenido ficticio como si fuera el negocio real.
- Regresión administrativa HTTP aprobada, incluida vista de tarjeta Gratis, acceso privado y 404. No se accedió al navegador bloqueado ni a producción.

- Se elimina la pestaña duplicada Pendientes de revisión y el título Catálogo y cobertura. El listado único permite filtrar la publicación; los enlaces antiguos de pendientes abren ese listado filtrado. Las solicitudes sin ficha permanecen en Solicitudes y seguimiento.

- Se retira Crear negocio y se bloquean las altas manuales por POST en administración. El enlace anterior informa la regla y abre el listado; editar fichas existentes continúa disponible. No se alteran el registro empresarial ni las importaciones del módulo de destinos; quedan separados de esta corrección.

- Segmentación obligatoria por origen en listado y seguimiento: registrados por personas, importados del portal, altas administrativas anteriores y origen por revisar. No hay una opción que mezcle los grupos; filtros, limpieza y paginación conservan el grupo. Tener cuenta no cambia el origen de una ficha importada. Las solicitudes sin ficha se muestran en el grupo de registros por personas. La importación y publicación masiva siguen restringidas a fuentes públicas, sin ampliar su alcance a registros personales. Sin migración ni reclasificación de datos.

- Se elimina la vista duplicada Atención / Solicitudes y seguimiento. Los enlaces antiguos abren el listado único, conservando la segmentación por origen. Las solicitudes sin ficha se consultan exclusivamente en Solicitudes de cuenta, enlazada desde Negocios. Se retira la tabla combinada de negocios y solicitudes; no cambia ningún proceso de aprobación. Este ajuste sustituye las referencias anteriores a una vista de seguimiento separada.

- Confirmado y sincronizado el menú lateral local: Revisión y pendientes no aparece como acceso principal. La revisión/publicación de importaciones queda únicamente dentro de Negocios.

- Exportar / actualizar pasa a Actualización masiva de datos. La explicación distingue exportación, edición externa, comparación y confirmación. Selector JSON con el componente existente en español, espaciado, foco visible y nombre del archivo. Sin cambios en la actualización de datos.

- Campañas: acceso Negocios destinatarios, filtros por lugar, negocio/correo y disponibilidad, selección explícita al crear. Brevo se configura desde Sistema, sin pestaña duplicada de campañas. Selectores nativos. Plantillas y mensajes explica el formato fijo actual y permite reutilizar el contenido de una campaña, sin copiar destinatarios ni aprobaciones. Biblioteca/editor de HTML personalizado pendiente, sin cambios en envíos ni permisos.

- Reclamos de negocio se retira del menú principal. La bandeja antigua queda enlazada desde Solicitudes de cuenta, sin borrar solicitudes ni alterar decisiones. Ambas rutas marcan la misma sección del menú.
- Requisito del propietario para el worker del registro: proceso único de reclamar/registrar, elegir Gratis o una de las suscripciones vigentes, aportar documentación y continuar con pago cuando corresponda. Sin justificación escrita adicional. Pendiente de implementar en el flujo público y sus validaciones; esta corrección administrativa no elimina requisitos del servicio actual ni cambia asociaciones, documentos o pagos. Mantener diferenciadas las disputas sobre un negocio con responsable existente, según el criterio previo del propietario de resolver esos casos manualmente.

- Cuentas: eliminada la asignación manual de negocios y bloqueado el POST de vinculación con 403. Se conserva consulta de asociaciones existentes y herramientas de acceso. No cambia el servicio de registro/reclamación ni sus validaciones o asociaciones automáticas. Regresión comprueba que intentar vincular desde el panel no modifica membresías.

- Suscripciones muestra solo planes de pago, ordenados por vencimiento, con días restantes y filtros de renovación a 7/30 días o vencidas. Gratis se conserva en Negocios. Preparar recordatorio lleva a la composición de campaña filtrada por negocio: no envía ni programa correos y depende de los canales habilitados existentes. Automatización y conexión con correo del representante siguen pendientes. Sin cambios de planes, pagos o vigencias.
