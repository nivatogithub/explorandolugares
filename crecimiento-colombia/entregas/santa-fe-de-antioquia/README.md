# Santa Fe de Antioquia · borrador editorial vigente

Worker Lugar 2 · rama `lugar/CO/antioquia/santa-fe-de-antioquia` · 19/10/2026.

## Entrega actual

- **Siete atractivos**, sin rellenar un mínimo; **una experiencia organizada neutral**, con evidencia de oferente/oferta solo en expediente interno.
- Textos ES/EN/PT, introducción directa, historia breve fullwidth sin imagen ni panel de motivos visible. El esquema mantiene una razón factual en los tres idiomas.
- **Nueve ilustraciones diferentes** en el ZIP; nueve WebP principales y variantes640 para uso responsivo, con PNG maestros fuera del ZIP web. Quince generaciones históricas: dos rechazadas y cuatro activos posteriormente retirados por criterios editoriales; no confundir generación con inclusión o aprobación.
- **30 fichas gratuitas documentales**:16hospedajes,8comercios de filigrana,6transportes. Fuente municipal antigua y algunas fuentes propias; vigencia operativa pendiente, sin canales inventados, fotografías o descripciones.

## Archivos para integrar

`paquete-destino-santa-fe-de-antioquia.zip` y `paquete-destino.json` son la entrega vigente. El ZIP solo incluye manifiesto y medios efectivamente referenciados. `paquete-negocios-30.zip` es el paquete separado de directorio. `criterios-editoriales.json` y `AUDITORIA_EDITORIAL_V2.md` son expediente interno: no publicidad dentro de las experiencias. `CONTACTO_VISUAL.jpg` muestra solo los componentes actuales.

`PROCEDENCIA_IMAGENES.json` conserva prompts, herramienta, maestros, derivados, hashes y estado de inclusión. `GENERACIONES_DESCARTADAS.json` y `REVISION_VISUAL.md` explican las correcciones del Puente/Catedral y el alcance contextual de museos/actividades. Una escena ilustrada no prueba la existencia del servicio ni reproduce exactamente un recinto.

## Pruebas

`validar.py` verifica traducciones, razones, límite máximo8 sin mínimo y sin cuota de experiencias, nombres únicos del directorio, fuentes y archivos de imagen. `validar-nativo.php` lee ZIP con DestinationPackage y aplica DestinationAdminService sin base de datos ni guardado; verifica también el manifiesto de negocios. `../preprod/scripts/validar-criterios-editoriales.py` comprueba elegibilidad documentada y neutralidad. `VALIDACION_ZIP.json` guarda el hash del ZIP actual. Estas pruebas no sustituyen revisión factual ni aprobación humana.

## Estado real

El paquete anterior fue integrado por coordinación en http://127.0.0.1:8088/destinos/santa-fe-de-antioquia/ (id4) y comprobado HTTP200ES/EN/PT y móvil sin acordeones/desbordes. Recibo histórico: `30fbc70b8f4815f1a6ed14c9b8ec5f3b68c6ca3b84102ef4cc89206c7149aaca`. **Esta revisión editorial necesita reimportación y QA nuevos:** ese recibo no corresponde al ZIP actual.

El coordinador debe retirar/archivar los slugs excluidos según criterios-editoriales.json:un importador que conserva trabajo existente puede mantenerlos si no se hace la transición explícita. Este worker no cambió preprod, DB o producción. Santa Fe sigue pendiente de revisión/aprobación humana.

## Presentación

Reutilizar diseño compartido Cartagena/Jericó: neutros, historia fullwidth sin imagen, secciones abiertas, textos cortos, tarjetas homogéneas3:2 y Cargar más según2filas con foto/4filas pequeñas. No copiar scripts por ciudad que sustituyan contenido ni introducir promoción del oferente como enlace editorial. Puente de Occidente es excursión compartida con Olaya, no expansión ficticia de jurisdicción.
