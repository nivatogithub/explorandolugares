# Guías de los 21 destinos iniciales

Actualización: 11 de octubre de 2026. Entrega local para revisión; no desplegada.

Los municipios coinciden exactamente con la categoría 1 habilitada en el formulario. Jericó conserva su guía; se incorporaron Jardín, Guatapé, Santa Fe de Antioquia, El Peñol, Támesis, San Jerónimo, Sopetrán, San Rafael, Cocorná, Venecia, El Retiro, San Carlos, El Carmen de Viboral, Rionegro, Medellín, Necoclí, Arboletes, Puerto Triunfo, San Pedro de los Milagros y Santa Rosa de Osos.

Cada guía ofrece historia breve, tres motivos para visitar, tres atractivos con mapa, fotografías locales identificadas, directorio del municipio, información práctica, eventos sin publicaciones ficticias e invitación comercial. Se conserva el alcance informativo de la primera etapa: sin reservas, pagos, cuentas ni reseñas. Captar una solicitud no publica un negocio.

## Contenido y mantenimiento

- `content/destinos-piloto.json`: textos originales ES/EN, subregión, atractivos, búsquedas de mapa, fotografías seleccionadas y fuentes editoriales por municipio. Las fuentes incluyen Colombia Travel, Gobernación, alcaldías e instituciones culturales. Los anuarios de 2014 aportan referencias históricas y geográficas, no disponibilidad actual.
- `content/destinos-fotografias.json`: autor, archivo de Commons, enlace, licencia, dimensiones y leyenda ES/EN. Fotografías de terceros con licencia abierta, inspeccionadas en una hoja de contacto; las cuatro incorporaciones finales se inspeccionaron individualmente. Algunas imágenes históricas contienen una representación de un edificio; no se presentan como fotografías actuales del atractivo.
- La presentación compartida vive en el renderer PHP y en sus vistas; Jericó no conserva una plantilla HTML independiente.
- El índice de destinos se sirve desde el portal y usa el catálogo para búsqueda sin tildes y filtro por subregión; no se genera una copia HTML por municipio.
- Los destinos se renderizan exclusivamente con PHP y el catálogo/base de datos; no existe un generador ni una reserva de páginas HTML por destino.
- `scripts/preparar-hosting.py`: opciones → contenido estático general → inglés → paquete PHP local. No despliega.

Editar los datos y las vistas PHP, no archivos publicados derivados. Luego ejecutar `python3 scripts/preparar-hosting.py`. Las páginas institucionales multilingües se preparan desde el contenido editorial bilingüe; los destinos conservan la ruta dinámica y las fotografías son compartidas.

## Criterios editoriales

Los atractivos son información del destino, no participantes comerciales. Cuando no hay una fotografía identificada y licenciada de un atractivo, la tarjeta conserva descripción y mapa. Ninguna imagen generada sustituye documentación de un lugar real.

El cañón del Río Claro se presenta como parte del corredor turístico **cercano a Doradal**, con ubicación y acceso por confirmar con el operador; no se atribuye toda la reserva al municipio de Puerto Triunfo. Riogrande II se presenta como entorno compartido con otros municipios. En Necoclí, la foto de portada identifica Bajo Caribaná y no se usa como foto de una playa urbana distinta.

Las fuentes se enlazan dentro de cada guía. Se comprobaron los enlaces; se retiró una ruta de Colombia Travel que devolvía 404. No se publican horarios, precios, eventos vigentes ni condiciones de baño no confirmadas. Los créditos y licencias se conservan en un bloque desplegable de cada guía y las imágenes se sirven localmente. `assets/destinos/creditos.json` acompaña también los archivos que se conservan como reserva editorial.

## Directorio y captación

`data-destino` separa el catálogo de cada municipio. El renderer dinámico admite los 21 destinos; permite el mismo identificador de negocio en municipios distintos y exige registro confirmado, información verificada, estado publicado, tipo de servicio válido, descripción bilingüe y canal de contacto seguro.

Las invitaciones de cada guía llevan al formulario con `?municipio=Nombre#contacto`. Se preselecciona solamente un municipio habilitado; se conserva cualquier selección que la persona ya haya hecho. El formulario y sus validaciones de privacidad y contacto siguen siendo compartidos. La vista estática local no guarda solicitudes; necesita el backend configurado en hosting.

## Verificación

- Cobertura de las 21 guías ES/EN, directorio correspondiente y preselección de contacto.
- Enlaces, imágenes y anclas locales en todo el sitio; ausencia de marcadores de plantilla pendientes.
- Búsqueda sin tildes, combinación con subregión, reinicio y paginación 9/9/3 ES/EN.
- Separación de fichas por municipio y rechazo de destinos fuera del piloto.
- Pruebas existentes del directorio y opciones del formulario.

La inspección visual de la composición en navegador no pudo ejecutarse en esta sesión por la restricción del navegador. Se revisaron las imágenes y la estructura; la validación responsive queda pendiente de revisión en la vista local.
