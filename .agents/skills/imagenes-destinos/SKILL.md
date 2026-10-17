---
name: imagenes-destinos
description: Produce and review accurate AI-generated editorial illustrations for Explorando Lugares destination pages. Use when creating, revising, selecting, or preparing a destination image for publication.
---

# Imágenes de destinos de Explorando Lugares

Antes de crear imágenes, lee `docs/PRODUCCION_IMAGENES_IA.md`, `docs/GUIAS_VISUALES_DESTINOS.md` y `AGENTS.md`.

## Procedimiento

1. Identifica el destino, el atractivo y el componente/página donde se usará la imagen.
2. Verifica sus rasgos con fuentes locales/institucionales y referencias visuales reconocibles. Registra URL y fecha.
3. Elige vía: transformación autorizada de una referencia cuyo permiso cubra la carga al generador y la publicación del resultado previsto, o composición nueva independiente basada en hechos del lugar sin copiar ni subir una foto restringida. No exigir permiso para publicar el original intacto cuando un permiso específico ya cubra el uso previsto; documentar condiciones del derivado. Consulta la sección de publicabilidad IA de `crecimiento-colombia/GUIA_GENERACION_DESTINOS.md`.
4. Genera una ilustración nueva con el atractivo principal claramente visible, contexto territorial fiel, composición adecuada al recorte real, paleta editorial del portal y acabado claramente ilustrado de caricatura realista (trazo de tinta/gouache), sin deformar formas y sin texto, logos ni elementos inventados.
5. Inspecciona la salida contra las referencias. Recházala si cambia arquitectura, monumento, ubicación, paisaje o acceso, o si el sitio no se reconoce al tamaño de tarjeta.
6. Guarda el maestro y la versión optimizada, completa el manifiesto `content/templates/procedencia-imagen-ia.json` y deja el activo en borrador.
7. Solicita revisión humana. No publiques, despliegues, subas a producción ni cambies una imagen pública sin aprobación humana explícita.

## Restricciones

- No presentar ilustraciones de IA como fotografías ni como prueba del estado actual.
- No inventar sitios, comercios, gente identificable, actividades, servicios ni condiciones.
- No atribuir al modelo un conocimiento geográfico no verificado.
- No asumir que la estilización elimina los derechos o condiciones de una foto usada como referencia.
- No quitar los créditos/licencias de fotografías y obras de terceros que siguen apareciendo en el portal.
- No afirmar que se conoce el origen del material de entrenamiento si el proveedor no lo informa.

La guía completa y la lista de revisión están en `docs/PRODUCCION_IMAGENES_IA.md`.

## Selección icónica obligatoria antes de generación

Aplicar la puerta de calidad de `crecimiento-colombia/GUIA_GENERACION_DESTINOS.md`: investigar símbolos y rasgos distintivos antes de buscar fotos; comprobar permiso comercial/adaptación y derechos adicionales; inspeccionar original y recortes reales. Una foto auténtica pero genérica no es apta. No generar ni completar con IA un símbolo que la referencia no permite verificar. Registrar rechazo/pendiente y opciones buenas por componente; mantener aprobación de original y de ilustración separadas. Incluir movilidad emblemática en investigación (Metro/Metrocable en Medellín).

## Resultado IA publicable y procedencia

El objetivo es una creación nueva fiel al destino y apta para publicación prevista, no un retoque para evitar identificación. Generar IA no elimina automáticamente derechos de referencia. No usar detectores de coincidencia como prueba de permiso, ni quitar marcas para eludir restricciones. Si falta permiso de adaptación, elegir otra referencia autorizada o una composición independiente sin copiar la expresión de una foto concreta. Documentar vía, referencias realmente enviadas, condiciones del proveedor/resultado y atribuciones aplicables; conservar prompt, maestro y hashes. No declarar «libre de restricciones» ni «foto real» por el solo hecho de ser IA. La aprobación editorial y la autorización de publicación siguen separadas.

## Descubrimiento y aprendizaje de correcciones

Antes de proponer referencias, consultar Tripadvisor para descubrimiento y contrastar con portales locales y nacionales de turismo; verificar nombres, rasgos icónicos y fuente original. No escoger primeras fotos disponibles. Aplicar el filtro de reconocimiento y ocupación de encuadre de `crecimiento-colombia/GUIA_GENERACION_DESTINOS.md`: sin edificios cortados ni grandes superficies genéricas que desplacen al sujeto. Cada corrección del propietario debe registrarse por componente y actualizar la regla reutilizable, este skill si aplica y el checklist antes de volver a proponer. No repetir referencias rechazadas sin modificación justificada; una actualización documental no significa que el activo ya esté corregido.
