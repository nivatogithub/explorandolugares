# Fuente única, integración y limpieza de destinos

Regla acordada con el propietario el 20 de octubre de 2026. Complementa las reglas operativas de despliegue; no autoriza saltarse validación ni modificar datos vivos.

## Una versión aprobada por destino

- Cada destino tiene una rama `lugar/CO/...` y una entrega canónica `crecimiento-colombia/entregas/<slug>/version-aprobada/contenido.json` con traducciones, orden, estados, medios y SHA-256.
- Los maestros y referencias conservan su procedencia fuera del paquete público. Solo los derivados finales usados se despliegan. El historial de Git conserva versiones retiradas; no deben seguir como alternativas activas.
- Una sola fuente produce paquete, importación y presentación. No mantener asignaciones paralelas en base de datos, mapas JSON y scripts de cada ciudad. Durante la transición, resolver esos overrides explícitamente y comparar antes de retirarlos.
- Las imágenes finales se identifican por hash. Un cambio crea una ruta nueva y actualiza su referencia en la misma entrega; nunca reutilizar un nombre mutable para bytes diferentes.
- Las aprobaciones registran el hash de contenido y medios. No heredar una aprobación para una versión posterior.

## Estilos y ramas

- Los estilos, componentes, filtros y traducciones comunes pertenecen al portal; las ramas de destino no mantienen copias divergentes del renderizador.
- Antes de integrar una rama de destino, revisar su diff completo y separar cambios históricos de estilos o funcionalidad ajena. No fusionar decenas de commits antiguos sin revisión ni resolver con ours/theirs global.
- El empaquetador debe consumir la entrega canónica y fallar si falta contenido, traducción, imagen o hash. Un build con exit0 que usa la semilla vieja no pasa.
- Integrar a preprod, reconstruir desde commit limpio, ensayar migración acotada y validar páginas ES/EN/PT, orden, recursos y capturas. Comparar con la versión aprobada; luego desplegar y validar producción antes de main.

## Limpieza trazable

1. Inventariar medios del contenido público, borradores pendientes, negocios, CSS/JS/PHP, mapas, manifiestos y base remota. Las imágenes de negocios y destinos ajenos al lote se preservan.
2. Clasificar: usado, referencia/maestro conservado fuera del público, obsoleto confirmado o sin evidencia suficiente. No equiparar “no aparece en una página” con “sin referencias”.
3. Retirar primero las referencias obsoletas del candidato. No borrar archivos mientras exista un override que pueda recuperarlos.
4. Probar el candidato reconstruido y generar lista exacta de retiros con ruta/hash/motivo. La ausencia del manifiesto nuevo por sí sola no prueba desuso en datos vivos.
5. Limpiar preprod y producción solo con esa lista contrastada. No eliminar directorios enteros, archivos no administrados, configuración, uploads de terceros, respaldos o estadísticas. No borrar `sources/` sincronizadas.
6. Comprobar cero imágenes rotas, cero referencias a retirados y paridad con manifiesto publicado. Registrar cuántos archivos/bytes se retiraron. No declarar limpieza terminada si solo se hizo un informe.

## Puertas de cierre

- Paquete por destino reproduce contenido e imágenes aprobadas.
- No mezcla de estilos ni asignaciones visuales ocultas.
- No dependencias en `.local`, rutas temporales o archivos de otro worker.
- Ninguna tabla de cuentas, estadísticas, pagos o suscripciones se importa desde preprod.
- Cambios editoriales de producción se reconcilian por slug/public_id, sin copiar IDs locales.
- Sin pruebas de reconstrucción y referencias no hay limpieza final ni despliegue.
