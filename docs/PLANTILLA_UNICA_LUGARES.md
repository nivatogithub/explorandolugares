# Presentación única de lugares

La entrada pública `backend/integration-medicion/public/destino.php` carga el contenido y utiliza `backend/app/views/destination-main.php` para presentar todos los lugares e idiomas. `ensayos/v1/destino.php` es solamente una entrada de compatibilidad que delega, sin HTML alternativo.

Las cuatro revisiones locales usan esa misma vista PHP mediante un adaptador CLI de datos; no mantener una maqueta Python/HTML separada. Los datos de revisión no eluden la publicación ni la autenticación de las páginas públicas.

Los accesos Qué conocer, Historia y Directorio van en la portada. No existe una segunda barra de navegación del lugar. El enlace de registro se muestra alineado a la derecha inmediatamente debajo de la portada. No eliminarlo mediante scripts específicos de una ciudad.

Información práctica y eventos permanecen ocultos por defecto en la presentación, conservando contenido y datos. Activar esas funciones requiere una decisión explícita; no mostrar secciones vacías ni enlaces a bloques ocultos.

Los títulos de secciones no llevan descripciones introductorias ni controles para contraerlas. La historia sin imagen no reserva una columna vacía. Las variantes por destino deben limitarse a datos y medios, evitando reglas de diseño específicas por ciudad.

Esta modificación se valida localmente en los nueve publicados (tres idiomas) y los cuatro borradores. No equivale a despliegue en preproducción o producción; los activos nuevos siguen sujetos al bloqueo editorial de imágenes.
