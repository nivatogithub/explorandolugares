# Consultas del módulo de lugares — 2 de noviembre de 2026

Rama: `refactor/presentacion-rendimiento-20261021`. Continúa el cambio de contenido en BD y renderizado directo. Terminología del producto y del código nuevo: **lugares / Place**.

## Responsabilidades

| Componente | Responsabilidad |
| --- | --- |
| `PlaceSelector` | Consultas públicas de lugares, traducciones, historia, motivos, fuentes, medios, atractivos, listado, selección de portada y rutas de sitemap. Columnas explícitas; no carga investigación interna ni procedencia privada de imágenes. |
| `PlaceAdminSelector` | Lecturas privadas del editor y comprobaciones de existencia, relaciones, orden e identidades utilizadas al guardar. Conserva el registro privado completo para administración. No lo consume el servicio público. |
| `BusinessDirectorySelector` | Consultas de negocios y categorías del directorio de un lugar, contactos públicos y prestaciones de planes; conserva la proyección de categorías múltiples sin duplicar negocios. |
| `ExperienceOperatorSelector` | Consulta compartida de operadores anunciados activos y revisados, en un lote para todas las experiencias de la página. |
| `PlaceRepository` | Reúne los datos públicos, agrupa medios por identidad y aplica los filtros existentes de contacto. Sin SQL ni HTML. Su fábrica compone selectores y configura la sesión de BD en UTC, como hacía el repositorio anterior. |
| Plantillas compartidas | Reciben los datos preparados y generan HTML. No consultan la base. |

Los controladores del detalle y listado usan directamente `PlaceRepository`. Portada y sitemap delegan sus consultas de lugares a `PlaceSelector`. `BusinessCategories::enrich` y `ExperienceOperatorAddon::active` reutilizan los selectores correspondientes; no mantienen una copia de esas consultas.

`DestinationRepository` fue eliminado; los consumidores y pruebas actuales usan `PlaceRepository`. Las referencias al nombre anterior en los comparadores cargan exclusivamente paquetes históricos congelados para comprobar equivalencia, no código de ejecución actual. Las tablas `destinations`, claves históricas de los contratos, parámetros internos y nombres de fachadas de escritura existentes se mantienen para no exigir una migración de identidad ni romper consumidores. No son el nombre del módulo nuevo. Las URL públicas continúan siendo `/lugares/<slug>/` y sus equivalentes de idioma.

La fachada administrativa existente delega todas las consultas de lectura y las comprobaciones previas a `PlaceAdminSelector`; sus validaciones y transacciones de escritura conservan el comportamiento previo. Los subselects que forman parte de una instrucción de escritura permanecen con esa instrucción. No se extrajeron todavía las políticas de negocio a clases de dominio ni los comandos de persistencia a otra capa.

## Verificación

- Comparación de los datos anteriores y nuevos en SQLite **y MariaDB**: 60 modelos públicos, 60 previews, listado y detalle privados de administración, listados públicos, selección de portada y sitemap. Contratos iguales, incluidos IDs, traducciones, mapas, medios, categorías y prestaciones comerciales.
- Comparación del HTML de los 20 lugares en tres idiomas: mismas 60 páginas, estructura, textos, enlaces, atributos y metadatos.
- Con la fixture versionada, Jericó pasa de 21 a 16 consultas; Santa Marta, de 33 a 16. Añadir otras 24 experiencias mantiene 16 consultas. Son recuentos de esta lectura, no un benchmark de capacidad del servidor.
- `backend/tests/place-selectors.php`: tres idiomas, consultas sin escrituras/campos privados, entrada de slug parametrizada, compatibilidad de idioma, alcance de borrador/preview, lotes vacíos sin SQL y consulta de operadores por lote. Sus cambios de prueba se revierten.
- `backend/tests/destination-admin.php`: listado y edición por ID, conservación de atractivos y traducciones, borradores, validación de publicación, archivado y restricciones de borrado. El refactor conserva el resultado de las operaciones existentes.
- Regresiones de categorías múltiples, operadores pagados, destinos/lugares, presentación, contactos, sitemap y metadatos; seis barreras específicas de arquitectura verifican separación de SQL, proyección pública, lectura privada y nombres de los consumidores nuevos.
- Paquete final: comprobaciones de contenido/migración anterior, 72 respuestas PHP de presentación y 69 páginas de SEO. Dev1 ejecuta también las 60 rutas públicas sobre Apache y MariaDB.

Paquete actualizado sin adaptador: `.local/lugares-sin-repository/`. La retirada se verificó de nuevo con datos SQLite/MariaDB, pruebas de contenido, selectores, administración y las 60 rutas Apache; informes en `.local/lugares-sin-repository-informes/`. Paquete de la extracción inicial: `.local/lugares-selectores-final/`. Informes sanitizados: `.local/lugares-selectores/` (`equivalencia-datos.json`, `equivalencia-html.json`, `mariadb.json`, `apache.json`, `contenido-compatible.json`, `presentacion.json`, `seo.json`). Los informes solo contienen resultados; las comprobaciones no publican el contenido privado del editor.

