# Promoción posterior del candidato

No se ejecuta en este encargo. No se consultó ni modificó producción.

1. Cerrar los pendientes de ESTADO_CANDIDATO.md y comparar una construcción limpia del commit exacto. Revisar todo el delta; imágenes sin cambios de bytes no deben transferirse otra vez por una diferencia de URL/query.
2. Integrar main actualizado en preprod y esta rama por función. Resolver explícitamente solapamientos con el proceso de registro/editorial de negocios. Mantener estadísticas, IDs, sesiones y datos vivos; no importar la fixture SQLite ni archivos bajo .local.
3. Validar el paquete en el Apache/PHP/MariaDB de preprod. Confirmar extensión DOM y rutas de app privada/raíz pública; servidor de revisión PHP/SQLite no reemplaza este control. No se requieren migraciones de tablas por estos cambios de presentación.
4. Aprobación humana del commit completo. Respaldo de datos/recovery según proceso vigente; no hacer copias integrales del sitio productivo ni acumular versiones de código.
5. Despliegue incremental de código y manifiestos. Mantener configuración, uploads, medios persistentes y métricas. No enviar correos ni crear cuentas ficticias en producción.
6. `retired-resources.json` contiene propuestas de retiro: no elimina hosting. Revisar referencias actuales, caché y consumidores antes de autorizar cada baja; no retirar imágenes por omisión. Los controladores antiguos pueden quedar temporalmente en hosting sin ser consumidos por el HTML nuevo.
7. Validar rutas públicas, sesión/permisos, formularios, recursos, idiomas/SEO, cache busting y continuidad de medición con tráfico concurrente. Solo después cerrar contingencia y promover el mismo commit a main con `scripts/sincronizar-main-produccion.py`.

Reversión: código desde Git y despliegue incremental de la versión confirmada. No restaurar una base vieja sobre datos nuevos. Si hay fallo en la aplicación del código, detener promoción/retiros y reconciliar los archivos mediante el mecanismo existente; conservar evidencia privada. Este plan no promete indisponibilidad cero.
