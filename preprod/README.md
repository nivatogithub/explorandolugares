# Preproducción local de Explorando Lugares

Esta carpeta contiene lo necesario para recrear el entorno en otro equipo. Las herramientas y sus plantillas van en Git; las claves, configuración efectiva y datos de cada equipo se generan fuera de Git. No necesitas copiar `.local` del equipo original ni acceder a GoDaddy para arrancar.

## Qué reproduce

- PHP **8.3.33**, MariaDB **10.11.19**, Apache y Linux x86_64 en un contenedor.
- Límites PHP, modo SQL, charset/collation y zonas horarias observados en GoDaddy.
- Rutas desde `/`, el paquete de la aplicación y sus migraciones.
- Datos semilla versionados, separados de producción.

La observación sanitizada está en `infra/production-runtime.json`. No contiene credenciales ni datos de clientes. No es un clon de CloudLinux/cPanel: LiteSpeed, módulos del proveedor y TLS del hosting no se reproducen exactamente. Ver las diferencias en `../docs/PREPRODUCCION.md` y el informe de comprobación local.

## Requisitos del equipo

- Git, Python 3.9 o posterior y Podman instalados.
- macOS: una máquina de Podman. `configurar.sh` crea la predeterminada si no existe, con 4 CPU y 4 GB de memoria; no cambia una máquina existente ni detiene otros contenedores.
- Linux: Podman configurado para el usuario. En Windows usar WSL2 con Git, Python y la conexión a Podman configurados; esta variante no se ha validado aquí.
- Acceso a Internet durante la primera construcción y puerto local **8088** disponible.
- En ARM la imagen x86_64 requiere emulación. Podman debe disponer de ella. La primera compilación puede ser lenta; no se repite al apagar/encender.

No necesitas instalar PHP, Apache ni MariaDB en el equipo anfitrión para levantar el entorno. Las comprobaciones PHP opcionales del repositorio sí requieren un PHP local o ejecutarlas dentro de un entorno de pruebas preparado.

## Primera instalación en otro equipo

Usar una revisión de `preprod` que contenga esta carpeta. Si los cambios siguen solo en el equipo original, primero debe compartirse el commit aprobado; no aparecen por clonar una revisión anterior. Subir cambios requiere autorización del propietario.

```sh
git clone https://github.com/nivatogithub/explorandolugares.git
cd explorandolugares
git switch preprod
./preprod/configurar.sh
./preprod/subir.sh
```

`configurar.sh` crea `.local/preprod/settings.json`. Por defecto el administrador **mantiene contraseña**. Para activar expresamente la excepción administrativa únicamente en ese nuevo equipo:

```sh
./preprod/configurar.sh --admin-sin-clave
./preprod/actualizar.sh
```

En una instalación todavía no iniciada, se puede elegir `--admin-sin-clave` antes del primer `subir.sh` y evitar la actualización adicional.

Portal: http://127.0.0.1:8088/
Administrador: http://127.0.0.1:8088/admin/

Si se conserva contraseña, el acceso generado aleatoriamente está en `.local/preprod/admin.json`. No se imprime la contraseña ni se comparte ese archivo. Las cuentas de negocios siguen utilizando su propia autenticación aun cuando el administrador local esté abierto.

## Uso cotidiano: un comando por acción

| Acción | Comando | Qué hace |
|---|---|---|
| Encender | `./preprod/subir.sh` | Reutiliza el contenedor, imagen y base. Si falta la instalación, la prepara. |
| Apagar | `./preprod/bajar.sh` | Detiene solo Explorando Lugares; conserva imagen, configuración y datos. |
| Actualizar la app | `./preprod/actualizar.sh` | Genera el paquete con el código de la rama y lo aplica localmente. No compila PHP/MariaDB. |
| Reconstruir infraestructura | `./preprod/reconstruir.sh` | Construye la imagen y actualiza el contenedor. Solo hace falta al cambiar versiones, extensiones o configuración de la imagen. |
| Ver estado | `./preprod/estado.sh` | Muestra estado y puerto del contenedor. |
| Ver errores | `./preprod/logs.sh` | Muestra las últimas líneas de los servicios. |

Los comandos resuelven la raíz a partir de su propia ubicación; no dependen de la ruta original del Mac. Arrancan la máquina de Podman si estaba apagada. Bajar el entorno no apaga toda la máquina de Podman ni los contenedores de otros proyectos.

