# Despliegue SFTP de Explorando Lugares

## Registro, borradores y cupones — 25/10/2026

`python3 scripts/migrar-cuentas.py --business-registration` inspecciona exclusivamente 052–057 sin aplicar SQL. El mismo alcance con `--apply --approved-preprod COMMIT` exige el candidato limpio y aprobado. Incluye tablas de cargas, borradores y cupones y los límites 0/100/300/600; amplía capacidad cuando falta, sin recortar textos, sustituir datos ni cambiar estadísticas. No crea cupones ni medios de pago de prueba en producción. Antes de aplicar: respaldo reciente, restauración aislada y migraciones repetidas con conservación de tablas protegidas. No mezclar este alcance con el de cuentas o destinos.

La revisión de cobertura queda expresamente separada por decisión del propietario. La comprobación local `python3 preprod/infra/verify.py --exclude-coverage` informa esa exclusión; su resultado no certifica cobertura. No alterar su catálogo, porcentajes ni métricas durante esta entrega.

El despliegue normal es directo e incremental. El script compara SHA-256 del paquete construido con el último manifiesto y transfiere solo archivos nuevos o cambiados. No crea copias completas del sitio ni instalaciones paralelas. Usa temporales privados únicamente para los archivos modificados y reemplazo individual verificable, como se explica en Recuperación incremental.

## Preparación

Completa `.local/sftp.json` a partir de `deploy/sftp.example.json`. No guardes contraseñas ni frases de acceso allí. La conexión requiere que la clave SSH ya esté desbloqueada en el agente y que la huella del servidor esté verificada en el `known_hosts` configurado. El `remote_base` debe ser una carpeta exclusiva fuera de `public_html`; para este dominio `public_root` es `public_html/explorandolugares.com`.

## Revisar y publicar

Desde la raíz del repositorio:

```sh
python3 scripts/desplegar-sftp.py
```

Esto corre las pruebas locales, construye el paquete en una carpeta temporal y muestra cuántos archivos y megabytes cambiarían. No se conecta ni modifica el hosting.

Para una entrega de alcance reducido se puede repetir `--only RUTA_DEL_PAQUETE` por cada archivo autorizado. Por ejemplo, permite desplegar solo las piezas del importador sin publicar cambios acumulados de otras áreas:

```sh
python3 scripts/desplegar-sftp.py --only app/BusinessPackage.php --only public/admin/negocios.php
```

La lista usa rutas exactas dentro de `app/` o `public/`, sin comodines. El manifiesto registra el hash nuevo de los archivos enviados y conserva los hashes conocidos de los archivos que quedaron sin tocar; la siguiente entrega completa detectará esos cambios pendientes. Las entregas parciales requieren un manifiesto previo válido.

Cuando quieras publicar ese código:

```sh
python3 scripts/desplegar-sftp.py --deploy
```

El script calcula el hash de cada archivo construido una sola vez y compara ese inventario con el JSON local del último despliegue. Envía en una sola sesión SFTP los archivos nuevos o modificados y, al final del mismo lote, el JSON nuevo. No consulta ni vuelve a descargar archivos del hosting para verificar hashes. Envía primero `app/` y después los archivos públicos. Tras el envío correcto, actualiza `.local/deployment-manifest.json`. Las migraciones SQL solo se transfieren; este comando no las ejecuta ni modifica datos de la base.

El manifiesto remoto vive en `remote_base/.explorandolugares-deploy.json`, fuera del directorio público, y registra commit, fecha, rutas, tamaños y SHA-256. El manifiesto local está en `.local/deployment-manifest.json` y queda excluido de Git. El local es la base de comparación; el remoto se descarga y se compara con el local antes de modificar archivos; una diferencia bloquea la publicación. En la primera ejecución, el script aprovecha el inventario local compacto generado desde el último despliegue registrado. Si no hay comparación local, el plan avisa que subiría el paquete completo.

Los archivos ausentes del paquete se reportan, pero el despliegue recuperable no los elimina. Cualquier retiro debe prepararse y autorizarse por separado. Un despliegue `--only` no borra nada. Tampoco modifica `private/config.php`, credenciales ni la base de datos. No hagas dos despliegues al mismo tiempo.

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

