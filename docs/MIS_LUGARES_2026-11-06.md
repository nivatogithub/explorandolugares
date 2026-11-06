# Mis lugares por conocer

Rama de revisión: `proposal/home-travelers-businesses`. Instalado únicamente en dev1.

## Comportamiento vigente

- El marcador de cada guía permite guardar o quitar ese destino desde la misma página. Una bandera rellena y `aria-pressed=true` indican que está guardado. PHP renderiza el estado inicial. La interacción se mejora con `saved-place.js`: envía el POST, espera el estado confirmado por el servidor y actualiza únicamente ese marcador; guardar y quitar no navegan ni modifican el scroll. Sin JavaScript permanece el formulario tradicional.
- Si no existe sesión activa, el marcador lleva al acceso e informa que guardar lugares es una función para usuarios registrados. El clic conserva una intención temporal en la sesión. La escritura se ejecuta después del POST de acceso protegido por CSRF y vuelve a la guía con el marcador relleno. La intención no escribe datos desde GET y expira a la hora.
- «Lugares» tiene el filtro privado «Mis lugares por conocer», usando las tarjetas, búsqueda y paginación del catálogo. Solo muestra datos de la cuenta autenticada. Los parámetros enviados por un navegador no determinan la identidad.
- El perfil no incluye un listado, bloque, enlace ni confirmación de lugares guardados.
- Un mismo destino no se duplica. Las guías despublicadas se ocultan de los guardados. Esta entrega guarda destinos completos, no atracciones individuales ni experiencias.
- La navegación pública continúa libre. No se añadieron cookies analíticas, preferencias de idioma ni seguimiento.

## Persistencia y arquitectura

Migración aditiva obligatoria antes de promover: `063-account-saved-places`, para MySQL o SQLite según corresponda. IDs estables, clave única cuenta/lugar, sin modificar catálogo, imágenes, estadísticas, cuentas existentes, negocios o membresías. Cerrar cuenta conserva los datos asociados de manera privada.

Controladores HTTP, servicio, selector, escritor, contexto de presentación y vista separados. Los cambios de estado usan POST y CSRF. Los redireccionamientos del marcador se construyen con slug validado y un idioma permitido; no aceptan URL externas. Las respuestas personalizadas de las guías no se almacenan en caché compartida y varían por Cookie. Las guías anónimas no abren una sesión nueva por mostrar el marcador.

## Validación

- `php backend/tests/saved-places.php`: migración dos veces, aislamiento, deduplicación, borradores y eliminación.
- `php backend/tests/saved-place-directory.php`: aislamiento, identidad enviada ignorada, búsqueda combinada, tres idiomas, paginación, rechazo anónimo y borradores ocultos.
- `php backend/tests/saved-place-actions.php`: SVG relleno/vacío, POST/CSRF, intención sin escritura, consumo autenticado una sola vez, caducidad y traducciones.
- `php backend/tests/place-directory.php`: 96 comprobaciones. `place-directory-100.php`: paginación y búsqueda de 100 lugares, tres idiomas.
- `php backend/tests/presentation.php`: plantilla nativa, header/footer y navegación por lugares.
- `node backend/tests/saved-place-controls.dom.js`: guardar/quitar, fallo sin cambio visual falso, sesión caducada, bloqueo de envíos duplicados y conflicto entre el campo `action` y la propiedad del formulario. El endpoint se obtiene mediante `getAttribute('action')`; nunca desde la propiedad susceptible de ser ocultada por un campo.
- Constructor público completo sin reparaciones HTML posteriores.
- Comprobación visual real con cuenta aislada: guardar y quitar mantuvieron `scrollY=266.5`, la URL y el filtro Hospedaje seleccionado. No hubo navegación ni pérdida de estado; la cuenta automática terminó sin guardados e inactiva.
- Los manejadores existentes de pestañas y de carga de atractivos se extrajeron sin alterar su comportamiento a `place-interactions.js`, recurso externo versionado, para conservar la ejecución bajo la política CSP de las páginas con sesión. No se relajó esa política.
- HTTP real dev1, cuenta sintética separada: invitación desde marcador anónimo, acceso que guarda y vuelve a la guía, quitar/guardar directamente, persistencia tras recargar, perfil sin bloque y rechazo de token CSRF inválido. Al terminar, las cuentas automáticas quedaron inactivas. Las selecciones del propietario permanecieron intactas.
- Comprobación visual por Cloudflare de Agustín Codazzi ya guardado: marcador relleno y `aria-pressed=true`, sin modificar su selección.

