# Reparación de solicitudes y registro de negocios

Rama: `reparacion/registro-negocios`. Implementación del plan del 20 de octubre de 2026, conservando la propuesta de navegación pública en su rama independiente.

## Línea base y límites de esta entrega

Base: `bc5fc07fa0ed58bd642632cb6795df672746d9b8`. Se volvió a consultar `origin/main` y el manifiesto remoto: ambos declaran `bc5fc07`. Esto identifica la base, pero no sustituye la comparación completa de archivos requerida antes de desplegar.

El caso privado de Casa Hotel Ruta no se consultó ni reparó; su causa continúa **no comprobada**. El desarrollo y sus pruebas usan datos ficticios. No se cambiaron cuentas, negocios, estadísticas, configuración ni código persistente de producción; tampoco la base persistente de preprod. La exportación empresarial solicitada por separado solo leyó canales y retiró su lector temporal protegido.

## Funciones implementadas

### E1. Estados y recursos

- Selectores cortos nativos, teclado y botón **Guardar estado**; otros catálogos conservan búsqueda.
- Resultado real: guardado, sin cambios o solicitud inexistente. CSRF y sesión obligatorios.
- Búsqueda/filtro/página conservados; la página se acota cuando deja de existir y se explica si una fila sale del filtro.
- CSS/JS administrativo versionado por contenido del paquete. Menú administrativo contraíble en móvil y solicitudes con disposición vertical en pantallas estrechas.
- Guardar estado no envía correos.

### E2. Revisión privada y relación con fichas

`solicitud-detalle.php` distingue consulta y solicitud de cuenta por tipo e ID. Presenta **Enviado por la persona**, **Ficha actual** y **Validaciones**. Usa los datos conservados; no reconstruye el pasado desde la ficha actual. Categorías, plan, texto, canales, documentos e imágenes se muestran mediante sus accesos privados.

Vínculos informativos explícitos, muchos-a-muchos, confirmados por ID y con actor/fecha. No crean permisos, no cambian origen, no certifican titularidad ni publican. No hay backfill por nombres o correo. Se avisa de una solicitud aprobada sin asociación. Los retornos de las nuevas colas se restringen a rutas administrativas permitidas.

### E3. Colas y filtros

- **Solicitudes de participación:** consultas sin exigir ficha ni cuenta.
- **Atención a comercios:** ficha con solicitud recibida, vínculo confirmado o comprobante de pago. Una membresía aislada, canal o plan no demuestra interés recibido.
- **Catálogo y cobertura:** todas las fichas; cuenta, suscripción, origen, publicación, categoría y lugar independientes.
- **Revisión y publicación:** borradores de todos los orígenes y solicitudes sin ficha, contadas separadamente. Los bloqueos usan el mismo validador de la publicación real.

Listado, conteo, paginación y selección filtrada comparten predicado, incluidas categorías secundarias. El lote conserva su restricción: **Publicar importaciones Gratis sin representante**. La sesión conserva las versiones revisadas durante 30 minutos; una confirmación vencida, un ID ajeno al conjunto o una ficha modificada no puede publicarse silenciosamente. La versión se comprueba dentro de la transacción, tras bloquear el negocio.

### E4. Primera asociación declarada

La ruta pública fija en el servidor la política `declared-document-v1`. Las solicitudes históricas conservan su recorrido compatible; no se reclasifican ni asignan retroactivamente.

Para Gratis se pide nombre, categoría, un documento del negocio y declaración de responsabilidad; la cuenta verifica su correo. No exige dirección, documento de identidad adicional, aprobación ordinaria ni creación manual de la suscripción. Una cuenta activa antigua sin correo verificado puede completar esa verificación sin perder su cuenta.

- Nuevo negocio: crea una ficha, asociación y Gratis `pending_publication`, automáticamente al completar requisitos.
- Existente sin responsable: conserva el mismo ID y sus datos, y asocia la cuenta. Solo crea Gratis si no había suscripción; no reemplaza otro plan.
- Misma cuenta: repetición idempotente. Otra cuenta responsable: disputa manual, manteniendo su acceso vigente.
- Cuenta sin verificar: conserva solicitud y documento, pero no reserva ni asigna anticipadamente una ficha.
- Coincidencia detectada al activar: cuenta utilizable y estado **Elegir una ficha existente**, sin convertirlo en aprobación administrativa ordinaria.
- Una elección paga queda conservada y ofrece retomar el pago desde la cuenta. Asociar no cobra ni activa beneficios pagos.

