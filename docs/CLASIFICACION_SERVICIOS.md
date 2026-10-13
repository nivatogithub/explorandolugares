# Una clasificación general, una plantilla común

La clasificación vigente vive en `content/service-categories.json`. Sustituye las 29 variantes anteriores por once tipos generales. Se comparte entre formulario, validación del servidor, agrupaciones del destino y ficha del participante mediante `tipoServicio`.

| Tipo general | Incluye | Información que ayuda a decidir en la ficha |
| --- | --- | --- |
| Hospedaje | Hoteles, hostales, fincas, cabañas, glamping | Ubicación, modalidades, capacidad y servicios confirmados |
| Alimentación | Restaurantes, cafés, panaderías, heladerías | Qué ofrece, horarios, menú si está disponible y opciones alimentarias confirmadas |
| Venta de licores | Licoreras y expendios | Productos y horarios; distinguir venta de consumo en el local |
| Bares, discotecas y vida nocturna | Bares, pubs, discotecas, nightclubs | Ambiente, horarios, música y condiciones de acceso confirmadas |
| Supermercados, tiendas y comercio | Supermercados, tiendas, artesanías, productores | Productos, ubicación y entrega si la ofrece |
| Farmacia y salud | Farmacias y servicios de salud | Servicios, horarios y contacto; no atribuir atención de urgencias sin confirmación |
| Experiencias y actividades | Recorridos, guías, aventura, naturaleza, operadores | Duración, punto de encuentro, dificultad, requisitos e inclusiones |
| Cultura y entretenimiento | Museos, galerías, talleres, espacios de espectáculos | Propuesta, horarios, accesibilidad y programación relacionada |
| Transporte | Traslados, taxis, transporte y alquileres | Cobertura, modalidad, punto de salida y condiciones |
| Servicios locales | Bienestar, belleza, profesionales, técnicos y oficios | Qué resuelve, ubicación/cobertura, horarios o citas |
| Otro | Propuestas que aún no encajan | Revisión editorial para evitar crear categorías prematuramente |

## Qué cambia en el formulario

Una sola pregunta de clasificación: «¿Qué tipo de servicio ofreces?». Se conserva una descripción opcional para explicar la propuesta. No se agregan preguntas específicas de hoteles, restaurantes o experiencias al primer contacto. Un negocio mixto selecciona su actividad principal; puede describir sus otras actividades en el texto. La ficha posterior puede enumerarlas como servicios, sin duplicar participantes.

## Qué cambia en la plantilla

La estructura sigue siendo común: nombre, tipo general, descripción, imágenes autorizadas, lo que ofrece, ubicación/horarios cuando corresponda y contacto directo. Se cambia «Conoce este lugar» por «Conoce su propuesta», válido también para transporte, guías o servicios sin local abierto al público.

Las diferencias de la tabla orientan la recopilación editorial de información, no representan campos o funciones ya implementados. Hoy la ficha admite descripción, imagen, lista de servicios, dirección, horarios y contacto; no hay reservas, inventario, menús estructurados, precios automáticos ni formularios especializados. Los bloques específicos deben añadirse cuando un participante real los necesite, manteniendo una sola plantilla.

La categoría organiza y permite filtrar; no determina un diseño diferente. No se publican categorías vacías. Los eventos siguen dentro de cada destino; un organizador puede clasificarse como cultura y entretenimiento, mientras que cada evento concreto conserva su fecha y entidad propia.

## Compatibilidad y datos

Los identificadores nuevos son estables: `hospedaje`, `alimentacion`, `licores`, `vida-nocturna`, `comercio`, `salud`, `experiencias`, `cultura`, `transporte`, `servicios`, `otro`. La generación de fichas requiere `tipoServicio` válido y deriva las etiquetas ES/EN de la clasificación compartida, evitando que cada ficha invente categorías. Esto sustituye el requisito anterior de ingresar `categoria` y `categoriaEn` manualmente.

Los contactos históricos no se modifican. Los valores antiguos se reconocen para mostrarlos agrupados en la administración; nuevas solicitudes utilizan los once identificadores. No hay migración de base de datos ni publicación automática. Los 21 municipios del piloto y las validaciones de consentimiento/contacto permanecen vigentes.
