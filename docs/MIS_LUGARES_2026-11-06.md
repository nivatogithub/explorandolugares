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
