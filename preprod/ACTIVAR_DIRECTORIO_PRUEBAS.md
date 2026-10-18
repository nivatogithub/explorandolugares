# Directorio y suscripciones sintéticas para revisión local

Operación explícita, exclusivamente local. No se ejecuta al arrancar el ambiente,
no forma parte del paquete de hosting y no modifica producción.

1. Con el contenedor arriba, ejecutar `./preprod/cuentas-prueba.sh` para crear o
   conservar las tres cuentas y cinco establecimientos ficticios. El quinto
   cubre Básico; los cuatro anteriores conservan sus identificadores.
2. Ejecutar `python3 preprod/activar-directorio-pruebas.py`.
3. Abrir `http://127.0.0.1:8088/destinos/jerico/` y entrar con la cuenta de negocios.

No requiere reconstrucción de imagen ni actualización del paquete: los dos
scripts envían PHP directamente por entrada estándar al contenedor existente.
Las guardas comprueban CLI, EL_PREPROD_LOCAL, local_test, nombre exacto de base y
rama preprod del ejecutor. Requiere Jericó publicado y los fixtures existentes.

## Alcance y respaldo

- Publica exclusivamente importados de Jericó con origen public_import, sin
  membresías y sin historial de suscripción paga; excluye cerrados definitivamente.
- No asocia importados a la cuenta ficticia, no cambia categorías ni otros lugares.
- Publica los cinco negocios ficticios identificados por UUID reservado y dueño
  esperado. Activa sus términos sintéticos por un año sin crear pagos ni soportes.
- Cada ejecución guarda antes de mutar un JSON privado0600 de filas afectadas:
  `/srv/state/private/directorio-pruebas/antes-*.json` (en el equipo corresponde al
  volumen privado de `.local/preprod`). Si falla el respaldo no hace cambios.
- Conserva un término activo vigente al repetir: no prorroga su duración.
- Emite conteos antes/después, ruta del respaldo y URL de cada fixture. Las
  suscripciones Gratis/Básico/Tarjeta se visualizan en el directorio; solo Página
  Comercial y Premium tienen URL de ficha independiente según beneficios reales.
- Las fichas ficticias se identifican explícitamente; utilizan iniciales como
  alternativa visual y no fotografías de negocios reales.

Restauración: los respaldos son datos privados para restauración controlada, no
un importador SQL automático. No introducir sus filas en producción. Los términos
activos son datos sintéticos de preproducción y no justifican ingresos ni ventas.