**Decisión del propietario:** crear y asociar; **publicar después**. Las fichas nuevas siguen en borrador. La asociación queda declarada y con `ownership_verified=false`; pagar tampoco certifica representación.

#### Documentos y duplicados

PDF, JPG, PNG o WebP, hasta 8 MB. Se valida carga HTTP auténtica, MIME y firma PDF/imagen; nombres aleatorios, archivos privados con permisos restringidos y rutas autenticadas. No se afirma que el contenido o la titularidad quedaron certificados. La conservación se rige por la política de tratamiento configurada para solicitudes y documentos; esta entrega no introduce purgas automáticas ni una duración diferente.

Duplicados: todas las categorías del mismo lugar; nombre normalizado sin acentos/puntuación, igualdad o similitud del 91% para nombres de al menos nueve caracteres, respetando números distintos. Se comprueba antes de crear y durante la transacción. MariaDB serializa altas por lugar y usa lecturas actuales tras el bloqueo; la prueba simultánea detectó y permitió corregir una lectura antigua.

Para un homónimo legítimo se permite declaración explícita de establecimiento distinto y dirección diferenciadora de 8–300 caracteres, conservadas con la solicitud. No permite reutilizar una dirección ya registrada entre las coincidencias. Es una declaración, no geolocalización verificada. No se fusionan fichas automáticamente.

Las imágenes de una solicitud original no se retiran físicamente al eliminar una ficha Gratis si siguen sirviendo de evidencia histórica de aquella solicitud.

### E5. Respuesta individual

`responder-solicitud.php` permite preparar, revisar y confirmar una respuesta individual desde la consulta; hay acceso al historial desde la lista. No usa campañas ni incorpora contactos a listas. Exige una solicitud con contacto autorizado.

- Plantilla HTML/texto, logo, tarifas obtenidas del catálogo y enlaces vigentes; mensaje y asunto editables, con escape.
- Borrador persistido con destinatario, remitente, respuesta, asunto, contenido y versión. La confirmación valida exactamente su hash y la configuración del remitente vigente.
- Vista previa privada y aislada; no llama al transporte. El estilo inline del correo solo se permite en ese visor restringido, no en el panel completo.
- Reclamo atómico de una operación estable: un intento aunque haya doble clic, repetición o dos procesos simultáneos.
- `draft`, `sending`, `accepted`, `failed`, `uncertain`. Aceptado por Brevo no equivale a entregado.
- Timeout o respuesta no concluyente: incierto, sin reintento automático. Tampoco permite preparar otra operación para eludir esa incertidumbre.
- Reconciliación manual con resultado contrastado en Brevo, referencia, explicación, actor y fecha; conserva el intento original. Un `sending` no se reconcilia antes de cinco minutos.
- Una respuesta adicional intencional exige nuevo borrador y advertencia del historial. Enviar no marca Contactado ni modifica marketing, representación, publicación o suscripción.
- Comparte `BrevoTransactionalClient` con el correo transaccional de cuentas y usa la conexión Brevo vigente. El entorno `local_test` de esta pantalla siempre simula; además, los servidores de regresión deshabilitan transporte externo.

## Migraciones

| Identificador | Cambio | Datos existentes |
|---|---|---|
| `047-lead-business-links` | Relación consulta/ficha, pareja única, claves foráneas, índice, actor y fecha | Sin backfill |
| `048-individual-lead-replies` | Operaciones de respuesta e intento único, confirmación y reconciliación | Sin envíos ni cambios de estado históricos |