La construcción del paquete también retira recursos de `assets/` sin referencias en las páginas, CSS/JS/PHP y el catálogo semilla privado. Conserva imágenes de destinos en borrador si están declaradas en ese catálogo, así como los archivos de licencias. La limpieza remota se limita a archivos del manifiesto anterior que el paquete nuevo ya no incluya; el script no explora ni elimina rutas ajenas al manifiesto.

## Si una versión sale mal

La recuperación acordada es volver a desplegar la etiqueta estable anterior desde Git. El despliegue directo actualizará solo los archivos que difieran con la versión publicada y retirará los archivos obsoletos que estén registrados en el manifiesto de despliegue. La base de datos requiere evaluación aparte si el cambio fallido incluyó migraciones: Git no revierte datos ni migraciones destructivas.

El envío SFTP confirma transferencia, pero no valida el funcionamiento de PHP, MySQL, formularios ni permisos del hosting. Después de publicar, revisar las páginas y acciones incluidas en la lista de verificación manual de preproducción.

## Reducción del historial anterior

Los despliegues previos dejaron builds de trabajo en `.local/deploy/`. Se conserva un inventario compacto para iniciar la comparación incremental; los builds fechados y temporales ya no son necesarios. El script nuevo no volverá a crearlos ni a guardar copias del sitio en el hosting.

## Recuperación incremental y tráfico activo (19/10/2026)

El despliegue principal conserva el mismo sitio y las mismas rutas. Antes de sustituir archivos descarga **solo los archivos modificados**, comprueba sus hashes contra el manifiesto y guarda un registro privado en `.local/deployment-recovery/ID/journal.json`. No copia imágenes ni el sitio completo. Transfiere todos los bytes nuevos a temporales privados, verifica su integridad y solo entonces renombra cada archivo completo. El manifiesto se reemplaza al final. Las dependencias nuevas preceden al código que las utiliza; recursos preceden a las páginas.

Una transferencia cortada antes de los renombrados deja el sitio anterior funcionando. Una interrupción durante los renombrados puede dejar una mezcla de versiones; no se promete una transacción global ni indisponibilidad cero. El registro permite comprobar los archivos y recuperar únicamente el cambio, en orden inverso. Si hay cambios ajenos, la recuperación se detiene para no pisarlos. La conexión debe estar restablecida para recuperar. No hay cambio de instalación, de raíz web, DNS ni modo de mantenimiento.

`python3 scripts/desplegar-sftp.py --config CONFIG --recover .local/deployment-recovery/ID/journal.json` restaura exclusivamente código/manifiesto. **Nunca restaura, reinicia ni modifica la base de datos o las estadísticas.** Los retiros intencionales de archivos se realizan aparte de estas entregas recuperables. Conservar el registro y sus archivos anteriores hasta validar la entrega; no borrarlos durante una recuperación pendiente. La copia anterior de código queda protegida por permisos locales, fuera de Git. Probar las funciones de recuperación con `python3 -m unittest discover -s scripts/tests -p test_deployment_recovery.py`.

Para la entrega de cuentas/formularios se debe usar `--preserve-places` y revisar la selección resultante; opcionalmente acotarla más con `--only`: excluir imágenes, archivos de contenido y páginas/renderizadores de lugares. No trasladar la base local ni los datos de demostración. Aplicar antes las migraciones aditivas 043 y 044 cuando falten, y verificar su estado completo; el código anterior puede funcionar con esas tablas añadidas. Recuperar código no requiere retirar las tablas nuevas ni borrar sus datos.

## Respaldo y recuperación de base de datos

`python3 scripts/respaldo-base-datos.py` consulta viabilidad. Con `--create` genera un volcado nativo de MariaDB/MySQL usando `--single-transaction --quick --skip-lock-tables`, con tablas InnoDB, vistas, disparadores, rutinas y eventos. No ejecutar cambios de esquema mientras se genera. No configura bloqueos globales ni modifica contadores. La compresión consume temporalmente CPU y lecturas: medir duración/tamaño y preferir períodos de menor tráfico al automatizar.

