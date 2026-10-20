# Lote visual V4 · Zipaquirá · 20-10-2026

Estado: listo para integración por coordinador y revisión humana. Sin publicación en producción. No se han aprobado resultados en nombre del usuario.

## Tres propuestas nuevas

- **Tren:** `imagenes/maestros/tren-patio-fiel-v4.png`. Edición directa de fotografía CC BY-SA 3.0 de Nils Öberg identificada como patio ferroviario de Zipaquirá. Comparada: se conservan cerros, entorno industrial urbano, vías de patio, locomotora y coches hacia izquierda. Se eliminan anuncios. Hay simplificaciones de mecanismos y texturas; no es plano técnico. Fuente histórica con discrepancia 2007/EXIF2003: no garantiza locomotora actual ni condiciones de salida.
- **Recorrido urbano:** `imagenes/maestros/urbano-centro-fiel-v4.png`. Edición directa de fotografía CC BY-SA 3.0 de Rémi Jouan de plaza de los Comuneros, 2010. Comparada: eje de calle, muro de iglesia de piedra a derecha, edificio con balcón a izquierda y cerro reconocibles. Grupo discreto ilustrativo, no una salida documentada. No duplica el archivo de la tarjeta plaza.
- **Experiencia minera:** `imagenes/maestros/minera-tematica-v4.png`. Nuevo primer plano temático de manos, pico y roca salina, sin referencia fotográfica de terceros. Basado únicamente en la acción descrita por el operador. No representa su túnel, equipo exacto, guía ni sala. No tiene infraestructura inventada. Alt ES/EN/PT declara expresamente que es temática y no una vista del recinto. Restricciones operativas se mantienen en criterios editoriales y resumen público.

Los tres derivados WebP tienen 960×640; pesos 207778, 183228 y 140012 bytes respectivamente. Maestros, referencias, hashes, licencias y límites en `CORRECCIONES_REFERENCIA_V4.json`.

## Dos imágenes aún pendientes, sin sustitución genérica

- **Colegio Gabo:** edificio histórico del antiguo Liceo Nacional de Varones, hoy Casa del Nobel/centro cultural, calle7 con carrera8; no confundir con sede moderna de La Salle. La fuente identificada muestra salón y mobiliario recreado: https://zipaquiraturistica.com/blog/post/detras-de-la-pluma-los-anos-de-formacion-de-gabriel-garcia-marquez-en-zipaquira . No se acreditó licencia de adaptación de esa fotografía. No se entrega un patio inventado.
- **Casa Museo Quevedo Zornoza:** inmueble identificado en calle3 número7-69 por guía FONTUR: https://fontur.com.co/sites/default/files/2020-12/CAPITULO_ZIPAQUIRA.PDF . La foto identificada muestra casa de una planta, blanca con zócalo rojo, puertas/ventanas azules y teja; no corresponde al patio genérico anterior. No se acreditó permiso de adaptación de esa fotografía. No se sustituye por una casa colonial cualquiera.

La búsqueda acotada en Commons/Flickr y fuentes municipales no acreditó una fotografía exacta con permiso para esos dos componentes. Necesitan foto propia o autorización del titular; quedan sin imagen visible mediante overlay. No se ha contactado a terceros.

Museo Arqueológico V3 conserva fachada y obelisco de su referencia de 2020; no muestra colección interior inventada. La fuente de 266×190 limita detalle fino.

## Paquete y aplicación

`paquete-destino-correcciones-v4.zip` SHA-256 `247c85528e9ee48c535ae05e6b70aaabb2b45c2f3480eda8ec64bd893483b4d9`.

Conserva siete atractivos, tres experiencias, ES/EN/PT y directorio separado de treinta fichas. Incluye descripción de estación como llegada del tren desde Bogotá. Mantiene el orden justificado minera, tren, recorrido urbano, sin afirmar demanda medida.

Validación nativa del ZIP: once imágenes válidas. Criterios editoriales: siete atractivos/tres experiencias, sin errores. Sintaxis JavaScript del overlay: válida.

El esquema ZIP aún exige `image.file`: los dos archivos rechazados siguen dentro del paquete, no deben mostrarse. El coordinador debe actualizar `visual-gate.js` con `runtime/visual-gate-v4.js` en ambas rutas locales y cambiar versión de carga; oculta solo colegio y Quevedo. Sustituir página local de créditos con `creditos-referencias-v4.html`. No se ha ejecutado esta importación desde el subproceso; el coordinador aplica y comprueba navegador. La versión anterior ocultaba también minera y urbano y debe reemplazarse para ver las nuevas propuestas.