Variantes MySQL/MariaDB y SQLite registradas en mantenimiento. Aplicar antes de habilitar las nuevas pantallas. Las respuestas conservan sus destinatarios/contenido privadamente bajo la política aplicable a la solicitud; no contienen API keys. Su FK impide eliminar el historial accidentalmente con una consulta. Ante necesidad de supresión, aplicar el procedimiento de privacidad; no restaurar una base antigua ni borrar el historial de forma improvisada.

## E6. Pruebas y revisión

Comando reproducible:

```sh
python3 scripts/probar-reparacion-negocios.py --mariadb
```

Ejecuta 20 grupos de servicios, la regresión HTTP y el ensayo de MariaDB. Crea bases aleatorias con prefijo de prueba y las elimina al terminar. No carga credenciales ni datos de producción. El reporte privado queda en `.local/reparacion-admin/resultado.json`, con commit, estado del árbol y resultados.

Cobertura: autorización, CSRF, 404, escape, instantáneas, vínculos idempotentes sin permisos/publicación, filtros secundarios, Gratis mínimo con carga HTTP real de documento ficticio, correo verificado, repetición, disputas, homónimos declarados, límites de contenido, suscripciones, prorrateo, CRM, campañas y mantenimiento. Correo simulado: vista previa, confirmación, cambio de versión/remitente, doble POST, timeout y reconciliación.

En MariaDB 10.11.19 se ensayan las migraciones, dos procesos creando el mismo negocio y dos procesos confirmando la misma respuesta, además de dump/restauración de datos ficticios en otra base. No se restaura sobre la base origen ni sobre preprod.

Se construye el paquete completo mediante `scripts/preparar-hosting.py`, incluyendo versionado final de recursos. La revisión visual se realiza en el navegador integrado; hay capturas de escritorio y revisión de HTML renderizado en un marco de 390 px, además de selección nativa con teclado. No confundir esa revisión de disposición con una ejecución funcional en Safari/Firefox/Chrome independientes: esos navegadores no están conectados en este entorno. Esa compatibilidad queda como comprobación manual del candidato, no como prueba pasada.

## Promoción pendiente de autorización

No se hizo merge a la rama compartida `preprod`, a `main` ni despliegue. El entorno compartido tiene trabajo de otros workers y no debe sustituirse. Antes de publicar: integrar el candidato revisado en preprod, ensayar su paquete, aprobar el commit exacto, respaldo privado productivo, migraciones aditivas antes de consumidores, publicación incremental, validación y paridad completa. Solo después promover ese mismo commit a main. No reiniciar ni sustituir estadísticas.

La consulta privada del incidente, el envío real de respuesta y la publicación productiva requieren sus autorizaciones específicas. El plan original permanece sin editar.

## Circuito editorial por suscripción · 21 de octubre de 2026

Implementación en esta rama, sin promover a preprod compartido/main ni producción. Migración aditiva **049-business-editorial** (MySQL/MariaDB y SQLite): versiones privadas, eventos de transición y referencias visuales privadas. Las fichas, pagos, vigencias, cuentas, canales de CRM y estadísticas existentes no se migran ni se sustituyen.

