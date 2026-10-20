# Contacto y ubicación por suscripción

Regla aprobada por el propietario el 20 de octubre de 2026:

| Información pública | Gratis | Destacado | Página Comercial | Premium |
|---|---|---|---|---|
| Ubicación y cómo llegar | No | Sí | Sí | Sí |
| Teléfono fijo/celular y WhatsApp | No | Sí | Sí | Sí |
| Correo e Instagram | No | No | Sí | Sí |
| Sitio web, Facebook y LinkedIn | No | No | No | Sí |

`BusinessContactVisibility` aplica esta regla en los repositorios públicos y en los renderizadores. La suscripción efectiva se obtiene de las suscripciones activas y vigentes; al vencer, el negocio vuelve al comportamiento gratuito. Los canales privados (`is_public=0`) nunca se publican. La regla solo filtra la presentación: no borra datos ni convierte canales privados en públicos. Guardar un canal tampoco autoriza campañas.

La tabla comercial, los formularios y las páginas mantienen la misma distribución. Las páginas Premium conservan además sus secciones, límites de contenido, fotografías y URL personalizada. No cambian precios, pagos ni datos históricos.

La URL principal es `/negocios-locales/suscripciones/`, con equivalentes `/en/` y `/pt-br/`. Las rutas antiguas `/planes`, `/planes/` y `/planes/index.html` redirigen con 301 conservando los parámetros. Enlaces internos, canonical, alternates y sitemap usan la ruta nueva. Los archivos antiguos se conservan únicamente como redirección no indexable, sin una segunda oferta comercial.
