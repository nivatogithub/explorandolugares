# Contrato vigente y auditoría del circuito de negocios

Fecha: 24 de octubre de 2026. Sustituye las decisiones contradictorias de documentos históricos de registro y administración. Fuente: instrucciones del propietario en esta conversación. Los estados siguientes indican evidencia comprobada, no una certificación de todo el producto.

## Recorrido requerido

1. Cuenta personal creada, correo verificado e inicio de sesión antes de crear/reclamar negocios. Nombre y correo se toman de la cuenta; no se vuelven a pedir credenciales ni residencia en el negocio.
2. Buscar en los lugares publicados, reclamar la ficha existente o crear una nueva comprobando nombres similares en el mismo lugar. Conservar identidad y datos; una disputa no sustituye al responsable actual.
3. Wizard ordenado: negocio → plan y periodicidad con ejemplos → contenido según plan → canales admitidos → identificación del negocio, relación y soporte privado → guardar → pago cuando corresponda. Conservar datos ante validaciones y al retomar el pago.
4. Gratis: nombre/categoría e identificación/documento, sin canales de pago. Destacado: resumen, dirección y teléfono. Página: portada antes del resumen y sus canales. Premium: descripción ampliada y hasta CINCO productos, cada uno con título, descripción breve y foto. Resumen 450 caracteres; descripción Premium 1200; producto título 100 y descripción 180. Contadores visibles. Horarios fuera del flujo activo.
5. Teléfono como control reutilizable con prefijo calculado; mapas públicos abren Google Maps en el punto elegido. Vista previa privada real con datos enviados, accesible sobre la página. Sin estadísticas de negocios nuevos ni nuevas elecciones de plan en pago.
6. Guardar crea solicitud/borrador privado asociado a la cuenta; no publica. El propietario puede retomar el pago; al enviar comprobante queda en revisión y no vuelve a mostrarse como pago por realizar.
7. Administración, desde Negocios locales: revisar soporte de titularidad y comprobante por separado; aprobar, pedir aclaración o rechazar con motivo. Verificar correo, asociar cuenta y confirmar pago son hechos distintos.
8. Solo administración exporta/importa ZIP. Exportación: originales, textos y referencias por posición en JSON, README con reglas editoriales y de imágenes. Excluir identificación, soporte legal, cuenta y comprobante de pago. Importación: imágenes finales, ES/EN/PT-BR y procedencia; guardar nueva versión privada. Reimportar durante preparación/correcciones conserva historia y revoca aprobaciones anteriores.
9. Administración revisa textos, imágenes, traducciones, canales y diseño de la versión exacta y envía prepublicación. Correo al propietario con enlace autenticado. El propietario aprueba y publica esa versión, o solicita cambios con motivo. Revisar pago no publica. Modificar una propuesta no altera la última versión pública.
10. Suscripciones: consulta por negocio, plan, vigencia y vencimiento. Renovación iniciada por propietario y validada por administración sin repetir preparación/publicación de contenido. Conservar periodo/saldo, historial y pagos.
11. Errores técnicos: referencia visible, registro privado acotado, consulta en administración y ZIP de diagnóstico para IA sin datos personales/secretos. Las validaciones ordinarias no son fallos técnicos.
12. Menú centralizado: Negocios locales, Solicitudes y mensajes, Cuentas, Suscripciones, Plantillas, CRM/campañas y Sistema. Editorial, paquetes y requisitos son partes de la ficha de negocio. Edición manual organizada por imágenes, textos con selector de idioma, canales y datos futuros Beta inactivos. No mostrar IDs internos al operador.

## Hallazgos corregidos en esta revisión

