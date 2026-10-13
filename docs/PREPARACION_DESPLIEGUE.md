# Preparación del primer despliegue

Actualización al 12 de octubre de 2026: primera versión informativa desplegada por autorización del propietario, con formulario deshabilitado. Ver `PUBLICACION_2026-10-12.md`. Los puntos siguientes siguen siendo requisitos para activar la captación y finalizar el lanzamiento; conservar `noindex` fue parte del despliegue de la versión tal como estaba.

## Qué se entrega

- Sitio vigente: `ensayos/v1/`; portada con Jericó, Medellín y Guatapé destacados, 21 guías ES/EN, navegación y captación comercial.
- Contenido y licencias: `content/`, créditos dentro de las guías y `assets/destinos/creditos.json`.
- Construcción: `scripts/preparar-hosting.py` prepara `.local/hosting/`; no subir esa carpeta completa como raíz pública.
- Backend PHP y administración privada: `backend/`, con configuración de ejemplo vacía. SQLite se usa para pruebas aisladas; MySQL del hosting sigue sin verificar.
- Despliegue SFTP: script, documentación y plantilla sin credenciales. Servidor, usuario y carpeta exclusiva siguen sin configurar.
- Históricos: `public/`, ZIP y `design/` se conservan. No publicar esos archivos en lugar del sitio actual.

## Pendientes antes de producción

1. Confirmar producto y capacidades del hosting GoDaddy. Decidir si se activa el backend PHP incluido o se reemplaza por otra solución; conservar el diseño y las validaciones del formulario.
2. Confirmar raíz exclusiva del dominio, acceso SFTP, HTTPS y estructura privada fuera de la raíz pública.
3. Configurar base de datos y administración con credenciales fuera de Git. Completar responsable, contacto y conservación de datos en privacidad.
4. Revisar composición responsive en navegador y contenidos finales. No se ha completado la inspección visual en navegador en esta sesión.
5. Preparar respaldo y plan de reversión del sitio que exista en el dominio.
6. Probar en el hosting un registro propio y su consulta privada. El servidor Python solo permite revisar el frontend.
7. Retirar `noindex,nofollow` de las páginas que se decida publicar y revisar metadatos, dominio y descubrimiento antes del lanzamiento. Las pruebas de esta entrega conservan la protección de ensayo.

Subir código a GitHub no implica publicar en el dominio ni activar la recepción de datos. No se incluyen registros de personas, bases locales, claves privadas, configuración real ni archivos temporales.

## Verificación realizada

Construcción completa, 18 pruebas Python de destinos/fichas/SFTP y cinco verificaciones JavaScript de destinos, directorio, opciones, registro y teléfono. Integración HTTP del backend con base SQLite aislada: recepción, validación, permisos, sesiones y administración. Las pruebas utilizan datos ficticios y no conectan a producción.
