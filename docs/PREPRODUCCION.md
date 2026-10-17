# Proceso de preproducción y publicación

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
python3 scripts/preprod-local.py build
python3 scripts/preprod-local.py up
python3 scripts/preprod-local.py status
python3 scripts/preprod-local.py logs
python3 scripts/preprod-local.py down
```

`build` prepara la imagen; `up` genera el paquete de hosting y arranca/reinicia el contenedor. `down` apaga sin eliminar datos. Después de cambiar infraestructura, ejecutar `build` y luego `up`: se detecta una imagen diferente y se recrea únicamente el contenedor, conservando el volumen MySQL y el estado local.

Portal: http://127.0.0.1:8088/
Administrador: http://127.0.0.1:8088/admin/

Un contenedor con Apache, PHP 8.3 y MySQL 8.0 en Ubuntu 24.04. Se mantiene sintaxis compatible con PHP 8.2 del hosting; el entorno local no reproduce exactamente su versión, configuración, arquitectura ni TLS. Escucha solo en loopback; MySQL no publica puertos. La red Podman es interna, sin salida a Internet desde la aplicación. Supervisor gestiona ambos servicios.

La base queda en el volumen `explorando-preprod-mysql`; configuración, credenciales, imágenes locales y documentos privados en `.local/preprod`, excluido de Git. El panel local abre sin contraseña por decisión del propietario. El script de arranque inyecta un acceso exclusivo del paquete local, condicionado a las variables de Apache, configuración local y host loopback; ese acceso no está en las fuentes desplegables de producción. Las cuentas de negocios mantienen su autenticación. Para probar métricas de visitantes, utilizar un navegador/perfil sin sesión administrativa. Las credenciales locales de reserva se generan en `.local/preprod/admin.json`; no usar contraseñas de producción.

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