La prueba realizada aquí de apagar y volver a encender tardó **6,9 segundos**, con Podman ya funcionando. No es una garantía para todos los equipos. Un arranque frío de la VM o la primera instalación puede tardar más.

## Configuración local reproducible

`settings.example.json` es la plantilla versionada, con la excepción administrativa desactivada. La configuración efectiva se guarda en `.local/preprod/settings.json`, ignorada por Git.

Para volver al acceso con contraseña:

```sh
./preprod/configurar.sh --admin-con-clave
./preprod/actualizar.sh
```

No se activa la excepción por pertenecer a la rama ni por copiar el repositorio. El arranque local lee la opción privada, la pasa a su Apache y añade el acceso solo al paquete `.local/preprod/release`. Se exige además configuración de prueba y host loopback. El empaquetador de producción no ejecuta ese inyector ni incluye esta carpeta; la prueba del paquete lo comprueba.

## Qué está en Git y qué se recrea

| En Git | Generado en cada equipo, fuera de Git |
|---|---|
| Containerfile, PHP/Apache/MariaDB y Supervisor | Imagen local de Podman y contenedor |
| Scripts, plantilla de opciones y guía | `.local/preprod/settings.json` |
| Esquemas, migraciones y catálogo semilla de la app | Base local y sus registros |
| Observación de versiones y comprobador de paridad | Claves, configuración PHP y acceso administrativo generado |
| Pruebas de seguridad y funcionamiento | Imágenes subidas en pruebas, documentos, logs e informes locales |

El volumen vigente es `explorando-preprod-mariadb-10-11`. El antiguo `explorando-preprod-mysql`, si existe, se conserva separado. No conectar sus archivos directamente a MariaDB. Los scripts no eliminan volúmenes ni hacen una conversión automática de datos.

Cambiar de equipo recrea **el entorno y las funcionalidades**, no tus registros de prueba anteriores. Migrar esos datos requiere una exportación/importación local separada y revisada. No copiar claves de GoDaddy ni importar su base para arrancar este entorno.

## Comprobarlo

```sh
python3 preprod/verificar.py
python3 preprod/infra/verify.py
```

El primero compara 27 controles del PHP servido por Apache y MariaDB contra la observación de GoDaddy. El segundo comprueba las páginas públicas, privacidad y administrador local, con o sin la excepción de contraseña. Los informes quedan en `.local/preprod/`.

Para las métricas de visitantes usar una ventana privada o perfil separado del administrador. La red del contenedor es interna, el correo PHP está desactivado y no se configuran proveedores de campañas de producción. Esto evita envíos reales; no demuestra entregabilidad de correos.

## Flujo de trabajo y límites

Rama de trabajo → merge revisado a `preprod` → `actualizar.sh` → validación y aprobación humana del commit → despliegue de ese commit a producción → validación de producción → merge a `main`.

Ninguno de los comandos de esta carpeta despliega a GoDaddy, hace push, modifica `main` ni envía campañas. No integrar ramas de lugares sin aprobar. Los accesos antiguos en `scripts/*-preprod.sh` y `scripts/preprod-local.py` se conservan como compatibilidad.

## Si algo falla

- Consultar `./preprod/estado.sh`, `./preprod/logs.sh` y `.local/preprod/prepare.log`.
- Puerto 8088 ocupado: identificar el servicio; no detener otros proyectos automáticamente.
- Podman no disponible o emulación ausente: completar la instalación/configuración del equipo antes de construir.
- Error durante la primera construcción: corregirlo y repetir `reconstruir.sh`; las capas completas se reutilizan.
- Eliminar imágenes, volúmenes o `.local` no es necesario para un reinicio. No hacerlo como reparación rutinaria.

## Probar cuentas empresariales y recuperación

En la configuración local inicial están habilitados `business_accounts_enabled` y `registration_enabled`. Las cuentas usan el acceso público unificado. La opción local de administración sin contraseña está desactivada en este entorno; cada equipo configura su acceso explícitamente.

- Crear cuenta personal: `http://127.0.0.1:8088/negocios/registro/`. Tras entrar, usar **Mi cuenta → Mis negocios → Registrar o reclamar un negocio** y buscar antes de crear. La cuenta se crea desde el sitio público; la ficha requiere revisión y aprobación antes de asociarse/publicarse. Usar únicamente documentos ficticios de prueba.
- Iniciar sesión: `http://127.0.0.1:8088/negocios/acceso/`.
- Portal y cierre de sesión: `http://127.0.0.1:8088/negocios/portal/`.
- Solicitar recuperación: `http://127.0.0.1:8088/negocios/recuperar-clave/`.
- Leer el correo simulado: `http://127.0.0.1:8088/admin/buzon-local.php`. Es una herramienta técnica local, fuera del menú normal. Requiere acceso administrativo local.

