# Paquete JSON para crear un destino

La página de administración acepta archivos `.json` con `schema_version: 1`. La importación solo rellena el formulario del navegador: no escribe en la base de datos. Revisa y edita el contenido, adjunta las imágenes en sus campos y pulsa **Guardar municipio** para almacenarlo como borrador.

El archivo de ejemplo está en `ensayos/v1/plantillas/paquete-destino-v1.json` y se puede descargar desde el formulario. El paquete agrupa:

Para pedir a otra IA que genere contenido conforme a esta estructura, copia `docs/PLANTILLA_PROMPT_IA_DESTINO.md`. Ese prompt explica las reglas editoriales, las fuentes y cómo mantener el paquete importable.

- `destination`: identificador opcional, departamento, subregión, coordenadas, traducciones, historia, razones y fuentes.
- `hero_image`: origen de la imagen (`ai_generated`, `own` o `external`), créditos y textos alternativos/leyendas por idioma para la portada.
- `places`: atractivo hijo por atractivo, con estado (`draft`, `published` o `archived`), tres traducciones y metadatos opcionales para su imagen (`image_origin`: `ai_generated`, `own` o `external`). Si se omite el estado, queda como borrador.
- `local_research`: inteligencia interna sobre posicionamiento, competencia, posibles aliados, prospectos comerciales, estrategias de adquisición y fuentes. Se guarda en `destinations.internal_research_json`, dentro de la administración; no se añade al modelo de datos público ni a las páginas renderizadas.

Idiomas admitidos: `es`, `en` y `pt-BR`. Para `history` y `reasons` se usan listas de párrafos o líneas. `sources` admite objetos `{section, label, url}`. Se pueden omitir datos opcionales; antes de publicar se valida que exista el contenido necesario en todos los idiomas. Las imágenes no van dentro del JSON: se seleccionan desde el equipo en el formulario. El guardado las copia al directorio público de medios y registra su origen y textos alternativos. Para imágenes externas se exige autor, página de origen, licencia y enlace de licencia; para imágenes propias o generadas con IA esos enlaces pueden omitirse.

No incluyas rutas del servidor, contraseñas ni datos de configuración en el paquete. La investigación debe incluir URL y fecha de consulta, separar oportunidades de hechos confirmados, evitar datos personales privados y marcar prospectos como pendientes de validar. Nunca dispara contacto automático.

En MySQL existente, aplicar una sola vez `migrations/005-destination-internal-research.mysql.sql` antes de usar el campo interno. En instalaciones nuevas, usar el esquema vigente. La vista previa local aplica la migración SQLite de forma aislada.
