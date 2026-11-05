# Inventario de preparación y presentación

Estado de la revisión del 4 de noviembre de 2026. La preparación no es una prueba de que todas las interacciones estén correctas.

- `localize-coverage.py`: genera JavaScript de cobertura en inglés y portugués mediante sustituciones de texto. Sustituir por un único módulo con traducciones explícitas y comprobar mapa, filtros, contadores y cambio de idioma.
- Estilos: las reglas finales se trasladaron a diez hojas fuente; se retiraron `apply-portal-identity.py` y `consolidate-shell-css.py`. La preparación comprueba la propiedad del shell sin reescribir hojas. La entrega pública resultó idéntica byte por byte en la comparación inicial; falta completar la revisión visual e interactiva.
- `version-analytics.py`, `version-presentation.py` y `version-resources.py`: cambian referencias de recursos para invalidar caché, y algunos recursos se reescriben para versionar dependencias. Mantener la invalidación de caché al unificar el mecanismo; comprobar rutas PHP, HTML, JS y CSS y dependencias antes de retirar cualquiera.
- `clean-public-package.py` y `retire-presentation-resources.py`: filtran archivos que no deben llegar a la entrega. Llevar las exclusiones al inventario de entradas cuando corresponda, preservando licencias, medios aprobados y recursos con referencias dinámicas.
- `apply-place-routes.py` y reglas de alias: preparan configuración de redirecciones, sin recomponer páginas. Deben conservar compatibilidad de rutas y consultas.
- `generate-release-sitemap.py`: genera el sitemap. Es una salida SEO necesaria; no modifica HTML.
- `compose-presentation.py` y `check-social-metadata.py`: comprueban la entrega; no recomponen páginas.

El transformador automático de terminología y el generador que recomponía tarjetas del directorio fueron retirados. La validación visual e interactiva completa, el flujo Premium y las pruebas pendientes siguen abiertos hasta contar con evidencia explícita.

## Recorrido público de preproducción

El 4 de noviembre se revisaron por HTTP las 102 URL que entregaba el sitemap dinámico y 256 recursos locales referenciados (imágenes, hojas y scripts): 101 páginas respondieron y los 256 recursos cargaron. Se detectó una ficha de prueba vencida incluida por error debido a zonas horarias distintas. El sitemap ahora utiliza UTC como el repositorio y un segundo recorrido verificó 101 URL sin errores y 256 recursos disponibles. Este recorrido no equivale a una validación visual ni de todas las interacciones en navegador.
