# Ventana de canales y filtros

Actualización del 15 de octubre de 2026 sobre `negocios-dinamicos-phm`.

El resumen muestra los totales de la consulta (destino, búsqueda y suscripción) antes de filtrar por presencia de canales. Seleccionar Con canales o Sin canales modifica la lista, sin convertir el resumen general en cero. Las tres tarjetas permiten filtrar directamente. Se mantienen separados los números de negocios y los registros de evidencia.

Ver canales abre un diálogo nativo sobre la lista mediante una consulta autenticada de solo lectura. No navega al final de la tabla ni recarga la lista; conserva filtros, URL y posición. Cerrar y Escape devuelven el foco al mismo negocio. El diálogo limita su tamaño a la pantalla y desplaza su propio contenido. Incluye tarjetas por canal/valor, botón Copiar, fuentes agrupadas y opciones de destinatario/cuenta responsable. Las fuentes y notas siguen escapadas; no cambia datos, permisos comerciales ni publicación. Tras guardar una anotación, el diálogo vuelve a abrirse con el resultado.

Pruebas: partición de filtros y resumen en `business-crm.php`; rutas, tarjetas y fuentes en `admin-pages.integration.py`. Ensayo local de navegador con datos ficticios en escritorio y móvil: apertura/cierre, Escape, devolución de foco, posición, URL, contraste y ausencia de desbordamiento horizontal de la página. Se conservan los IDs empresariales y la asociación de los canales existentes.