- `business/editorial.php` es la entrada autenticada desde Mis negocios para Gratis, Destacado, Página Comercial y Premium. Usa límites y canales del catálogo real; las referencias originales permanecen privadas. No copia el correo personal del representante a los contactos públicos.
- Las vistas previas reutilizan `views/directory-card.php` y `negocio.php`, no maquetas paralelas. Página/Premium conservan vistas privadas ES/EN/PT y enlaces de idioma privados. Se exige sesión y relación activa con el negocio o acceso administrativo; no hay enlace público de borrador ni token de acceso libre. Las páginas no se indexan.
- Cada edición crea una versión nueva con padre, hash del contenido y huella de la ficha de origen. No reemplaza datos publicados. Un guardado concurrente sobre una versión anterior se rechaza. Cambiar contenido invalida la aprobación anterior. Los adjuntos retirados de una nueva versión siguen conservados en el historial privado.
- Estados: `draft` → `in_editorial` → `awaiting_owner` → `owner_approved` → `published`. El representante puede pedir `changes_requested`; administración puede rechazar con motivo (`rejected`) sin retirar la ficha pública anterior.
- La preparación exige los mismos límites de texto, traducciones, portada/fotos y contactos que la publicación; se ensaya dentro de un savepoint que siempre se revierte. **La generación de imágenes y traducción no invoca automáticamente un proveedor de IA**: el equipo prepara ese material y lo incorpora a esta revisión. No se ha creado una promesa de generación automática ni contratado una API. El formulario informa y recoge la autorización para preparar/adaptar imágenes a partir de referencias.
- Administración tiene `admin/editorial-negocios.php` y enlace desde Negocios. Prepara la versión final, abre las vistas previas y notifica al representante con el Brevo transaccional existente. El correo contiene botón a la revisión privada; se debe iniciar sesión y no concede permisos nuevos. En entorno de prueba se simula siempre. Un intento registrado bloquea reenvíos automáticos, incluso con resultado incierto; aceptación por el proveedor no equivale a entrega.
- La publicación final aplica la versión exacta aprobada en una transacción; vuelve a comprobar asociación, cambios concurrentes, suscripción y reglas de publicación. Un primer negocio pago sigue exigiendo comprobante revisado. Actualizar una ficha ya publicada no renueva ni recalcula su vigencia ni importe. El botón de publicación anterior no puede saltar una versión editorial en curso.
- El acceso anterior `premium_profile` redirige al circuito privado: ya no guarda cambios públicos desde el formulario del representante. Se conserva la capacidad de URL personalizada dentro de la versión aprobada y el historial de alias al publicarla.

Pruebas reproducibles: `php backend/tests/business-editorial.php` y `python3 scripts/probar-reparacion-negocios.py --mariadb`. La segunda utiliza bases aleatorias desechables y un servidor HTTP aislado. No se usa la base productiva ni se envían correos reales. Se añadieron comprobaciones de páginas Premium completas en los tres idiomas, vista de tarjeta real, CSRF, asociación, aprobación exacta, rechazo, cambios simultáneos, publicaciones sin pago bloqueadas y conservación íntegra de las condiciones de una suscripción paga al editar.

La coordinación con `docs/ENTREGA_ADMIN_A_PORTAL_2026-10-21.md` del worker administrativo se hace por contratos, sin integrar su rama: separar cuenta verificada/asociación/documentación/pago/publicación, conservar Gratis y procedencia, no asignar propietarios manualmente, no mezclar CRM con permisos públicos y mantener la conciliación excepcional de disputas. Sus pantallas administrativas y recordatorios de renovación no se duplican ni se consideran una automatización ya implementada aquí.

Limitaciones explícitas de esta entrega: el material editorial se prepara mediante el proceso humano/herramientas existente, no mediante una API de generación/traducción conectada al portal; los correos productivos de prepublicación aún necesitan una prueba real autorizada de entrega. Los navegadores externos y equipos móviles físicos no se dan por validados por una captura de escritorio del navegador integrado. No se integra automáticamente el candidato del worker administrativo.

### Presentación y capacidad Premium (21 de octubre)

- Portadas con proporción 4:3 y esquinas redondeadas, sin máscaras ovaladas. Premium presenta portada y galería mediante carrusel manual, navegable con botones y desplazamiento; Página Comercial conserva portada única.
- Canales públicos según la escalera vigente de suscripciones, con SVG fijos, nombre accesible y tooltip; nunca se exponen canales privados o canales no incluidos.
- Premium admite tres productos o servicios opcionales, con nombre (100 caracteres), descripción (400), traducciones y una imagen final revisada por producto. Las referencias se cargan en el circuito privado; la preparación elige medios con procedencia ya registrada. Hasta siete referencias privadas Premium, con límites de tamaño existentes.
- Los productos se conservan en las versiones editoriales inmutables (049). El público lee exclusivamente el último snapshot publicado. Guardar, preparar o aprobar no modifica su contenido público. No hace falta otra tabla ni migración para estos datos.
- Un operador Premium puede referenciar experiencias publicadas del mismo lugar en su página. Se reutilizan título, texto e imagen del catálogo; una experiencia retirada deja de renderizar. Esto no agrega al negocio como operador anunciado en el lugar: ese espacio conserva los requisitos del complemento pagado.
- Menú: enlace HTTP(S) de menú/catálogo ya persistido, presentado para consultar o descargar. La descarga efectiva depende del recurso externo (PDF u otro formato); no se implementó almacenamiento de archivos PDF nuevos.
- Se mantiene la preparación editorial de imágenes y traducciones; no se afirma generación automática conectada por API.