## Túnel de revisión

El error HTTPS estaba en el tramo local Cloudflare → dev1. Se resolvió únicamente en Apache del contenedor dev1, confiando en la cabecera HTTPS para el hostname temporal explícito. La protección HTTPS de producción no se modificó.

Si cambia el enlace: `python3 dev/enable-review-tunnel.py --host HOST.trycloudflare.com --container explorando-dev1`. El comando se limita a contenedores dev y exige configuración local.

## Imagen pendiente independiente

Monumento Las Guitarras, Agustín Codazzi: `assets/editorial-38/QC-40-2.webp` contiene bandas blancas laterales dentro del archivo. No se modificó ni regeneró la imagen aprobada; necesita revisión de encuadre independiente.

Producción no se ha modificado. Requiere aprobación visual y el circuito habitual de integración, migración y despliegue.

## Explicación de registro

Tanto el acceso desde un marcador como el filtro privado muestran el título «Guarda tus lugares por conocer» y explican, antes de los campos, que se necesita una cuenta para conservar la lista personal y recuperarla al volver a iniciar sesión o desde otro dispositivo. El mismo contexto se conserva al abrir Crear cuenta. Las pantallas de acceso ajenas a esta función no cambian. Texto renderizado en servidor en ES/EN/PT-BR. Verificado visualmente sin sesión desde `/lugares/?guardados=1` y al pasar a Crear cuenta.

## Contexto de acceso explícito

El aviso se muestra únicamente si la navegación de acceso o registro lleva `beneficio=lugares`, añadido al llegar desde el marcador o el filtro privado. No depende del rol de viajero/comercio, de si la cuenta existe ni de intenciones viejas en la sesión. Los enlaces entre acceso y registro conservan ese contexto dentro de ese recorrido; abrir acceso o registro normal lo cancela, evitando guardar por sorpresa una selección anterior. El resto de retornos de negocios se conserva. La verificación de correo mantiene la continuación pertinente sin convertir el aviso en global.

Comprobación visual en la misma sesión: marcador → aviso; crear cuenta desde allí → aviso; iniciar sesión normal → cero avisos; filtro de guardados → aviso; registro normal → cero avisos. No se crearon cuentas ni se modificaron selecciones para esta revisión.

## Autenticación sobre la página de origen

El marcador anónimo y el filtro privado abren el diálogo de acceso reutilizado sobre la página actual, sin navegar al inicio ni usarlo como fondo. El marcador tiene un único formulario PHP tanto para visitas anónimas como autenticadas. Después de entrar, consulta el estado confirmado y el CSRF del servidor y actualiza ese mismo control, sin sustituir la guía. El acceso normal del encabezado también conserva la página y actualiza la disponibilidad del marcador mediante el evento compartido de autenticación. El contexto explícito del aviso se conserva dentro del diálogo, incluido el cambio a Crear cuenta.

Cancelar devuelve el foco sin desplazar la página; en una entrada independiente heredada utiliza el retorno local validado o la navegación atrás cuando hay una página pública de origen. El catálogo conserva su búsqueda y filtros; cancelar no deja seleccionado un filtro privado que no pudo aplicarse. Las búsquedas posteriores continúan renderizadas por el servidor y recuperan la posición de navegación.

Validación visual aislada: Barichara → marcador sin sesión → diálogo con guía intacta, URL de Barichara y ningún fondo de inicio; cerrar mantuvo `scrollY=266.5`; acceso y guardado mantuvieron exactamente esa URL y posición; acceso normal también quedó sobre la guía con el marcador habilitado. Catálogo `?q=Barichara` → filtro privado → diálogo sobre catálogo → acceso → mismo catálogo con `q=Barichara&guardados=1`. La cuenta automática terminó inactiva y sin guardados; no se tocaron las selecciones del propietario.
