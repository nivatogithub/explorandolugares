# Categorías de negocios

Cada negocio conserva una categoría principal en `businesses.category_code` y puede tener categorías adicionales del catálogo activo en `business_categories`. La relación tiene clave única por negocio/categoría; no genera negocios, cuentas ni suscripciones adicionales.

La migración aditiva `030-business-categories` crea únicamente la relación y sus índices. Las fichas existentes conservan su categoría principal sin reclasificación automática. Antes de aplicar la migración, el portal sigue leyendo las categorías principales y muestra la actualización pendiente en el editor.

## Activación

En Administración → Base de datos, revisar que `030-business-categories` esté pendiente y aplicar los cambios de estructura pendientes. El hosting actual permite SFTP pero responde que el acceso de shell está deshabilitado; por eso la migración se activa desde el panel. No se ha añadido ninguna ruta pública que ejecute SQL sin autenticación.

## Edición e intercambio

- El editor ofrece una categoría principal obligatoria y casillas para las demás categorías existentes.
- El listado, CRM y las fichas públicas muestran las categorías asociadas. Los filtros del directorio y de exportación encuentran también las categorías adicionales, manteniendo una sola ficha por negocio.
- Los paquetes `business-import-v1` admiten `category_codes` junto a `category_code`.
- La exportación `business-bulk-update-v1` conserva `fields.category_code` como principal y añade `category_codes` al objeto de cada negocio. Esa lista se sustituye al actualizarla; la principal siempre se incluye. Una lista vacía retira las adicionales.
- Omitir la lista conserva las categorías adicionales existentes. Cambiar únicamente la principal no convierte automáticamente la principal anterior en otra actividad.
- La vista previa y la auditoría incluyen los cambios de categorías. Se conservan ID, contactos, fuentes y suscripciones.
- Un archivo antiguo de actualización sigue siendo válido si el registro no cambió desde su exportación. Una modificación posterior de categorías invalida su versión para evitar sobrescribir cambios.

Las pruebas usan negocios ficticios: importación con categorías repetidas, rechazo de categorías inventadas, edición parcial, exportación por categoría adicional, actualización JSON, filtros públicos sin duplicados y borrado de relaciones al eliminar una ficha elegible.
