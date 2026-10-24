# Contrato vigente y auditoría del circuito de negocios

Fecha: 24 de octubre de 2026. Sustituye las decisiones contradictorias de documentos históricos de registro y administración. Fuente: instrucciones del propietario en esta conversación. Los estados siguientes indican evidencia comprobada, no una certificación de todo el producto.

## Recorrido requerido

1. Cuenta personal creada, correo verificado e inicio de sesión antes de crear/reclamar negocios. Nombre y correo se toman de la cuenta; no se vuelven a pedir credenciales ni residencia en el negocio.
2. Buscar en los lugares publicados, reclamar la ficha existente o crear una nueva comprobando nombres similares en el mismo lugar. Conservar identidad y datos; una disputa no sustituye al responsable actual.
3. Wizard ordenado: negocio → plan y periodicidad con ejemplos → contenido según plan → productos (solo Premium, paso independiente) → canales admitidos → identificación del negocio, relación y soporte privado → guardar → pago cuando corresponda. Conservar datos ante validaciones y al retomar el pago.
4. Planes acumulativos: Gratis requiere nombre, lugar/categoría, NIT o RNT, relación, declaración y soporte privado. Destacado agrega una descripción de hasta 300 caracteres, dirección/punto en mapa y teléfono (WhatsApp disponible). Página conserva lo anterior, aumenta la descripción a 450 y agrega portada, página propia y correo. Premium conserva lo anterior, aumenta la descripción a 1200, agrega sitio web, Instagram, Facebook y LinkedIn, y hasta CINCO productos en un paso propio, cada uno con título de 100, descripción breve de 180 y foto. Una sola descripción en el registro, no un resumen y otra ampliada simultáneos. Contadores visibles y variables con el plan. Cambiar a un plan menor conserva el texto y exige acortarlo, nunca lo trunca automáticamente. Horarios fuera del flujo activo. No borrar contenido editorial existente. Reglas y límites aceptados por el propietario después de revisar la matriz del historial el 24 de octubre.
5. Teléfono como control reutilizable con prefijo calculado; mapas públicos abren Google Maps en el punto elegido. Vista previa privada real con datos enviados, accesible sobre la página. Sin estadísticas de negocios nuevos ni nuevas elecciones de plan en pago.
6. Guardar crea solicitud/borrador privado asociado a la cuenta; no publica. El propietario puede retomar el pago; al enviar comprobante queda en revisión y no vuelve a mostrarse como pago por realizar.
7. Administración, desde Negocios locales: revisar soporte de titularidad y comprobante por separado; aprobar, pedir aclaración o rechazar con motivo. Verificar correo, asociar cuenta y confirmar pago son hechos distintos.
8. Solo administración exporta/importa ZIP. Exportación: originales, textos y referencias por posición en JSON, README con reglas editoriales y de imágenes. Excluir identificación, soporte legal, cuenta y comprobante de pago. Importación: imágenes finales, ES/EN/PT-BR y procedencia; guardar nueva versión privada. Reimportar durante preparación/correcciones conserva historia y revoca aprobaciones anteriores.
9. Administración revisa textos, imágenes, traducciones, canales y diseño de la versión exacta y envía prepublicación. Correo al propietario con enlace autenticado. El propietario aprueba y publica esa versión, o solicita cambios con motivo. Revisar pago no publica. Modificar una propuesta no altera la última versión pública.
10. Suscripciones: consulta por negocio, plan, vigencia y vencimiento. Renovación iniciada por propietario y validada por administración sin repetir preparación/publicación de contenido. Conservar periodo/saldo, historial y pagos.
11. Errores técnicos: referencia visible, registro privado acotado, consulta en administración y ZIP de diagnóstico para IA sin datos personales/secretos. Las validaciones ordinarias no son fallos técnicos.
12. Menú centralizado: Negocios locales, Solicitudes y mensajes, Cuentas, Suscripciones, Plantillas, CRM/campañas y Sistema. Editorial, paquetes y requisitos son partes de la ficha de negocio. Edición manual organizada por imágenes, textos con selector de idioma, canales y datos futuros Beta inactivos. No mostrar IDs internos al operador.
13. Formularios compactos: idiomas por pestañas, con un solo idioma visible. Productos por pestañas agregadas a demanda hasta cinco; mostrar únicamente las posiciones configuradas (dos, cuatro o cinco), un producto visible por vez. Conservar valores al navegar y enviar todos los productos/idiomas configurados. Quitar un producto no mueve las fotografías de los demás.

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
| Media | Administración todavía no cumple toda la organización pedida | Menú principal centralizado; idiomas y productos ya usan pestañas compartidas en la rama. Faltan agrupación general por imágenes/textos/canales y revisión visual: apertura del navegador de prueba rechazada. Cuentas enlaza página, sin panel superpuesto. Hay páginas antiguas alcanzables. |
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

