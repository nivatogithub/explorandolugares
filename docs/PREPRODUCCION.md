# Proceso de preproducción y publicación

Regla vigente del 20/10/2026: main debe reconstruir exactamente la última producción validada. Integrar main en preprod antes de aprobar; publicar el delta completo del candidato aprobado; validar producción y sus migraciones; comprobar paridad completa; solo entonces fast-forward de main mediante `scripts/sincronizar-main-produccion.py`. No saltarse pasos por urgencia ni fusionar toda una rama después de publicar solo una parte. El detalle vinculante está en `REGLAS_OPERATIVAS_DESPLIEGUE.md`; los apartados históricos siguientes no sustituyen esta regla.

La entrada portable es `preprod/README.md`; reúne instalación en otro equipo, configuración local, comandos de operación y diferencias con GoDaddy. Los scripts antiguos en `scripts/` se conservan como accesos de compatibilidad.

Decisión del propietario del 16 de octubre de 2026. Prevalece sobre instrucciones históricas de publicación directa o merge previo a main.

## Secuencia obligatoria

1. Desarrollar en una rama propia. No modificar main ni desplegar desde ramas de workers.
2. Actualizar referencias remotas y revisar el diff completo antes de integrar ramas aprobadas en `preprod`. No incluir las ramas de lugares que aún esperan aprobación de contenido.
3. Construir y probar el paquete de `preprod` en el entorno local Apache/PHP/MySQL. Conservar funciones previamente aprobadas: tres pestañas de Estamos creciendo, consentimiento, estadísticas y cobertura.
4. Comunicar URL local, commit candidato, cambios y pruebas. El propietario revisa y aprueba expresamente ese candidato. No asumir aprobación por transcurso del tiempo o por aprobar un diseño anterior.
5. Después de esa aprobación, desplegar el mismo commit de `preprod` en producción. El script exige `--approved-preprod COMMIT`; este argumento registra el candidato, no sustituye la aprobación humana en la conversación. Conservar el control de manifiesto remoto y las prohibiciones de borrado automático.
6. Validar producción. Si falla, detener la promoción y corregir en preprod; repetir aprobación cuando cambie el candidato.
7. Solo con producción validada, integrar ese mismo commit en `main` y subir main. Si main avanzó mientras se probaba, reconciliar en preprod y volver a validar; nunca forzar main.

La rama preprod es una referencia de integración, no otro sitio público. La primera validación se hace localmente. El hosting público sigue siendo producción; no se usa como primera prueba.

## Copia fija y controles

Copia inicial: `trabajo/preprod`, junto a `trabajo/explorandolugares`, dentro de este proyecto local. Es un worktree Git real, sin mezclar cambios con los workers. Rama `preprod`, creada desde `origin/main` en `42876b2`; la rama de negocios apuntaba al mismo commit, por lo que su integración inicial no añadió cambios. Las ramas independientes de lugares no se integraron.

Desde esa copia:

```sh
./scripts/subir-preprod.sh
./scripts/bajar-preprod.sh
./scripts/actualizar-preprod.sh
./scripts/reconstruir-preprod.sh
```

`subir-preprod.sh` enciende el contenedor existente sin construir ni regenerar código; si falta la instalación, la prepara. `bajar-preprod.sh` lo apaga sin eliminar imagen ni datos. `actualizar-preprod.sh` prepara el código nuevo sin recompilar PHP/MariaDB. `reconstruir-preprod.sh` reconstruye la imagen y actualiza el entorno, únicamente cuando cambian versiones o extensiones. Los scripts arrancan la máquina de Podman si estaba apagada, sin detener otros contenedores. `down` apaga sin eliminar datos. Después de cambiar infraestructura, ejecutar `build` y luego `up`: se detecta una imagen diferente y se recrea únicamente el contenedor, conservando el volumen MariaDB y el estado local.

Portal: http://127.0.0.1:8088/
Administrador: http://127.0.0.1:8088/admin/

