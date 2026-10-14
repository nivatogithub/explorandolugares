# Revisar Advance Admin en el Mac

Este es el entorno local de revisión solicitado por el propietario. No hay hosting de pruebas externo. Escucha únicamente en `127.0.0.1`, conserva datos ficticios en `.local/advance-preview` y mantiene Resend deshabilitado.

Desde la raíz del repositorio:

```sh
python3 scripts/preview-advance-admin.py
```

Abrir `http://127.0.0.1:8037/admin/index.php`. La herramienta muestra el usuario y contraseña locales al iniciar. Si el puerto está ocupado, elegir otro con `--port 8038`. Detener con Ctrl+C en el terminal que ejecuta el servidor. Para volver a levantarlo, ejecutar la misma instrucción; conserva los cambios de la demostración y no vuelve a insertar registros.

La primera ejecución crea una base SQLite aislada con el esquema territorial compartido, migraciones 007–010, un destino, un negocio, un contacto y una plantilla de demostración. Todos son ficticios. La base, configuración y acceso están en una carpeta privada ignorada por Git y fuera de la carpeta pública; no copiar estas credenciales a producción. Las sesiones usan una carpeta y nombre propios para evitar compartir autenticación con otros paneles locales del Mac.

El panel permite crear y revisar campañas; enviar permanece deshabilitado. Los enlaces de baja del entorno local apuntan al mismo puerto del Mac mediante una adaptación de la copia desechable de la aplicación. La validación HTTPS de la aplicación original permanece intacta. Al cambiar de puerto, recrear las campañas locales cuyos enlaces todavía apunten al anterior.

No introduce un servicio de correo en el Mac ni abre acceso desde Internet. No lee configuración del hosting, no importa clientes y no utiliza credenciales de Resend. Los recursos visuales comunes del panel se copian desde el mismo repositorio.

Esto valida navegación y lógica con SQLite. Sigue pendiente comprobar instalación, consultas y migraciones en MySQL/MariaDB aislado; no se encontró ese servidor ni Docker instalado al preparar esta revisión. No llamar a esta comprobación una validación de MySQL.

Comprobación realizada: pantalla de login obligatoria con sesión aislada, acceso al dashboard y visualización de un contacto/un negocio de prueba. El servidor local quedó activo en el puerto 8037 al entregar esta revisión.