## Plan completo de implementación por bloques

Solicitado el 24 de octubre de 2026. Las tres áreas son cliente, administración y arquitectura/estabilidad. Reutilizar los servicios actuales; no reconstruir funciones existentes por una reorganización visual. La lista de pendientes anterior es el diagnóstico; los bloques siguientes son el orden de ejecución y sus condiciones de cierre.

### Control del trabajo

- Estados permitidos por bloque: pendiente, en ejecución, implementado, probado en entorno aislado, verificado en Dev2, aceptado por el propietario. Un estado no implica los posteriores.
- Registrar commit, origen/puerto, paquete, casos ejecutados, resultado y pendientes. Una corrección posterior invalida únicamente las verificaciones que afecta.
- Cada fallo encontrado se asigna al bloque correspondiente. Si impide continuar, resolver la dependencia y volver al mismo bloque; no cambiar el proceso comercial por conveniencia técnica.
- Probar la causa original, el caso satisfactorio, el error y el reintento. Actualizar pruebas antiguas contra el contrato vigente; no conservar expectativas contradictorias.
- Revisar con navegador integrado. No lanzar Chrome externo por defecto. Una prueba visual no realizada permanece pendiente, aunque sintaxis y servicios pasen.
- Construir siempre con scripts/preparar-hosting.py en una salida nueva. No llamar directamente al constructor interno ni mezclar paquetes. Datos/configuración/archivos de usuarios son propios del ambiente; el paquete de código se promueve sin variantes funcionales por ambiente.
- No borrar ni restablecer cuentas, estadísticas, solicitudes, pagos o contenido vivo para lograr que un caso pase.

### Área A — Cliente: registro y seguimiento

**A1. Cuenta, búsqueda y wizard.** Cuenta y correo verificados antes del negocio; identidad de la cuenta solo lectura; crear/reclamar en cualquier lugar publicado; detección de duplicados y disputa sin apropiación automática; plan y periodicidad en su paso; contenido y canales por beneficios del plan. Reutilizar teléfono/dirección/selectores; enlaces Google Maps; sin horarios ni campos futuros activos. Contadores según límites del contrato. Idiomas y productos mediante pestañas, sin bloques verticales repetidos.

Cierre: completar Gratis, Destacado, Página y Premium; nuevo y reclamado; comprobar dos lugares distintos, duplicado, negocio con otro responsable y entrada de otra cuenta. En Premium probar 0, 2, 4 y 5 productos, quitar uno intermedio, agregar otro y conservar la relación foto/producto. Un sexto no se admite en navegador ni servidor.

**A2. Archivos, vista previa y recuperación.** Subir portada/productos/documento progresivamente con estado visible; conservar referencias privadas; permitir retirar/reemplazar sin acumular referencias activas. Vista previa real con datos capturados, tarjeta y página según plan, sobre la pantalla y con regreso claro. Preservar datos ante validación, reintento y sesión vencida, sin recuperar credenciales/documentos personales desde almacenamiento inseguro del navegador. Unificar respuesta de sesión y manejo de 401 en interacciones.

