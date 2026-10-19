# Coveñas — borrador depurado para revisión

Rama `lugar/CO/sucre/covenas`. Paquete vigente: **cinco atractivos, una experiencia, siete imágenes y 30 fichas**, con textos ES/EN/PT-BR. Hasta ocho es un máximo; no se completan cupos.

## Selección actual

Primera Ensenada, Segunda Ensenada y malecón agrupados, Ciénaga La Caimanera, Museo de Infantería de Marina y La Coquerita. Consulta las condiciones actuales de acceso, especialmente al recinto militar del museo.

La única experiencia es **Recorrido por manglares**, con descripción neutral. La oferta pública concreta y sus oferentes se comprueban en `criterios-editoriales.json`, solo para auditoría interna. No se anuncian proveedores, paquetes, contactos ni precios gratuitamente dentro de la experiencia.

Se apartaron Coveñitas por solapamiento no despejado, Represa de Villeros y Casa de la Cultura por falta de acceso o propósito turístico actual suficientemente contrastado. Caminar por el malecón y comer son planes autónomos; el kayak queda como candidato sin oferente concreto comprobado. La investigación y los originales se conservan. Ver `AUDITORIA_EDITORIAL.md` y `prioridades.json`.

## Archivos y evidencia

- `paquete-destino-covenas.zip`: manifiesto v2 y los siete WebP referenciados; sin maestros PNG.
- `paquete-negocios-covenas.zip`: 30 fichas gratuitas documentales, sin cambios ni suscripciones inventadas.
- `criterios-editoriales.json`: decisión, finalidad, fuentes y oferta por componente.
- `procedencia-imagenes.json`: prompts, herramienta, hashes y aprobación pendiente; distingue imágenes actualmente incluidas de las conservadas fuera del paquete.
- `validar.php`: validación nativa de manifiestos, directorio y referencias de imágenes. Usar además lector nativo DestinationPackage y `scripts/validar-criterios-editoriales.py`.

El museo usa una ilustración temática de historia naval, no un registro de su colección o distribución exacta. Las ilustraciones restantes interpretan los rasgos generales del entorno, no condiciones actuales ni un levantamiento georreferenciado. No se generaron imágenes adicionales para esta depuración.

Las 30 fichas provienen de ocho sitios propios, una vitrina empresarial y 21 listados secundarios. Operación, domicilio y canales siguen pendientes de verificación; no son aliados ni comercios inscritos confirmados.

## Preproducción

http://127.0.0.1:8088/destinos/covenas/

Importación exclusivamente local en MariaDB, id26; sin duplicar negocios. Aprobación humana pendiente. Jardín tiene aprobación propia y no transfiere aprobación a este borrador. No se ha publicado este lote en producción.

Diseño compacto común de Cartagena/Jericó: historia sin imagen, secciones abiertas, tarjetas homogéneas, descripciones breves y Cargar más.