### Idioma y responsabilidad editorial

En el circuito actual de Colombia, el comerciante entrega y aprueba su contenido en español. Sus formularios solo aceptan español y sus previsualizaciones privadas no ofrecen otros idiomas; cambiar `lang` manualmente tampoco da acceso a las traducciones de esa prepublicación. El equipo editorial conserva las tres vistas para revisar.

Antes de enviar una prepublicación, el portal exige revisión explícita de traducciones, fidelidad/permisos/estilo/calidad de imágenes y presentación/enlaces/móvil. Se registra el administrador y la versión exacta revisada. Publicar exige esa revisión además de la aprobación del comerciante y de los controles de contenido/pago; una nueva versión requiere otra revisión. Los controles automáticos de integridad y completitud no sustituyen la revisión humana de calidad semántica y visual. No hay API de traducción o generación de imágenes conectada automáticamente.

### Ajuste posterior: portada fija y cinco productos

La decisión vigente reemplaza el carrusel principal: Página Comercial y Premium muestran una portada fija, rectangular y redondeada. Premium permite hasta cinco productos o servicios con imagen (no tres). Se retira el JavaScript y el CSS del carrusel; el límite se verifica en el servidor y el editor presenta cinco posiciones. Las referencias privadas Premium admiten hasta nueve imágenes para cubrir portada, material del negocio y productos, conservando los límites de tamaño por archivo y por guardado.

### Página comercial compacta

Cabecera con categoría principal y modalidad en la misma fila, retorno alineado a la derecha sobre la portada. Una sola presentación del negocio, usando la descripción ampliada (el resumen se conserva para el directorio), sin repetir el nombre en una sección «Acerca de». La ubicación se presenta junto a los canales, con iconos de color propio sobre fondo neutro. Horarios, rango de precios, capacidad y menú aparecen de forma compacta cuando existen; no se repite una sección de ubicación.

La historia y el texto narrativo general de servicios dejan de ser obligatorios para Premium. Una historia suministrada sigue requiriendo traducciones y se presenta contraída. Secciones de servicios y productos con foto permanecen exclusivas de Premium. Se actualizó la prueba que anteriormente exigía historia obligatoria, conservando la comprobación de traducciones de contenido opcional.

### Tarjeta Destacada simplificada

Título con fondo de panel y acento de marca, nombre truncado a una línea para mantener tarjetas uniformes, resumen inmediatamente debajo. Sin etiqueta «Información pública» ni desplegable «Más información». Ubicación, teléfono y WhatsApp comparten la fila inferior de contacto con controles visibles. La modalidad Gratis conserva la exclusión de ubicación y contactos. El mismo componente se usa en el directorio y la previsualización privada.

### Contactos y datos prácticos: decisión vigente

Instagram pasa a ser exclusivo de Premium, tanto en filtros del servidor como en formularios, tarjetas, páginas y tabla comercial (ES/EN/PT). Página Comercial muestra teléfono, WhatsApp, correo y ubicación. Se retiran rango de precios y capacidad del editor público y de la presentación de las páginas; los datos históricos no se borran ni se modifican. Horarios y enlace de menú permanecen disponibles.

### Premium: servicios por iconos y cuatro destacados

Decisión vigente: no se muestra «Nuestra historia» ni se solicitan narrativas de historia o secciones de servicios en el editor. Los servicios se representan mediante los iconos y etiquetas del catálogo de categorías, exclusivamente en Premium y únicamente cuando tiene más de una categoría distinta. No se crean categorías adicionales ni se infiere que un negocio ofrece algo que no seleccionó.