Cierre: cambiar pestañas/pasos no pierde valores; error RNT/NIT devuelve al paso pertinente; sesión vencida lleva a acceso y permite retomar lo recuperable. Subida fallida no se presenta como completada. Guardar dos veces no duplica negocio, solicitud, membresía ni archivos vinculados.

**A3. Guardado, pago y seguimiento.** Guardar muestra confirmación, mantiene borrador privado y asociación correcta; pago aparte con plan elegido, periodicidad, valor e instrucciones reales del ambiente. Retomar pago después; al enviar soporte mostrar revisión y ocultar la acción de pago ya realizada. Mostrar aclaraciones/rechazos y permitir entregar lo solicitado. El propietario no exporta ni importa ZIP ni edita la propuesta mientras está enviada a revisión.

Cierre: Gratis no solicita pago; tres planes pagos conservan selección e importe; comprobante queda asociado al negocio/cuenta; volver a entrar conserva el estado; pago pendiente/aprobado no publica. No mostrar métricas vacías de un negocio recién creado.

### Área B — Administración: revisión, publicación y operación

**B1. Ficha central y editor.** Negocios locales con filtros y grid de negocio, lugar, estado, cuenta y suscripción; estado consultable, sin edición arbitraria. Cuenta abre detalle superpuesto con cantidad de negocios; acciones por icono accesible. Dentro de la ficha: requisitos, edición, paquete, previsualización e historial. Editor por Imágenes, Textos (idioma seleccionable), Canales y Datos adicionales Beta deshabilitados. Productos agregados a demanda. IDs internos estables no expuestos como campos que el operador deba inventar. Sin menús independientes redundantes para editorial/exportar/importar/catálogo.

Cierre: llegar desde el grid a cada operación y regresar conservando filtros; editar solo campos del plan; guardar una versión privada sin cambiar la pública; imágenes, textos y canales conservados; Beta no escribe datos.

**B2. Titularidad y pago.** Ver/descargar ambos soportes privadamente, separados del ZIP editorial. Verificaciones independientes con responsable/fecha; aclaración y rechazo con motivo visible para la cuenta. Unificar las dos vías documentales: conservar soporte hasta publicar, borrarlo después y conservar constancia; si falla el retiro, registrar/reintentar sin perder validación ni repetir publicación.

Cierre: bloquear prepublicación sin los requisitos aplicables; Gratis omite pago; un soporte de otro negocio/cuenta no se acepta; revisar pago no publica ni cambia contenido; aclarar/rechazar no borra historia. Verificar secuencia documental antes/después de publicar y recuperación ante fallo del retiro.

**B3. Paquetes e idiomas.** Exportar JSON de última versión, originales por posición y README con contrato/formato/reglas de imágenes. Excluir documentos de identidad, titularidad, comprobante, cuenta y datos privados de contacto. Importar textos ES/EN/PT-BR e imágenes preparadas con procedencia; validar límites/estructura/archivo/tamaño/idiomas antes de confirmar. Reimportación permitida en preparación/correcciones, con historial y aprobación ligada a la versión exacta.

Cierre: ida y vuelta completa desde captura real, con logo cuando exista, portada y 2/4/5 productos; confirmar textos/fotos por posición en la vista previa. ZIP incompleto, idioma faltante, producto inválido o fallo de escritura no deja contenido parcial ni altera la página pública. La generación/traducción la realiza la IA fuera del portal con el paquete; no se promete una API automática inexistente.

**B4. Prepublicación, correo y publicación del propietario.** Revisión administrativa de contenido, presentación, traducciones y requisitos; envío del enlace a la versión exacta. Enlace exige la cuenta responsable. Propietario revisa, solicita correcciones con comentario o aprueba y publica. Conservar la última versión pública durante correcciones. Historial visible de correo y resultado; reintento seguro según aceptación/fallo/incertidumbre. Aviso de publicación con URL pública.

Cierre: circuito completo sin usar al administrador como propietario; impedir acceso ajeno, enlace/versiones antiguos y publicación sin requisitos. Doble clic/reintento no duplica publicación ni avisos. Correo simulado en Dev2; entrega externa solo se certifica con prueba real autorizada.

