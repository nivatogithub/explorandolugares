# Reportes generales de Explorando Lugares

Fecha: 14 de octubre de 2026. Ruta privada `/admin/reports.php`. Rama `feature/advance-admin`.

## Separación de responsabilidades

Reportes generales pertenece a la administración del negocio y se enlaza desde el panel principal y Advance Admin. La entrada anterior se llama ahora «Resumen CRM y campañas»; no representa el estado completo del portal.

El reporte general contiene:

- Destinos y negocios registrados/publicados/en borrador/archivados; lugares de interés por estado.
- Bandeja de trabajo con solicitudes nuevas, contactos en revisión, negocios en borrador y campañas por revisar.
- Preparación de destinos: textos españoles básicos e imagen principal, estado registrado y negocios publicados por destino; enlace a la edición existente.
- Negocios agrupados por destino y categoría usando el catálogo único y traducciones existentes.
- Operadores turísticos y operadores de experiencias clasificados explícitamente. Un negocio puede tener ambas capacidades sin duplicarse.
- Suscripciones vigentes, próximas a vencer en 30 días, vencidas y lista de periodos a renovar.
- Recaudo registrado, devoluciones y neto separados por moneda, sin conversiones ni sumas entre monedas.
- Área de tráfico que identifica que aún no hay una fuente de analítica conectada. No muestra visitas ficticias ni confunde leads con visitas.

La publicación reflejada es el estado de la base, no una comprobación HTTP de la web pública. La preparación territorial no abarca todos los requisitos editoriales y no publica nada. Un borrador se presenta como trabajo por revisar, no como una aprobación formal solicitada; el flujo centralizado de solicitudes de aprobación sigue pendiente.

## Nuevos datos propios, en inglés

La migración 011 añade exclusivamente conceptos que no existían en el esquema revisado:

- `business_subscriptions`: negocio por FK, referencia única de periodo, fechas y estado `active`/`cancelled`.
- `subscription_payments`: suscripción por FK, comprobante único, movimiento `payment`/`refund`, importe entero en unidades menores, moneda y fecha recibida.
- `business_capabilities`: relación negocio/capacidad con clave compuesta. Valores iniciales `tour_operator` y `experience_operator`, independientes de la categoría principal.

No crea otra entidad de destino, negocio, categoría, contacto o imagen. Clasificar una capacidad es una tarea interna y no acredita requisitos legales ni cambia el contenido público. Añadir o retirar una clasificación está auditado.

## Cálculos y registro manual

El corte usa fecha de Colombia (`America/Bogota`). El día final está incluido: un periodo que termina hoy sigue vigente y próximo a vencer. La próxima ventana incluye hasta 30 días. Un periodo iniciado en el futuro no se cuenta como activo todavía.

Para que una renovación no produzca una falsa suscripción vencida, se toma el último periodo iniciado de cada negocio; los periodos anteriores permanecen como historial y sus pagos siguen incluidos. Se rechazan periodos activos solapados. Cancelar conserva pagos e historial y no efectúa una devolución. Las cancelaciones se excluyen de activos y vencidos. El alcance inicial representa una suscripción vigente por negocio; múltiples productos simultáneos requieren ampliar el modelo acordado.

El panel permite registrar periodos, pagos realmente recibidos y devoluciones realizadas con referencia única y confirmación humana. No inicia cobros, no configura tarifas ni integra bancos. Los importes usan dos decimales, sin cálculo en coma flotante; monedas iniciales COP, USD y EUR. No admite recaudos con fecha futura ni devoluciones superiores al saldo registrado de la suscripción/moneda. MySQL bloquea el padre para serializar registros concurrentes del mismo negocio/suscripción.

Sin registros comerciales se muestran guiones y «sin registros»; no se afirma que el recaudo real sea cero. Las cifras dependen de la integridad del registro manual y no constituyen conciliación bancaria. Todavía faltan la asociación de cada devolución con su comprobante original y correcciones contables más completas. Los pagos se conservan: no hay borrado desde el panel.

## Instalación y pruebas

Aplicar `011-admin-reports.mysql.sql` en una base de prueba respaldada y con el backend detenido; no es reejecutable. No se ha aplicado en producción ni validado en MySQL/MariaDB real.

El entorno local del Mac se actualiza con `python3 scripts/preview-advance-admin.py --prepare-only`, conservando sus datos. No se insertan suscripciones, pagos u operadores ficticios en el panel de revisión. Las pruebas aisladas sí usan movimientos ficticios.

Validación: `php backend/tests/advance/reports.php`, pruebas HTTP privadas y lint PHP. Cubre ausencia de registros, fecha final inclusiva, renovaciones sin doble conteo, periodos solapados, duplicación de comprobantes, centavos, monedas separadas, devoluciones, cancelación sin alterar pagos, capacidades múltiples y preservación de publicación.

## Pendientes expresados por el propietario

- Conectar estadísticas del sitio: visitas, páginas, búsquedas, clics a negocios y conversiones; elegir fuente e instrumentar con su configuración de privacidad.
- Bandeja de aprobaciones formales ligada a entidad y versión, conservando la aprobación de campañas existente.
- Motivos editoriales de bloqueo y tareas de implementación explícitas. Las comprobaciones actuales solo señalan faltantes observables; no inventan por qué no se publicó.
- Filtros por periodo/destino, exportaciones y gestión más completa del ciclo comercial.
- Validación MySQL/MariaDB, datos reales y conciliación antes de interpretar el reporte como estado real del negocio.

## Navegación por pestañas

Cada sección se muestra por separado mediante `reports.php?tab=overview|work|billing|catalog|traffic`. La pestaña activa queda marcada y su URL permite actualizar o compartir la vista local sin perderla. Guardar datos comerciales vuelve a Suscripciones y recaudo; clasificar operadores vuelve a Negocios por destino. No se usa desplazamiento por anclas para cambiar de reporte.
