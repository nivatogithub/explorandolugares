# Consolidación de cinco destinos: capas visuales

Auditoría estática del release local y snapshots del 20 de octubre. Solo lectura. HTTP8088 no accesible desde este worker: no representa validación DOM en vivo. No modificar ni eliminar activos a partir de este informe sin comparación del navegador.

| Destino | Roles actuales analizados | Hash diferente DB → selección renderizador | Activos seleccionados no incluidos en exportación DB |
|---|---:|---:|---:|
| Jericó |11|0|0|
| Cartagena |14|6|11|
| Bogotá |14|8|9|
| Medellín |15|9|10|
| Santa Marta |18|7|18|

## Orden efectivo

1. `public/destino.php` líneas67–73: carga `assets/destinos-semirrealistas.json`, reemplaza hero, elimina imagen de historia y reemplaza media de cada POI presente en mapa, incluyendo experiencias.
2. JSdeferporciudad vuelve a modificar figuras, imágenes y textos. Bogotá usa `bogota-semirrealista/contenido.json`; Medellín contiene JSONembebido; Santa Marta usa `santa-marta-semirrealista/contenido.json` y reconstruye ambas secciones completas. CartagenaJSno reemplaza imágenes, sí descripciones/historia/intro.
3. Los archivos fuente yrelease tienen el mismo JSON visual SHA256 `ce13a13af772be46d2e7c6b63e46dd91ee7a5a6123e023fc8c8b5bc7c4925a04`.

## Hallazgos concretos

- Portadas DBdifieren de las elegidas por renderizador en las cuatro ciudades. No eliminar `*-semirrealista` pensando que son versiones viejas: son activos seleccionados actuales.
- Cartagena museo y bus tienen igualhash DB/assetestático; no son versiones diferentes aunque tengan rutas distintas. Rosarioexcursión queda desdeDB porque no figura en mapaviejo, y su archivo está preservado en exportación.
- Medellín Comuna13 `city-comuna13` carece de enlace de media DB: solo aparece imagen por PHP/JS. Si se eliminan overrides sin materializarla, queda sinfoto.
- Los nuevos citybus/silleteros/chiva/parapente/planetario deMedellín quedan desdeDB y exportados; no están en mapaJSantiguo. No sustituirlos por imágenes viejas.
- Bogotá `cielo` pasa ahora como lugar para visitar pero su slug sigue figurando en el listadoJSdeexperiencias, que lo busca por `data-experience-slug`. Como elDOMusa `data-place-slug`, esa ramaJSno lo sustituye; PHPsíaplica `cielo-800.webp`. HashidénticoDBactual. Una clasificación migrada debe juzgarse por tipoPOI, no por nombre delarchivoviejo.
- Bogotá jardín conserva aprobado: `bogota-jardin-aprobado.webp` mismoSHAqueDB. No cambiarlo.
- SantaMarta reconstruye 8lugares+9experiencias desdeJSON, independientemente delcontenido de sus tarjetas DB. En este snapshotlos slugs públicos coinciden, pero cambios futuros hechos soloDBpodrían desaparecer/reaparecer.
- Jericó no recibe estas cuatro capas porciudad; imagenhistoria puede persistir en DBaunque diseño compartido la oculte. Investigar visibilidadfinalantes de podarla.

## Materialización reproducible propuesta

`auditar-overrides.py --public RELEASE/public --snapshots cierre-nueve... --out INFORMES` produce mapa por rol/slug con rutaDB, rutaefectivaestática, SHA256, cadena deoverride, archivo físico o copiaexportada. Plan JSONes declarativo y NOaplica cambios.

Preparar copia candidata de cada snapshot. Porrolactual, crear/reutilizar media porSHA, conservar metadatos de procedencia y enlazar hero/POI a selección comparada. Capturar también descripciones/historia/intro que JSreemplaza; un cambiosoloimagenno conservaelresultado. Normalizar srcsets antes de retirar scripts. Quitar enlaceshistoriaoculta del candidato, preservando fuentesoriginales en archivo de auditoría. Conservaroriginalesrechazados fuera delpaquetepúblico, nunca borrado global.

Solo entonces retirar overrides porciudad, comparar DOMyhash en tresidiomas y tamaños, incluyendo cargar más. Un activo sin referenciaDBpuede seguir referenciado porJS/PHP/listado; búsqueda y cierreglobalde referenciasantes de eliminar. No reivindicar aprobación nueva por hash distinto.