**B5. Suscripciones y renovaciones.** Listado por negocio, filtros/orden por plan, vigencia y proximidad de vencimiento; distinguir vigente y solicitud pendiente. Renovación desde la cuenta: periodicidad, pago y comprobante; administración valida el pago y programa/aplica el periodo correspondiente sin nueva publicación editorial. Vencimiento conserva negocio/historial y aplica los beneficios efectivos. Upgrade conserva reglas de saldo/fecha existentes.

Cierre: renovación antes y después de vencer, pago rechazado/aclarado, doble aprobación, cambio de periodicidad y vencimiento; no perder periodo pagado, activar sin pago ni exigir republicar el mismo contenido. Consulta administrativa no renueva ni cobra.

**B6. Cuentas, solicitudes y comunicación.** Cuentas con negocios asociados, suspender/reactivar sin borrar y enviar recuperación al correo registrado. Bandeja única para interés de comercio, información, feedback y participación local. Plantillas: listado, edición, vista real y envío de prueba. CRM: selección de negocio(s), plantilla o mensaje individual, campañas agrupadas e historial de envío. Distinguir comunicaciones de servicio y campañas con sus permisos actuales.

Cierre: suspensión invalida acceso; recuperación funciona con expiración/uso único y destinatario correcto. Todas las fuentes llegan a la bandeja correspondiente sin duplicar registros. Elegir plantilla/destinatarios o ver preview no envía. No duplicar envíos por refrescar. No enviar correo real sin autorización.

### Área C — Arquitectura, errores y entrega

**C1. Línea base y pruebas coherentes.** Identificar fuentes/commit de trabajo y paquete servido por Dev2; inventariar cambios ajenos/generados sin descartarlos. Reconciliar pruebas con el contrato de esta página y asignar cada criterio a una comprobación. No llamar cerrado a un bloque por encontrar un nombre de función en un archivo.

Cierre: requisitos con caso/prueba/estado, paquete identificable y separación explícita entre pruebas de servicios, HTTP, interfaz y entrega de correo. Ningún bloque queda marcado verificado en Dev2 por una prueba SQLite aislada.

**C2. Fuente única y componentes compartidos.** Un productor por recurso público; lugares solo desde PHP y datos publicados. Retirar implementaciones sustituidas, generadores estáticos territoriales, recursos sin uso comprobado y correcciones de contenido por JavaScript. Reusar teléfono, selectores, pestañas, grillas, sesión y layout; una regla comercial común por plan para formulario/guardado/paquete/renderizado. Revisar consumidores antes de retirar y preservar archivos de usuario.

Cierre: construcción aislada sin modificar fuentes ni reintroducir páginas territoriales estáticas; comparación de rutas/recursos; cuatro modalidades coherentes en captura, exportación/importación y publicación. Limpieza focalizada sobre los módulos del circuito; no abrir una reescritura general del portal.

**C3. Errores y exportación para IA.** Registro técnico privado de fallos PHP/base/archivos con referencia, componente y ubicación; distinguir validaciones normales. Acceso visible desde Sistema y enlace contextual cuando falle una operación administrativa. ZIP con incidencias y guía de diagnóstico, sin secretos, documentos ni formularios. Resultado comprensible y recuperación sin perder datos.

Cierre: provocar fallos controlados de archivo, importación y base en un entorno descartable; localizar su referencia, exportar y comprobar exclusión de datos privados. Exportación vacía debe indicarlo; no interpretar falta de registros como ausencia de errores.

**C4. Paquete único y cierre en ambientes.** Reconciliar cambios ya preparados, construir paquete completo reproducible y desplegar Dev2 según autorización vigente. Configuración/BD/medios quedan por ambiente. Verificar mismo paquete sobre Apache/MariaDB, recursos, rutas y flujo completo. Promover a preprod y producción por el circuito aprobado del repositorio y autorización del candidato exacto.

