# Plan por etapas: destinos dinámicos en PHP

Estado: implementación local en `feature/municipios-dinamicos-php`. El catálogo carga 21 destinos y las 63 combinaciones de idioma en SQLite; Jericó fue aprobado visualmente. El CRUD administrativo local está implementado y queda para validación manual. Siguen pendientes la prueba MySQL de staging y la limpieza final de la transición. Sin despliegue ni cambios en producción.
Alcance actual: reemplazar gradualmente las 21 páginas estáticas por una plantilla PHP y administrar el catálogo desde el panel protegido existente. No publicar ni modificar producción durante estos hitos.

## Decisión de arquitectura

- PHP renderiza el HTML completo en el servidor desde una plantilla compartida y datos MySQL. El navegador recibe una página utilizable sin JavaScript; JS se reserva para mejoras como filtros del directorio.
- La URL pública seguirá siendo legible: `/destinos/jerico/`, `/en/destinos/jerico/` y `/pt-br/destinos/jerico/`. Apache/.htaccess resolverá el slug hacia el controlador PHP. Se verificará localmente antes de asumir que el hosting acepta las mismas reglas.
- Las páginas públicas actuales son la referencia visual y editorial. Se preservan intactas durante el piloto y sirven como respaldo hasta aprobación.
- Imágenes continúan como archivos web optimizados. MySQL guarda rutas y metadatos, autoría, fuente, licencia, leyendas y texto alternativo; no se guardan binarios de imagen en la base.
- La clave primaria numérica de cada destino es la identidad relacional. Comercios, atractivos y medios se relacionan por IDs y claves foráneas. El slug legible solo resuelve la ruta pública y no reemplaza los IDs; el nombre visible se puede editar sin cambiar la identidad del registro.
- Las imágenes nuevas se organizarán bajo `assets/destinos/{destination_id}/` y `assets/negocios/{business_id}/`, con nombres descriptivos de uso (`portada`, `historia-01`, `atractivo-cristo-del-rey`). La base guarda el asset ID y su ruta. Los archivos actuales no se renombrarán hasta mapear referencias y verificar cada archivo.
- Visitantes no requieren cuenta. La base pública no expone documentos ni datos privados. Usuarios, reclamaciones, onboarding, suscripciones y pagos quedan fuera del primer alcance dinámico.

## Modelo de datos previsto

1. `destinations`: identidad no traducible, slug único, tipo (municipio/ciudad/otro), código de país, departamento, subregión, coordenadas y estado editorial.
2. `destination_translations`: una fila por destino e idioma (`es`, `en`, `pt-BR`), con nombre visible, titular, introducción y descripción SEO.
3. `destination_story_blocks` y su contenido traducido: párrafos ordenados de historia y otros bloques editoriales; evita meter HTML completo en una columna y admite longitudes distintas.
4. `destination_reasons` y sus traducciones: motivos ordenados para visitar el destino.
5. `points_of_interest` y sus traducciones: lugares recomendados, descripción, mapa/coordenadas, orden y estado. No son comercios inscritos.
6. `media_assets`, traducciones de leyendas/textos alternativos y vínculos ordenados a portada, historia y puntos de interés. Conserva créditos y licencia.
7. `service_categories`: catálogo controlado común. `businesses`: establecimiento ligado a un destino, slug, categoría, textos, datos públicos aprobados y estados editoriales. Sus cuentas, reclamaciones y suscripciones se agregarán después.

Antes de la migración se resolverá cómo persistir el estado comercial sin mezclar conceptos: procedencia/verificación (`PUBLICO`, `VERIFICADO`), reclamación por representante y operación (`CERRADO`). La interfaz podrá mostrar las etiquetas acordadas; una sola columna no debe hacer que “reclamado” signifique “abierto”. Se acordará también qué estados aparecen en el directorio y con qué distintivo.

## Hitos, entregables y puertas de aceptación

### Hito 0 — Inventario y línea base

