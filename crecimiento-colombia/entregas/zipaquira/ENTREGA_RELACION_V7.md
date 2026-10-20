# Estación y experiencia ferroviaria · V7

20-10-2026. Colegio Gabo y Quevedo aprobados por usuario: no se modifican. No se modifican imágenes, sus metadatos ni recorte de Catedral.

Base real: ZIP V6 del stage de preproducción SHA `6934821b6f1e3863ac8d9a473a483a18037da73706ef965d36a9d287a2284639`. Se verificó que todos los archivos de imagen y metadatos siguen idénticos en el candidato.

## Cambio preparado

Textos ES/EN/PT conectan explícitamente estación patrimonial, llegada desde Bogotá y experiencia ferroviaria. Se conserva la brevedad y no se introduce publicidad del operador. El itinerario propio consultado hoy enumera la Estación del Tren de Zipaquirá como llegada y partida de regreso: https://turistren.com.co/planifica-tu-viaje/ . El nodo cartográfico corrobora la estación, sin probar geometría del andén. No se añaden precios, horarios rígidos ni promesas de locomotora.

`runtime/place-relations.js` es un módulo reutilizable configurado por destino y slugs. Añade icono ferroviario SVG al título de la estación y enlaces recíprocos a las tarjetas relacionadas: «Ver la experiencia del tren» / «Ver la estación de llegada», con traducciones. Anclas en la misma página, sin nuevas pestañas ni enlaces sobre fotos. El destino recibe foco y, si está oculto por el paginador móvil, se usa su botón existente «Cargar más» para revelarlo sin romper el número de filas.

## Integración por coordinador

- Aplicar los dos cambios de descripción de `PATCH_RELACION_FERROVIARIA_V7.json` selectivamente.
- Copiar `runtime/place-relations.js` como `/assets/place-relations.js` en fuente y release locales; cargarlo con `<script defer src="/assets/place-relations.js?v=zipaquira-v7"></script>` en la página. El módulo se limita a la relación configurada de Zipaquirá.
- Conservar CSS V6, aprobaciones, imagen original del tren V5 y activos del colegio/museo V6.
- Comprobar ambos enlaces por teclado y en móvil cuando la estación esté tras «Cargar más»; comprobar idiomas y que ninguna imagen se abre en otra pestaña.

Candidato completo de respaldo: `paquete-destino-relacion-v7.zip`, SHA `05a2dc00ca4e6e002dae2beee56be241cdce60ab7ad643967d5370b1fad80dcd`. JS SHA `0b176a328de099c2cc3ca14a4b975e7ea5fe6bdf9321b925902e91dfa20e040e`.

Validación nativa ZIP: 11 imágenes válidas. Criterios editoriales: 7 atractivos/3 experiencias, sin errores. Sintaxis JS válida. Prueba en navegador e importación pendientes del coordinador; no se presentan como realizadas. Producción intacta. No se generan ni buscan más imágenes para este cambio.