Cierre: paquete y commit identificados; ningún secreto/dato de Dev2 en el código; migraciones pertinentes ensayadas e idempotentes; cuentas, medios y métricas conservados. Preprod/producción no se consideran autorizados por este plan. Revisión final móvil/escritorio mediante navegador integrado y aceptación del propietario.

### Orden operativo y puntos de revisión

1. C1 acotado: fijar línea base y criterios, sin refactorizar.
2. A1 → A2 → A3: cerrar cliente y verificarlo en Dev2 con los servicios administrativos actuales.
3. B1 → B2 → B3 → B4: cerrar ficha administrativa y circuito de publicación. Si A necesita una dependencia de B, implementar esa dependencia sin abrir otras reorganizaciones.
4. B5 → B6: cerrar renovaciones y operación diaria.
5. C2 → C3: resolver deuda transversal pendiente y diagnóstico; los cambios indispensables de sesión/componentes ya se hacen dentro del bloque que los necesita.
6. C4: comprobación conjunta y entrega. Las pruebas de bloques anteriores se ejecutan cuando sus dependencias cambian; no rehacer indiscriminadamente toda la revisión por cada cambio visual.

Estado al escribir este plan: plan fijado; C1 iniciado con historial y árbol de trabajo consultados. Hay implementaciones/pruebas parciales de A/B/C descritas arriba, pero ningún bloque completo se declara aceptado ni verificado integralmente en Dev2. Primer bloque funcional: A1. No pedir al propietario que repita los requisitos ya registrados.

### Avance A1 — registro del cliente, 24 de octubre

- Captura de negocio separada de cuenta: identidad generada desde PHP, sin campos repetidos de nombre/correo/teléfono personal/contraseña ni aclaración ordinaria. Cuenta sin correo verificado se dirige a verificación antes de capturar el negocio.
- Retirado el segundo controlador de visibilidad/pasos que existía en business-registration.js; búsqueda informa selección y business-onboarding-wizard.js gestiona navegación. Cada paso comprueba sus campos antes de avanzar; errores abren el paso pertinente. Gratis omite canales.
- Restauración de negocio existente/nuevo tras error; filtros de imágenes temporales según plan. Campos opcionales de dirección mantienen su condición; portada y productos configurados requieren su imagen. Contadores ya no quedan ocultos al abrir un paso.
- Corregido el regreso desde el acceso sobrepuesto: antes la cabecera cambiaba pero seguía visible «Antes de registrar un negocio»; ahora el ingreso continúa al wizard.
- registration-flow.integration.py probó las rutas reales de acceso, búsqueda y registro sobre paquete construido/base SQLite descartable: dos lugares publicados, búsqueda con disputa, cuatro planes guardados privados, archivo de portada e identidad autenticada frente a POST manipulado. Pruebas de autoalta/reclamación/disputa/idempotencia y DOM de búsqueda pasaron.
- Vista integrada: acceso desde pantalla previa a cuenta, selección de Medellín, búsqueda/creación, elección Premium, dos productos por pestañas con textos conservados, validación de presentación y cambio a Gratis/autorización sin volver a pedir identidad. Captura local: /tmp/explorando-registro-productos-a1.jpg. Esta evidencia visual usa datos ficticios en servidor aislado; no certifica todo Apache/MariaDB.
- Desplegado en Dev2, paquete .local/a1-registration-dev2-20261024; conservados los montajes de base y archivos. Registro responde 200 y el SHA-256 del wizard servido coincide con la construcción; administración sin sesión responde 303 al acceso. No se importó la base ficticia a Dev2. Sin preprod/producción.
- Estado: implementación y pruebas aisladas realizadas; revisión visual parcial realizada; despliegue y recursos de Dev2 comprobados. Aún falta el recorrido completo de aceptación de A1 en Dev2, móvil y 0/2/4/5 productos. A2/A3 (archivos, vista previa real con datos, recuperación y pago completo) siguen abiertos. No declarar cerrado todo el registro.

### Ajuste A1 — descripción única y paso Premium