La recuperación usa el servicio existente: enlace de un solo uso que vence a las 24 horas; la base guarda únicamente su hash. El token completo está en el mensaje local para poder probar el flujo. El buzón conserva los últimos 50 mensajes en `.local/preprod/private/mailbox.json`, fuera de Git y de la raíz pública. No compartir ese archivo ni capturas de sus enlaces. El formulario responde igual para correos existentes e inexistentes, y limita solicitudes.

El buzón simulado y su visor se añaden **solo al paquete local** desde `preprod/infra/`. Exigen entorno local, configuración de prueba y host loopback. No se incluyen en producción. El registro, la verificación y la recuperación públicos usan el backend compartido y pueden entregar correo real con Brevo configurado. Las pruebas locales capturan los mensajes en el buzón, salvo un destinatario habilitado expresamente para correo real. Google/Microsoft y otras identidades externas siguen pendientes de configurar.

Comprobación automática:

```sh
python3 preprod/probar-cuentas.py
```

Crea una cuenta ficticia temporal, prueba recuperación, contraseña de un solo uso, login y logout, y retira únicamente sus propios datos al terminar. La creación pública de cuenta/solicitud y su revisión se comprueban además en `backend/tests/admin-pages.integration.py` con base desechable. No se crea ni publica un negocio real durante esas pruebas.

El encabezado público muestra **Iniciar sesión** junto a los idiomas. El diálogo contiene **Crear cuenta** y **Olvidé mi contraseña**. Una persona autenticada ve su nombre, **Mi cuenta** (Personal / Mis negocios) y **Cerrar sesión**; una administradora también ve **Administración**. No hay accesos de crear cuenta, iniciar sesión, recuperar contraseña ni buzón técnico en el menú administrativo.

Acceso, alta personal, recuperación y enlaces de activación/restauración comparten una presentación flotante. Las URL directas conservan sus procesos y muestran el portal de fondo. Mi cuenta, registro/reclamación y pagos usan páginas completas con header/footer comunes. Los formularios se procesan en servidor con CSRF; el diálogo carga ese mismo formulario, sin una segunda implementación de autenticación.

Residencia: Colombia se muestra inicialmente con listas dependientes de Departamento y Municipio / ciudad o pueblo (33 divisiones y 1.122 municipios en el catálogo). Los rótulos se adaptan a ES/EN/PT; los topónimos conservan su nombre. Todos los países están disponibles. Colombia usa listas de departamento y municipio; los demás países usan región y ciudad en texto libre. Solo se habilita y muestra un control por dato, preservando los datos internacionales existentes. Los desplegables son editables y filtran al escribir en el propio campo (sin buscador adicional), sin distinguir mayúsculas o tildes, navegación por teclado y aviso sin coincidencias. Las URL de los componentes de cuenta y del selector se versionan por contenido para evitar que caché conserve implementaciones anteriores.

Identificación privada: empresa o persona natural con RUT utiliza NIT y dígito de verificación; persona sin RUT utiliza su documento; titular extranjero admite pasaporte, CE, PPT o identificación tributaria extranjera. Se valida formato y dígito en navegador y servidor. No se certifica existencia oficial ni representación. El identificador queda en los datos privados de la solicitud, asociado al establecimiento al revisarla; no sustituye el UUID ni impone exclusividad a un titular que opera varios negocios. No requiere una tabla ni migración nueva. Las alternativas de comprobación oficial están documentadas en `docs/VERIFICACION.md`.

El formulario muestra primero la búsqueda. Al elegir crear o reclamar, habilita identificación, suscripción y soportes. Las categorías adicionales muestran iconos y excluyen la principal. Canales, fotos y datos complementarios se agrupan en desplegables. El estado de una solicitud pendiente se distingue de la suscripción vigente. Gratis oculta datos de pago y usa una acción explícita de guardar.

Validación del candidato: `scripts/verificar-antes-de-desplegar.py`, pruebas nuevas `account-page.php` y `business-holder-identity.php`, integración HTTP de identificación privada y separación de suscripción vigente/solicitud. Recorrido visual en navegador de escritorio y ancho efectivo de 390 px; las capturas e informes quedan privados en `.local/preprod/evidencia/estabilizacion-cuentas/`. No se enviaron campañas ni se efectuaron pagos. El diagnóstico de Brevo consultó su API sin enviar correo.

