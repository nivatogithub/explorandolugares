# Portal → destino → participante

El hilo conductor ayuda a conectar las partes del viaje, sin imponer duración ni prometer reservas, pagos o planificación automática.

1. **Portal:** inspira, explica el propósito y conecta con destinos. Selector Pueblo/Ciudad conservado.
2. **Destino:** Jericó es la primera landing. Presentación, identidad cultural, preparación de la visita, participantes y agenda. Las categorías son agrupaciones dentro del destino y aparecen solo con participantes publicados.
3. **Participante:** ficha pública resuelta por la ruta PHP del portal a partir de su identificador. Incluye descripción, imagen autorizada si existe, servicios, ubicación y contacto directo. No hay archivos HTML por negocio ni cuentas obligatorias para visitantes.

Las fichas de negocios se renderizan únicamente con PHP y datos verificados de la base de datos. No se generan archivos HTML de negocios durante el empaquetado. Las reseñas, rankings y edición de eventos siguen pendientes; no se muestran valores ni opiniones ficticias.

Cada registro necesita `destino: jerico`, `slug` único con letras minúsculas/números/guiones, `nombre`, `categoria`, `categoriaEn`, `descripcion`, `descripcionEn`, `contactoUrl` HTTP(S) verificado, `registroConfirmado: true`, `informacionVerificada: true`, `estado: publicado`. Opcionales: `nombreEn`, `imagen` relativa dentro de assets, `servicios` y `serviciosEn` con igual longitud, `direccion`/`direccionEn`, `horarios`/`horariosEn`. Los nombres propios y direcciones pueden conservarse en ambos idiomas. La carga es editorial; el formulario de contacto no publica registros automáticamente.

El empaquetado conserva las páginas institucionales que son estáticas, pero los destinos y negocios siempre se resuelven con PHP y datos del catálogo/base de datos. El despliegue sigue siendo un paso independiente. SEO de producción, dominio y configuración de contacto deben cerrarse al conectar el hosting; el entorno local conserva noindex.

Se retiraron etiquetas visibles de revisión y anotaciones sobre imágenes conceptuales. Se mantiene la atribución exigida por la licencia de la foto de Jericó, y los estados funcionales honestos de formulario, catálogo y agenda.

Contenido de Jericó basado en [Colombia Travel](https://colombia.travel/es/jerico), consultado el 11 de octubre de 2026: cultura, paisaje cafetero y tradición del carriel. No se anuncian horarios, operadores, tarifas ni actividades reservables sin datos confirmados.

Verificación: enlaces y anclas ES/EN, sintaxis JavaScript y pruebas de publicación dinámica de fichas (escape HTML, exclusión de borradores/no verificados, seguridad de contacto y slugs duplicados). Revisión visual en navegador pendiente.

## Clasificación vigente de servicios

Cada participante usa ahora `tipoServicio` del catálogo global de once categorías. Las etiquetas `categoria` y `categoriaEn` se derivan al renderizar la ficha; ya no se ingresan manualmente. Formulario y catálogo comparten la clasificación. Ver `CLASIFICACION_SERVICIOS.md` para alcance de la plantilla común y diferencias editoriales por tipo.

## Directorio con pestañas y tarjetas

Jericó reemplaza el bloque de conexión local por «Directorio de Jericó». Todos y categorías generales funcionan como pestañas; solo aparecen tipos con participantes publicados, registrados y verificados. Se mantiene el orden del catálogo global. Selección mediante clic o teclado (flechas, Inicio/Fin); el panel y la selección tienen semántica accesible.

Cada tarjeta incluye foto autorizada (`imagen` relativa dentro de `assets/`), nombre, categoría, resumen ES/EN y enlace a la ficha del participante. Sin foto se usa la inicial del nombre, sin imágenes de otro comercio. No se añadieron comercios de ejemplo ni imágenes ficticias a los datos del sitio. La rejilla utiliza tres columnas, dos o una según ancho.

El catálogo sigue vacío: se muestra solo Todos y una indicación breve de que no hay lugares publicados. La llamada comercial se conserva al final de la landing, separada del directorio. Para poblarlo hacen falta datos y fotos reales autorizadas de cada participante.

## Directorio organizado por necesidades del viaje

Se unifican los cuatro bloques extensos de preparación y el directorio, reduciendo la extensión vertical. Las pestañas son agrupaciones para el visitante, distintas de la clasificación de once servicios que usa el negocio:

- Cómo llegar y moverte: transporte.
- Dónde quedarte y comer: hospedaje y alimentación.
- Qué hacer y disfrutar: experiencias, cultura y vida nocturna.
- Compras y servicios: comercio, venta de licores, salud, servicios locales y otro.

La relación está en `travelGroups` dentro de `content/service-categories.json`, compartida en el JSON público. El generador comprueba que los once servicios estén asignados exactamente una vez. Solo aparecen agrupaciones con participantes publicados; sin catálogo se mantiene Todos. El enlace al mapa se conserva junto al directorio.

La misma rejilla responde a pestaña, búsqueda por palabras (sin distinguir mayúsculas ni tildes), tipo de servicio, calificación mínima y orden. Los filtros se combinan; cambiar de pestaña reinicia el tipo de servicio para evitar selecciones incompatibles. Las tarjetas se paginan a seis por página. Un filtro sin resultados muestra un estado distinto del catálogo vacío.

### Calificaciones pendientes de una fuente real

No se han implementado recepción, moderación ni almacenamiento de reseñas. Por tanto no hay calificaciones reales ni estrellas en el sitio. El filtro mínimo y la opción de ordenar por mejor calificados permanecen deshabilitados.

El directorio admite para una integración futura `resenasHabilitadas: true` en el catálogo y, por participante, `valoracionPublica: {promedio, total}`. Solo debe habilitarse cuando una fuente confiable calcule estos valores desde reseñas publicadas. La interfaz exige promedio entre 1 y 5 y cantidad entera positiva; estas comprobaciones de formato no reemplazan la moderación ni verifican opiniones. No editar puntuaciones manualmente para simular reputación. Sin reseñas válidas, ningún negocio recibe puntuación.

Pruebas en memoria: búsqueda de varias palabras sin tildes, combinación con filtros, paginación, orden por nota, ausencia de reputación cuando no se habilitan reseñas, exclusión de borradores, navegación por teclado y equivalentes ES/EN. No se publicaron los datos sintéticos de prueba.
