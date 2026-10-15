# Integración de main con Advance Admin

Fecha: 2026-10-14. Rama: `feature/advance-admin`. Main integrado: `c01b776` (Merge dynamic destinations PHP implementation). No se desplegó ni se accedió a una base de producción.

## Resolución

- Sitio público: se incorporó la versión de main, incluidos contenidos, imágenes, experiencias, renderer PHP, generadores y preparación del hosting. `ensayos/v1/destino.php` es idéntico al de main. Se conserva la ruta adicional del sitemap dinámico de Advance Admin.
- Administración: se conservaron navegación/diseño compartidos, reportes por pestaña, seguimiento de negocios, CRM, campañas, pagos, filtros y el flujo Editar / Vista previa / Publicar con país/región editables.
- Se integraron también las novedades de main: importación ZIP con imágenes, experiencias y enlaces externos, procedencia privada de medios y mantenimiento de base de datos. La importación sigue creando borradores.
- Conflictos resueltos manualmente: `backend/README.md`, `backend/public/admin/destinos.php`, `backend/public/admin/index.php` y `scripts/php-destinations-router.php`.
- El JavaScript del editor se sirve como archivo externo para cumplir la política de seguridad existente. Las imágenes del ZIP se revisan mediante rutas autenticadas del mismo origen.

## Base de datos y conservación

Se mantienen intactos los archivos de migración de producción. Existen dos migraciones con prefijo 007: `007-media-provenance` y `007-advance-admin`. Son independientes; el identificador completo, no solo el número, distingue su estado. No renumerar una migración ya aplicada ni ejecutar todas las migraciones con un comodín.

`/admin/base-datos.php` reconoce ambas líneas y las ampliaciones de Advance hasta 013. Revisa el esquema antes de aplicar cambios, registra identificadores completos en `el_schema_migrations`, evita dos ejecuciones simultáneas mediante bloqueo MySQL y se detiene ante estados parciales. La migración 008 usa `SharedCatalogMigration`, nunca SQL sin comprobaciones: si hay establecimientos heredados con datos se bloquea antes de escribir; solo se retira esa tabla cuando está vacía. Los contactos existentes, estados de solicitudes y catálogo compartido no se sustituyen. La normalización 009 solo afecta estados internos históricos de Advance; no modifica snapshots de correo ni estados del catálogo/solicitudes.

No se añade una segunda tabla de destinos o negocios. No se reimporta la semilla sobre un catálogo poblado. No se toca la configuración privada, medios del hosting, datos comerciales ni credenciales. MySQL no garantiza rollback de DDL: si aparece un fallo, comprobar el estado y el respaldo antes de continuar; no repetir SQL a ciegas.

## Validación realizada

- Sintaxis PHP de la aplicación y panel; sintaxis del JavaScript del editor.
- Pruebas PHP de destinos, paquetes ZIP, diagnóstico de base y toda la suite Advance Admin (CRM, concurrencia, catálogo único, reportes, seguimiento, pagos, bajas y sitemap).
- Pruebas HTTP de solicitudes/administración y de Advance Admin; incluye CSRF, privacidad del borrador, rechazo de publicación incompleta e importación ZIP con imágenes guardadas como borrador.
- 40 pruebas de scripts, incluyendo render público, experiencias, SEO/enlaces y herramientas de entrega.
- Nueva prueba `backend/tests/advance/main-upgrade.php`: instala el esquema de main y su catálogo en SQLite con negocios y solicitudes ficticios, aplica las ampliaciones de Advance, compara todas las filas previas y comprueba claves foráneas. También comprueba los dos identificadores 007, estados parciales y normalización de valores históricos.
- Preparación del paquete de hosting en una copia temporal desechable; comprobación de que incluye el sitio, panel, ZIP y migraciones privadas. Verificación de rutas de 008 dentro del paquete.
- Revisión del editor local actualizado en el navegador; los datos de demostración anteriores se conservan.

## Antes de desplegar

1. Obtener respaldo verificable de MySQL/MariaDB, configuración privada y archivos subidos. Mantener la versión anterior del código para reversión coordinada.
2. Ensayar esta misma versión del paquete sobre una copia privada de la base en MySQL/MariaDB con los envíos deshabilitados. No usar la base real para el ensayo ni compartir secretos o datos privados en el chat.
3. Revisar `/admin/base-datos.php`; las migraciones de main ya aplicadas deben aparecer como aplicadas y las nuevas como pendientes. No instalar el esquema base ni importar catálogo en una base poblada. Aplicar pendientes con la herramienta protegida y volver a comprobar.
4. Comparar conteos/IDs y contenido previo: solicitudes, destinos publicados/borrador, negocios, imágenes, fuentes y traducciones. Probar sesión, vista previa, edición de borrador, reportes, seguimiento, pagos y preparación de campaña; no enviar correos reales durante el ensayo. Revisar sitemap y rutas públicas de los tres idiomas, incluidas experiencias.
5. Solo tras validar el ensayo, planificar el despliegue con una ventana que evite servir código nuevo sobre esquema incompleto. Conservar `private/config.php` y medios existentes; el paquete no contiene secretos. La reversión después de DDL requiere coordinar código y respaldo, no borrar tablas nuevas improvisadamente.

**Pendiente material:** no hay instancia MySQL/MariaDB disponible en este Mac ni se consultó el esquema/datos reales de producción. SQLite demuestra conservación lógica y funcionamiento local, pero no certifica todavía DDL, permisos, índices y claves foráneas del hosting. Este ensayo en MySQL es obligatorio antes de considerar la rama lista para desplegar.
