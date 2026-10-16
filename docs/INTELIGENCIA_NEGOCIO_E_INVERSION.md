# Inteligencia de negocio e inversión

El panel privado `/admin/inversionistas` es una vista de lectura para seguimiento comercial, financiero, operativo y estratégico. Su regla editorial es que cada cifra debe identificar la tabla y el estado que la originan. Una métrica sin fuente no se presenta como cero ni como estimación.

## Indicadores disponibles en la primera entrega

- **Destinos:** total publicado y en borrador; destinos creados en el período.
- **Negocios:** fichas totales y publicadas, verificadas/activas, reclamadas y activas, y negocios publicados con suscripción paga vigente.
- **Interés comercial:** formularios de contacto, solicitudes de cuenta empresarial, solicitudes anteriores de representación y cuentas empresariales creadas. Se cuentan por separado: no son necesariamente personas u oportunidades únicas.
- **Suscripciones:** conteo e importe cotizado de filas cuya revisión de pago fue marcada por administración en `business_subscriptions.reviewed_at`. Es una señal operativa, no libro contable ni confirmación de conciliación bancaria.
- **Retención por renovación:** negocios cuya suscripción paga vence en el período y que tienen una nueva suscripción paga publicada antes o en esa fecha, enlazada en `renewal_of_subscription_id`. Reactivaciones posteriores al vencimiento se cuentan por separado. La migración `025-subscription-renewal-history` conserva la fecha original y enlaza el período nuevo; no reconstruye fechas históricas que antes fueron reemplazadas.
- **Tendencia:** destinos, negocios y contactos nuevos por mes durante los últimos doce meses; valor de suscripciones marcado como revisado por mes.
- **Comparación temporal:** 30, 90 o 365 días contra el período inmediatamente anterior del mismo tamaño.

Los contadores de publicación describen el estado actual. “Creados en el período” usa `created_at`; no equivale a publicaciones realizadas en ese intervalo, porque aún no existe una fecha de publicación para destinos y negocios.

## Límites conocidos; no presentar como resultados

Todavía no existe instrumentación de visitantes, visitas únicas, búsquedas o clics; conciliación contable de pagos, devoluciones o comisiones; registro de costos de adquisición, operación o incorporación por destino; medición de resultados para cada negocio; ni registro de cancelaciones con causa. El panel puede calcular retención a partir de vencimientos y renovaciones enlazadas desde la migración 025, pero no atribuir causas de cancelación ni reconstruir períodos previos a esa migración.

Rentabilidad, CAC, LTV, punto de equilibrio, costos por municipio y proyecciones requieren primero implementar fuentes de datos y acordar sus definiciones. Las proyecciones futuras deben separar claramente los datos observados de los supuestos editables, incluir quién los fijó y la fecha de actualización. No crear una puntuación de inversión o semáforo subjetivo.

## Extensión futura

Agregar fuentes mediante tablas de hechos con moneda, fecha efectiva, origen, responsable, estado de verificación y relación opcional con destino, negocio, cuenta o campaña. Mantener los eventos crudos inmutables cuando representen pagos, costos, visitas o cancelaciones y construir los indicadores a partir de esos registros. Minimizar datos personales y conservar la analítica de visitantes con controles de privacidad.