- Congelar el alcance visual: header, aviso, navegación, portada, historia, motivos, lugares, directorio vacío/lleno, prácticos, eventos, invitación, créditos y footer.
- Comparar Jericó artesanal con las otras 20 guías generadas; conservar los 63 resultados actuales (21 destinos × 3 idiomas) como referencia.
- Inventariar los 21 destinos, textos, 3 motivos por municipio, 65 puntos de interés (5 de Jericó y 3 por cada uno de los otros 20), imágenes, licencias, enlaces y contenido ausente.
- Definir un registro editorial de referencia y capturas locales de escritorio/móvil para comparar visualmente.

Aceptación: inventario sin campos desconocidos y lista explícita de contenido faltante; no se editan ni reemplazan las páginas vigentes.

### Hito 1 — Esquema y migración reversible

- Preparar SQL MySQL nuevo, versionado, sin alterar `leads` ni `rate_limits`.
- Añadir claves foráneas, restricciones únicas de slug e idioma, índices para destino/estado/categoría y campos de fechas.
- Mantener rutas de imagen y créditos sin copiar archivos ni incluir configuración privada.
- Preparar carga repetible (idempotente) de datos editoriales y una instrucción de respaldo/rollback.

Aceptación: instalar en una base de pruebas vacía; una segunda importación se rechaza sin cambiar el catálogo; claves e índices se validan; migración no toca tablas del formulario ni configuración.

### Hito 2 — Migrar el contenido y cotejarlo

- Unificar Jericó, ahora escrito directamente en HTML, con la fuente editorial de los otros destinos.
- Importar ES, EN y PT-BR desde las páginas de referencia actuales; no traducir ni reescribir texto como parte de una migración estructural.
- Importar historia en bloques, motivos, puntos de interés, enlaces de mapa, imágenes, fuentes y licencias.
- Registrar nulos conscientemente cuando no existe foto o dato; no inventar información para completar tarjetas.

Aceptación: exactamente 21 destinos, 3 variantes de idioma por destino y 65 puntos de interés; conteos y texto/URLs/imágenes cotejados con la referencia; toda imagen conserva procedencia y permiso.

### Hito 3 — Plantilla PHP de destino, primero Jericó

- Crear una ruta de prueba local y el controlador de lectura por slug/idioma.
- Renderizar desde MySQL la página completa de Jericó con consultas preparadas, escape HTML, metadatos SEO y enlaces de idioma.
- Mantener iguales estructura, contenido, estilos, estados vacíos y responsive; no integrar todavía cambios visuales.
- Verificar rutas desconocidas como 404, slugs inválidos y traducciones faltantes con política explícita de fallback.

Aceptación: prueba visual local de escritorio y móvil aprobada por el usuario; prueba sin JS conserva historia, lugares y directorio básico; errores no revelan SQL, rutas privadas ni configuración.

### Hito 4 — Directorio de negocios por destino

- Leer negocios mediante `destination_id`; filtrar solo categorías activas y registros elegibles para publicación.
- Mantener separadas la ficha editorial de un atractivo y la ficha comercial. Si representan el mismo lugar, usar un vínculo explícito en vez de duplicar datos.
- Añadir tarjetas, categoría, búsqueda y ordenamiento de manera progresiva; mantener consulta server-side inicial y JS como mejora opcional.
- No crear negocios de prueba dentro del catálogo real ni exponer contacto no autorizado.

Aceptación: negocio de prueba aparece solo en su municipio y con su estado/categoría; uno no publicado o cerrado no se filtra como publicado; destino sin negocios conserva el estado vacío actual.

### Hito 5 — Extender a los otros 20 destinos e idiomas

- Reutilizar la plantilla de Jericó, eliminar la excepción HTML exclusiva y resolver diferencias mediante datos, no ramas especiales de código.
- Probar todas las rutas ES/EN/PT-BR, selección de idioma, canónicos, enlaces internos, fotografías y créditos.
- Añadir caché de lectura prudente y evitar consultas repetidas por tarjeta; la base se consulta por página con un número acotado de consultas.

Aceptación: las 63 rutas responden correctamente; cada destino corresponde al slug y a sus datos; la inspección automatizada detecta enlaces rotos, duplicación de navegación, errores de consola y contenido cruzado.

### Hito 6 — Regresión, revisión manual y decisión de publicación