El hosting actual permite SFTP pero no consola SSH. El transporte por defecto usa una acción PHP fija, temporal, con token secreto en cabecera, POST, caducidad de diez minutos y retirada al terminar. No acepta SQL ni comandos del cliente. Helper y respaldo viven fuera de la raíz web. No es una función pública permanente ni lleva claves en URL. Si se habilita consola en el futuro, puede usarse `--transport ssh`; no se requiere habilitarla ahora.

El respaldo comprimido se guarda con permisos 0600 en `private/backups` del hosting y se descarga a `.local/database-backups` del Mac. Las credenciales permanecen en el hosting y los datos no se imprimen. Se verifican SHA-256 y gzip. La copia contiene datos personales y debe mantenerse privada; los permisos no equivalen a cifrado.

`python3 scripts/verificar-respaldo-local.py --backup RUTA.sql.gz` comprueba el hash y restaura exclusivamente en una base temporal `el_restore_check_*` del contenedor local, con el planificador de eventos apagado. Comprueba cantidad de tablas y presencia de las tablas estadísticas, y elimina solo esa base temporal. No importa sobre la base activa local ni conecta a producción.

### Plan operativo propuesto (no automatizado todavía)

- Antes de cambios de esquema: respaldo consistente y verificación de restauración.
- Diariamente en horario de menor uso: respaldo completo de base; conservar siete diarios, cuatro semanales y tres mensuales. La conservación automática implementada se detalla más abajo; no modifica datos activos.
- Conservar al menos una copia fuera del hosting. La copia del Mac cumple una separación inicial, pero no sustituye una copia externa cifrada y automatizada que sobreviva a la pérdida del equipo. Elegir y configurar ese destino antes de dar el plan por operativo.
- Prueba de restauración aislada mensual y antes de cambios importantes. Registrar fecha, checksum, tamaño, número de tablas y resultado; alertar ante fallo, sin enviar datos personales.
- Recuperación habitual de un despliegue: **solo código**, con base y estadísticas actuales intactas. No restaurar un respaldo viejo para corregir un error de PHP.
- Recuperación de desastre de base: detener escrituras de forma controlada, conservar toda evidencia/datos recuperables actuales, restaurar primero en otra base y reconciliar los datos posteriores antes de cualquier cambio de conexión aprobado. Requiere autorización expresa; este repositorio no ofrece un botón de sobrescritura de producción.
- Un respaldo diario puede dejar hasta 24 horas sin cubrir si el hosting pierde toda la base. Recuperación a un instante preciso exige binlogs/servicio del proveedor o réplica; eso no está configurado ni verificado. No prometer pérdida cero de visitas con respaldos diarios. No alterar jamás contadores activos para hacer coincidir una copia antigua.


### Migración de cuentas preparada para la entrega

`python3 scripts/migrar-cuentas.py` consulta el estado actual sin aplicar SQL. `--apply --approved-preprod COMMIT` exige el commit limpio aprobado de preprod y aplica exclusivamente 043 por defecto. `--include-subscription-changes` añade 044 a la selección fija; sin `--apply` solo consulta. Ningún argumento permite SQL o migraciones arbitrarias. La herramienta lleva la clase y el SQL candidato a una carpeta privada temporal, sin sustituir el código activo, y los retira al terminar. Primero respaldo y restauración aislada; después aplicar 043 y, si se incluye el cálculo de upgrades, 044; comprobar estados completos y solo entonces publicar el código que la utiliza. No retirar tablas al recuperar código.

La migración crea únicamente las tablas de historial y enlaces de reactivación y conserva los estados anteriores en el historial nuevo. No cambia identificadores, correos, hashes de contraseñas, relaciones comerciales ni tablas estadísticas. Si se interrumpe entre tablas puede reanudarse, verificando estructura e índices existentes; no inventa ni corrige columnas desconocidas. Limita a cinco segundos la espera de metadatos durante este cambio para detenerse en lugar de prolongar una espera. `preprod/probar-migracion-cuentas.php` comprueba casos activo/pendiente/deshabilitado, reanudación, idempotencia y preservación de contadores en base desechable.


### Contingencia temporal: cierre obligatorio de cada entrega

