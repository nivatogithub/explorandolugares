# Experiencias de Jericó habilitadas para revisión local

Solicitud del propietario del 17 de octubre de 2026. Se habilitan en preproducción las tarjetas editoriales de helicóptero Medellín–Jericó, parapente biplaza y visita a finca cafetera, en español, inglés y portugués. Las ilustraciones seleccionadas anteriormente conservan su procedencia y textos alternativos; no representan operadores, fincas ni puntos de vuelo verificados.

La experiencia editorial se separa de una futura vinculación o contratación de operador. No se crean operadores, precios, contactos, reservas ni acuerdos comerciales. Los enlaces externos se dejan vacíos; cuando se añadan, siguen requiriendo HTTPS y un texto descriptivo. El estado publicado de la experiencia no confirma disponibilidad de un servicio.

## Fuentes revisadas

Consultadas el 17 de octubre de 2026 para confirmar el contexto de las actividades, sin asumir vigencia de tarifas, permisos, certificaciones ni disponibilidad:

- Helicóptero: https://turismojerico.com/producto/cafe-patrimonio-helicoptero/
- Parapente: https://turismojerico.com/producto/aventura-altura/
- Visita cafetera: https://turismojerico.com/producto/experiencia-mundo-cafe/

Son referencias de ofertas publicadas por un tercero, no acuerdos con Explorando Lugares. Las descripciones son resúmenes editoriales propios; no se copian precios, condiciones de venta ni datos de contacto.

## Datos y reproducción

El catálogo `content/destinos-dinamicos.json` y su extractor conservan las tres experiencias publicadas para nuevas instalaciones. La base local existente se actualiza selectivamente mediante:

```sh
python3 preprod/habilitar-experiencias-jerico.py
```

El script solo funciona por CLI con la marca local de preproducción, configuración local y base `explorando_preprod`. Actualiza exclusivamente estas tres experiencias de Jericó y sus traducciones existentes, en una transacción. No cambia imágenes, otros lugares, negocios ni operadores. Los datos persisten en el volumen al reiniciar.

Ruta de revisión: `http://127.0.0.1:8088/destinos/jerico/#experiencias-locales`.

Producción no se modifica. Cuando se apruebe el despliegue, la actualización de sus registros existentes debe realizarse explícitamente; cambiar el catálogo semilla no reemplaza los datos de una base ya instalada.
