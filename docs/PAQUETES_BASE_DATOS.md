# Cambios de base de datos desde administración

## Formato permitido

Solo ZIP de hasta 2 MB, máximo 65 entradas y 4 MB descomprimidos. En la raíz:

- `manifest.json` con `format: explorandolugares-db-v1`, `engine: mysql-mariadb` y una lista ordenada `migrations`.
- Por migración: `id`, `file`, `sha256` y el archivo `.mysql.sql` correspondiente.

Los IDs, nombres y hashes deben coincidir con el catálogo privado `DatabaseMaintenance::MIGRATIONS` y los scripts de **la versión desplegada**. No se aceptan rutas, duplicados, archivos extra, scripts desconocidos o modificados. El ZIP no se extrae al disco ni se conserva en una carpeta pública. Se ejecuta únicamente el archivo del servidor después de verificar su coincidencia. Un hash declarado por el propio ZIP no constituye autorización: se compara con el servidor.

Ejemplo de generación desde la raíz del repositorio:

```sh
python3 scripts/generar-paquete-db.py --output .local/cambios-db.zip 030-business-categories
```

No subir archivos `.db`/SQLite, respaldos completos, PHP ni SQL arbitrario. Para una migración nueva: crear su SQL revisado, registrar dependencias y controles de tablas/columnas/datos en `DatabaseMaintenance`, probar en preprod, desplegar la definición aprobada y generar el ZIP desde esa misma revisión. Los paquetes no introducen código desconocido a producción.

## Aplicación

1. Copia de seguridad verificable de la base y revisión del script. Los cambios históricos pueden incluir datos de configuración; no asumir que todo es solo DDL.
2. Base de datos → cargar ZIP → validar. No ejecuta SQL.
3. Revisar IDs y estado. La revisión caduca en 10 minutos y se invalida si cambia el catálogo o el estado del esquema.
4. Escribir `APLICAR PAQUETE` y ejecutar. Se exige administrador y CSRF. Se respeta el orden del catálogo y las dependencias. Si falta una migración anterior, incluirla primero.
5. Comprobar el resultado y funcionamiento. Los componentes ya completos no se repiten. El registro `el_schema_migrations` conserva resultado y fecha; una aplicación fallida debe diagnosticarse por su estado real.

Un bloqueo de base impide aplicar simultáneamente desde esta herramienta. No protege frente a un operador que ejecute SQL por fuera sin el mismo bloqueo. MariaDB confirma muchas operaciones DDL automáticamente: un fallo puede dejar un estado parcial y **no se promete rollback completo**. No reintentar ciegamente.

La inicialización de una base completamente vacía corresponde al procedimiento de instalación/preprod; no al botón cotidiano del administrador. Se retiraron los botones genéricos de aplicar todo e importar catálogo desde esta pantalla.

## Diagnóstico y almacenamiento

Base de datos muestra todas las tablas del esquema y sus índices; tamaños y filas son estimaciones del motor. “Sin clave primaria” requiere revisión; no basta para prescribir índices. Para optimizar índices se necesita observar las consultas reales, su frecuencia y planes de ejecución. No se ejecuta `CHECK TABLE`, `ANALYZE` ni optimizaciones costosas automáticamente.

Estado del sistema muestra mediciones puntuales. Los contadores globales pueden incluir otras cuentas del hosting y se reinician al arrancar MariaDB. Una cifra acumulada distinta de cero no prueba un incidente actual. El panel no realiza una prueba de carga ni garantiza capacidad futura.

GoDaddy no expone necesariamente la cuota de cuenta mediante PHP. Para habilitar porcentaje y alerta roja desde 80%, configurar fuera de la web:

```php
'hosting_storage' => [
    'quota_bytes' => null, // Sustituir por cuota real de la cuenta, en bytes.
    'used_bytes' => null,  // Lectura real del proveedor, en bytes.
    'measured_at' => null, // ISO 8601 con zona horaria de esa lectura.
],
```

Se marca desactualizada una lectura mayor a 24 horas. No es un sensor automático. Hasta conectar métricas del proveedor, revisar periódicamente recursos cPanel/GoDaddy: almacenamiento, CPU, memoria, procesos de entrada/PHP, errores/rechazos, tráfico y conexiones por usuario. El espacio libre del sistema de archivos **no** sustituye la cuota contratada. No se modificó la configuración privada de producción.
