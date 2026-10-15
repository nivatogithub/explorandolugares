# Administración y datos del portal

## Estado y separación

El portal publicado conserva sus 21 guías estáticas en español e inglés. PHP está preparado para solicitudes privadas y administración, pero aún requiere configuración y comprobación MySQL real. No existen cuentas de comerciantes ni publicación automática.

- `/`: portada, guías y documentación pública estática.
- `/api/participacion.php`: servicio dinámico público que valida y almacena solicitudes; no entrega la lista de interesados.
- `/admin/index.php`: administración privada con usuario y contraseña, sesión, protección CSRF y límite de intentos. Permite revisar interesados y cambiar su seguimiento.
- `app/`, `private/`, `database/`, `bin/`: fuera de `public_html`; lógica, secretos, esquemas y herramientas privadas.

No hace falta una carpeta de páginas públicas de configuración de usuarios en esta etapa: aún no hay usuarios registrados. Una solicitud de contacto no crea cuenta ni ficha pública.

## Preparación de la base nueva

Las operaciones repetibles ahora están disponibles en la página privada `/admin/base-datos.php` cuando se despliegue la versión que la incluye. Inicia sesión en `/admin/index.php`; la nueva página muestra conexión, motor, tablas y migraciones. Puede instalar el esquema base solo si la base está completamente vacía, aplicar las migraciones permitidas del repositorio o importar el catálogo inicial si aún no hay destinos, categorías, negocios ni imágenes en la base. El catálogo deja Jericó publicado y Sonsón más los otros 19 destinos como borradores. Cada escritura exige confirmación escrita y usa archivos fijos del despliegue. No ofrece un editor SQL, no expone configuración ni crea respaldos. Una migración parcial detiene el proceso para revisar el esquema. Las herramientas requieren MySQL/MariaDB y permisos DDL al instalarlas o migrarlas.

La página `/admin/index.php?tab=sistema` sigue siendo solo de diagnóstico; el control de migraciones y carga inicial está separado en `/admin/base-datos.php`. No se debe ejecutar el importador semilla si el catálogo ya contiene registros.

1. Crear en cPanel una base exclusiva y un usuario exclusivo. Asociar usuario y base. Para importar el esquema se necesita permiso CREATE; la aplicación diaria solo necesita SELECT, INSERT, UPDATE y DELETE.
2. Desde el repositorio, ejecutar `python3 scripts/preparar-hosting.py`.
3. Ejecutar `python3 scripts/configurar-registros.py`. Pide contraseñas sin mostrarlas, genera la clave interna y el hash del administrador. No reemplaza configuración existente ni activa el formulario. Si PHP no está en PATH, usar `--php /ruta/a/php`.
4. Transferir por SFTP **solo** `.local/hosting/private/config.php` a `/home/d9rgqsde6im4/explorandolugares/private/config.php`. Mantener permisos 600. No compartir este archivo, pegarlo en el chat ni subirlo a Git. Base64 dentro del archivo es codificación, no cifrado.
5. Desplegar la versión que incluye la herramienta privada, iniciar sesión y abrir `/admin/base-datos.php`. La pantalla permite instalar el esquema base si la base está vacía, luego aplicar las migraciones y cargar el catálogo inicial. Escribe la frase que solicita cada operación y comprueba el resultado en esa misma página. No hay que ejecutar SQL manualmente.
6. Si ya existen tablas, no uses la instalación base. La herramienta identifica migraciones por su estructura y aplica solo las pendientes; ante una migración incompleta se detiene para revisión. MySQL puede dejar parte de una operación DDL si falla, por eso la herramienta no intenta repetir una migración parcial ni ofrece botones para borrar o reiniciar datos.
7. Revisar responsable, contacto y conservación de datos en ambos idiomas. Comprobar HTTPS, ejecución de PHP, administrador y respaldos. Luego cambiar `registration_enabled` a `true` en la configuración privada.
8. Probar una solicitud propia desde español e inglés, verla en el panel, cambiar su seguimiento y cerrar sesión. Confirmar rechazo de envío sin consentimiento o sin contacto válido. No usar datos ajenos para pruebas.

La activación no está hecha. Crear la base en cPanel no configura por sí solo la conexión. El script de despliegue preserva la configuración privada y no la incorpora a las versiones públicas.

## Comercios: siguiente módulo dinámico

Objetivo acordado: mantener guías estáticas y gestionar tarjetas y fichas de comercios desde datos, sin editar HTML por comercio. El generador actual es un paso intermedio: requiere catálogo JSON y regeneración. No resuelve todavía la administración dinámica.

Modelo propuesto para la próxima implementación:

- `businesses`: municipio, categoría, nombre, slug único por municipio y estado borrador/publicado/archivado. Puede relacionarse con una solicitud privada; nunca exponerla públicamente.
- `business_translations`: descripción y contenido por idioma.
- `business_media`: imágenes autorizadas y créditos.
- `business_contacts`: canales de contacto aprobados para publicación, independientes del contacto privado del interesado.
- `events`: destino, fecha inicial/final, organizador y estado; ocultar eventos vencidos según reglas definidas.

El administrador creará un borrador desde una solicitud, completará información y fotos autorizadas, revisará y publicará. La ficha y tarjeta usarán una plantilla común con marca del comercio. El directorio de cada municipio consultará solo publicaciones aprobadas. No publicar automáticamente teléfonos, correos o descripciones recibidos para contacto.

Las páginas públicas dinámicas podrán estar bajo `/lugares/` y su API pública expondrá únicamente campos aprobados. No agregar una segunda pantalla de inicio de sesión ni cuentas de negocios hasta definir su necesidad. Autogenerar una ficha desde datos es distinto de permitir que un interesado publique sin revisión.

Esta arquitectura está documentada; el editor de comercios, las tablas de publicaciones, la carga de imágenes y los eventos dinámicos todavía no están implementados. Se mantiene el catálogo público vacío hasta tener participantes aprobados.
