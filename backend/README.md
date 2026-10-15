# Registro de negocios y administración inicial

Todos navegan libremente, incluidos los comercios. Único flujo público inicial: dejar datos para que Explorando Lugares contacte al negocio y evalúe agregarlo manualmente. No hay selección de perfiles ni registro de usuarios. No hay pago, planes contratables, publicación automática, emails automáticos ni cuentas para negocios.

## Entrega para cPanel

Requisitos: PHP 8.2 o posterior con PDO MySQL, MySQL/MariaDB, HTTPS y posibilidad de mantener carpetas privadas fuera de la raíz pública. Confirmar estas capacidades con el hosting antes de activar. El frontend histórico `public/` del repositorio no se modifica.

Ejecutar `python3 scripts/preparar-hosting.py` desde la raíz. Prepara `.local/hosting/` con esta estructura:

```
hosting/
  public/                  raíz del dominio: páginas, assets, api/, admin/
  app/                     lógica PHP privada
  private/config.php       configuración real, creada manualmente
  database/mysql.sql       esquema para importar, fuera del sitio público
  content/destinos-dinamicos.json catálogo semilla ES/EN/PT-BR para la migración PHP
  config.example.php       plantilla sin credenciales
```

En cPanel, asignar al dominio una carpeta pública propia. Situar `app/` y `private/` como hermanas de esa carpeta. Si la estructura no se puede conservar, ajustar los `require` de los dos puntos de entrada PHP para apuntar a la carpeta privada; no subir credenciales al directorio público. Evitar carpetas compartidas con otros sitios.

1. Respaldar el sitio existente antes de reemplazarlo. El portal informativo ya está publicado; conserva aviso y `noindex`. La recepción de interesados sigue pendiente de configurar y comprobar en MySQL.
2. Crear una base y un usuario exclusivo desde cPanel. Importar `database/mysql.sql` una sola vez en una base nueva. No elimina tablas existentes. Dar al usuario del sitio permisos SELECT/INSERT/UPDATE/DELETE solo sobre esa base; no necesita privilegios globales o administrativos.
3. Copiar `config.example.php` a `private/config.php` y completar DSN, usuario y contraseña. Configurar un `app_key` aleatorio de al menos 32 caracteres, un nombre de administrador y un hash de contraseña. No hay credenciales por defecto.
4. Generar clave y hash con PHP en la terminal del hosting, sin colocar contraseñas en comandos, historial o Git. Ejemplo para clave: `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`. Para contraseña, usar un generador de hash local o la herramienta CLI descrita abajo; guardar solo el hash con `password_hash`, nunca la contraseña en texto plano.
5. Completar `privacy.operator`, `privacy.contact` y `privacy.retention` con datos reales. El último es el plazo de conservación acordado por el responsable; no viene inventado. Revisar el texto de privacidad antes de activar. Dejar `local_test=false` en hosting.
6. Comprobar HTTPS, permisos de archivos y que PHP se ejecute correctamente. Configurar cookies/sesiones del hosting y `display_errors=Off`; consultar errores de servidor sin incluir datos personales ni credenciales.
7. Cambiar `registration_enabled=true` únicamente tras esas comprobaciones. La página consulta al servidor y habilita el botón si están completos los datos de privacidad y existe la tabla.
8. Entrar a `/admin/index.php` con las credenciales configuradas. Probar un registro propio, la visualización, el filtro por estado, el seguimiento y cierre de sesión. No publicar sin verificar esta conexión en MySQL.

El script de empaquetado no copia configuración real y no modifica una ya creada. Regenerar la entrega cuando se cambien los archivos fuente; revisar diferencias antes de subir. No compartir `.local/hosting/private/` ni incluirla en ZIP públicos. No subir la carpeta completa como raíz pública.

El controlador dinámico de destinos también se incluye en `public/destino.php`, y las reglas de ruta están en `public/.htaccess`. La administración CRUD se abre en `/admin/destinos.php` tras iniciar sesión en el panel; comparte sus controles de sesión y CSRF. La lógica privada va en `app/`. El importador de catálogo se despliega en `app/bin/importar-destinos.php`, su semilla en `app/content/destinos-dinamicos.json` y las migraciones en `app/database/migrations/`, fuera del directorio público. Aplicar en orden `app/database/migrations/003-dynamic-destinations.mysql.sql`, `004-media-origin.mysql.sql`, `005-destination-internal-research.mysql.sql`, `006-poi-experience-links.mysql.sql` y `007-media-provenance.mysql.sql`; después importar el catálogo vacío con `cd /home/USUARIO/explorandolugares && php app/bin/importar-destinos.php --confirm-empty-catalog`. El importador no modifica las tablas de interesados. Revisar antes el host y reemplazar `USUARIO` por la cuenta real.

