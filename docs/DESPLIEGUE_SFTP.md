# Despliegue SFTP de Explorando Lugares

El despliegue normal es directo e incremental. El script compara SHA-256 del paquete construido con el último manifiesto y transfiere solo archivos nuevos o cambiados. No crea copias del sitio, carpetas de staging, versiones remotas, bloqueos ni una activación posterior.

## Preparación

Completa `.local/sftp.json` a partir de `deploy/sftp.example.json`. No guardes contraseñas ni frases de acceso allí. La conexión requiere que la clave SSH ya esté desbloqueada en el agente y que la huella del servidor esté verificada en el `known_hosts` configurado. El `remote_base` debe ser una carpeta exclusiva fuera de `public_html`; para este dominio `public_root` es `public_html/explorandolugares.com`.

## Revisar y publicar

Desde la raíz del repositorio:

```sh
python3 scripts/desplegar-sftp.py
```

Esto corre las pruebas locales, construye el paquete en una carpeta temporal y muestra cuántos archivos y megabytes cambiarían. No se conecta ni modifica el hosting.

Cuando quieras publicar ese código:

```sh
python3 scripts/desplegar-sftp.py --deploy
```

El script calcula el hash de cada archivo construido una sola vez y compara ese inventario con el JSON local del último despliegue. Envía en una sola sesión SFTP los archivos nuevos o modificados y, al final del mismo lote, el JSON nuevo. No consulta ni vuelve a descargar archivos del hosting para verificar hashes. Envía primero `app/` y después los archivos públicos. Tras el envío correcto, actualiza `.local/deployment-manifest.json`. Las migraciones SQL solo se transfieren; este comando no las ejecuta ni modifica datos de la base.

El manifiesto remoto vive en `remote_base/.explorandolugares-deploy.json`, fuera del directorio público, y registra commit, fecha, rutas, tamaños y SHA-256. El manifiesto local está en `.local/deployment-manifest.json` y queda excluido de Git. El local es la base de comparación; el remoto es una copia de consulta, no se descarga durante los despliegues. En la primera ejecución, el script aprovecha el inventario local compacto generado desde el último despliegue registrado. Si no hay comparación local, el plan avisa que subiría el paquete completo.

El script no borra archivos que ya no aparezcan en la versión nueva. Los anota en `removed_not_deleted` para que se puedan revisar y limpiar por separado cuando se confirme que no hacen falta. Tampoco modifica `private/config.php`, credenciales ni la base de datos. No hagas dos despliegues al mismo tiempo.

## Limpieza reversible de releases antiguos

El despliegue actual no crea releases ni copias. Para inspeccionar las carpetas heredadas del proceso anterior, usa:

```sh
python3 scripts/limpiar-hosting-sftp.py
```

El listado solo revisa `.el-releases/` y `.el-trash/`, fuera del directorio público. Para apartar un release fechado, usa el ID que muestre el listado:

```sh
python3 scripts/limpiar-hosting-sftp.py --quarantine 20261015T030930Z-1409e3
```

El script lo mueve a una papelera fechada en el mismo espacio del hosting. Esto permite restaurarlo para una comprobación, pero no libera espacio todavía. Para restaurar, ejecuta `--restore ID_DE_PAPELERA`. Después de comprobar que el sitio funciona y que no hace falta recuperar el release, `--purge ID_DE_PAPELERA` borra permanentemente solo ese release de la papelera. No se aplica a archivos activos, configuración privada, imágenes subidas por usuarios ni base de datos. La lista previa y el ID son requisito; el script se detiene ante carpetas o nombres inesperados.

La papelera es temporal, no un sistema de respaldo: los archivos del portal están en Git y la base de datos se respalda por separado cuando ya existan datos de clientes que proteger. No se hacen copias adicionales en cada despliegue.

La construcción del paquete también retira imágenes sin referencias en las páginas, CSS/JS/PHP y el catálogo semilla privado. Conserva imágenes de destinos en borrador si están declaradas en ese catálogo. El envío no borra automáticamente archivos remotos obsoletos; antes de retirarlos, compáralos con el manifiesto y confirma que no estén enlazados desde datos de producción.

## Si una versión sale mal

La recuperación acordada es volver a desplegar la etiqueta estable anterior desde Git. El despliegue directo actualizará solo los archivos que difieran con la versión publicada. Los archivos añadidos por la versión fallida que ya no use el código quedan sin referencia y se anotan para limpieza posterior; no se borran a ciegas. La base de datos requiere evaluación aparte si el cambio fallido incluyó migraciones: Git no revierte datos ni migraciones destructivas.

El envío SFTP confirma transferencia, pero no valida el funcionamiento de PHP, MySQL, formularios ni permisos del hosting. Después de publicar, revisar las páginas y acciones incluidas en la lista de verificación manual de preproducción.

## Reducción del historial anterior

Los despliegues previos dejaron builds de trabajo en `.local/deploy/`. Se conserva un inventario compacto para iniciar la comparación incremental; los builds fechados y temporales ya no son necesarios. El script nuevo no volverá a crearlos ni a guardar copias del sitio en el hosting.