La suite pública general conserva pendientes reales: asignación de negocio libre condicionada a verificación automática (no implementada), contratación bloqueada si se alcanza el límite antiabuso, y consolidación del recorrido visual en el ejecutor. No borrar contadores ni marcar criterios como aprobados por falta de evidencia. El despliegue requiere revisión humana del commit exacto de preprod; luego validación de producción y el mismo commit en main.

## Cuenta administradora desde el portal

La migración del acceso administrativo al inicio de sesión público, los permisos y el aprovisionamiento local explícito se documentan en [Autenticación de administrador](../docs/AUTENTICACION_ADMINISTRADOR.md). La credencial de producción no se distribuye con el repositorio: otros equipos deben aprovisionar su propia cuenta autorizada.

## Perfiles permanentes para revisión

Se mantienen cuentas ficticias de viajero, negocio y administrador. Los accesos de este equipo están en `.local/preprod/CUENTAS_PRUEBA.md`. La guía [Cuentas persistentes de prueba](CUENTAS_PRUEBA.md) explica cómo recrearlas en otro equipo o restablecer su acceso sin borrar las pruebas. Se preparan al subir o actualizar el ambiente; también mediante `./preprod/cuentas-prueba.sh`.

## Regresión de aceptación antes de proponer producción

Ejecutar `./preprod/regresion.sh` después de actualizar el candidato local. Ver [alcance, criterios y seguridad](regresion/README.md). El informe queda en `.local/preprod/regresion/resultado.html` y JSON. Un fallo, pendiente o comprobación bloqueada devuelve salida distinta de cero: **NO LISTO**. Las pruebas no publican cambios ni sustituyen la aprobación humana. Los recorridos visuales y las funciones todavía no implementadas se indican explícitamente; no se cuentan como aprobados.

### Prueba de correo transaccional real

La conexión se guarda desde Administración → Conexión con Brevo. La API no se pega en chats ni se versiona. El modo habitual conserva el buzón local. Para probar recepción real en una sola dirección autorizada:

```sh
python3 preprod/correo-real.py --destinatario CORREO_PROPIO
./preprod/actualizar.sh
```

El archivo privado `account-mail-test.json` habilita activación y recuperación reales únicamente para esa dirección. Los registros sintéticos y los demás correos siguen en el buzón local. Se requieren las guardas `local_test`, `EL_PREPROD_LOCAL` del servidor local y host loopback, más la conexión Brevo guardada/habilitada. Los enlaces enviados usan exactamente `http://127.0.0.1:8088`; abrirlos en el equipo con preproducción encendida. No cambia configuración de producción ni envía campañas. Guardar el modo no envía mensajes: los solicita el usuario al registrarse o recuperar contraseña.

Para volver al buzón local para todos:

```sh
python3 preprod/correo-real.py --desactivar
```

No necesita reconstruir imagen. El destinatario y las llaves permanecen fuera de Git; otro PC debe configurar su propio modo. La prueba aislada `php backend/tests/account-mail-local.php` comprueba límites y URLs sin enviar. Confirmación del proveedor no equivale a recepción: el usuario debe comprobar la bandeja y completar ambos enlaces antes de aprobar.

La prueba real requiere salida a Internet. Cuando el modo privado está habilitado, los scripts conectan una segunda red administrada `explorando-preprod-mail-egress`; no añade puertos publicados y no restringe la red a un dominio. Al desactivar el modo y volver a subir el entorno, la desconectan. El código de correo limita el destinatario y usa la URL fija HTTPS de Brevo. Los comprobantes privados `account-mail-receipts.jsonl` guardan fecha, tipo, hash de destinatario y aceptación/código HTTP; nunca API, contraseña ni enlace. Un aceptado201 no certifica entrega al buzón.

### Arranque persistente en macOS

`subir.sh` inicia Podman mediante un trabajo del servicio de usuario de macOS cuando la máquina está apagada. Así el proceso de virtualización no depende de que continúe abierto el terminal o worker que ejecutó el arranque. No se instala un inicio automático al iniciar sesión. La definición y los registros se generan en `.local/preprod/podman-launch.plist`, `podman-launch.log` y `podman-launch-error.log`; en otro Mac se regeneran con sus propias rutas. El trabajo no se reinicia automáticamente ni cambia volúmenes, credenciales o puertos. `bajar.sh` sigue deteniendo únicamente preproducción.
