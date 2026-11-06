# Entrega: portada y lugares guardados — 6 de noviembre de 2026

Rama: `proposal/home-travelers-businesses`. Revisión realizada en dev1. Esta entrega no despliega producción ni sincroniza main/preprod por sí sola.

## Incluye

- Portada que explica el propósito del portal y los recorridos de viajeros y negocios; ocho atractivos editoriales, panel comercial crema y subtítulos acordes al diseño.
- Consolidación de propietarios de estilos del encabezado, pie y panel comercial; marca y canales YouTube/ayuda de la entrega de esta rama.
- Procedencia de imágenes conservada de forma privada, sin créditos editoriales técnicos visibles en las páginas.
- Lugares por conocer privados por cuenta: marcador consistente en guías y tarjetas; guardado/eliminación confirmados por servidor; filtro en el catálogo. Sin bloque redundante en el perfil.
- Acceso sobrepuesto en la página de origen. Explicación del registro solo desde guardar o consultar guardados; acceso normal sin aviso. Conservación de contexto y posición.
- Con el filtro de guardados activo, quitar un lugar retira su tarjeta y actualiza el conjunto de resultados usando la misma plantilla PHP, incluido el estado vacío y la paginación.
- Ilustración de Las Guitarras corregida y aprobada para probar en dev1, sin franjas blancas y con el monumento completo.

## Antes de producción

1. Obtener el main productivo vigente e integrar por función en preprod, preservando los cambios de otros workers. No copiar el runtime de dev1, sus credenciales, cuentas sintéticas ni su base.
2. Comprobar la tabla de migraciones y el esquema existente. Las nuevas migraciones de este trabajo son `063-account-saved-places` y `064-guitars-landscape`, con variantes MySQL/SQLite. No reiniciar ni importar datos sobre producción.
3. Conservar respaldo de datos conforme al procedimiento existente. 063 es aditiva; 064 actualiza solo el medio vinculado a `agustin-codazzi` / `qc-40-2`, conservando IDs y traducciones. Ambos ensayados dos veces.
4. Para 064, publicar primero el activo aprobado `assets/media/09/09a29f6d636e6061a615f9764c670c41c67cbb56e4b1f019c75cb7c8f1f1356a.webp`. Comprobar su hash antes de actualizar su referencia en base de datos. No sustituir otras imágenes ni borrar el original como parte de esta entrega.
5. Construir el paquete público completo desde el commit integrado, ejecutar las barreras y comprobaciones habituales y seguir el despliegue incremental autorizado. Verificar autenticación, sesión, formularios CSRF, tres idiomas, catálogo con/sin sesión, marcado/desmarcado y rutas públicas; después completar paridad y sincronización de main según AGENTS.md.

No se modificaron estadísticas, cuentas vivas ni datos de producción. Las cuentas usadas en las pruebas aisladas de dev1 terminaron inactivas y sin guardados. La cuenta de prueba solicitada por el propietario conserva su lista; sus cinco guardados seguían presentes cuando se revisó la confusión causada por la búsqueda de Jericó.

## Validación realizada

Constructor público completo; pruebas de guardados, aislamiento entre cuentas, intención de acceso, CSRF, estado del marcador, sesión anterior, filtros y catálogo paginado. Revisión visual de abrir/cerrar/acceder sobre la guía, guardado sin navegación y eliminación de tarjeta con el filtro activo. Comparación HTTP satisfactoria tanto local como por el túnel temporal de Cloudflare.

Detalle funcional y pruebas: `docs/MIS_LUGARES_2026-11-06.md`. Procedencia de la ilustración: `content/image-review/guitarras-20261106.json`.

El ajuste de reconocimiento HTTPS del túnel se limita al contenedor dev1; no es una configuración para instalar en producción. El túnel temporal no forma parte del despliegue.
