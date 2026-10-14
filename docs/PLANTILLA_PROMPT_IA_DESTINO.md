# Encargo para crear un destino con cualquier IA

Este documento se entrega junto con `ensayos/v1/plantillas/paquete-destino-v1.json` a cualquier chat o herramienta de IA. Ambos archivos cumplen funciones distintas:

- **Este MD** explica el encargo editorial, la investigación, la producción de imágenes, las reglas de veracidad y cómo entregar los archivos.
- **El JSON** es el contrato de datos importable por la administración. La IA debe conservar su estructura y producir una copia completada, no reemplazarla por otro formato.

Adjuntar estos archivos da contexto para esa tarea; no reentrena permanentemente el modelo ni le concede acceso al sitio o a la base de datos. Así, la instrucción puede funcionar con distintos proveedores de IA. El paquete y las imágenes resultantes se revisan localmente; el uso de IA no publica nada.

## Cómo iniciar un trabajo en cualquier IA

Adjunta este MD y el JSON de ejemplo, o pega el siguiente mensaje junto con ambos archivos. Sustituye los datos entre corchetes. Si la herramienta no puede adjuntar o crear archivos, pídele que entregue el JSON en un bloque y cada imagen en una salida separada descargable.

```text
Actúa como investigador y editor de contenido turístico para Explorando Lugares.

Usa los dos archivos adjuntos como especificación e insumo: el MD contiene el encargo y el JSON define exactamente el formato de importación. No cambies el esquema ni elimines campos. Prepara un paquete JSON, investigación interna del mercado local e imágenes correspondientes para este destino:
- Municipio o ciudad: [NOMBRE]
- Departamento o estado: [DEPARTAMENTO]
- País: [PAÍS]
- Subregión (si aplica): [SUBREGIÓN o vacío]
- Idiomas del portal: español (es), inglés (en) y portugués de Brasil (pt-BR)
- Enfoque local opcional: [naturaleza, patrimonio, gastronomía, aventura, vida urbana, etc.]
- Cantidad aproximada de imágenes: [1 portada + una por atractivo / cantidad deseada]

Investiga primero la información en fuentes confiables, priorizando alcaldía, entidades de turismo y cultura, parques o entidades responsables de los lugares. Añade las URL HTTPS consultadas en `destination.sources` para los tres idiomas. Prefiere fuentes directas y recientes cuando el dato pueda cambiar.

Devuelve un único paquete JSON con `schema_version: 1`, compatible con el archivo `ensayos/v1/plantillas/paquete-destino-v1.json` y el importador de `/admin/destinos.php`. No inventes datos, atractivos, hechos históricos, horarios, precios, condiciones de acceso, contactos, negocios, testimonios ni disponibilidad. Si no puedes confirmar un dato opcional, déjalo vacío o exclúyelo. Si las fuentes discrepan, no ocultes la discrepancia: omite el dato y explícalo después del JSON.

Redacta contenido original, concreto y útil para quien planea una visita. Describe tanto el patrimonio como la naturaleza, las actividades y la vida local que estén documentadas; no presentes el lugar como si solo tuviera una temática. No prometas reservas, ventas ni resultados. No uses lenguaje de relleno ni afirmaciones de posicionamiento.

Incluye de 4 a 8 atractivos concretos con nombre oficial o reconocible y descripciones breves. Todos quedan en `draft`; la página y los atractivos no se publican al importar. El sitio genera automáticamente la dirección web y los enlaces de mapa; no incluyas campos de URL de mapa.

Completa español, inglés y portugués brasileño con traducciones naturales, no traducción literal torpe. Conserva nombres propios. Para cada idioma incluye nombre visible, frase breve, título, introducción, descripción para buscadores, historia en párrafos separados, motivos para visitar como elementos breves y fuentes. Si falta información confirmada para la historia, redacta solo lo respaldado y no rellenes por cumplir.

Produce también los archivos de imagen por separado, no dentro del JSON. Entrega una portada y una imagen para cada atractivo que tenga sentido ilustrar. Las imágenes generadas deben ser coherentes con el destino y con su identidad visual; si no hay referencias visuales confiables, crea una ilustración turística inspirada en rasgos documentados, no una fotografía falsa que pretenda mostrar el sitio real. No agregues texto, logotipos ni marcas de agua. Sugiere nombres sencillos de archivo que relacionen cada imagen con el slug del destino o del atractivo.

Para cada imagen registra en el JSON el origen, el crédito y los textos alternativos/leyendas. En imágenes generadas con IA, usa `image_origin: "ai_generated"` y una nota de origen clara, sin atribuirles que son fotografías documentales del lugar. Si recomiendas una foto externa, incluye autor, página original, nombre de licencia y URL de licencia verificables; si no puedes verificar todos esos datos, no la presentes como lista para publicar. No inventes licencias ni afirmes que un archivo puede publicarse comercialmente sin comprobarlo. Si el chat no puede generar imágenes ni adjuntar archivos, entrega prompts individuales listos para un generador de imágenes y marca esas imágenes como pendientes; no las declares producidas.

Reglas del formato:
- `destination.slug`: minúsculas ASCII con guiones, estable y derivado del nombre (ej. `santa-fe-de-antioquia`).
- `destination.department`, `subregion`, `latitude` y `longitude`: agrega coordenadas solo si puedes verificar un punto representativo de la cabecera; si no, usa `null`.
- `destination.translations`: claves `es`, `en`, `pt-BR`; cada una usa `display_name`, `eyebrow`, `headline`, `intro` y `meta_description`.
- `destination.history`: objeto con arrays de párrafos bajo `es`, `en`, `pt-BR`.
- `destination.reasons`: objeto con arrays de motivos bajo esos mismos idiomas.
- `destination.sources`: objeto por idioma con arrays de `{ "section": "destination|history|places", "label": "...", "url": "https://..." }`.
- `hero_image`: objeto con `image_origin`, `image_creator`, `image_source_url`, `image_license`, `image_license_url`, `alt` y `caption`. Usa origen `ai_generated` cuando no exista una foto documental con derechos comprobados.
- `places`: array de atractivos; cada elemento lleva `slug`, `publication_status: "draft"`, `translations` con `name` y `description` en los tres idiomas, e `image` con los mismos metadatos de origen/crédito y `alt`/`caption` por idioma.
- `local_research`: objeto interno con `schema_version: 1`, `researched_at`, `positioning`, `competitors`, `potential_allies`, `business_prospects`, `acquisition_strategies`, `sources` y `notes`, siguiendo los ejemplos del JSON adjunto. Guarda competidores, posibles organizaciones aliadas y prospectos como investigación con fuente y fecha, no como hechos confirmados ni participantes del portal.
- No agregues propiedades inventadas al esquema ni campos de cuenta, negocio, precios, eventos, secretos o datos personales.

La investigación de mercado es privada: no incluyas contactos personales, correos o teléfonos de personas. Para negocios potenciales, limita los datos a información empresarial publicada y cita la página exacta. Marca cualquier registro de directorio o cámara como pendiente de verificar vigencia y representación. No envíes mensajes, no abras cuentas, no atribuyas una alianza y no publiques esa información.

Entrega el trabajo en este orden:
1. Un archivo descargable llamado `paquete-destino-[slug].json`, válido y listo para importar. Si no puedes crear archivos, entrega su contenido exacto en un bloque `json`, sin comentarios ni comas finales.
2. Las imágenes generadas como archivos individuales descargables, en JPG, PNG o WebP. No incrustes base64 ni datos binarios en el JSON. Indica al lado de cada archivo si corresponde a portada o a qué atractivo.
3. Una lista breve que empareje archivo, campo de imagen, nombre sugerido y estado (generada, externa verificada o pendiente).
4. Las fuentes principales usadas y los datos inciertos que omitiste.

No marques como generado o verificado un recurso que no hayas entregado o comprobado. La explicación y la lista de archivos van fuera del JSON.
```

## Qué hacer con la entrega

1. Comprueba que estén los dos insumos: este MD y el JSON de ejemplo. Completa el municipio, el país, el departamento y la cantidad de imágenes solicitada en el mensaje de encargo.
2. Descarga el JSON nuevo y todas las imágenes. Conserva sus nombres para saber qué archivo pertenece a cada campo.
3. En la administración, importa el JSON. La importación rellena el formulario; no guarda ni publica por sí sola.
4. Revisa datos y traducciones, carga las imágenes en los campos correspondientes y confirma sus metadatos de origen. Guarda como borrador y abre la vista previa.
5. La sección de investigación se guarda en la base de datos para uso administrativo y no se renderiza en el sitio. Revísala por separado; no es contenido publicable.
6. Publica únicamente tras verificar manualmente contenido, derechos de imagen y presentación.

La estructura técnica completa y las limitaciones vigentes están en [Formato del paquete de destino](../backend/database/FORMATO-PAQUETE-DESTINO.md). El ejemplo descargable del editor está en `ensayos/v1/plantillas/paquete-destino-v1.json`. El MD no es un sustituto del JSON: se usan juntos.
