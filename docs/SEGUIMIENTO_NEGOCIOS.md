# Seguimiento integral de negocios

Fecha: 14 de octubre de 2026. Ruta privada `admin/advance/index.php?section=establishments`.

Negocios abre un resumen, no el formulario de creación. Cada fila corresponde al mismo `businesses.id` del catálogo y reúne tres frentes independientes:

1. Comercial: participación gratuita/suscripción/sin clasificar; confirmación de participación gratuita; plan acordado, periodo efectivo, pagos registrados, vencimiento y propuesta interna.
2. Validación: estados de información y reclamación del catálogo compartido, notas de lo que falta comprobar y evidencias.
3. Publicación: estado editorial existente, notas de impedimentos y detección de negocios en borrador con un pago registrado en el periodo efectivo.

Los filtros muestran todos, gratis, gratis confirmados/sin confirmar, con suscripción, suscripciones activas, próximas a vencer, vencidas, por validar, borradores, con pago sin publicar, publicados y sin clasificar. Hay búsqueda y paginación de 25. Los conteos representan el catálogo completo, mientras el listado aplica la búsqueda.

## Datos y definiciones

La migración 012 añade `plan_name` a los periodos existentes y una tabla `business_follow_up` (una fila por negocio) para modalidad, confirmación gratuita, notas de validación, impedimentos de publicación, propuestas, siguiente acción, fecha y versión. No duplica negocios, pagos, suscripciones ni estados editoriales.

No se asigna «gratis» por carecer de un pago o periodo; requiere clasificación humana. Confirmar participación gratuita no verifica información, representación, habilitación ni alianza. Un periodo registrado como activo prevalece sobre la modalidad manual y se muestra con su vigencia calculada; para cambiar a gratis hay que revisar/cancelar ese periodo, conservando historia y pagos. El periodo efectivo es el último iniciado al corte de Colombia. Un periodo vencido no queda oculto por uno futuro aún sin iniciar.

Pago registrado significa saldo neto positivo en al menos una moneda del periodo efectivo; no mezcla monedas ni acredita pago completo. El filtro «con pago, sin publicar» incluye borradores con ese pago registrado. Archivados no se presentan como publicaciones pendientes. No se conoce saldo por pagar porque no hay una tarifa/factura aprobada contra la que conciliarlo.

«Por validar» significa información no marcada como verificada o reclamación en trámite según el modelo compartido. No sustituye un workflow formal de aprobación ni cambia esos estados. Las notas permiten explicar pendientes sin inventar motivos. Guardar seguimiento no publica, no verifica, no cobra, no envía ofertas y no crea una suscripción.

La edición del seguimiento tiene control de versión y auditoría. El plan se indica manualmente al registrar un periodo en Suscripciones y recaudo; no incorpora tarifas ni inventa niveles comerciales.

Contactos y seguimiento es el nombre de la agenda de personas, separado de Negocios. Los antiguos registros de relación `partner` se conservan como relación histórica por revisar; no se crea un módulo de alianzas ni se confirma una colaboración por registrar un contacto.

## Revisión y límites

Aplicar 012 después de 011 en una base de prueba con respaldo y backend detenido. No se ejecutó en producción. El lanzador local actualiza su copia de demostración sin añadir modalidades, pagos o planes ficticios al negocio existente.

Pruebas: `php backend/tests/advance/business-follow-up.php`, regresión de reportes, prueba HTTP de administración y lint. Validan clasificación explícita, confirmación separada, cambio concurrente, fecha inválida, plan/pago/publicación, fecha de vencimiento inclusiva, búsqueda parametrizada y conservación de publicación.

Pendientes: catálogo aprobado de planes/beneficios, facturas y saldo exacto, validación formal por versión, gestión de periodos futuros desde esta vista, filtros adicionales por destino/responsable y verificación en MySQL/MariaDB. Las propuestas comerciales se mantienen como notas privadas para preparar revisión humana.