Las copias de archivos **no son un archivo histórico de versiones**. Después de comprobar funcionalmente la entrega se ejecuta:

`python3 scripts/desplegar-sftp.py --config CONFIG --finalize .local/deployment-recovery/ID/journal.json --validation-report INFORME.json`

El informe debe tener `schema_version: 1`, `commit` idéntico al del manifiesto publicado y un objeto `checks` con `public_pages`, `accounts`, `statistics` y `assets` a `true`. Estos valores se registran después de las comprobaciones, no por terminar la transferencia. La herramienta vuelve a verificar manifiesto y archivos remotos; después elimina los bytes anteriores/nuevos de contingencia, copias de verificación, manifiestos temporales y restos remotos propios. Conserva únicamente un recibo pequeño con versión, fecha, cantidad de archivos y resultados. Una limpieza interrumpida se puede reintentar.

Un nuevo despliegue se bloquea mientras exista una contingencia anterior sin finalizar; así no se acumulan diez copias por diez publicaciones. Si falla la validación, conservar la contingencia y recuperar o corregir. Una vez finalizada, cualquier versión histórica se recupera desde Git mediante otro despliegue incremental revisado. Este cierre nunca borra respaldos de base de datos ni sus metadatos: tienen su política separada, ya que Git no guarda datos productivos.


### Conservación implementada de respaldos de datos

La herramienta de respaldo aplica en hosting y Mac una política UTC: copia más reciente por hora en las últimas 48 horas, por día en los últimos 30 días y por mes en los últimos 12 meses. Una misma copia puede cubrir varios períodos y no se duplica; quedan como máximo 90 copias verificadas bajo gestión. Siempre conserva la más reciente. La política solo se aplica a nombres y metadatos del exportador, con ruta de origen, tamaño y SHA-256 coincidentes; no toca archivos ajenos, enlaces simbólicos, copias no verificadas, tablas ni estadísticas.

`python3 scripts/limpiar-respaldos.py` muestra la propuesta; `--apply` aplica la conservación en hosting y Mac, y `--local-only` limita la operación al Mac. El borrado de copias vencidas exige otra copia verificada de no más de dos horas, con integridad gzip/CRC. Sin esa protección se detiene. Un bloqueo evita dos limpiezas simultáneas. Los archivos no reconocidos o corruptos se conservan y se reportan para revisión, por lo que la política no sustituye la vigilancia de almacenamiento. Una copia restaurada de prueba mantiene sus metadatos de verificación.

La conservación ya forma parte de cada ejecución futura del exportador, pero **todavía no está programada la creación periódica de respaldos** ni la subida cifrada a Drive. La política elimina únicamente archivos antiguos de respaldo y su metadato; jamás borra registros de producción.


### Paquete acotado de la primera auditoría

Preparar desde un commit guardado de preprod, contrastando el manifiesto remoto vigente. `scripts/preparar-publicacion-auditoria.py` usa solo fuentes de Git y produce en `.local/production/primera-auditoria/` un ZIP con el delta, sus hashes y un informe de exclusiones. No publica, aplica migraciones ni crea una copia integral del sitio. El ZIP es evidencia del paquete preparado; el despliegue normal debe reconstruir el commit aprobado y verificar los mismos hashes y la misma base remota. Si otro worker publica, recalcular antes de desplegar. No incorporar imágenes, contenido ni renderizadores de lugares, ni trabajo ajeno de encabezado/idiomas.

Consultar previamente `python3 scripts/migrar-cuentas.py --include-subscription-changes`: estado de 043/044, cuentas y suscripciones, sin SQL de escritura. El comprobador temporal está autenticado por token en cabecera, tiene caducidad y se retira al terminar. No deja acceso de prueba permanente. El respaldo consistente reciente y su restauración aislada se realizan inmediatamente antes de una aplicación autorizada, no se consideran completados por preparar el ZIP.

La 044 añade solo el registro de saldo/diferencia de cambios futuros. No recalcula ni actualiza suscripciones anteriores. Ambas migraciones limitan a cinco segundos la espera de metadatos; si existe un bloqueo se detiene y se reintenta tras revisión. No se carga la base de preprod ni sus cuentas o negocios ficticios en producción.
