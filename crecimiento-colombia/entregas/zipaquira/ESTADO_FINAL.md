# Zipaquirá · estado vigente

Revisión de criterios del 19-10-2026: **7 atractivos, 3 experiencias respaldadas y neutrales, 11 imágenes existentes, 30 negocios**. ZIP actualizado de 924.151 bytes, validadores nativos y editorial aprobados técnicamente. Pendiente reintegración coordinada y revisión humana; no publicar producción. Ver `AUDITORIA_CRITERIOS_2026-10-19.md` y `criterios-editoriales.json`.

## Historial de la entrega anterior (no describe el ZIP vigente)

19-10-2026 · Lugar 1 · rama `lugar/CO/cundinamarca/zipaquira`.

## Entregado y comprobado

- 8 atractivos priorizados, 5 experiencias separadas y textos breves ES/EN/PT.
- Historia breve de ancho completo, sin imagen o columna lateral.
- **14 ilustraciones finales de borrador**: portada subterránea, ocho atractivos y cinco experiencias. Maestros originales, derivados WebP, prompts, procedencia, fuentes y hashes conservados.
- **30 fichas gratuitas**: 6 alojamientos, 16 restaurantes/cafés y 8 comercios artesanales. Sin fotografías, descripciones, contactos deducidos o alianzas ficticias.
- `paquete-destino.zip`: **1.145.069 bytes** (~1,09 MiB), manifiesto v2 y 14 imágenes WebP distintas. Solo el WebP de portada de 1200 px y las 13 tarjetas de 640 px se incluyen; maestros y rechazadas no se suben al hosting.
- `paquete-negocios.zip`: manifiesto `business-import-v1`, 30 fichas sin imágenes.
- Lectores PHP reales del repositorio `DestinationPackage::readZip` y `BusinessPackage::readZip`: **pasan**, con 14 imágenes y 30 negocios.
- Verificados textos por idioma, longitud, orden Top8, ausencia de duplicados de nombres/imágenes, rutas, MIME, dimensiones, hashes y portada única en revisión editorial.

## Correcciones antes del empaquetado

Se rechazaron cuatro primeras salidas: relieves figurativos no documentados en la estación salina, torres diocesanas genéricas en dos escenas y una bandera ajena en Independencia. Sus sustitutas se generaron e inspeccionaron individualmente. Los originales rechazados quedan fuera del ZIP, con motivo y hash en `imagenes/revision-agente.json`.

La portada representa la **Catedral de Sal subterránea**; el atractivo Catedral Diocesana representa el templo exterior de la plaza. La tarjeta de Catedral de Sal tiene un encuadre cercano de cruz abstracta en roca, diferente de la nave panorámica de portada.

Se inspeccionaron las fotografías ya contenidas en la guía municipal de 2026. No se subieron fotografías externas al generador ni se republican en el paquete. El origen de las ilustraciones es generación independiente, no una licencia fotográfica inventada.

## Límites y revisión pendiente

El paquete está **completo para importar como borrador y revisar**, no aprobado para producción. El coordinador ya realizó la importación local y el QA del sitio: destino ID 25, 14 imágenes, 8 atractivos, 5 experiencias y 30 fichas; tres idiomas HTTP 200 y DOM móvil sin acordeones ni desborde horizontal. La revisión humana sigue pendiente.

El Colegio de Gabo usa una escena temática de memoria escolar; no se afirma que sea su patio actual. Museo Arqueológico representa una visita/exposición de cerámica, no piezas catalogadas ni salas exactas. Las experiencias son representaciones editoriales de actividades, no reservas, precios ni disponibilidad garantizados. Arquitectura y entorno son interpretaciones contrastadas, sin promesa de reconstrucción métrica. Horarios, aperturas y vigencia operativa de negocios permanecen sin confirmar.

El propietario debe revisar la vista real, textos e imágenes; cualquier aprobación debe quedar ligada al hash del componente. No hay aprobación transferida de Cartagena/Jericó ni publicación autorizada de Zipaquirá.

## Integración coordinada

1. Importar `paquete-destino.zip` mediante el flujo de borrador de preproducción. No publicar producción.
2. Con destino creado, importar `paquete-negocios.zip`; conservar 30 fichas como borradores gratuitos no reclamados.
3. Aplicar el diseño compartido vigente: secciones abiertas, Cargar más por filas, tarjetas homogéneas, historia completa a una columna, sin descripciones de sección.
4. Revisar portada de catálogo y página sincronizadas, ocho atractivos/cinco experiencias, tres idiomas y directorio 30. Obtener evidencia de navegador y registrar resultado de DB por separado del QA del ZIP.

## Reproducibilidad

`preparar.py` recrea la base editorial (deja imágenes pendientes por diseño). Después ejecutar `imagenes/optimizar.py` con Pillow, `empaquetar.py` y `validar.py`. No volver a importar el archivo `paquete-destino.borrador.json`: el actual completo es `paquete-destino.json` dentro del ZIP.

`revision-editorial.html` es una lectura independiente de los tres idiomas con imágenes; no suplanta la previsualización del sitio real. `qa.json` conserva los resultados exactos. Los estados anteriores de README describen únicamente el commit editorial inicial.

## Compatibilidad con guardado del destino

Se añadió una razón breve factual en ES/EN/PT porque `DestinationAdminService` exige `reasons` no vacío al guardar. Es un campo de compatibilidad; el diseño compartido lo mantiene oculto y no se agregó panel de motivos en la revisión editorial. Se conservan las mismas 14 imágenes y sus hashes.

## Integración local confirmada por el coordinador

Vista: http://127.0.0.1:8088/destinos/zipaquira/ · ID 25. Recibo SHA-256 `9a957abba75cd9a0d895dd1b3396d43f996ec5b55a220f26b3677748dbbdbdbe`. Esta actualización solo registra estado: no altera ZIP, contenidos, imágenes ni hashes de activos. Los resultados de `qa.json` corresponden al momento de validación anterior a la importación; el recibo posterior se registra en `checklist.json`. Publicación en producción y aprobación humana siguen pendientes.
