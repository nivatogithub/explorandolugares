# Diseño y navegación compartidos de administración

Actualizado: 14 de octubre de 2026. Rama `feature/advance-admin`.

La administración utiliza una sola estructura generada por `backend/app/advance/AdminLayout.php` y una hoja común `backend/public/admin/admin.css`. Aplica a inicio de sesión, solicitudes, sistema, destinos, negocios, CRM, campañas, plantillas, resumen CRM y reportes.

La navegación pertenece a toda la administración, no a Reportes. Está agrupada en visión general, contenido, relaciones/comunicación y gestión. Destinos es un módulo de contenido al mismo nivel que los demás; Reportes es una vista general. Cada página marca su entrada activa y conserva el menú al navegar. El cierre de sesión está disponible desde todas las pantallas privadas.

Logo, verdes, fondos cálidos y acentos naranjas se toman del ensayo vigente de Explorando Lugares dentro de este repositorio. No representan una aprobación definitiva de identidad. Se eliminó el diseño azul/gris exclusivo de Reportes y las cabeceras duplicadas. Los estilos específicos del reporte cubren únicamente tarjetas, tablas y agrupaciones del contenido; la estructura, formularios y navegación vienen del diseño común.

Se mantienen URLs, servicios, controles de acceso y acciones existentes. No se modificaron migraciones ni datos como parte de esta unificación. La pantalla pública de baja conserva su hoja independiente: no pertenece al panel administrativo.

El entorno local copia también el logo vigente a sus recursos públicos y mantiene datos/sesiones aislados. La actualización se hace con `python3 scripts/preview-advance-admin.py --prepare-only`.

Validación: lint PHP, pruebas de administración de destinos, reportes, recorrido HTTP de Advance Admin y regresión de registro/sesión/solicitudes. Se inspeccionaron visualmente Negocios y Destinos en el navegador local; ambas usan la misma estructura. Los estilos incluyen adaptación a pantallas estrechas y enlace para saltar al contenido.