| Hallazgo comprobado | Corrección y prueba |
|---|---|
| Importar ZIP abría una transacción y save() intentaba abrir otra | Guardado respeta la transacción del importador. Subida HTTP real e importación repetida verificadas. |
| ZIP leía textos públicos y referencias antiguas; omitía productos/posiciones | Exporta última versión privada, textos/productos y image_slots. Prueba comprueba texto actualizado y quinto producto. |
| JavaScript quitaba productos a partir del tercero y cambiaba textos después de renderizar | Retirada esa transformación; cinco posiciones y límite de 180 desde PHP. Retirado bloque inerte completo del wizard antiguo. |
| Fotografía product_4 rechazada por el servicio | Quinta posición admitida y comprobada con transacción reversible. |
| Premium solo admitía cuatro imágenes temporales | Seis: portada y cinco productos. |
| Productos y posiciones de registro no llegaban al borrador | Se preservan índices y asociaciones al consumir archivos; se crea borrador dentro de la transacción de registro y se hidrata desde la solicitud. |
| Página ocultaba ubicación necesaria después | Página muestra ubicación dentro de la captura. |
| Publicación exigía descripción ampliada incluso donde el plan la prohíbe | Exigencia ligada al límite real del plan. |
| Rechazar se mostraba pero su acción no era aceptada | Rechazo conectado a la transición validada, con motivo. |
| Fallar publicación tras aprobar dejaba al propietario sin botón para reintentar | Estado owner_approved ofrece Publicar mi negocio al propietario. |
| Errores de ejecución no permitía exportar | ZIP incidents.json + README.md con guía de diagnóstico y límites del registro. |

## Pendientes demostrados: impiden declarar cierre completo

| Prioridad | Pendiente | Evidencia / aceptación |
|---|---|---|
| Alta | Renovación desacoplada de publicación | PaymentEvidenceService deja el pago en paid_pending_publication; SubscriptionChangeService.activation exige el vencimiento y depende de publicación. Falta circuito claro de renovación futura/activación sin nueva editorial. |
| Alta | Retención documental coherente | BusinessOwnershipEvidence usa delete-after-verification-v1 y exige borrado antes de publicar. La decisión del propietario pide conservar soporte hasta publicar y borrarlo después. Dos pantallas validan con semántica distinta. Unificar verificación y retiro con reintento. |
| Alta | Entrega de correo real | Prueba de prepublicación comprobada en simulación. No se enviaron correos reales. Fallo/resultado incierto necesita tratamiento visible; no inferir entrega por aceptación del proveedor. |
| Alta | Registro y publicación completos en Apache/MariaDB de Dev2 | Pruebas PHP/SQLite y ZIP HTTP real comprobadas. Falta recorrer las cuatro modalidades en el paquete final de Dev2 con captura, pago, soporte y correo simulado. |
| Media | Administración todavía no cumple toda la organización pedida | Menú principal centralizado; editor aún tiene formulario largo, no las pestañas e idioma seleccionable solicitados. Cuentas enlaza página, sin panel superpuesto. Hay páginas antiguas alcanzables. |
| Media | Última pasada visual del wizard | Ejemplos/modal y estados requieren navegador en móvil/escritorio. La vista del wizard apunta al ejemplo, no certifica vista previa con todos los datos enviados. |
| Media | Sesión durante subida asíncrona/registro | Se centralizó el guard de páginas privadas, pero cargas y búsqueda tienen tratamiento separado. Falta demostrar redirección del navegador ante 401 en cada interacción con conservación de borrador. |
| Media | Diagnóstico de fallos operativos de archivos | PortalIncidents excluye RuntimeException ordinarias. Fallos de almacenamiento del paquete necesitan clasificación técnica para quedar exportables, sin registrar datos sensibles. |

## Evidencia de esta revisión

- backend/tests/editorial-roundtrip.integration.py: archivo ZIP real subido por HTTP → importar dos veces → cinco productos y tres idiomas → revisión → correo simulado → aprobación/publicación por propietario.
- business-editorial.php: versiones privadas, permisos, conflictos, traducciones, pago previo, conservación de vigencias, referencias y rollback de transacción externa.
- business-editorial-package.php: ZIP de fuentes y textos de última versión, quinto producto, posiciones y exclusión de soportes privados; bloqueo sin pago revisado.
- business-self-onboarding.php: cuenta verificada, nuevo/reclamado, disputa, duplicados, múltiples lugares, idempotencia y elección de plan.
- business-onboarding-limits.php, business-account-portal.php, business-subscriptions.php, subscription-changes.php: límites, rollback de solicitud, planes/periodos/saldos.

Estos cambios están en la rama de trabajo. No equivalen a revisión visual ni a despliegue productivo. Los archivos modificados previamente de ensayos/v1 se preservan fuera del cambio auditado.
