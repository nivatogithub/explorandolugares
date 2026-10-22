# Enlaces de ubicaciones revisados · 22/10/2026

Alcance aprobado: 109 referencias de 13 lugares, ES/EN/PT-BR. 85 enlaces a fichas nombradas compartidas por Google Maps (13 generales y 72 de atractivos/experiencias con punto fijo); retirar 24 enlaces de actividades sin ubicación fija o duplicados. No se retiran tarjetas ni se promocionan operadores.

`backend/app/content/reviewed-map-links.json` conserva identidad destino/slug, valor anterior, enlace aprobado, coordenadas y observaciones. `ReviewedMapLinks::apply(PDO)` actualiza exclusivamente mapas/coordenadas por identidad existente, en una transacción. Requiere tres traducciones y tipo coincidente, rechaza una edición posterior a la revisión y admite repetición sin cambios. No importa cuentas, negocios, medios ni estadísticas; no crea tablas ni entidades.

El catálogo semilla se actualiza con los mismos campos para reconstrucciones limpias. El mapa general del lugar utiliza la URL nombrada revisada, sin afectar las coordenadas territoriales. El administrador no vuelve a generar mapas de experiencias sin punto fijo al guardarlas; conserva enlaces vacíos existentes.

No se promete acceso vehicular, entrada libre ni ocultar la barra de Google Maps. Monserrate señala la estación inferior; Las Nubes señala la entrada. Los dispositivos móviles abren el enlace compartido conforme a su configuración de Google Maps; esta entrega no incorpora un selector de aplicaciones de mapas.

Comprobaciones: `php backend/tests/reviewed-maps.php` ensaya aplicación, segunda ejecución y conflicto con rollback. La entrega requiere además MariaDB, HTTP de las 39 páginas y comprobación de enlaces/medios contra producción. Evidencias y respaldo privados fuera de Git, en `.local/mapas-publicacion` y `.local/database-backups`. Recuperación de datos: restaurar exclusivamente los campos revisados desde su instantánea, tras comprobar que no hubo ediciones posteriores; nunca restaurar la base completa sobre tráfico nuevo.
