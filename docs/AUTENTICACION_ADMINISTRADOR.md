# Acceso unificado de administración

La cuenta es una persona en `accounts`; el permiso interno `account_roles.role=administrator` concede acceso al panel. El correo y los datos de registro no otorgan permisos. La migración aditiva 033 crea la relación; no crea administradores ni copia credenciales automáticamente.

El inicio de sesión existente admite cuentas empresariales y personas con permiso administrador. Mantiene la sesión del portal y la administrativa vinculadas. Cada petición valida cuenta activa, permiso vigente y caducidad de 30 minutos; cerrar sesión elimina ambos accesos. La cuenta administradora no necesita negocio asociado.

La configuración privada `unified_admin_only=true` desactiva el acceso anterior por usuario configurado y rechaza sesiones administrativas antiguas. Sin esta opción, se mantiene el acceso previo como compatibilidad hasta aprobar la migración de producción. No habilitar sin haber preparado la cuenta y probado su acceso.

## Aprovisionamiento local autorizado

El hash existente del administrador se consulta por SFTP verificado sin cambiar el hosting. Nunca se muestra en salidas ni se incorpora a Git. Se deposita temporalmente en `.local/preprod/private/administrator-password.hash`, con permisos 0600. Esta es una excepción autorizada al aislamiento habitual de credenciales: el equipo local debe protegerse como equipo con acceso administrativo a producción.

Después de aplicar 033, ejecutar `preprod/crear-administrador.php` dentro del contenedor por CLI, mediante entrada estándar. Solo funciona con `EL_PREPROD_LOCAL=1` y `local_test=true`. Crea `admin@explorandolugares.com` como persona con permiso administrador, conserva exactamente el hash (no lo vuelve a hashear), activa `unified_admin_only` localmente y elimina el archivo temporal. Rechaza una cuenta preexistente para no sobrescribir credenciales. Desactivar `admin_without_password` en la configuración local y actualizar el ambiente para probar la autenticación real.

Ni el script local, ni la cuenta, ni la configuración privada se publican automáticamente. Producción requiere aprobación posterior y aprovisionamiento explícito en su propia base, reutilizando su hash dentro del hosting.
