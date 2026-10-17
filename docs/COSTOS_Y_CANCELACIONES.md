# Costos y solicitudes de cancelación

Disponible desde la migración **031-costs-cancellation-requests**. Aplicada solo en preproducción durante el desarrollo; producción requiere el despliegue aprobado y su paquete de migración.

## Gastos

Administración → Dirección → Costos y cancelaciones. Registrar fecha real, categoría, importe COP, descripción y lugar opcional. Sin lugar corresponde al costo general del portal. No se admiten fechas futuras, importes negativos ni separadores de miles. Un gasto incorrecto se anula con motivo y se vuelve a registrar correctamente; el original permanece en la base. No hay eliminación de gastos en esta pantalla.

Las categorías incluyen infraestructura, publicidad/adquisición, viajes, eventos, herramientas, trabajo/honorarios y otros. El resumen de inteligencia usa la fecha del gasto y excluye anulados. No reparte automáticamente un gasto entre lugares. No es contabilidad fiscal ni conciliación bancaria; un costo no registrado no se supone igual a cero.

Para calcular ganancia y CAC todavía hacen falta cobros netos conciliados, devoluciones, costos completos, atribución de clientes nuevos y un criterio consistente de asignación del tiempo del propietario. No se presenta diferencia entre cotizaciones y costos como utilidad real.

## Solicitudes de cancelación / no renovación

El responsable autenticado entra a su portal y selecciona un negocio asociado, motivo y detalle opcional. Solo se admite una suscripción paga vigente y una membresía activa; repetir el envío conserva una única solicitud por suscripción. Administración consulta motivo y registra una respuesta **visible para el negocio**. No incluir notas privadas en esa respuesta.

Solicitud y revisión no alteran automáticamente el período pagado ni efectúan devoluciones o cancelaciones en proveedores externos. La aplicación no opera actualmente una renovación con cobro automático desde este formulario. Se debe gestionar la petición conforme a las condiciones comerciales aplicables; “atendida” registra la gestión, no prueba una cancelación efectiva. Los indicadores cuentan solicitudes recibidas, no una tasa de abandono.

## Medición de audiencia

El tablero de inversión reutiliza los servicios de medición existentes. Conteo sin cookies: páginas vistas, exposición de tarjetas y clics hacia fichas. Medición opcional con permiso: visitantes estimados, vistas de negocios y clics de contacto, con umbrales de privacidad. No sumar ambos grupos como personas únicas. Aún no hay instrumentación de búsquedas, reservas ni ventas atribuibles.

La audiencia se muestra en 30/90 días. Si el selector financiero está en 12 meses, la audiencia permanece explícitamente en 90 días; no se inventa un histórico anual con datos sujetos a retención más corta. Los importes financieros conservan el período seleccionado. La disponibilidad en producción debe validarse después del despliegue; las cifras locales son datos de prueba.

“Capacidad de abrir nuevos lugares” reemplaza “Riesgos y repetibilidad”: evaluar si otro lugar puede incorporarse con costos, plazos, adquisición y retención previsibles. El registro de costos aporta una parte de la evidencia; todavía falta medir horas y resultados comparables.
