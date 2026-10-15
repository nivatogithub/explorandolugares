# Catálogo dinámico de destinos

Este módulo se prueba con una base aislada. La migración MySQL `migrations/003-dynamic-destinations.mysql.sql` crea tablas nuevas y no modifica `leads`, `rate_limits` ni la configuración del formulario.

## Modelo y estados

- `destinations` contiene la identidad, el slug estable y el estado editorial del pueblo o ciudad.
- `destination_translations`, `destination_story_*`, `destination_reasons`, `points_of_interest` y sus traducciones almacenan contenido de cada idioma y cada sección.
- `media_assets` guarda rutas bajo `assets/` y metadatos de autoría/licencia; los archivos de imagen siguen siendo estáticos.
- `media_assets.origin` distingue imágenes externas, propias/cedidas y generadas con IA. Después de crear el catálogo con la migración 003, aplicar una sola vez `migrations/004-media-origin.mysql.sql` antes de desplegar el editor de imágenes actualizado.
- `destinations.internal_research_json` contiene investigación privada de mercado por destino; no la consulta la ruta pública. En una base existente aplicar una sola vez `migrations/005-destination-internal-research.mysql.sql` después de 003.
- `points_of_interest.poi_type` distingue lugares (`place`) de experiencias (`experience`). Sus traducciones admiten un enlace y una etiqueta localizados al operador. Aplicar `migrations/006-poi-experience-links.mysql.sql` después de 003 y antes del importador/editor actualizado. Las experiencias enlazan con operadores externos; el portal no confirma cupos ni procesa reservas.
- `businesses` es la entrada del directorio, separada de los atractivos editoriales. Un negocio puede ser público sin tener cuenta.
- `information_status` describe verificación de información (`PUBLICO` o `VERIFICADO`); `claim_status` describe la gestión de propiedad (`NO_RECLAMADO`, `EN_TRAMITE`, `RECLAMADO`); `operation_status` describe actividad (`DESCONOCIDO`, `ABIERTO`, `CERRADO_TEMPORAL`, `CERRADO_DEFINITIVO`). `publication_status` determina si se muestra.
- Solo se consultan negocios con `publication_status='published'` y no marcados como cerrados definitivamente. El directorio muestra si la información fue comprobada; no presenta una reclamación como prueba de operación.
- Los medios y contactos comerciales solo se exponen si fueron vinculados a un registro publicado; los contactos además requieren `is_public=1` y un canal permitido.

## Importación

1. Hacer respaldo y aplicar las migraciones 003, 004, 005 y 006, una sola vez, sobre una base MySQL vacía para el catálogo dinámico. En instalaciones existentes, aplicar solo las migraciones que falten, en orden.
2. Revisar `content/destinos-dinamicos.json` y `backend/app/contact-options.json`.
3. Ejecutar `php backend/bin/importar-destinos.php --confirm-empty-catalog` desde el paquete privado, con una configuración que apunte a esa base de pruebas.
4. El importador rechaza el catálogo si las tablas de destino, categorías o negocios ya tienen filas. No crea comercios ficticios ni borra datos.

Para rollback, respaldar primero y eliminar solamente las tablas nuevas en orden inverso de dependencias. No se proporciona un `DROP` automático para evitar borrar datos cargados por error.

## Vista local

`scripts/preview-destinos-dinamicos.sh` crea o reutiliza una SQLite dentro de `.local/`, importa el catálogo de referencia y sirve las rutas localizadas con PHP. No lee secretos ni se conecta a GoDaddy. El parámetro `PORT` cambia el puerto si 8011 está ocupado.

## Límite de verificación

La migración y consultas MySQL deben probarse en una base de staging compatible con el hosting. La suite local usa SQLite para validar relaciones, consultas y renderizado, pero no sustituye esa comprobación de compatibilidad del motor.