Un contenedor x86_64 con Apache, PHP 8.3.33 y MariaDB 10.11.19. Versiones y ajustes observados directamente en GoDaddy el 16 de octubre de 2026, en `preprod/infra/production-runtime.json`. El controlador PDO se llama mysql, pero el motor real de producción es MariaDB. Se fijan límites PHP, modo SQL, charset/collation de servidor, conexión y base, zona horaria y distinción de mayúsculas. El hosting ejecuta PHP mediante LiteSpeed sobre CloudLinux; localmente se usa el módulo Apache sobre Debian. No se clonan los componentes propietarios i360/ionCube ni la infraestructura cPanel, TLS y límites LVE; no es una copia idéntica del hosting. Escucha solo en loopback; MariaDB no publica puertos. La red Podman es interna, sin salida a Internet desde la aplicación. Supervisor gestiona ambos servicios.

La base vigente queda en el volumen `explorando-preprod-mariadb-10-11`. El volumen anterior `explorando-preprod-mysql` se conserva separado y no se conecta a MariaDB; no se borró ni se convirtió automáticamente. La nueva base carga la semilla versionada, sin copiar registros de producción. La configuración, credenciales, imágenes locales y documentos privados permanecen en `.local/preprod`, excluido de Git. En este equipo, el panel local abre sin contraseña por decisión del propietario mediante `.local/preprod/settings.json` (`admin_without_password: true`). Este archivo está ignorado por Git. Una instalación nueva crea la opción en false y conserva la contraseña. El script de arranque inyecta un acceso exclusivo del paquete local, condicionado a las variables de Apache, configuración local y host loopback; ese acceso no está en las fuentes desplegables de producción. Las cuentas de negocios mantienen su autenticación. Para probar métricas de visitantes, utilizar un navegador/perfil sin sesión administrativa. Las credenciales locales de reserva se generan en `.local/preprod/admin.json`; no usar contraseñas de producción.

Se crea el esquema mediante el mantenedor de migraciones y se carga únicamente el catálogo semilla versionado. No se copia la base real, clientes, contactos, claves o documentos de producción. El catálogo local puede contener borradores históricos que no tienen aprobación pública. Las campañas no tienen proveedor configurado y el correo PHP está desactivado; no introducir credenciales externas en este entorno. Las estadísticas que genere la revisión son exclusivamente locales.

## Restauración incluida en este candidato

- Cobertura, Audiencia y Haz parte; métricas agregadas, países, períodos, fechas y procedencia restaurados desde el padre de `42876b2`.
- Se conserva la medición por entidades privada y las mejoras de rutas/sitemap; no se revierte el commit completo.
- Borradores y candidatos internos no se anuncian automáticamente. Solo una guía publicada o un anuncio explícito autorizado puede aparecer como lugar nombrado en cobertura.
- Jericó y Cauca Viejo se muestran como lugares independientes.
- El porcentaje vuelve con un denominador explícito: municipios del catálogo geográfico con al menos una guía publicada / total de municipios del mismo catálogo, nacional y departamental. No es porcentaje de comercios ni de todos los posibles lugares turísticos. Cartografía MGN 2018; no presentarla como censo administrativo actualizado.

Cambiar el denominador es una corrección explícita respecto al antiguo porcentaje ponderado por candidatos. Se presenta para aprobación local, sin desplegarse automáticamente.

## Criterios mínimos para aprobar

Comprobar las tres pestañas en escritorio y móvil; porcentaje y denominador; ausencia de borradores no anunciados; aceptar/rechazar y reabrir preferencias; estadísticas básicas separadas de las consentidas; navegación de Jericó; acceso al administrador, sistema, base de datos y canales; versión exacta del candidato. No tratar una transferencia SFTP correcta como validación funcional.

## Compatibilidad comprobada durante el arranque

