# Revisión y trazabilidad de pagos de suscripciones

14 de octubre de 2026. Ruta privada: Reportes → Suscripciones y recaudo.

La migración 013 añade `subscription_payment_claims`, vinculada a la suscripción canónica, que ya se relaciona con `businesses`. No duplica el registro contable: un reporte pendiente o rechazado no es una entrada en `subscription_payments`.

## Flujo manual

1. Registrar el periodo/plan de la suscripción del negocio.
2. Registrar un pago informado: negocio/suscripción seleccionados, referencia única, pagador reportado, importe, moneda, fecha y nota de comprobación. Estado `pending`.
3. Cotejar con el movimiento recibido en banco/proveedor. Registrar el resultado y decidir `confirmed` o `rejected`.
4. Confirmar crea un único `subscription_payments` y guarda su ID en el reporte, junto con usuario administrador, fecha y nota de revisión. Rechazar conserva el reporte sin sumar recaudo.
5. Consultar estados e historial de movimientos, pagos y devoluciones. Negocios muestra el filtro «Pagos por comprobar» y un aviso por negocio.

La revisión utiliza la cuenta administrativa existente, no múltiples identidades personales. El pagador ingresado sigue siendo un dato reportado hasta cotejarlo: el sistema no verifica identidad bancaria por sí mismo. Se conservan referencias únicas y bloqueo transaccional; confirmar dos veces o una referencia ya asentada no duplica ingresos. Ante conflicto, la revisión queda pendiente para reconciliar.

Confirmar no cambia la vigencia ni el estado de la suscripción y no verifica ni publica el negocio. Un pago puede corresponder a un periodo cancelado o histórico; se conserva como hecho contable. Pago parcial/completo y saldo pendiente requieren importe facturado/tarifa acordada, todavía no modelados.

Los movimientos que ya estaban conciliados pueden seguir registrándose directamente desde la sección existente. El historial no inventa nombre de pagador o revisor para movimientos anteriores. Devoluciones se registran por separado; la comprobación ya realizada sigue como historial.

No hay lectura de SMS, conexión bancaria, pasarela ni carga de comprobantes en este incremento. Las notas no deben contener credenciales ni números completos de cuenta. Una captura o notificación no sustituye comprobar que el abono fue recibido. Una integración futura deberá validar notificaciones auténticas del proveedor y conciliar importe, moneda, referencia y suscripción.

## Estados de los negocios: alcance actual

- Modalidad y confirmación gratuita: Negocios → Ver seguimiento.
- Plan, fechas y cancelación de periodo: Reportes → Suscripciones y recaudo.
- Borrador/archivo: editor de un negocio no publicado.
- La validación formal y publicación final de negocios requieren todavía el flujo editorial de aprobación de una versión. No se añadieron cambios automáticos a esos estados al confirmar dinero.

## Instalación y validación

Aplicar 013 después de 012 en la base de prueba respaldada, con el backend detenido. El lanzador local actualiza solo su base ficticia; no se tocaron datos productivos. MySQL/MariaDB sigue pendiente de validación real.

Pruebas de servicio: reporte pendiente sin recaudo, referencia duplicada, suscripción inexistente, evidencia de revisión obligatoria, autor, confirmación única, rechazo, historial, conflicto con asiento directo y conservación de publicación/vigencia. Prueba HTTP: acceso privado, CSRF, creación de reporte, confirmación única y rechazo de segunda confirmación. Se inspeccionó la pantalla local sin insertar pagos de demostración en el entorno del propietario.

Límites del incremento: últimos 100 registros por estado e historial; selector de las últimas 100 suscripciones. Faltan búsqueda/exportación, múltiples revisores, correcciones contables avanzadas, adjuntos privados, facturación y conciliación bancaria automatizada.
