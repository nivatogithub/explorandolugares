# Primera entrega: contenido editorial de lugares en base de datos

Base confirmada por el manifiesto productivo: `c6b1ee2b4395e748fd736f3a9dc176982e34be81`.
Rama de trabajo: `refactor/presentacion-rendimiento-20261021`, actualizada por avance directo a esa base. No se modificaron main, preprod ni producción.

Continuación del 2 de noviembre: la separación de consultas del módulo de **lugares** quedó implementada después de esta entrega. Estado, componentes, compatibilidad y pruebas en [Selectores de lugares](SELECTORES_LUGARES.md). Las referencias posteriores a consultas como trabajo futuro describen el cierre de esta primera entrega.

## Actualización del 2 de noviembre: renderizado directo y validación real

Se completó también el retiro del corrector de HTML de los destinos. `DestinationPresentation.php` y `scripts/normalizar-destinos.php` fueron eliminados. El controlador renderiza directamente `views/destination-main.php`, que emite los títulos de sección, clases, posición del mapa, jerarquía de encabezados y estructura de imágenes definitivos. Los rótulos comunes proceden de `destinationSectionTitles` en el diccionario de la vista. No se introduce otro procesador de HTML.

El directorio de negocios conserva su componente compartido, sus niveles comerciales, iconos, contactos y páginas. Las pruebas que antes invocaban el corrector ahora ejercitan esa plantilla real con datos sintéticos, incluidos niveles pagos, límites del nivel gratuito, operadores anunciados y relaciones entre atractivo y experiencia. La construcción rechaza un controlador de destinos que reintroduzca el corrector, DOMDocument, sustitución de HTML o buffer de salida.

Se compararon las 60 páginas publicadas (20 destinos × tres idiomas) contra el paquete anterior utilizando la misma base aislada. Se conserva la estructura, texto, atributos, imágenes, enlaces y metadatos. Diferencia deliberada: el serializador antiguo convertía tildes dentro de cadenas JSON-LD en entidades HTML; el renderizado directo conserva Unicode real. La comparación trata ese cambio explícitamente y las pruebas HTTP verifican el nombre original del lugar dentro del JSON-LD, sin relajar el resto del HTML ni los otros scripts.

Dev1 quedó reservado con el propietario `arquitectura-contenido-render-20261101`. La validación de `dev/probar-contenido-destinos.php` creó una base nueva dentro de su MariaDB, a partir de datos sintéticos/versionados: esquema 060 repetido, carga estricta de la fixture, 244 cambios en la primera aplicación, cero en la segunda, conflictos y medios inválidos revertidos, tablas protegidas sin cambios y ediciones posteriores conservadas. La base anterior del dev se mantiene; código y configuración local anteriores quedaron respaldados en su estado privado. No se copió una base viva ni configuración productiva.

Paquete vigente de la revisión: `.local/render-directo-final/`. Evidencia en `.local/render-directo/`: `mariadb.json`, `contenido-sqlite.json`, `comparacion-dom.json`, `presentacion.json`, `seo.json`, `visual.json`, `http-apache.json` y `revision-local.jpg`.

- Apache/MariaDB: las 60 rutas canónicas locales responden directamente 200, con idioma, un layout, mapa en su posición nativa y JSON-LD Unicode correcto.
- Paquete PHP: 72 respuestas con un shell y recursos/idiomas válidos; 69 páginas con comprobaciones SEO.
- Navegador: escritorio y pantalla pequeña, anchos efectivos observados de 1800 y 487 píxeles; sin desbordamiento horizontal ni imágenes cargadas rotas. Probados Cargar más, filtro Comida, menú móvil y navegación ES/EN/PT-BR. Se restableció el tamaño normal del navegador al terminar.
- Pruebas PHP de vistas, acciones, directorio pagado, contenido compartido, destinos, mantenimiento y categorías; siete pruebas Python de límites de construcción, geografía y recursos; diff sin errores de espacios.

Revisión disponible en `http://127.0.0.1:8086/lugares/jerico/`. Se mantiene la reserva durante la revisión humana; no debe apropiársela otra tarea. No hay commit, integración, push ni despliegue. Antes de producción siguen pendientes el circuito de integración/aprobación del paquete exacto y el ejecutor privado compatible con el hosting para la transferencia de contenido.

Alcance restante: los generadores de páginas generales por idioma y la composición histórica del shell usan todavía transformaciones sobre HTML. No se declara eliminado ese mecanismo de todo el portal. La centralización de consultas/selectores y la separación de reglas de negocio son fases posteriores.

## Alcance implementado

Las historias, titulares, introducciones, nombres/descripciones de tarjetas e imágenes que antes sobrescribían cinco archivos de presentación se transfieren a las tablas existentes de destinos, traducciones y medios. Los destinos afectados son Bogotá, Cartagena, Jericó, Medellín y Santa Marta. Las imágenes siguen siendo archivos; la selección, sus textos alternativos y sus variantes adaptables quedan en la base de datos. No se generan ni sustituyen sus bytes.

