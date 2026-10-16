# Indexación y URL públicas

Revisión del 16 de octubre de 2026. Cambios preparados localmente; no desplegados.

## Evidencia de producción

Las consultas públicas devolvieron HTTP 200 para robots.txt, sitemap.xml, la guía
`/destinos/jerico/` y `/negocios-locales/`. Robots permite el sitio y señala el sitemap.
Jericó declara `index,follow` y canonical a su URL limpia. La plantilla
`/destino.php?destino=jerico&idioma=es` también responde 200: hay una variante
duplicada, aunque el canonical ya indica la preferencia correcta.

El sitemap servido es estático. Se genera antes de las integraciones finales del
paquete: no consulta las publicaciones de la base de datos ni incluye cobertura.
Esto limita el descubrimiento; no demuestra por sí solo el motivo de exclusión
que Google asignó a una URL. No se ha accedido a Search Console ni a sus informes.

## Funcionamiento preparado

- `.htaccess` reescribe internamente `/sitemap.xml` a `sitemap-live.php`.
  El visitante y Google conservan la dirección habitual del sitemap.
- `PublicSitemap` combina el inventario estático final con publicaciones actuales.
  Cada petición consulta la base: publicar/archivar un destino incorpora/retira
  su URL y las traducciones existentes, sin reconstruir el sitio.
- Los negocios deben estar publicados, con destino publicado y derechos vigentes
  de página propia (`has_business_page` y `business_page`). Se excluyen pendientes
  de revisión, cerrados definitivamente y planes vencidos o pendientes de pago.
  Un destacado sin página propia no genera una URL nueva: su presencia está en
  el destino. El directorio gratuito permanece disponible.
- Solo se anuncian traducciones realmente existentes de negocios; el renderer
  actual permite fallback español, pero no se promocionan copias de idioma vacías.
- Las páginas HTML nuevas se detectan al preparar la entrega, después de aplicar
  las integraciones. Deben tener canonical coherente y no contener `noindex`.
  Una nueva ruta PHP diferente de destinos/negocios requiere un proveedor de URL
  explícito; no se adivinan rutas recorriendo archivos privados del servidor.
- Las peticiones directas a las plantillas de destino/negocio redirigen con 301
  hacia la URL limpia, después de validar existencia y publicación. Las vistas
  previas privadas conservan sus controles y no se redirigen ni indexan.
- No se crean tablas, no se publica contenido y no se inicia sesión al consultar
  el sitemap. Ante fallo de base/configuración/XML se responde 503 sin secretos.
  No se inventa `lastmod`. El límite de 50.000 URL bloquea la respuesta y exige
  implementar partición en un índice antes de alcanzar ese volumen.

## Publicación y comprobaciones pendientes

Realizar entrega completa revisada, con aprobación humana: incluye reglas del
hosting, endpoint, clase privada, plantillas y sitemap estático final. No usar
un despliegue parcial de cobertura. Conservar las protecciones de paridad y los
medidores pendientes; no sustituir configuración ni modificar datos.

Después, verificar por HTTP `/sitemap.xml`: XML válido, sin cookies ni
`X-Robots-Tag: noindex`; URL publicadas 200 y canonical consistente; plantillas
directas 301 sin bucles; borradores 404 o vista previa privada. La validación
local SQLite no reemplaza la comprobación de MySQL y Apache en hosting.

En Search Console: enviar una vez `/sitemap.xml`, inspeccionar inicio, Jericó y
un negocio elegible, revisar canonical elegido, última lectura del sitemap y
motivo de exclusión. No solicitar manualmente cada nueva URL ni usar la Indexing
API para fichas turísticas: Google limita esa API a JobPosting y BroadcastEvent.
El sistema automatiza descubrimiento y señales; Google decide rastreo e indexación.

Fuentes oficiales:
- https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap
- https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls
- https://developers.google.com/search/docs/crawling-indexing/canonicalization-troubleshooting
- https://developers.google.com/search/apis/indexing-api/v3/using-api

Pruebas: `php backend/tests/seo-sitemap.php`, pruebas de `test_seo_release.py`
y la validación completa del repositorio. La clase de sitemap de destinos toma
como base el trabajo local existente en `advance-admin`; se amplía aquí a negocios
y al inventario de la entrega final, sin fusionar otros cambios de esa rama.
