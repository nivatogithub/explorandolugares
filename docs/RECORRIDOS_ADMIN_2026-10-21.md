# Recorridos preparados para revisión administrativa

Fecha: 21 de octubre de 2026. Instancia: http://127.0.0.1:8046/admin/index.php.

Estado: escenarios y datos preparados; recorrido visual pendiente de que el propietario lo indique. No se ha realizado ni aprobado la revisión visual de estos casos. No se enviaron correos, publicaron contenidos reales ni modificó producción.

## Preparación y uso

Los ocho negocios llevan el prefijo RECORRIDO y los contactos usan example.invalid. Son ficticios. Buscar por el número permite distinguirlos de las otras pruebas existentes. El script scripts/preparar-recorridos-admin.py añade los casos únicamente a .local/revision-admin-operativa/private/review.sqlite, guarda un respaldo local previo y no reemplaza registros existentes al repetirlo. Manifest privado: private/walkthrough-cases.json. Sin cambios de esquema. Los vencimientos se calculan con America/Bogota al crear los casos; si la revisión ocurre días después, consultar las fechas concretas y no asumir que siguen faltando cinco días.

La pestaña antigua negocios.php?tab=atencion abre el listado unificado; comenzar los recorridos por los enlaces actuales del menú. Mantener las herramientas de navegador bloqueadas fuera de esta preparación: no se usaron automatizaciones alternativas para obtener imágenes o realizar recorridos.

## Datos preparados

| Código | Caso ficticio | Grupo de origen | Cuenta | Plan registrado / estado |
| --- | --- | --- | --- | --- |
| 01 | Importado Gratis sin cuenta | Importados del portal | Sin cuenta | Gratis, pendiente de publicación |
| 02 | Registrado Gratis publicado | Registrados por personas | Correo validado, asociación activa | Gratis, activo |
| 03 | Pago vence en cinco días | Registrados por personas | Correo validado, asociación activa | Resaltado (featured), activo |
| 04 | Página vencida | Registrados por personas | Correo validado, asociación activa | Página Comercial (page), vencida hace tres días al crear el caso |
| 05 | Premium vigente | Registrados por personas | Correo validado, asociación activa | Premium (page_premium), activo durante otros 60 días al crear el caso |
| 06 | Pago confirmado pendiente de publicación | Registrados por personas | Correo validado, asociación activa | Página Comercial, paid_pending_publication; sin comprobante bancario simulado |
| 07 | Importado reclamado con cuenta | Importados del portal | Correo validado, asociación activa | Gratis, activo |
| 08 | Solicitud sin documentos | Registrados por personas | Correo validado, asociación activa | Gratis pendiente; solicitud documental pendiente sin adjuntos |

Los estados de pago son fixtures para visualizar etiquetas, no evidencia de dinero recibido. No confirmar pagos basándose en ellos. Los documentos ausentes son intencionales: no se fabricaron identidades ni documentos de acreditación.

## Recorridos propuestos

### 1. Menú y separación de negocios — 5 minutos

Abrir Negocios. Seleccionar Registrados por personas y buscar RECORRIDO. Después cambiar a Importados del portal. Limpiar filtros y comprobar que conserva el grupo. Abrir el enlace antiguo tab=atencion.

Esperado: un solo listado, sin Atención/Catálogo/Revisión duplicadas. Casos 02–06 y 08 en registrados; 01 y 07 en importados. El caso 07 sigue siendo importado aunque tenga cuenta. No existe Crear negocio ni Asignar cuenta manualmente. El enlace antiguo abre el listado vigente.

### 2. Prospecto comercial y negocio con responsable — 5 minutos

Abrir Gestión comercial, suscripción Todas, grupo Prospectos sin cuenta, buscar RECORRIDO. Cambiar a Negocios con cuenta. Abrir canales del 07.

Esperado: 01 entre prospectos; 02–08 con cuenta. Canales de investigación y cuenta responsable diferenciados. Sin contactos duplicados al cambiar de grupo; sin autorización automática de campañas. Que un negocio tenga cuenta no convierte su origen en registro personal. Consultar el detalle no cambia sus datos.

### 3. Cuenta verificada y documentación faltante — 5 minutos

Abrir Cuentas y encontrar Persona ficticia 08. Abrir Revisión de negocios y seleccionar Pendientes. Buscar RECORRIDO 08, abrir el detalle privado y consultar el plan solicitado.

Esperado: cuenta activa con correo confirmado; revisión documental pendiente separada. La falta de documentos se indica y la ficha no se presenta como publicada. Los radios cambian de filtro sin botón y muestran conteos. No aprobar durante el recorrido de consulta.

Limitación conocida: las notas mínimas y la aprobación/asociación del servicio anterior no se eliminaron en este worker; no confundir la mejora de presentación con la unificación completa del registro.

### 4. Plan Gratis y vista real de tarjeta — 3 minutos

En Negocios abrir Ver tarjeta del 02. Consultar el 01 sin publicarlo. Abrir Suscripciones y buscar ambos.

