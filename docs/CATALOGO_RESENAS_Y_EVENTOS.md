# Catálogo, reseñas, rankings y eventos

Fecha: 11 de octubre de 2026. Requisitos incorporados por el propietario; diseño técnico propuesto. No equivale a funciones implementadas ni autoriza a inventar datos.

## Base del producto

Todos pueden navegar públicamente. El contacto de un comercio es una solicitud privada; no crea una cuenta ni una ficha pública. El administrador confirma la información y crea la publicación. Pueblos y ciudades son destinos dentro de la misma arquitectura.

Separar las solicitudes de contacto (`leads`) de las entidades públicas. Una ficha puede estar asociada a un contacto, pero nunca mostrar automáticamente su nombre personal, correo o teléfono. Solo publicar los canales autorizados del negocio.

## Fichas y calificaciones

Cada hotel, restaurante, bar, comercio, actividad o servicio tendrá su propia ficha y URL estable. Una reseña se relaciona con el ID de esa ficha, no con el nombre del negocio ni con su categoría. Un cambio de nombre o de URL no pierde el historial.

Propuesta de experiencia: calificación de 1 a 5, cantidad de opiniones y comentarios públicos con fecha y nombre visible elegido por el autor. Sin opiniones, mostrar “Aún no hay opiniones”, nunca 0 estrellas. El promedio y el contador consideran únicamente reseñas publicadas; no incluir pendientes, rechazadas u ocultas. No traducir silenciosamente las opiniones de los usuarios; conservar el idioma original.

La lectura de opiniones es pública. Su envío requiere un mecanismo de verificación a definir al implementar (por ejemplo, confirmar correo sin crear una cuenta completa). No reintroducir registro de usuarios en esta primera versión. No marcar “visita verificada” salvo que exista evidencia de visita, independiente de la verificación del correo.

Moderación propuesta: pendiente → publicada, rechazada u oculta, conservando motivo y fecha de decisión de forma privada. Permitir reportar contenido y gestionar correcciones; la misma política se aplica a opiniones favorables y críticas. El panel debe separar contacto personal privado del nombre mostrado. No permitir que pagar pauta cambie la puntuación o elimine críticas legítimas. Respuesta del negocio y cuentas de propietarios pueden añadirse después.

## Rankings con significado claro

Tres vistas diferentes:

| Vista | Qué mide | Qué mostrar |
| --- | --- | --- |
| Mejor valorados | Opiniones publicadas | Puntuación, cantidad de opiniones y regla de elegibilidad |
| Más consultados en Explorando Lugares | Consultas de fichas o páginas de destino dentro del portal | Periodo, metodología y fecha de actualización |
| Destacados | Selección editorial o, si se define, espacio patrocinado identificado | Motivo de selección o etiqueta de patrocinio |

“Más visitados” presencialmente no se puede deducir de las visitas al sitio web. Hasta contar con una fuente fiable para ese dato, usar “Más consultados en Explorando Lugares”. Pueblos/ciudades y actividades tendrán rankings separados; no mezclar consultas a destinos con consultas a fichas.

Propuesta inicial de periodo: últimos 30 días, configurable. Antes de activar, definir deduplicación, exclusión de robots, previsualizaciones y tráfico administrativo, tratamiento de datos y conservación. Las vistas repetidas de una misma persona no deben inflar sin límite el ranking. No afirmar exactitud de visitantes únicos o visitas físicas.

Para “Mejor valorados”, acordar un mínimo de opiniones y una regla que considere el tamaño de muestra; no ordenar sin matiz un único 5/5 por encima de cientos de opiniones. Empates con criterio estable. No publicar rankings vacíos ni valores ilustrativos que parezcan reales.

## Eventos

Primera forma de publicación propuesta: el administrador crea eventos desde el panel, sin que el organizador necesite una cuenta. Los interesados pueden contactar para proponer un evento; recepción no equivale a aprobación. No hay venta de entradas, reservas ni pagos.

Datos del evento: título, destino, lugar, organizador y contacto público autorizado, inicio y fin con zona horaria, descripción en español e inglés, categoría, imagen con permisos y fuente de confirmación. Añadir precio solo si está confirmado; ausencia de precio no significa entrada gratuita. No derivar fechas o recurrencia por suposición.

Estados: borrador, publicado, cancelado, finalizado y archivado. Solo mostrar eventos publicados con información confirmada; los cancelados deben conservar una advertencia visible en su URL para evitar confusión. Al terminar, salen de “Próximos” y pueden permanecer en archivo. Un evento de varios días sigue vigente hasta su fin; un evento recurrente necesita fechas concretas por ocurrencia.

Agenda general y agenda por destino comparten los mismos registros. La portada puede mostrar próximos eventos cuando existan datos. Cada evento tiene URL propia y puede vincularse con una ficha de negocio o actividad, sin obligar a que exista esa ficha.

## Modelo de datos previsto, sin migraciones aplicadas

| Entidad | Relación principal |
| --- | --- |
| Destinos | Pueblo/ciudad, nombre, slug, estado de publicación |
| Categorías | Taxonomía general, identificador estable |
| Fichas | Destino + categoría, datos públicos autorizados, estado y traducciones |
| Reseñas | Ficha + puntuación + comentario + idioma + estado de moderación; verificación privada separada |
| Estadísticas diarias | Destino o ficha + fecha + consultas agregadas bajo una metodología definida |
| Eventos | Destino + fechas/zona horaria + organizador + estado; ficha opcional |
| Historial editorial | Entidad + acción + fecha + administrador |

Usar relaciones por ID, UTF-8, fechas de auditoría en UTC y zona horaria explícita para eventos. La infraestructura debe permitir español e inglés, sin duplicar una reseña o un evento al cambiar de idioma. No calcular rankings con las solicitudes de contacto ni publicar sus datos.

## Orden propuesto

1. Conectar el contacto comercial y el panel al hosting; terminar la configuración pendiente.
2. Añadir gestión de destinos y fichas reales desde el panel: borrador, revisión y publicación manual.
3. Añadir creación y publicación de eventos al mismo panel y alimentar ambas agendas.
4. Incorporar reseñas públicas con verificación y moderación, sin exigir inicialmente cuentas completas.
5. Medir consultas y publicar rankings cuando haya datos suficientes y reglas definidas.

Estado actual: contacto comercial y panel básico implementados localmente; conexión al hosting pendiente. Catálogo vacío; agenda con estado vacío. Reseñas, estadísticas, rankings y editor de eventos **todavía no implementados**. Este documento evita que la primera entrega cierre la arquitectura a estas capacidades.
