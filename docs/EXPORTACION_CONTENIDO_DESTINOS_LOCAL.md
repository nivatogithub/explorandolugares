# Exportación reproducible de contenido local

Herramienta `scripts/exportar-contenido-destinos-local.php`. Preparada para MariaDB preprod; **no ejecutada contra la DB real aún**. Validación realizada: sintaxis PHP. No declarar snapshot completo hasta ejecutar y revisar sus reportes.

## Uso

PHP CLI con PDO MySQL y ZipArchive, configuración privada existente con `local_test=true`, DSN `dbname=explorando_preprod`, usuario/contraseña. No copiar esa configuración al repositorio ni pasar secretos por argumentos.

```sh
php scripts/exportar-contenido-destinos-local.php \
  --config=/ruta/privada/config.php \
  --public-root=/ruta/portal/public \
  --output=/ruta/revision/exportacion-nueva \
  --slugs=jerico,cartagena,bogota,medellin,santa-marta,jardin,covenas,zipaquira,santa-fe-de-antioquia
```

Si el script se copia a staging en vez de conservar su estructura del repositorio, añadir `--app-root=/srv/portal/app`. No depende de `.local` ni de un nombre de contenedor: configuración, raíz pública y salida son argumentos explícitos. Los nueve slugs están acotados; no acepta destinos nuevos.

## Salida por destino

- `snapshot-public-content.json`: tablas/proyecciones de contenido, IDs de destino/POI/negocios/medios, traducciones ES/EN/PT existentes, historia/motivos/fuentes y asociaciones de medios. Incluye registros archivados para conservar decisiones de retiro.
- `media/`: solo activos referenciados por destino, POI o negocios, deduplicados por SHA256, conservando en snapshot la ruta pública original.
- `paquete-destino.json` y ZIP v2 cuando se puede validar sin inventar imágenes/metadatos. POI archivados quedan fuera del ZIP y permanecen en snapshot. ZIP importa siempre como borrador. Su estado por registro y sus IDs se conservan en snapshot, no se fuerzan dentro de un formato incompatible.
- `report.json`: conteos, archivos ausentes, resultado técnico y hash snapshot.
- Reporte global para los nueve destinos.

El exportador usa una transacción `READ ONLY` y SELECT de columnas explícitas. No incluye cuentas, reclamaciones, contactos privados, suscripciones, pagos, analítica, notas internas de investigación, notas libres de fuentes comerciales ni JSON arbitrario de procedencia. Conserva autor/origen/URL/licencia y fuentes públicas; prompts y aprobaciones por hash permanecen en las carpetas de evidencia del worker y se consolidan aparte. No desreferenciar ni borrar activos de esas carpetas por no estar en la DB.

## Límites que deben comprobarse

`DestinationAdminService::find` no basta para exportación fiel: trae catálogo global de medios y pierde orden/identificadores de algunos bloques al agrupar, además solo selecciona una imagen por POI. El script consulta directamente asociaciones completas y conserva filas/IDs.

`BusinessPackage` / `BusinessPublicImporter` son importación de altas: generan IDs, no preservan slug/IDs, traducciones completas ni estado comercial. **No usarlos para restaurar o actualizar los negocios existentes**. El snapshot conserva esa información pública para una migración separada por IDs/slug explícitos; no se crea un ZIP de negocios que aparente fidelidad cuando el importador no la ofrece.

No es copia completa de DB ni restaurador. Contenido SEO/presentación servido mediante JS/CSS/assets fuera de asociaciones DB requiere diff separado del código del portal. Comparar snapshot con la página servida y con archivos de la rama antes de dar por consolidado un destino.

La exportación no elimina activos. Para retirar obsoletos se requiere comprobar referencias globales en DB **y** PHP/JS/CSS/HTML/manifiestos/otros destinos, conservar aprobaciones/historial y presentar lista revisable. Un activo ausente de un ZIP no demuestra que esté sin uso.

Después de ejecutar: comprobar cero medios faltantes, ZIPs validados, hashes de activos servidos y aprobaciones exactas. Integración secuencial por el coordinador; nada de producción.