MySQL estricto detectó tres incompatibilidades previas: DEFAULT de TEXT sin paréntesis (024), columna generada almacenada sobre una clave con borrado en cascada (028) y ordenación DISTINCT por un campo no seleccionado. Se corrigieron para instalaciones nuevas, además de hacer explícito el JSON privado inicial del importador. Las migraciones ya aplicadas en producción no se reejecutan por cambiar sus archivos. Referencia de la restricción de claves: https://dev.mysql.com/doc/refman/8.0/en/create-table-generated-columns.html.

## Verificación de paridad

Después de `build` y `up`, ejecutar `python3 scripts/check-preprod-parity.py`. Compara el PHP de Apache por HTTP, no el CLI, con la observación real de GoDaddy. Comprueba versión exacta PHP, versión MariaDB, arquitectura, límites, extensiones de aplicación y configuración SQL. El endpoint de inspección se añade solo al paquete local; nunca forma parte de producción. El informe queda en `.local/preprod/parity-report.json` y distingue las diferencias de SAPI/componentes del proveedor.

## Acceso administrativo abierto: exclusivamente local

El cambio de acceso directo no se subió a GitHub al rechazarse el permiso. Los archivos de infraestructura describen un entorno local; no activan un acceso abierto en producción. El inyector solo se ejecuta en `preprod-local.py`, después de generar la copia `.local/preprod/release`. El empaquetador de producción no copia `preprod/infra`, no ejecuta el inyector y conserva la autenticación de `backend/app/bootstrap.php`. `test_full_release_coverage.py` comprueba que el paquete de producción no contiene herramientas preprod ni el marcador de sesión local. No volver a subir este cambio sin autorización del propietario.

## Resultado de la comparación local

La revisión por HTTP pasó los 27 controles de versiones, arquitectura, límites PHP, configuración SQL y extensiones de aplicación. Se conservan diferencias explícitas: SAPI LiteSpeed en GoDaddy frente a apache2handler local; los módulos calendar, ftp, imap, mcrypt, readline y shmop del hosting no están reproducidos, ni i360/ionCube/litespeed. Local añade OPcache y sodium. Estos módulos adicionales no son usados por el código actual revisado; las diferencias se informan, no se declaran equivalentes. HTTP local y el acceso administrativo abierto son excepciones intencionales. No es una clonación completa de CloudLinux/cPanel.

La excepción administrativa no se activa en Apache con un valor fijo versionado: Apache solo recibe la variable definida por la configuración privada local. Cambiar `admin_without_password` en `.local/preprod/settings.json` y ejecutar `./scripts/actualizar-preprod.sh` aplica el cambio. El valor de este equipo nunca se añade a commits ni a paquetes de producción.


## Períodos de observación de base de datos

En Administración → Base de datos → Conexiones y rendimiento se puede empezar un período desde cero. El botón guarda una única lectura privada en `private/database-health/period.json`, con permisos 0600, generada en cada ambiente: no hay que copiar `.local` ni ese archivo para instalar en otro equipo. No modifica tablas, no reinicia MariaDB ni ejecuta `FLUSH STATUS`. Los indicadores siguen siendo globales del servidor compartido y no representan visitantes del portal.

La lectura de referencia permite calcular aumentos al volver a abrir la página. Los valores instantáneos (conexiones abiertas, consultas en ejecución) se muestran por separado y no se restan. Si se detecta reinicio del servidor o reducción de un contador, se pide empezar otro período; los indicadores ausentes no se convierten en cero. Un período nuevo reemplaza al anterior, sin acumular históricos ni copias. En SQLite, o cuando el hosting no permite leer contadores globales, no se habilita un período ficticio.

Estado del sistema muestra primero el diagnóstico de Brevo y conserva un resumen de conexión y almacenamiento. Las cifras detalladas de MariaDB y el período se consultan exclusivamente en Base de datos. La comprobación de Brevo es explícita, no envía correos y su resultado vence a los quince minutos.
