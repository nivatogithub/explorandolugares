# Sitemap automático del catálogo

Fecha: 14 de octubre de 2026. Implementación en `feature/advance-admin`, sin despliegue.

El generador anterior producía un archivo XML durante la preparación del paquete de hosting. La nueva ruta pública `sitemap-live.php` compone ese listado estático con destinos publicados de la base en cada consulta. Una regla específica sirve la respuesta desde `/sitemap.xml`, conservando la URL ya anunciada en robots.txt y potencialmente enviada a buscadores.

Publicar un destino incorpora su URL y solo los idiomas con traducción existente. Crear un borrador no la incorpora. Archivar o devolver a borrador retira la URL aunque siga en el XML estático anterior. No escribe en base, no utiliza sesiones administrativas y no cambia el servicio de publicación del Worker de destinos. Devuelve XML escapado, deduplicado y sin campos internos.

La lista estática solo se conserva para el origen configurado y excluye rutas privadas, parámetros de preview y fragmentos. Las páginas estáticas descendientes de un destino solo se conservan cuando su padre/idioma está publicado. El XML estático se lee como archivo local; se rechazan DTD/entidades externas. No se inventa lastmod: todavía no existe un historial fiable de versiones públicas para calcularlo. La partición automática para más de 50.000 URLs queda pendiente; se bloquea una respuesta que exceda el límite.

## Negocios: dependencia de la ruta pública

En esta rama los negocios dinámicos se muestran dentro de la página del destino. No hay un renderizador público individual basado en businesses.id/slug. Por tanto no se inventan URLs de ficha al publicarlos: el destino ya está incluido y su contenido cambia cuando el directorio consulta la base.

Las fichas estáticas que ya aparecen en el sitemap aprobado siguen dependiendo de su generador y del proceso de entrega. Cuando el Worker del directorio implemente la ficha dinámica, su constructor de URL y reglas de visibilidad deben integrarse con PublicSitemap. Eso permitirá incluir/retirar cada ficha de negocio según su publicación, destino, categoría, operación e idioma. No se debe prometer que esa integración ya existe.

## Instalación y coordinación

- Configurar `public_base_url` con el origen HTTPS real y prefijo público; no se toma del encabezado Host.
- Incluir app/seo y public/sitemap-live.php en el paquete; el preparador existente ya copia estas carpetas.
- Integrar únicamente la regla `RewriteRule ^sitemap\.xml$ sitemap-live.php [L]` de `.htaccess` con la versión vigente del Worker de destinos. No sustituir otras reglas modificadas por ese Worker. El router local de destinos tiene una regla equivalente para `/v1/sitemap.xml`.
- La base debe tener el catálogo compartido. Ante un fallo devuelve 503; no ofrece una lista vacía con 200 como si todo hubiera sido retirado.
- Comprobar `/sitemap.xml` y robots.txt en la entrega Apache real. No se desplegó ni se envió el sitemap a cuentas de buscadores durante esta tarea.

Pruebas: `php backend/tests/advance/sitemap.php` y prueba HTTP de administración. Cubren transiciones reales de publicación/archivo, idiomas existentes, duplicados, borradores previamente presentes en XML estático, acceso público sin noindex y rechazo de entidades externas. La reescritura de Apache sigue pendiente de validación en el hosting de prueba.

Google explica que el sitemap ayuda a descubrir URLs, pero no garantiza rastreo ni indexación: https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview . Los enlaces internos, accesibilidad pública, canonical, directivas robots y contenido siguen siendo relevantes. Registrar el sitemap en Search Console/Bing Webmaster Tools es una tarea separada con acceso y autorización a esas cuentas.
