# Alta básica de Cauca Viejo

La administración de destinos ofrece **Agregar Cauca Viejo en borrador**. El alta usa un registro preparado y revisado en `backend/app/destination-starters.json`; no recibe archivos, rutas ni contenido arbitrario del navegador.

El registro conserva Colombia, Antioquia, Suroeste y el nombre Cauca Viejo en los tres idiomas. Se clasifica como `other`, no como municipio independiente. Su pertenencia a Jericó queda documentada en las notas privadas y en la fuente del destino: https://caucaviejo.com/pueblo-cauca-viejo/, consultada el 16 de octubre de 2026.

El sitio oficial describe un pueblo privado y acceso con reserva. Antes de preparar su página pública deberán comprobarse las condiciones de entrada. No se han inventado coordenadas, atractivos, horarios, imágenes ni reseñas.

El alta crea un borrador editable sin necesidad de portada ni paquete ZIP. Los negocios pueden asociarse a este destino desde su editor inmediatamente después de crearlo; la página pública no se publica automáticamente.

La operación requiere sesión administrativa y CSRF válido. Si el destino ya existe por nombre o dirección web en la misma ubicación, abre el registro existente sin sobrescribir textos, estado ni ID. Repetir el alta no duplica el destino. No se trasladan negocios automáticamente desde Jericó.

El mapa usa búsquedas generales para destinos de tipo `other` y conserva la búsqueda de municipio para los demás. El departamento y país se obtienen del destino seleccionado, evitando asumir Antioquia para todos los destinos futuros.

No requiere una migración nueva. La actualización 030 de categorías múltiples, si sigue pendiente, se activa por separado desde Base de datos.