El editor permite importar un paquete ZIP v2, revisar todos los textos en español, inglés y portugués junto con sus imágenes, y guardar el destino como borrador. El ZIP lleva `paquete-destino.json` y las imágenes referenciadas dentro de `images/`; la validación rechaza rutas inseguras, imágenes ausentes/no usadas y formatos o tamaños no admitidos. Las copias temporales se guardan fuera de la web, asociadas a la sesión autenticada, por hasta 30 minutos. Al guardar, PHP valida otra vez y almacena imágenes en `public/assets/destinos/{destination_id}/`. El ejemplo descargable contiene ilustraciones genéricas marcadas para reemplazar; no las publiques como contenido del municipio. Sigue disponible la importación JSON v1 anterior, que requiere adjuntar las imágenes a mano.

Los destinos nuevos no se pueden publicar durante su creación. Para editar destinos existentes, los IDs numéricos son la identidad; el slug solo resuelve la URL. La investigación local de competencia, aliados potenciales, prospectos y estrategias se guarda en `destinations.internal_research_json`, solo en el panel privado; no se incorpora a la página pública. Las imágenes generadas se marcan como ilustraciones, no como fotografías documentales. El importador guarda su procedencia visual en `media_assets.provenance_json`, un campo privado que no se entrega al renderizado público. Las fuentes y licencias de fotografías originales se conservan ahí; los conceptos generados sin foto se marcan como tales. Quitar un vínculo no borra el archivo. El hosting debe habilitar PHP `ZipArchive` y configurar `upload_max_filesize` de 64 MB o más y `post_max_size` de 66 MB o más; usar límites menores que los soportados por el plan contratado. Antes de activar el módulo en hosting, probar todas las migraciones y estos flujos en una copia de MySQL de staging; la prueba SQLite local no certifica el hosting.

## Datos y controles

Datos solicitados: persona, nombre del negocio, municipio/departamento, tipo de negocio, correo y/o teléfono, descripción opcional, versión de autorización y fecha UTC. El esquema conserva columnas auxiliares de marca/interés por compatibilidad, pero el formulario no las pide y el servidor fija interés=contacto y solicitud de contacto autorizada. No se aceptan archivos ni datos de pago. La autorización queda asociada al registro y los datos son privados. No se envían correos o WhatsApp automáticamente.

El panel requiere contraseña y sesión: no está vinculado desde la navegación pública. Sesión privada expira por inactividad tras 30 minutos; cookie HttpOnly/SameSite y Secure en HTTPS; renovación al entrar, cierre de sesión, protección CSRF en operaciones y escape de contenido. Consultas preparadas. Límite de intentos por conexión, campo contra bots, límite de tamaño por campo y clave de envío para reintentos sin duplicación.

Estados: nuevo, revisado, contactado, archivado. Cambiar estado no publica en el catálogo ni contacta al interesado. “Archivado” conserva los datos; no equivale a borrado. No hay exportación, eliminación desde el panel, múltiples administradores ni recuperación de contraseña todavía. La recuperación inicial consiste en generar y configurar un hash nuevo en la carpeta privada. El responsable debe gestionar las solicitudes de datos y la política de conservación; respaldar y limpiar las tablas con sus herramientas privadas del hosting.

Los límites guardan una clave HMAC derivada de la conexión y ventana temporal, sin dirección IP legible en la tabla. El hosting puede mantener sus propios logs. Limpiar periódicamente: `DELETE FROM rate_limits WHERE expires_at < UNIX_TIMESTAMP();`. No se usa contenido de registros para analítica.

