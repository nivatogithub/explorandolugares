# Acceso público en preproducción local

Autorización expresa del propietario: hacer públicos en preproducción los destinos preparados. No es autorización de producción ni aprobación final de ilustraciones para producción.

Destinos habilitados: Jericó (ya público), Jardín, Cartagena, Bogotá, Medellín y Santa Marta. Los demás permanecen en borrador. Las fichas de lugares y experiencias seleccionadas pasan a publicadas solo en MariaDB local. Los negocios importados de fuentes públicas se habilitan allí; no se publican negocios ficticios adicionales ni registros archivados. No cambian estados de reclamo, operación ni verificación.

`scripts/preprod-habilitar-destinos.php` exige `local_test` y DSN `explorando_preprod`, usa lista cerrada y conserva copia previa. La plantilla local conserva ilustraciones y reglas compactas en rutas públicas mediante una condición igualmente limitada a preproducción; la autenticación de `preview=1` sigue intacta. El catálogo local incluye seis destinos en ES/EN/PT. Los assets dinámicos están también en la carpeta de integración para sobrevivir al limpiador de archivos del empaquetado.

Verificación sin cookies/sesión: seis rutas `/destinos/{slug}/` responden HTTP200 sin aviso de sesión ni banner privado. Evidencias y catálogo en `docs/evidencias-densidad/`. Producción sin cambios.