## Estado y continuación

Revisión local: `http://127.0.0.1:8086/lugares/jerico/`. Se reutiliza dev1 bajo la reserva `arquitectura-contenido-render-20261101`, con su misma base sintética y configuración; se conserva el código anterior. No se modificaron producción ni preproducción. Esta extracción de consultas no requiere nuevas migraciones de datos; la rama completa conserva la migración 060 de la entrega anterior.

No hay commit, integración, push ni despliegue de esta rama todavía. El siguiente bloque es separar explícitamente las políticas de negocio de lugares y sus comandos de edición/publicación, manteniendo estos selectores como vía de lectura. Otras áreas del portal que consultan referencias a lugares —registro comercial, analítica y finanzas— se migrarán con sus respectivos módulos; no se afirma que todas las consultas de toda la aplicación estén centralizadas.


## Unificación de nombres técnicos — 2 de noviembre de 2026

La implementación usa inglés y el concepto `Place`; el portal y la administración tienen español como idioma principal, con traducciones a inglés y portugués. Los textos visibles no se traducen por cambiar identificadores técnicos.

Primer bloque completado: `PlaceAdminService`, `PlaceImporter`, `PlacePackage`, `PlaceContentMigration` y `BusinessPlaceTransfer` sustituyen a las cinco clases anteriores con `Destination`. Se actualizaron sus consumidores de administración, importación, mantenimiento, traslado, exportación y pruebas, sin conservar alias de clase. Los comparadores cargan los nombres anteriores únicamente al leer el paquete histórico congelado.

Este bloque no renombra todavía tablas, columnas, contratos JSON/ZIP, rutas internas, plantillas ni herramientas con nombres históricos. Esos cambios requieren revisar sus consumidores y, para almacenamiento y formatos externos, preparar migración y compatibilidad explícitas. Se conservan IDs, datos, contenido visible, URL canónicas y políticas de publicación. `TouristDestination` en JSON-LD es un término externo de Schema.org, no el nombre de una clase interna que deba cambiarse por sustitución.


Validación del primer bloque: edición administrativa, importación semilla, ZIP y validación de rutas de imágenes, traslado con conservación de IDs/relaciones y rollback, mantenimiento, sintaxis de 214 archivos PHP y 13 barreras automatizadas. Paquete `.local/place-class-names/`: 60 modelos públicos y 60 previews equivalentes en SQLite y MariaDB, 72 respuestas de presentación, 69 páginas SEO y 60 rutas Apache con HTTP 200. Informes en `.local/place-class-names-reports/`. Dev1 actualizado conservando base y configuración; producción y preproducción intactas.


## Idioma de la implementación activa — 2 de noviembre de 2026

Se normalizaron 130 nombres de archivos del circuito de ejecución y construcción: controladores públicos y administrativos, API, plantillas de lugares, herramientas de importación, pruebas y recursos CSS/JavaScript. `backend/integration-analytics/` sustituye el nombre técnico mixto anterior. Se actualizaron las referencias y la documentación operativa. Los métodos de metadatos y relaciones usan `placeTitle`, `placeGraph`, `placeSectionTitles`, `placeRelation`, `placeRelationLink` y `placePointActions`; registro y reclamaciones usan `registrationPlaces` y `places`.

Las URL canónicas `/lugares/<slug>/` y sus prefijos de idioma conservan su significado público en español. Los parámetros `place` y `locale` son internos; se siguen aceptando los parámetros antiguos como compatibilidad de entrada. Los nombres anteriores de 49 endpoints se resuelven a los mismos controladores nuevos mediante un catálogo explícito (`public-route-aliases.json`); no se copiaron controladores ni se cambiaron permisos. Los recursos usados por páginas en caché conservan alias; los recursos ya retirados no se reactivan por figurar en ese catálogo.

Comprobado: 30 pruebas de fronteras, construcción, idiomas y recursos; sintaxis de 385 archivos PHP; 19 controladores privados con interfaz española y enlaces/recursos existentes; 72 respuestas públicas de presentación; 69 páginas SEO; equivalencia de datos en SQLite y MariaDB; 60 rutas Apache HTTP 200. Comprobación HTTP adicional: 49 alias de controladores con la misma respuesta/autorización y 23 alias de recursos con idénticos bytes. Informes sanitizados: `.local/platform-naming-reports/`. Paquete comprobado: `.local/platform-naming-final/`, actualizado en dev1 sin cambiar datos ni configuración.

Alcance pendiente explícito: las tablas/columnas `destination_*`, las claves de contratos JSON/ZIP anteriores, los nombres de migraciones 001–060, los archivos de contenido histórico y las herramientas operativas fuera del circuito normalizado todavía conservan nombres históricos. Los esquemas ya usan inglés, pero la unificación del concepto `Place` en esos contratos necesita una migración versionada; no se alteraron sus claves, hashes, IDs ni relaciones en este cambio. No se afirma haber eliminado todas las palabras históricas de todo el repositorio. La convención permanente está en `AGENTS.md`.