- Retirada del formulario la segunda descripción ampliada. Esta primera revisión dejó 450 caracteres en todos los planes pagos; quedó sustituida por el ajuste acumulativo descrito abajo. Se conserva el esquema y el contenido editorial existente.
- Productos fuera de Información: fieldset independiente y paso exclusivo de Premium, antes de Canales. Navegación, numeración y validación omiten ese paso en otros planes. El contenedor deshabilitado impide enviar productos de Premium al cambiar de plan; no se reactivan las posiciones sin configurar.
- registration-flow.integration.py sobre paquete aislado: los cuatro planes guardan sin enviar description; HTML con un solo summary y productos como hermano de Información. Pasaron también autoalta, portal de cuenta, límites y DOM de reclamación.
- Navegador integrado en http://127.0.0.1:51882: Premium Información → Productos; Página Información → Canales sin Productos; regreso a Premium conserva nombre/descripción del producto y la descripción del negocio. Captura de datos ficticios: /tmp/explorando-productos-paso-premium.jpg.
- Dev2 actualizado con .local/a1-products-step-dev2-20261024; registro responde 200 y el SHA-256 del wizard servido coincide con el paquete (e7de918c609717449bcfd3233429dc1095d811606d2ad27a19692e6baf926d94). Mismos volúmenes de base y archivos, sin datos de prueba incorporados. La revisión visual fue aislada; no sustituye aceptación integral en Dev2. Preprod/producción intactos.

### Ajuste A1 — planes acumulativos

- BusinessRegistrationPlan es la fuente de reglas del registro para PHP y el wizard: descripción 0/300/450/1200; portada desde Página; productos solo Premium; canales acumulativos desde BusinessContactVisibility. NIT o RNT se valida en todos los planes en la ruta de registro; descripción, dirección y teléfono se requieren en los tres planes pagos.
- Los contadores actualizan su denominador al cambiar de plan sin duplicarse. Un texto más largo que el plan menor permanece íntegro y se marca inválido; no hay borrado o truncamiento automático. Los archivos ya subidos no se reactivan por escribir la descripción.
- registration-flow.integration.py sobre paquete construido verificó acceso, dos lugares, reclamación/disputa, los cuatro planes guardados privados, identificador obligatorio en todos, límites Unicode exactos, rechazo sin escrituras al excederlos, canales filtrados y descripción completa en el borrador editorial.
- La prueba encontró un bloqueo real al transferir 1200 caracteres a editorial. Se amplió únicamente la capacidad necesaria en los validadores de entrega/paquete/publicación, conservando datos y permisos históricos; no se reorganizó la administración ni se cambiaron condiciones de pago o publicación. editorial-roundtrip.integration.py verificó ZIP real/reimportación, 1200 caracteres distintos en ES/EN/PT-BR, cinco productos, correo simulado y publicación por propietario en una base descartable.
- Navegador integrado en http://127.0.0.1:54017: Gratis sin descripción/portada/canales; controles y contador 300/450/1200 en planes pagos; productos solo Premium; cambio Premium → Página conserva 800 caracteres y exige acortar; regreso a Premium conserva 800 y deja de ser inválido. Captura de datos ficticios: /tmp/explorando-registro-premium-1200.jpg. No afirmar que esto es aceptación completa de A2/A3 ni del circuito administrativo.
- Desplegado en Dev2 mediante .local/a1-cumulative-dev2-20261024, conservando volúmenes de base y archivos. SHA-256 del wizard servido y del paquete: 6a2675741a248e0014dae672fa9512bc96a91a8503e43ca0563f1799903473ba. Sin migraciones ni importación de datos de prueba; sin preprod/producción. Las reglas acumulativas quedan implementadas/probadas; la aceptación del propietario y los otros bloques del cliente siguen diferenciados.
- Aclaración del propietario: cerrar el proceso del cliente antes de revisar/rediseñar administración. El borrador editorial solo es entrega interna de la información, no un requisito adicional de edición, revisión o aprobación del cliente al registrar. No desviar decisiones del registro a una futura reorganización administrativa.
