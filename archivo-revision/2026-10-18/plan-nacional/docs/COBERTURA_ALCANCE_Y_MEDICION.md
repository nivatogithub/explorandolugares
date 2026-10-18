# Cobertura, alcance de trabajo y medición — revisión 16/10/2026

## Resultado de la revisión

Sí se requieren ajustes locales de datos e implementación. La planificación revisada contiene 100 destinos candidatos y un objetivo P1–P2 de 97. La categoría 4 queda fuera de trabajos, pero se conserva para medición. No se alteró producción, no se ejecutó migración remota y no se eliminaron registros.

El generador anterior proyectaba solo los candidatos y la instalación de medición usaba INSERT IGNORE: las nuevas prioridades y el estado en preparación de registros existentes podían quedar obsoletos en la base. Además, la API prefería ese catálogo de base y perdía versión/alcance al volver a cargarlo.

## Ajustes implementados para revisión

- Proyección versionada: 100 candidatos con pertenencia al objetivo, preparación/profundidad derivadas de evidencias y publicabilidad. Los cuatro destinos asignados se reflejan en preparación sin llamarlos publicados.
- Registro geográfico separado: se conservan 1122 unidades de la cartografía histórica MGN2018. Los 125 municipios de Antioquia mantienen su categoría original (incluidos los 22 de categoría 4); fuera de Antioquia la categoría desconocida es null, nunca 4 ni cero inventado. Los alias geográficos están comprobados, sin confundir municipio con atractivo o corregimiento.
- Tablas aditivas `el_coverage_scope` y `el_coverage_registry`, mediante `CoverageCatalogService`. La sincronización actualiza prioridades/estados/metadatos por ID, conserva registros ausentes del nuevo fichero y les retira únicamente pertenencia al objetivo. Los cambios de catálogo se agrupan en una transacción; un fallo revierte la sincronización. Las tablas se crean antes de la transacción por compatibilidad DDL MySQL.
- `AnalyticsService.install()` llama a la sincronización del catálogo; su lectura conserva alcance y versión. Las instalaciones antiguas sin tablas nuevas siguen leyendo su catálogo y muestran objetivo sin configurar, no 0% ficticio.
- API: `editorial_target` entrega denominador 97 y numerador de guías públicas que realmente pertenecen a ese objetivo. Una publicación fuera del objetivo sigue contando entre todas las publicaciones del país, pero no aumenta su porcentaje. `country.coverage_percent` permanece null porque no hay universo completo validado de destinos turísticos del país.
- `measurement_reference` identifica el registro histórico y las categorías 4 conservadas; no presenta 1122 como censo administrativo vigente ni como total de destinos turísticos.
- La tarjeta pública separa el porcentaje del objetivo de esta etapa de la aspiración nacional. Un error de base muestra dato no disponible, no falso cero. No se cambió la paleta ni el sistema de estilos.

## Denominadores y significado

Objetivo editorial público: un destino = una unidad; publicación = publicados del objetivo / destinos del objetivo. Preparación/profundidad = media de sus porcentajes con evidencia; publicabilidad = versiones completas aprobadas del objetivo / destinos del objetivo. Los borradores no suman como publicados. Un hijo no hereda la publicación del padre.

Plan privado: mantiene además el indicador ponderado por prioridad y departamento, con su método explícito. No comparar la media ponderada privada con la fracción pública sin distinguir método. El inventario general conserva P3/P4 y unidades no clasificadas; no desaparecen al limitar los trabajos a P1/P2.

Audiencia: páginas vistas, sesiones/visitantes estimados con consentimiento, clics y comunidad conservan definiciones, privacidad y retención. No se borran ni reetiquetan eventos por cambiar prioridades. Una visita puede corresponder a un destino fuera del objetivo. El catálogo geográfico conserva esas unidades para selección y medición, aunque no tengan tarea editorial activa. No se crean eventos para simular actividad.

## Validación y límites

Probado con PHP y SQLite local: retención de P4, 97 de objetivo, sincronización idempotente de prioridades y producción, lectura de metadatos desde base, numerador sin publicaciones ajenas, porcentaje nacional null, base no disponible y rollback. Pruebas previas de cobertura y audiencia pasan. No se probó MySQL real ni producción.

La prueba nueva de navegador está preparada en `backend/tests/coverage-scope.browser.cjs`. Chrome no arrancó dentro del sandbox; no se continuó tras el rechazo del permiso de navegador comunicado por Lugar 2. No se declara QA de navegador de este cambio aprobado. La inspección visual de cada borrador se reporta por separado.

Antes de integrar: revisar cambios concurrentes de main (la base del plan sigue siendo a75d341), ejecutar pruebas MySQL, revisar las dos tablas y la sincronización, regenerar catálogo y preparar una entrega incremental completa. La sincronización en hosting requiere autorización concreta; no ejecutar instalación remota solo para mostrar un porcentaje.
