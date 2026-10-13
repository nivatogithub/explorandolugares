# Eventos dentro de cada destino

Decisión del propietario, 11 de octubre de 2026: retirar Agenda del menú principal y denominar Eventos a la sección dentro de cada landing de municipio. En inglés: Events. Jericó utiliza `destinos/jerico/index.html#eventos`. Las rutas anteriores redirigen al destino o a Destinos, evitando mantener una sección global duplicada.

## Fuentes externas y mantenimiento

Revisión inicial, no integración confirmada:

- [Medellín.Travel](https://www.medellin.travel/calendario-de-eventos-busqueda/) ofrece un calendario público para Medellín. No se verificó permiso o soporte de iframe, API o feed reutilizable. Es una fuente candidata para enlazar desde la futura página de Medellín, no para mostrar eventos de Jericó.
- [Google Calendar](https://support.google.com/calendar/answer/41207?hl=es) permite incrustar calendarios públicos. Para que lo vea cualquier visitante, el calendario debe compartirse públicamente. Se necesita un calendario pertinente del municipio y un responsable que mantenga sus eventos actualizados. No se ha localizado ni configurado uno para Jericó.
- Se encontraron publicaciones de Comfenalco sobre eventos concretos de Jericó, pero una publicación fechada no demuestra un calendario municipal permanente ni una integración automática. No se copiaron eventos pasados como próximos.
- No se confirmó una fuente única, actualizada y reutilizable que cubra los 21 municipios del piloto.

Una página pública no necesariamente admite iframe: [X-Frame-Options](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-Frame-Options) y CSP frame-ancestors pueden impedirlo. No eludir estas restricciones. Un iframe no permite aplicar libremente nuestro diseño o filtrar su contenido por municipio; eso debe soportarlo el proveedor. La actualización de la fuente tampoco garantiza traducción al inglés.

## Propuesta por etapas

1. Por destino, mantener pocos enlaces directos a programación pertinente de fuentes verificadas, mostrando quién publica. Esto evita copiar cada evento, aunque los enlaces deben revisarse.
2. Cuando exista un calendario público pertinente y con soporte de incrustación, incorporar su vista con enlace alternativo. Evaluar funcionamiento móvil, idioma y carga antes de habilitarlo. No cargar iframes vacíos, calendarios personales ni páginas de terceros sin soporte confirmado.
3. Para conservar tarjetas propias, incorporar posteriormente una API o feed ICS autorizado, con municipio, identificación de origen, fechas, cancelaciones y deduplicación. Requiere una sincronización programada y gestión de errores; no es una función implementada hoy.

Los organizadores podrían mantener un calendario compartido por municipio si se acuerda su responsabilidad editorial. Eso reduce el trabajo técnico de introducir eventos, pero alguien debe seguir manteniendo las fechas y cancelaciones.

Estado actual: navegación y nombre implementados, estado vacío honesto en Jericó. Ningún iframe ni importación automática activado. No se crean calendarios, cuentas o enlaces de participación con datos inventados.
