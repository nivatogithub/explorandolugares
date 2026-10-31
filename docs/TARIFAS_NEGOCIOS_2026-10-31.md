# Tarifas aprobadas — 31 de octubre de 2026

Decisión del propietario en esta conversación. Sustituye las tarifas y descuentos
anteriores para nuevas cotizaciones, no para documentos financieros históricos.

| Plan | Mensual | Trimestral · 10 % | Semestral · 15 % | Anual · 20 % |
|---|---:|---:|---:|---:|
| Destacado | 25.000 | 67.500 | 127.500 | 240.000 |
| Página Comercial | 40.000 | 108.000 | 204.000 | 384.000 |
| Premium | 60.000 | 162.000 | 306.000 | 576.000 |
| Complemento de experiencia | 20.000 | 54.000 | 102.000 | 192.000 |

Importes totales por período, en COP. Descuentos frente a pagar cada mes por
separado: mensualidad × meses × (1 − descuento). El complemento sigue siendo
opcional y requiere un plan pago y una experiencia elegible del mismo lugar.

## Fuente y presentación

- Migración nueva 058 para MariaDB y SQLite, sin editar migraciones históricas.
- Catálogo vigente en `subscription_plan_prices`, `experience_operator_prices` y
  `subscription_pricing_policy` (factores 1 / 2,70 / 5,10 / 9,60).
- Selección, pago, API pública y simulador consultan esos mismos registros.
- Tabla informativa y texto de experiencias se renderizan en PHP en ES/EN/PT-BR;
  no se mantienen cifras escritas a mano en las páginas ni se corrigen con JS.
- Descuento por período visible en selección y tabla; ahorro monetario en
  simulador y pago, comparado con pagar mes a mes. Cupones separados.
- No atribuir un descuento actual a una cotización histórica con cupón ni a un
  upgrade prorrateado; su importe y saldo contratado se conservan.

## Protección de datos y entrega

La migración no modifica suscripciones, cotizaciones capturadas en solicitudes,
canjes, comprobantes, vigencias, cuentas, contenido ni estadísticas. Repetible y
transaccional mediante el mantenedor de migraciones. Los controles 022/050 dejan
de exigir importes superados, para no intentar restablecer tarifas antiguas.

Pruebas: `backend/tests/rounded-subscription-prices.php` y
`preprod/probar-tarifas-redondas.php` (MariaDB local; base temporal propia).
La publicación productiva requiere el candidato revisado y aprobado por el
circuito del repositorio. Un cambio de fuentes no cambia por sí solo producción.

## Evidencia local

- 104 comprobaciones específicas y regresiones de selección, suscripciones,
  cambios prorrateados, cupones en Pago, mantenimiento y selector de archivos.
- MariaDB en Dev1: migración dos veces, fallo inyectado, rollback y reintento
  sobre una base temporal; aplicación a Dev1 conservando otras 78 tablas.
- HTTP del paquete construido: 16 precios, descuentos, tabla inicial sin JS,
  rutas de directorio/index.html y proceso informativo en ES/EN/PT-BR.
- Navegador integrado: tabla y selector de descuentos; Página Comercial anual
  muestra 384.000 COP y ahorro del 20 % / 96.000 COP. No se realizó un pago real.
- Dev1 reservado para revisión; Dev2, preprod y producción sin modificar.
