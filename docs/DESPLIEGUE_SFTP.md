# Despliegue SFTP de Explorando Lugares

Script independiente: `scripts/desplegar-sftp.py`. Solo requiere Python 3 y OpenSSH `sftp`. No ejecuta comandos shell en el hosting, no despliega automáticamente y no consulta credenciales de otros proyectos.

## Configuración

Copiar `deploy/sftp.example.json` a `.local/sftp.json`, completar los datos propios y mantener el archivo local fuera de Git. No admite passwords ni passphrases en JSON. La autenticación usa una clave SSH autorizada en cPanel, desbloqueada previamente en el agente SSH si corresponde. Con `identity_file` vacío se usa el agente o las identidades predeterminadas de OpenSSH; para aislar el acceso, indicar una clave propia de Explorando Lugares.

Campos: servidor, puerto, usuario, ruta de clave, archivo de huellas verificadas, `remote_base`, `public_directory` y, opcionalmente, `public_root`. Por autorización del propietario, la configuración local puede referenciar la misma cuenta y llave SSH del hosting compartido. Solo se reutiliza el acceso técnico; la carpeta, contenidos y configuración de Explorando Lugares son independientes. Nunca copiar la llave privada al proyecto.

Estructura requerida, con base exclusiva fuera de `public_html`, `www`, `htdocs` o `httpdocs`:

```
/home/USUARIO/explorandolugares/     remote_base, debe existir
  public/                         raíz web del dominio, public_directory
  app/                            PHP privado gestionado por el despliegue
  private/config.php              configuración real; nunca transferida por este script
  .el-releases/                   versiones y respaldos fuera de la raíz web
```

### Raíz del dominio confirmada por el propietario

El dominio usa `public_html/explorandolugares.com`. La configuración local `.local/sftp.json` incorpora `public_root` con esa ruta absoluta dentro de la cuenta; `remote_base` sigue siendo una carpeta exclusiva fuera de `public_html`.

```
/home/USUARIO/public_html/explorandolugares.com/  sitio público
/home/USUARIO/explorandolugares/app/             backend privado
/home/USUARIO/explorandolugares/private/         configuración real
/home/USUARIO/explorandolugares/.el-releases/    versiones y respaldos
```

Con `public_root`, el script ajusta las dos entradas PHP solo en el paquete generado para que carguen el backend privado por su ruta absoluta. No modifica los archivos fuente ni sube `private/config.php`. Solo permite ese dominio, en la misma cuenta; rechaza la raíz general `public_html` y otros dominios. El staging y los respaldos permanecen fuera de la web; la activación apunta a `public_root`. `--confirm-target` sigue requiriendo el `remote_base` privado exacto.

La ruta privada debe existir antes de transferir. Inspeccionar la instalación existente antes de escoger actualización o primera instalación: el modo existente requiere sitio público y `app`; no usar `--initial` si alguna de esas carpetas existe. Si hay un sitio público y todavía no hay backend privado, preparar una migración explícita después de respaldar e inspeccionar; no forzar el modo inicial.

Para desbloquear la llave desde Terminal en este Mac, ejecutar `ssh-add --apple-use-keychain ~/.ssh/paradigma_godaddy` e introducir su frase de acceso únicamente en esa Terminal. No enviarla por chat ni guardarla en JSON.

Crear/asignar la raíz pública del dominio en cPanel según la disposición elegida. Si la cuenta exige otra disposición, adaptar el script y los require PHP antes de usarlo; no cambiar `remote_base` por la raíz del sitio principal. El script sustituye por completo las carpetas `public_directory` y `app`: deben ser exclusivas de esta aplicación. Si existen `.htaccess`, archivos de verificación del dominio, subdominios o contenido manual, incorporar los necesarios a la entrega antes de activar. Los viejos quedan en el respaldo, no en el nuevo sitio. La base de datos y `private/` permanecen fuera de esas carpetas.

## Huella y autenticación

La conexión exige una huella ya verificada; no acepta servidores desconocidos automáticamente. Obtener/comparar la huella con la información del proveedor y establecer una conexión interactiva SFTP con el servidor correcto para registrarla. Nunca desactivar `StrictHostKeyChecking` ni confiar solo en una huella descargada de la misma conexión sin contrastarla. El script ignora la configuración SSH personalizada y no usa ProxyJump ni shell remoto.

Autorizar la clave pública en cPanel; no copiar la clave privada al repositorio o al hosting. Si está cifrada, cargarla mediante `ssh-add` antes de ejecutar el script. Las contraseñas de la base se configuran exclusivamente en `private/config.php` por separado.

## Revisar, subir y activar

### Abrir el portal empresarial solo para pruebas

La configuración de producción debe conservar `business_accounts_enabled` en `false` durante pruebas privadas. En el panel autenticado, abre **Estado del sistema → Preproducción · Acceso al portal de negocios** para generar/copiar una llave, rotarla o revocarla. La pantalla guarda esos dos ajustes de forma privada en `private/config.php`, fuera de `public_html`. Luego entra una vez a `https://explorandolugares.com/negocios/registro/?prueba=LLAVE_GENERADA`; la sesión privada dura cuatro horas y permite probar el flujo sin abrirlo a visitantes. Al elegir **Abierto al público** en ese panel, escribe `ABRIR REGISTRO PUBLICO` para confirmar; se activa `business_accounts_enabled` y se borra la llave de prueba. Para volver al modo cerrado, selecciona **Cerrado al público**; para seguir probando, genera una llave nueva. Rotar o borrar la llave invalida las sesiones privadas iniciadas con la anterior. La llave aparece en la primera petición y los registros técnicos del hosting podrían conservarla.

