# Despliegue de administración y canales — 15 de octubre de 2026

Rama: `negocios-dinamicos-phm`. Código publicado: `f1507c0`, que incluye `0f5d51b` y los ajustes de cuentas, canales y departamentos. Autorización: solicitud expresa del propietario de desplegar los cambios acumulados de esta rama para validarlos.

## Entrega

26 archivos nuevos o modificados, 0,34 MiB; cero eliminaciones remotas. Configuración privada preservada. Transferencia SFTP y actualización del manifiesto completadas.

Aplicadas mediante la sesión del propietario y la herramienta autenticada `/admin/base-datos.php`: `027-business-bulk-update-audit` y `028-accounts-business-channels`. Ambas quedaron listas en MySQL. La herramienta registró también las migraciones anteriores ya existentes, sin reinstalarlas.

Antes y después: 199 negocios, 21 destinos y 65 imágenes. Tablas: 40 antes, 41 después. No se importaron negocios, canales o destinos ni se cambiaron estados de publicación. No se enviaron comunicaciones.

## Validaciones

Sintaxis de 69 archivos PHP y 38 Python; pruebas PHP funcionales; 57 pruebas de navegación, generación y despliegue; integración HTTP de participación y administración. Todas aprobadas. Ensayos locales usan SQLite; la aplicación de 027/028 se comprobó después en MySQL real desde el panel.

En producción, con sesión administrativa del propietario: migraciones listas; administración de canales carga 199 negocios, todos sin canales; filtro de negocios con canales devuelve cero. Exportación/actualización JSON abre con las acciones de descarga y revisión. Destinos muestra 21 filas con columnas País, Departamento, Destino y Estado, sin Sonsón.

Validación HTTPS sin sesión: inicio y Jericó en español, inglés y portugués devolvieron 200, sin errores PHP visibles. CSS y JavaScript nuevos devolvieron 200. Canales y actualización masiva devolvieron 403, como corresponde a rutas privadas.

## Pendientes identificados

- Los canales del archivo privado de 99 negocios no aparecen vinculados en el CRM productivo. No se reimportaron a ciegas ni se atribuyó una causa no comprobada. Revisar la carga previa y, con autorización para importar datos, usar el paquete privado idempotente; no volver a importar las fichas empresariales.
- Sonsón está en un ZIP separado, visualmente aprobado como borrador, pero no existe en la lista de producción. Incorporarlo como borrador llevaría el total a 22, sin publicarlo.
- Brevo no está implementado en esta rama. La integración Resend de `feature/advance-admin` es trabajo separado; no se mezcló en este despliegue. No hay envío de campañas habilitado por esta entrega.