Documentación técnica consultada: [password_hash](https://www.php.net/manual/en/function.password-hash.php), [consultas preparadas PDO](https://www.php.net/manual/en/pdo.prepared-statements.php).

## Pruebas

- `php -l` en archivos PHP y `node --check ensayos/v1/assets/registro.js`.
- `python3 backend/tests/integration.py`: crea PHP y SQLite temporales, solicita un puerto local, prueba habilitación, validación, consentimiento, CSRF, persistencia, duplicados, autenticación, escape HTML, actualización de estados, cierre y límite de login. Borra los datos temporales al terminar. Nunca usa credenciales de producción.
- `php backend/tests/destination-package.php`: valida el ZIP de ejemplo, las traducciones, las referencias y el rechazo de rutas maliciosas e imágenes ausentes.
- `python3 backend/tests/destination-package-preview.py`: con el servidor local aislado activo, prueba login, carga autenticada del ZIP, lectura privada de imágenes, guardado del borrador y limpieza de archivos temporales; elimina el registro de prueba al terminar.
- Para probar un destino y sus imágenes sin tocar la base local habitual, inicia otra instancia con `EL_DESTINATIONS_PREVIEW_DB="$PWD/.local/sonson-preview.sqlite" EL_LOCAL_CONFIG_PATH="$PWD/.local/sonson-admin-config.php" EL_ADMIN_MEDIA_ROOT="$PWD/.local/sonson-media" PORT=8015 sh scripts/preview-destinos-dinamicos.sh`; luego usa `scripts/ensayar-paquete-destino.py` con el JSON y la carpeta de imágenes. El ejemplo de Sonsón y su investigación comercial se guardan localmente en `.local/paquetes/sonson/`, excluido de Git.
- SQLite sirve únicamente para esta prueba aislada. La integración MySQL del hosting sigue pendiente de comprobar.
- El servidor estático de `ensayos/` no ejecuta PHP: muestra el formulario deshabilitado y explica que no se almacena. No subir PHP a un servidor que lo entregue como texto.

## Idiomas

El sitio público tiene versión inglesa bajo `en/`; el panel funcional privado permanece en español. Configurar `privacy.retention_en` con el plazo de conservación en inglés para habilitar la recepción en ese idioma. El formulario y la API presentan validaciones y confirmaciones en español o inglés. Los datos introducidos por el comercio se conservan tal como fueron enviados. No se traducen nombres de marca ni datos de contacto.

## Canal de contacto telefónico

El formulario guarda Fijo, Celular o WhatsApp en `leads.phone_type`; WhatsApp usa un número celular colombiano. La opción indica cómo prefiere ser contactado, sin verificar la existencia de una cuenta de WhatsApp ni enviar mensajes automáticamente. Registros sin teléfono conservan el canal vacío.

Instalaciones nuevas: usar el esquema actualizado. Bases MySQL existentes: hacer respaldo y ejecutar una sola vez `database/migrations/001-phone-type.mysql.sql` antes de actualizar el backend. Los registros históricos quedan con tipo sin especificar; no se presume que tengan WhatsApp.

## Comprensión del alcance de participación

El formulario exige `understanding=1` además de privacidad, con validación en servidor. Guarda `participation_version=participacion-informativa-v1` y muestra esa versión en administración. El texto documentado en `docs/PARTICIPACION_INFORMATIVA.md` describe una solicitud de contacto, no un contrato ni la aceptación de tarifas futuras.

Para bases existentes, respaldar y ejecutar una vez `database/migrations/002-participation-version.mysql.sql` antes de actualizar el backend (y 001 si no estaba aplicada). Los registros previos conservan el campo vacío. Instalaciones nuevas usan el esquema completo actualizado.

## Herramientas de puesta en marcha

Ver [Administración y datos](../docs/ADMINISTRACION_Y_DATOS.md) para la estructura vigente del hosting y los pasos completos. `scripts/configurar-registros.py` crea configuración privada sin reemplazar archivos; `backend/bin/base-datos.php --instalar` instala únicamente sobre una base MySQL vacía y `--comprobar` revisa conexión y columnas. Son herramientas privadas, nunca páginas públicas. El panel administra solicitudes y destinos; las fichas y tarjetas dinámicas de comercios siguen siendo el siguiente módulo.

Tras desplegar una versión que incluya esta herramienta, el administrador autenticado puede abrir `/admin/base-datos.php`. La pantalla comprueba conexión, tablas y estado de las migraciones; permite instalar el esquema base únicamente si la base no tiene tablas, aplicar las migraciones incluidas en el repositorio y cargar el catálogo semilla únicamente cuando el catálogo está vacío. Jericó queda publicado y los otros 20 destinos —incluido Sonsón— conservan el estado borrador definido en el catálogo. Cada escritura pide una frase de confirmación. No acepta SQL arbitrario, no muestra credenciales y no genera copias de respaldo. En bases con datos existentes, las migraciones añaden estructura; si detecta un estado parcial, se detiene para evitar repetir cambios. Esta página queda protegida por la misma sesión y CSRF del panel. No desplegarla por separado de la versión del backend con sus migraciones y semilla.