El modo `--incremental` aplica directamente solo archivos distintos al último manifiesto de despliegue confirmado en esta copia; no crea una carpeta de release ni un duplicado del sitio. Para borrados conocidos exige `--remove-removed`, que debe usarse solo después de revisar las rutas. El modo `--upload`/`--activate` conserva una versión completa previa bajo `.el-releases/` y ocupa más espacio. Revisar el conteo y el manifiesto local antes de elegir.

Desde la raíz de esta copia:

```sh
python3 scripts/desplegar-sftp.py
```

Sin flags construye una entrega nueva y un manifiesto con archivos, tamaños y SHA-256 en `.local/deploy/`. Muestra el destino y el total; no se conecta. Revisar el manifiesto antes de transferir. Los campos de ejemplo deben sustituirse por datos reales incluso para generar el plan del destino.

Solo subir y verificar los archivos en una carpeta de versión, sin tocar el sitio activo:

```sh
python3 scripts/desplegar-sftp.py --upload
```

Para primera instalación, con `remote_base` existente y sin `public_directory` ni `app` previas:

```sh
python3 scripts/desplegar-sftp.py --activate --initial --confirm-target /home/USUARIO/explorandolugares
```

Para actualizar una instalación existente, con ambas carpetas presentes:

```sh
python3 scripts/desplegar-sftp.py --activate --confirm-target /home/USUARIO/explorandolugares
```

Si una versión ya está subida y verificada, puede activarse sin volver a transferirla, después de completar las migraciones de base de datos y comprobar el catálogo:

```sh
python3 scripts/desplegar-sftp.py --activate-release ID_DEL_RELEASE --confirm-target /home/USUARIO/explorandolugares
```

Sustituir la ruta de ejemplo por `remote_base` exacto. `--activate` construye/sube una nueva versión; no reutiliza automáticamente una subida anterior. Cada transferencia usa una carpeta nueva. SFTP confirma cada `put`; además, el script vuelve a leer y coteja SHA-256 solo de siete archivos clave, sin descargar de nuevo todas las imágenes. No comprueba funcionamiento PHP, HTTP, SSL o MySQL: verificar esos puntos después del despliegue.

En una actualización, se renombran `public` y `app` antiguas a `previous-public` y `previous-app` dentro de la versión y se activan las nuevas. No se borran archivos remotos ni respaldos. Los renombrados individuales no forman una transacción conjunta: puede haber unos segundos de indisponibilidad. La cuenta necesita permiso para renombrar las carpetas y el servidor debe mantenerlas en el mismo sistema de archivos. No usar este esquema si el hosting no permite renombrar la raíz del dominio.

No se transfieren `private/`, la configuración real, credenciales, referencias `sources/`, el borrador v0.1 ni el histórico `public/` del repositorio. Las migraciones SQL van dentro de `app/database/`, fuera del directorio público, para que puedan aplicarse por el procedimiento de administración del hosting. No habilitar recepción sin configurar MySQL/privacidad conforme a `backend/README.md`.

## Publicación inicial de destinos

La entrega de octubre de 2026 deja Jericó como único destino publicado; los otros 20 destinos del piloto se importan como borrador. Sonsón se agrega desde su paquete ZIP en el panel de administración y también debe permanecer en borrador. Antes de activar el código dinámico en producción, respaldar la base de datos, aplicar una sola vez las migraciones 003 a 007 enumeradas en `backend/README.md`, ejecutar el importador del catálogo semilla y luego cargar el ZIP de Sonsón desde el panel. El importador se detiene si el catálogo de destinos, categorías o negocios ya contiene filas, y no modifica los registros de interesados. Al final verificar que Jericó es el único destino público, que Sonsón aparece en administración como borrador y que el sitemap solo contiene la guía de Jericó.

## Interrupción y recuperación

Se usa un bloqueo remoto exclusivo `.el-deploy-lock`. Si queda tras un error, inspeccionar primero el diario `manifest.json` de la operación y el estado remoto. No iniciar otra publicación encima de una incompleta. El script no hace rollback ciego: una pérdida de conexión después de un rename puede dejar su resultado incierto.

Para recuperar una actualización, detener otras operaciones y revisar cuáles de `previous-public`, `previous-app`, `public` y `app` existen realmente. Si las nuevas están activas, moverlas a nombres libres de respaldo en la versión; después renombrar la pareja `previous-*` a sus ubicaciones originales. Si solo se movió una carpeta antigua, restaurar solo esa. No sobrescribir ni borrar configuraciones. Verificar el sitio y retirar únicamente el directorio vacío de bloqueo al terminar. En primera instalación no hay respaldo previo; preservar lo subido y corregir la configuración antes de continuar.

Los respaldos consumen espacio; no hay borrado automático. Programar su revisión manual y mantener respaldo de base de datos separado.

## Validación realizada

Pruebas locales con destino ficticio: configuración y rutas inválidas, rechazo de contraseñas, batch con rutas literales, subida solo a versión, respaldo de ambas carpetas, detención ante fallo de activación, primera instalación y comprobación estricta de host. El 12 de octubre de 2026, el propietario desbloqueó la llave en Terminal y se comprobó el acceso real. Se confirmó la raíz del dominio y se inspeccionó su contenido: solo `.well-known/acme-challenge` y `cgi-bin`, ambas sin archivos. Se guardó respaldo local completo y se crearon las carpetas privadas exclusivas del portal. Se preparó una carpeta `app` vacía para que la primera migración preserve la pareja sitio/app mediante el modo de actualización. El staging conserva la estructura de esos directorios auxiliares vacíos; si en futuras instalaciones contienen archivos, incorporarlos a la entrega antes de activar. Consultar el registro de publicación para el resultado final.

Referencia técnica: [OpenSSH SFTP](https://man.openbsd.org/sftp) y [StrictHostKeyChecking](https://man.openbsd.org/ssh_config#StrictHostKeyChecking).
