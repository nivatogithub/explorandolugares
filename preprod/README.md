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
