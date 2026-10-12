# Despliegue SFTP de Explorando Lugares

Script independiente: `scripts/desplegar-sftp.py`. Solo requiere Python 3 y OpenSSH `sftp`. No ejecuta comandos shell en el hosting, no despliega automáticamente y no consulta credenciales de otros proyectos.

## Configuración

Copiar `deploy/sftp.example.json` a `.local/sftp.json`, completar los datos propios y mantener el archivo local fuera de Git. No admite passwords ni passphrases en JSON. La autenticación usa una clave SSH autorizada en cPanel, desbloqueada previamente en el agente SSH si corresponde. Con `identity_file` vacío se usa el agente o las identidades predeterminadas de OpenSSH; para aislar el acceso, indicar una clave propia de Explorando Lugares.

Campos: servidor, puerto, usuario, ruta de clave, archivo de huellas verificadas, `remote_base` y `public_directory`. No se reutilizó el host, usuario, clave ni carpeta de Paradigma.

Estructura requerida, con base exclusiva fuera de `public_html`, `www`, `htdocs` o `httpdocs`:

```
/home/USUARIO/explorandolugares/     remote_base, debe existir
  public/                         raíz web del dominio, public_directory
  app/                            PHP privado gestionado por el despliegue
  private/config.php              configuración real; nunca transferida por este script
  .el-releases/                   versiones y respaldos fuera de la raíz web
```

Crear/asignar la raíz pública del dominio en cPanel según esa estructura. Si la cuenta exige otra disposición, adaptar el script y los require PHP antes de usarlo; no cambiar `remote_base` por la raíz del sitio principal. El script sustituye por completo las carpetas `public_directory` y `app`: deben ser exclusivas de esta aplicación. Si existen `.htaccess`, archivos de verificación del dominio, subdominios o contenido manual, incorporar los necesarios a la entrega antes de activar. Los viejos quedan en el respaldo, no en el nuevo sitio. La base de datos y `private/` permanecen fuera de esas carpetas.

## Huella y autenticación

La conexión exige una huella ya verificada; no acepta servidores desconocidos automáticamente. Obtener/comparar la huella con la información del proveedor y establecer una conexión interactiva SFTP con el servidor correcto para registrarla. Nunca desactivar `StrictHostKeyChecking` ni confiar solo en una huella descargada de la misma conexión sin contrastarla. El script ignora la configuración SSH personalizada y no usa ProxyJump ni shell remoto.

Autorizar la clave pública en cPanel; no copiar la clave privada al repositorio o al hosting. Si está cifrada, cargarla mediante `ssh-add` antes de ejecutar el script. Las contraseñas de la base se configuran exclusivamente en `private/config.php` por separado.

## Revisar, subir y activar

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

Sustituir la ruta de ejemplo por `remote_base` exacto. `--activate` construye/sube una nueva versión; no reutiliza automáticamente una subida anterior. Cada transferencia usa una carpeta nueva. Comprueba el contenido descargándolo de nuevo por SFTP y cotejando SHA-256 antes de activar. No comprueba funcionamiento PHP, HTTP, SSL o MySQL: verificar esos puntos después del despliegue.

En una actualización, se renombran `public` y `app` antiguas a `previous-public` y `previous-app` dentro de la versión y se activan las nuevas. No se borran archivos remotos ni respaldos. Los renombrados individuales no forman una transacción conjunta: puede haber unos segundos de indisponibilidad. La cuenta necesita permiso para renombrar las carpetas y el servidor debe mantenerlas en el mismo sistema de archivos. No usar este esquema si el hosting no permite renombrar la raíz del dominio.

No se transfieren `private/`, SQL, configuración real, credenciales, referencias `sources/`, el borrador v0.1 ni el histórico `public/` del repositorio. La entrega actual conserva el aviso de revisión y `noindex`; preparar la versión final y comprobar sus metadatos antes de hacerla indexable. No habilitar recepción sin configurar MySQL/privacidad conforme a `backend/README.md`.

## Interrupción y recuperación

Se usa un bloqueo remoto exclusivo `.el-deploy-lock`. Si queda tras un error, inspeccionar primero el diario `manifest.json` de la operación y el estado remoto. No iniciar otra publicación encima de una incompleta. El script no hace rollback ciego: una pérdida de conexión después de un rename puede dejar su resultado incierto.

Para recuperar una actualización, detener otras operaciones y revisar cuáles de `previous-public`, `previous-app`, `public` y `app` existen realmente. Si las nuevas están activas, moverlas a nombres libres de respaldo en la versión; después renombrar la pareja `previous-*` a sus ubicaciones originales. Si solo se movió una carpeta antigua, restaurar solo esa. No sobrescribir ni borrar configuraciones. Verificar el sitio y retirar únicamente el directorio vacío de bloqueo al terminar. En primera instalación no hay respaldo previo; preservar lo subido y corregir la configuración antes de continuar.

Los respaldos consumen espacio; no hay borrado automático. Programar su revisión manual y mantener respaldo de base de datos separado.

## Validación realizada

Pruebas locales con destino ficticio: configuración y rutas inválidas, rechazo de contraseñas, batch con rutas literales, subida solo a versión, respaldo de ambas carpetas, detención ante fallo de activación, primera instalación y comprobación estricta de host. No se ha conectado a un hosting real ni probado renombrados contra un servidor SFTP real.

Referencia técnica: [OpenSSH SFTP](https://man.openbsd.org/sftp) y [StrictHostKeyChecking](https://man.openbsd.org/ssh_config#StrictHostKeyChecking).