- Ejecutar pruebas unitarias de validadores, slugs, traducciones, selección de medios y visibilidad.
- Ejecutar integración contra MySQL de pruebas para migraciones, carga y consultas; pruebas SQLite existentes se conservan para formularios, no sustituyen MySQL.
- Probar rutas bonitas y errores HTTP bajo el mismo Apache/PHP compatible con cPanel; comprobar carga sin JS, escritorio, móvil y conexión lenta simulada.
- Revisar accesibilidad básica, seguridad de salida, consultas preparadas, cabeceras, caché y respuesta del hosting local o entorno de prueba.
- Preparar un paquete de despliegue separado con respaldo y rollback; no ejecutarlo hasta aprobación explícita.

Aceptación: suite completa aprobada, validación manual del usuario y autorización aparte para subir/desplegar. El merge a `main` y la etiqueta de versión se hacen después de la validación acordada.

### Hito 7 — Administración CRUD de destinos — implementado localmente, pendiente de revisión manual

- Se añadió “Destinos” al panel con la sesión y contraseña existentes; no es un área pública independiente.
- El editor lista, crea y modifica destinos por ID; permite contenido ES/EN/PT-BR, geografía, publicación, historia, motivos, lugares de interés, mapas, fuentes, créditos y asociaciones a imágenes registradas.
- Acepta borradores incompletos y exige el contenido esencial en los tres idiomas para publicar. Permite archivar conservando relaciones; el borrado definitivo requiere confirmación y la base lo bloquea si hay negocios relacionados.
- Los cambios usan CSRF y consultas preparadas. Los lugares conservan su ID al editarlos. Subidas de portada limitadas a JPG/PNG/WebP, máximo 5 MB, con atribución y licencia; se guardan bajo `assets/destinos/{id}/`. Quitar una asociación no borra archivos.
- En el preview local, el acceso está en `/v1/admin/index.php`; las credenciales de revisión local aparecen en la instrucción de entrega y solo sirven para el servidor ligado a localhost.

Aceptación automática: suite local probó listado, edición por ID, alta de borrador, bloqueo de publicación incompleta, archivo y borrado seguro; una prueba HTTP confirmó rechazo anónimo y acceso autenticado al catálogo de 21 destinos. Falta revisión manual del usuario, comprobar una subida de imagen en la interfaz y, más adelante, probar contra MySQL de staging.

### Hito 8 — Organización y limpieza de la transición

- Probar el render de los 21 destinos, tres idiomas, enlaces, estados sin imagen y referencias a archivos antes de retirar HTML heredado.
- Generar un inventario que relacione `media_assets.id`, ruta actual, destino/atractivo, licencia y referencias restantes. Reubicar los archivos aprobados a carpetas por ID y actualizar las rutas de base sin perder atribuciones.
- Retirar del paquete público las páginas estáticas reemplazadas solo cuando las rutas PHP y redirecciones equivalentes estén aprobadas. Conservar el historial en Git y no borrar ni modificar archivos dentro de `sources/`.
- Preparar una lista explícita de archivos que el despliegue deberá eliminar del hosting; el despliegue incremental actual no borra archivos remotos por sí solo.

Aceptación: búsqueda del repositorio y del paquete confirma que cada URL pública llega a la plantilla dinámica y cada ruta de imagen existe; no hay referencias huérfanas, páginas duplicadas ni borrados remotos implícitos.

## Protocolo de trabajo y validación

Se avanza un hito por vez. Para cada entrega se informa qué cambió, se ejecutan las pruebas automáticas pertinentes y se abre la vista local afectada para revisión. El usuario confirma o solicita correcciones antes de comenzar el siguiente hito si el cambio altera datos o presentación. Nunca se usa producción para experimentar ni se reemplaza el sitio estático aprobado sin un plan de reversión y aprobación.

## Fuera de este primer ciclo

- Registro/inicio de sesión de viajeros o representantes, reclamaciones, documentos de onboarding, planes, pagos, reservas, reseñas y eventos administrables.
- Cambios de marca, rediseño, traducción editorial nueva, escritura automática de contenido y despliegue de producción.
- Borrado de HTML estático histórico hasta completar el hito 8 y aprobar equivalencia; la limpieza local y la eliminación remota se revisan por separado.
