# Logo y footer — revisión en dev1

Rama: `fix/approved-cream-logo`. No publicado en producción.

Se conserva el logo transparente original del sitio: cambió únicamente el canal alfa de 1.340 píxeles blancos de los espacios interiores de las letras. No se cambiaron sus colores RGB, forma ni composición. El fondo visible es el crema del encabezado. La imagen adjunta por el propietario se usó como referencia; su sustitución inicial fue descartada tras aclarar el alcance.

Recursos: WebP transparente de 140/280/360 px para el encabezado adaptable; favicon PNG de 32 px e ICO de 16/32/48 px; icono Apple de 180 px; PNG para perfiles de 512/1080 px con fondo crema. Los maestros y la comparación están fuera de la carpeta pública. La URL anterior permanece compatible y tiene un único productor en backend/public. Las nuevas rutas evitan depender de la caché anterior.

El footer compartido añade YouTube y un icono de ayuda accesible, conserva el idioma del contacto y alinea el botón de privacidad dentro del contenedor. Los márgenes públicos se reducen a 24 px por lado desde 651 px, con máximo de 1440 px. El botón compacto del menú centra las líneas.

Validación: construcción pública aislada completa; controles de recursos e identidad; `php scripts/tests/test_brand_logo.php`; revisión navegada en dev1 con anchos efectivos 1366 y 390 px, sin desbordamiento horizontal, y apertura/cierre del menú. No se importaron datos ni se modificaron bases de datos, cuentas, métricas o imágenes de destinos. Dev1 usaba fragmentos estáticos antiguos: se actualizó su código con el paquete vigente para poder revisar el shell compartido.

Evidencias: entregables/logo-corregido-20261106 (fuera del paquete público).
