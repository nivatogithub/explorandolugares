# Corrección de la primera tanda

Estado: en corrección; ninguna aprobación nueva inferida. Procedimiento: `PROCEDIMIENTO_DESTINOS_V2.md`.

| Destino | Corrección solicitada | Revisión local |
|---|---|---|
| Cartagena | Ampliar lugares y actividades vinculables a operadores; no confundir visita editorial con oferta comercial | http://127.0.0.1:8061/cartagena/ |
| Bogotá | Sustituir candidatura de portada con cables por opciones mejores; ampliar actividades con fotos | http://127.0.0.1:8061/bogota/ |
| Medellín | Proponer Coltejer y alternativas; ampliar barrios, parques, patrimonio y actividades | http://127.0.0.1:8061/medellin/ |
| Santa Marta | Aplicar mismo procedimiento; ampliar investigación, actividades, delimitación y faltantes visibles | http://127.0.0.1:8061/santa-marta/ |

Las URLs son privadas y locales; funcionan mientras el servidor esté activo en este equipo. Cada ruta lee el worktree de su destino. No equivalen a producción ni prueban que el contenido esté completo.

## Qué se aprueba en cada ronda

1. Elegir portada y aprobar cada original para su uso identificado, los textos por idioma y los permisos de adaptación documentados. Una elección no aprueba otras fotos.
2. Con esas referencias aprobadas, producir ilustraciones mediante el skill existente. Conservar original y maestro; mostrar comparación y recortes reales. Aprobar cada ilustración por separado.
3. Validar integración y previsualización; autorización separada de publicación de versión exacta.

La ampliación de fichas no aumenta automáticamente cobertura nacional. La profundidad requiere conteos separados: inventario identificado, investigación, textos por idioma, referencias licenciadas/aprobadas, ilustraciones generadas/aprobadas, integración y publicación. Los sitios fuera del municipio se relacionan como excursiones y no inflan el inventario municipal.

Pendiente: versiones finales corregidas y revisión humana. Las ramas siguen independientes; no hay PR nuevo confirmado ni aprobación de publicación.

## Versiones comprobadas

- Cartagena: `lugar/CO/bolivar/cartagena` · `f2f106c`.
- Bogotá: `lugar/CO/bogota-dc/bogota` · `d17cd56`.
- Medellín: `lugar/CO/antioquia/medellin` · `82c222e`.
- Santa Marta: `lugar/CO/magdalena/santa-marta` · `f62ac14`.

Cartagena:17 sitios/9 actividades/2portadas; Bogotá:19 sitios/18actividades/2portadas; Medellín:28 sitios municipales y4 externos/ocultos,10actividades,6encuadres con aptitud individual (no seis recomendados); Santa Marta:10entradas de investigación,2actividades y referencias poruso. Fotografías específicas de actividades siguen pendientes salvo referencias editoriales/contextuales expresamente identificadas. Santa Marta mantiene la ampliación en investigación, sin traducciones completas nuevas.

Esta revisión pasó controles de aislamiento de ramas, archivos de imagen locales y estados de operación. Las nuevas versiones son commits locales; no se ha confirmado subida de estas correcciones ni creación de PR.

Medellín: portadas recomendadas primero (Coltejer propuesta4; Picacho5; Orquideorama6), con IDs preservados. Descartadas/solo detalle en antecedente colapsado y sin selector de elección de portada.

## Incidencia de verificación

Lugar1 ejecutó Chrome headless vía Playwright en esta corrección, sin ventana visible. Incumplió la preferencia del propietario. Las capturas y qa-navegador.json de Cartagena son evidencia nueva de esa ejecución; no afirmar que todo el QA se hizo sin Chrome. Coordinación comprobó las vistas de Bogotá/Medellín con el navegador interno. Se explicitó prohibición de Chrome/headless en el procedimiento. Comprobación de procesos posterior: no se encontraron procesos identificables de prueba Playwright/Chrome por perfil playwright_chromiumdev_profile o revisar.cjs; no se cerraron navegadores personales.

## Cartagena: exportado recibido y selección de ocho

Versión local `7c71e57`, rama `lugar/CO/bolivar/cartagena`. Exportado original3fab25 preservado íntegro con116 decisiones:22 aprobadas,1 corrección y93 pendientes. Nueva revisión `1fb7e17c75bc93b6eafcaffd172af1508134431fcf75620a793f6d8d855438e2`:79componentes,15 aprobaciones intactas activas; otras7 conservadas en historial por cambio de texto o salida de selección. No aprobación de selección completa, ilustraciones finales o publicación inferida.

Selección: centro histórico/murallas, SanFelipe, Getsemaní, IslasdelRosario, PlayaBlanca–Barú, LaPopa, MuseoHistórico y LaBoquilla. Ocho referencias locales aptas; portada distinta de foto deLaPopa. Inventario19 y actividades9 preservados aparte. QA coordinador IAB: estados aprobados y pendientes visibles, sección principal8 y referencias cargadas; validador82archivos y aislamiento pasan. ZIP actual solo referencias, salidaIAfinal e integración bloqueadas hasta fases correspondientes. Nuevas correcciones aún locales, sin PR confirmado.