Esperado: Gratis es una modalidad válida; borrador se llama Pendiente de publicación. El listado general de Suscripciones de pago no incluye 01/02. La vista privada corresponde a la ficha real y no modifica plan ni publicación. La página comercial completa no debe darse por validada con una tarjeta.

### 5. Renovaciones próximas, vencidas y Premium — 5 minutos

Abrir Suscripciones, filtrar renovación en 7 días y buscar RECORRIDO 03. Cambiar a Vencidas y buscar 04. Quitar filtro y buscar 05.

Esperado: 03 con fecha y días restantes; 04 en vencidas; 05 fuera del filtro de 7 días. La historia de una suscripción vencida permanece registrada. En cualquier representación pública efectiva del 04 debe aplicarse Gratis, sin resaltado ni página comercial/Premium. Ese último comportamiento se registra como resultado a comprobar, no como aprobado por tener un fixture vencido.

### 6. Pago y publicación separados — 3 minutos

Buscar RECORRIDO 06 en Negocios y Suscripciones. Consultar el detalle del plan.

Esperado: Pago confirmado, pendiente de publicación; no inferir que está publicado ni activar un cobro o renovar al consultar. Sin comprobante simulado que pretenda demostrar una transferencia real. No pulsar Publicar durante esta revisión de consulta.

### 7. Plantilla y correo individual — 8 minutos

Abrir Plantillas y correos → Plantillas HTML. Revisar Respuesta sobre participación o Aviso de vencimiento. Usar una plantilla con el correo ficticio del 03. Revisar variables completadas; probar un ajuste de asunto/contenido individual y abrir la vista previa. Consultar Historial filtrado por el negocio.

Esperado: destinatario, remitente, asunto, HTML y texto visibles; nombre/destino/fecha corresponden al registro. Datos ausentes quedan vacíos. El ajuste individual no altera la plantilla base. Guardar plantilla o preparar borrador no envía. En local no hay botón habilitado de envío real. El historial conserva un borrador con su contenido. No se requieren campañas para una respuesta individual.

Requiere escritura solo en datos sintéticos: guardar plantilla/borrador. No ejecutar envíos. No interpretar Solicitud aceptada como entrega comprobada.

### 8. Recordatorio desde la suscripción — 3 minutos

En la fila 03 seleccionar Preparar recordatorio. Elegir Aviso de vencimiento y el contacto ficticio. Abrir vista previa sin enviar.

Esperado: el compositor queda filtrado por negocio, permite elegir plantilla y revisar fecha/destinatario antes de confirmar. No se envía ni programa automáticamente. El vencimiento implica pérdida de beneficios de pago; no eliminación de la ficha o del historial.

### 9. Campaña y permisos — 5 minutos

Abrir Campañas → Negocios destinatarios. Filtrar por RECORRIDO. Consultar el estado de los correos y abrir Crear campaña. Ir a Plantillas y mensajes.

Esperado: los contactos recién preparados quedan pendientes, sin consentimiento inventado. Ningún correo pendiente aparece seleccionable como autorizado por defecto. Brevo se configura en Sistema, sin pestaña duplicada en Campañas. Las plantillas HTML individuales tienen acceso claro; las campañas grupales aún usan formato fijo. No autorizar correos ficticios ni preparar envíos externos durante el recorrido.

### 10. Actualización masiva, reportes y pantallas pequeñas — 8 minutos

Consultar Actualización masiva de datos: exportar → editar externamente → revisar diferencias → confirmar. Revisar el selector de JSON en español y su espaciado. Abrir Reportes y Solicitudes de usuarios. Reducir el ancho de la ventana y recorrer el menú/tabla con teclado.

Esperado: ningún título tapado por el menú, tablas desplazables, filtros y enlaces legibles, foco visible y selector sin círculo rojo. El menú móvil permite llegar a todas las secciones. No importar ni aplicar archivos durante este recorrido de consulta. Exportar contiene datos privados; mantener el archivo dentro de la carpeta local de revisión si se prueba.

## Registro de resultados para la sesión

| Recorrido | Estado inicial | Resultado observado | Ajuste / decisión |
| --- | --- | --- | --- |
| 1–10 | Preparado, no revisado visualmente | Completar durante la sesión con el propietario | No declarar aprobado por las pruebas automatizadas |

Se puede dividir en tres sesiones: navegación/negocios (1–4), suscripciones/comunicación (5–9), presentación y exportación (10). El tiempo total es orientativo y no programa ninguna ejecución automática.

## Exclusiones y verificaciones posteriores

No incluye despliegue, envíos reales, autorizaciones comerciales, pagos, publicación ni reparación de datos. La biblioteca HTML aplicada a campañas grupales, recordatorios automáticos, seguimiento de aperturas/clics individuales y circuito editorial completo del portal siguen pendientes. Apache/MariaDB y aprobación del candidato de preprod se validarán aparte. La sesión visual empieza solo cuando el propietario avise.