`PlaceRepository` devuelve el contenido publicado y el renderer lo utiliza directamente. `HomeAttractions` consulta los mismos medios y el estado de publicación, sin catálogo editorial alternativo ni lista de destinos autorizados desde JSON. La respuesta pública mantiene una proyección explícita: no entrega procedencia privada ni investigación interna.

En la primera entrega se retiró de `DestinationPresentation` la carga de archivos por destino y las sobrescrituras editoriales. La actualización del 2 de noviembre indicada arriba retiró también esa clase y trasladó su estructura a la plantilla. Esta entrega no declara terminada toda la arquitectura ni convierte las páginas generales estáticas a base de datos.

Los cinco JSON originales se conservan **solo como evidencia histórica**, fuera de la aplicación, en `content/migrations/060-legacy-presentation/`. No son una vía de edición ni entran en el paquete ejecutable. El archivo privado `app/content/destination-content-060.json` es un plan inmutable de migración; ninguna petición pública lo consulta. Después de migrar, las ediciones se hacen sobre los registros de la base, no sobre ese plan.

## Migración y controles

1. Aplicar el esquema versionado `060-destination-content` mediante el mantenimiento habitual. Agrega dos columnas de variantes de imagen y una tabla de registro de migraciones; no modifica las entidades protegidas.
2. Ejecutar la herramienta privada de contenido **sin** `--apply`. Comprueba valores anteriores, identidades, selecciones y hashes de los archivos ya aprobados. Cualquier divergencia detiene y revierte la transacción completa. Una diferencia real exige revisar el registro, no quitar el control ni sustituirlo con una importación antigua.
3. Revisar el informe y preparar la aprobación del paquete exacto por el circuito del proyecto. El CLI no constituye autorización de producción. El hosting actual no tiene shell habilitado: antes de publicar hay que preparar y validar un ejecutor privado compatible con el circuito existente; no exponer este CLI mediante una ruta pública.
4. La aplicación del contenido y la promoción del código deben coordinarse. No desplegar solo el renderer nuevo mientras los registros conserven los textos anteriores que antes estaban ocultos por los archivos.
5. La ejecución con `--apply` registra el hash del plan y los valores anteriores/posteriores en `destination_content_migrations`. Repetir el mismo plan devuelve cero cambios; una versión diferente con la misma clave se rechaza. Las ediciones hechas después de la migración se conservan. El registro sirve para preparar una reversión selectiva revisada, no para restaurar ciegamente una base anterior.

Ejemplo para una copia aislada o para la ejecución concreta posteriormente autorizada:

```sh
php bin/migrar-contenido-destinos.php --config=/ruta/privada/config.php --public=/ruta/public
php bin/migrar-contenido-destinos.php --config=/ruta/privada/config.php --public=/ruta/public --apply
```

No crea destinos, atractivos, medios, negocios ni cuentas. Conserva sus IDs, estados, coordenadas, relaciones y datos comerciales. Los párrafos históricos sustituidos conservan los IDs de bloque; las traducciones vacías no se muestran. Las referencias de tarjetas ausentes de los JSON históricos no crean entidades. Si esas tarjetas reaparecen activas en la base real, el preflight se detiene para revisión.

## Comprobaciones realizadas

Paquete aislado: `.local/refactor-contenido-060-v2/`, construido sin mutar las fuentes. Base sintética creada desde contenido versionado; ninguna base ni credencial productiva se copió a desarrollo.

- `scripts/probar-contenido-destinos.py`: 15 combinaciones destino/idioma, simulación sin persistencia, aplicación de 244 cambios en la fixture, segunda aplicación sin cambios, rechazo de conflictos y medios inválidos con reversión completa. Verifica igualdad de todos los registros protegidos, mapas y procedencia legal; cambios reales de texto en BD llegan al HTML inicial con escape seguro; un JSON contradictorio no prevalece; portada y detalle comparten selección de imagen y estado de publicación.
- `scripts/check-package-presentation.py`: 72 respuestas PHP, un header/footer/menú, variantes de idioma y recursos existentes.
- `scripts/check-package-seo.py`: 69 páginas PHP con títulos, canonical, metadatos, idiomas recíprocos, datos estructurados y atributos de imagen.
- Pruebas PHP de destinos, presentación, acciones, conservación de directorio pagado, hallazgos visuales, mantenimiento y categorías de negocios.
- Seis pruebas de límite del renderer único y catálogo geográfico; sintaxis PHP y revisión de espacios del diff.

Al cerrar la primera entrega ambos dev estaban ocupados y las comprobaciones disponibles eran de SQLite/PHP. Ese pendiente se completó en el dev1 posteriormente liberado y reservado, según la actualización del 2 de noviembre. La validación local no constituye paridad productiva ni autorización de despliegue.
