# Migración acotada de contenido editorial — candidato sin desplegar

Herramienta CLI: `scripts/aplicar-snapshot-destino.php`; clase `scripts/lib/DestinationSnapshotMigration.php`. Solo nueve slugs autorizados, snapshot de exportación y hash exacto. No ejecutada contra preprod ni producción.

```sh
php scripts/aplicar-snapshot-destino.php \
 --config=/ruta/privada/config.php \
 --snapshot=/ruta/medellin/snapshot-public-content.json \
 --snapshot-sha256=HASH_CONFIRMADO \
 --public-root=/ruta/public \
 --report=/ruta/nueva/dryrun-medellin.json
```

Sin `--apply` ejecuta las operaciones en transacción y revierte. Usar primero una copia aislada de MariaDB; en MySQL/MariaDB incluso rollback puede consumir valores autoincrementales, aunque no persista contenido. `--apply` confirma la transacción: únicamente después de revisar snapshot, respaldo y candidato autorizado. Reporte debe ser nuevo. Configuración nunca se exporta ni se imprime. CLI no admite SQL ni nombres de tablas aportados por usuario.

## Preservación

- Destino se busca por slug; sus IDs locales no se copian.
- POI se busca por destino remoto + slug. Solo se crean los publicados; los retirados que ya existen pasan a draft, sin borrar filas. Contenido remoto ausente del snapshot no se retira automáticamente.
- Medios se reutilizan por asset_path. Exige que los archivos ya estén desplegados bajo raíz pública y su hash coincida; no descarga, elimina ni copia medios durante la transacción DB. Traducciones y autoría/licencia se guardan en las filas correspondientes.
- Negocios se buscan por public_id, luego slug/destino, luego nombre español normalizado. Ambigüedad detiene transacción. Todos los negocios existentes se conservan enteros, incluyendo sus traducciones, titularidad y estado; el exportador no puede sobrescribir una ficha comercial viva. Los nuevos conservan public_id, reciben ID remoto y traducciones/categorías/fuentes públicas.
- No consulta ni escribe cuentas, contactos, estadísticas, suscripciones, pagos o reclamaciones. No activa asociaciones comerciales. No hay DELETE, TRUNCATE ni IDs locales en INSERT.
- Historia/motivos se corresponden por sección+orden; si hay varias filas remotas con esa identidad, se bloquea y no elige arbitrariamente.

## Limitaciones abiertas antes de producción

- Revisar diferencias de historia/motivos/fuentes: el helper actual conserva filas remotas ausentes del snapshot. No prueba paridad visual si el destino anterior tenía bloques adicionales; requiere plan explícito para vaciar/retirar esos bloques sin borrado.
- Asociaciones de medios se actualizan por rol/orden. Se conservan roles remotos ausentes; pueden seguir visibles. Debe revisarse snapshot y página en staging.
- Negocios nuevos no reciben nueva suscripción automáticamente. Comprobar cómo el portal presenta fichas sin fila de suscripción; si requiere plan gratuito, preparar acción separada únicamente para IDs nuevos. Nunca modificar planes existentes.
- No copia business_media de negocios nuevos, internos, JSON de procedencia omitido, relaciones de operadores ni configuración del sitio. Negocios gratuitos actuales no necesitan foto. No declarar migración completa si algún caso depende de esos campos.
- No tiene bloqueo de mantenimiento ni comparación de concurrencia de contenido editorial remoto. Ejecutar candidato en ventana controlada por coordinador; datos comerciales vivos quedan fuera de los UPDATE.
- Una transacción es por destino, no por nueve destinos. Conservar cada reporte y reintentar de forma idempotente; no afirmar lote completo si uno falla.

## Prueba realizada

`php scripts/tests/probar-migracion-snapshot.php`: SQLite aislada, IDs remotos distintos de locales, dryrun revertido, segunda aplicación sin duplicados, POI retirado pasa a draft y negocio reclamado/cuenta/analítica/contacto privado permanecen idénticos. Sintaxis PHP verificada. Falta ensayo MariaDB con copia real, claves/constraints y QA de página. No desplegar esta herramienta automáticamente.

La ruta actual `DestinationAdminService::save` preserva destino/POI por IDs cuando recibe formulario, pero necesita adaptar todo el lote y puede archivar omitidos; `BusinessPublicImporter` siempre genera negocios y rechaza duplicados. Ninguno sirve para restaurar fielmente los IDs/comercios vivos de una exportación local sin reconciliación explícita.