Premium permite elegir hasta cuatro productos o servicios estrella con foto; es un máximo, no una cantidad obligatoria. Se reemplaza el límite anterior de cinco, con control en guardado, preparación/publicación y presentación. Los textos históricos de historia/servicios se conservan sin exponerse ni bloquear una publicación por su traducción. Cada texto realmente público continúa sujeto a revisión y traducción. La tabla de suscripciones incorpora la nueva capacidad en los tres idiomas.

### Revisión adaptable de las tres modalidades (21 de octubre)

Revisión local de Destacado, Página Comercial y Premium en anchos efectivos de 360, 390 y 1440 px. Se comprobaron desbordamientos del contenido, carga de imágenes, tamaño de botones, distribución de productos y apertura/cierre del menú móvil. Se corrigió el teléfono descentrado y se compactó la cabecera móvil ocultando el prefijo redundante «Página del negocio» para mantener categoría y modalidad en una fila.

El control de viewport del navegador no aplicaba el tamaño solicitado a las pestañas existentes. Se descartaron esas capturas y se utilizó un contenedor local de ancho fijo con la salida del mismo controlador autenticado; se midió su ancho real. No se desactivó la protección de marcos ni la autenticación. Evidencias operativas privadas en `.local/reparacion-admin/responsive/`, con mediciones y seis capturas completas. Alcance: simulación en navegador, no pruebas en dispositivos físicos ni en otros motores.


### Cierre del recorrido comercial y fotografías por posición · 21 octubre 2026

- La selección de suscripción y el simulador ofrecen «Ver ejemplo» en otra pestaña. `/business/demo.php` usa las plantillas reales con contenido fijo de demostración, sin insertar negocios ni reproducir borradores. No indexa ni carga la medición de visitas; sus canales pertenecen a Explorando Lugares. Premium muestra cuatro productos; Comercial no los muestra.
- Cada portada/producto tiene una fotografía original asignable o cargable. Las asociaciones se conservan en `payload_json.source_images` mediante nombres privados estables y se verifican contra las referencias de la versión. No se necesita otra migración además de 049. Se conservan los índices de producto al retirar posiciones para no desplazar sus fotografías.
- El primer guardado recupera las referencias privadas entregadas al registrar el negocio. Reemplazar una fotografía retira la anterior solo de la nueva versión; conserva archivos y versiones históricas privadas. Las originales no se convierten en medios públicos.
- El responsable puede ver su borrador con originales antes o después de enviar el soporte de pago. No se le exige aprobar este borrador: la aprobación es sobre la prepublicación final preparada. El pago se revisa por separado y no publica.
- Administración prepara los reemplazos con `.agents/skills/imagenes-destinos/SKILL.md` y las guías de producción de imágenes del sitio: identificar negocio/producto y posición, comprobar el permiso de la referencia, preservar identidad/material real sin inventar servicios, generar/adaptar en estilo editorial, inspeccionar, registrar procedencia y seleccionar el activo final por posición. La generación se realiza mediante herramientas humanas, no hay API automática conectada. Las traducciones siguen a cargo del portal.
- Al preparar la versión final se intenta notificar automáticamente al correo verificado del responsable. El botón lleva a la revisión privada autenticada, donde aprueba o solicita correcciones; no se interpretan respuestas de correo automáticamente. Fallar el aviso no deshace la preparación; el resultado queda visible y se evita duplicar intentos. Aceptación del proveedor no equivale a entrega.
- La vista final usa activos editoriales, no las fotografías originales privadas. Se retiraron controles de galería y campos históricos (historia, precio/capacidad y reservas) del registro que ya no corresponden al diseño. El generador dejó de inyectar la antigua oferta de secciones narrativas Premium. No se borraron datos históricos.
- Regresión ampliada: archivos reales por HTTP con igual nombre en posiciones diferentes, consentimiento, autorización de lectura, sustitución sin acumulación en la nueva versión, conservación del historial, fotos heredadas del registro, ejemplos de cuatro modalidades, pago Premium y revisión, preparación con activos finales, aviso simulado, corrección, aprobación y publicación exactas. Todo en bases desechables, sin producción ni correo real.
- Pendiente externo antes de declarar entrega de correo comprobada: una prueba real de la notificación de prepublicación. La generación/selección de imágenes finales requiere operación editorial humana con material real.
