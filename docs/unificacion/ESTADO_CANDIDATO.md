# Candidato aislado de presentación y rendimiento

21 de octubre de 2026. Base `f26842de`; rama `refactor/presentacion-rendimiento-20261021`.

**No aprobado para integrar ni desplegar.** El propietario renovó el permiso de navegador después del rechazo inicial. Se ejecutó un recorrido visible de17rutas del candidato fijo y una pasada móvil. Esa revisión encontró y documentó el solape del sidebar administrativo, el recorte del nombre autenticado y una ruta de recuperación ausente en el router local (sí existía en Apache). Se preparó un segundo candidato con esas reparaciones; sus capturas anteriores se conservan. La matriz completa final y Lighthouse siguen siendo puertas separadas; no se sustituyen por renderizados CLI.

## Implementado

- Componentes públicos comunes PHP/JSON de encabezado, pie, menú, idiomas, contactos y recursos. Generación estática y PHP usan las mismas fuentes. Decisión posterior del propietario: administración usa layout privado independiente, sin header/footer públicos, con retorno al portal y cierre de sesión firmado. Se comparten marca/tokens. No se inventan equivalencias de idioma de cobertura: accesos alternativos llevan la etiqueta visible de inicio del portal.
- Cuentas ya no extraen encabezado/pie/estilos del HTML de portada. Traducciones de autenticación y contacto se resuelven antes de entregar HTML; menú/registro/modal mantienen sus interacciones.
- El menú no construye navegación ni carga otros recursos. Se retiraron sus dos fuentes históricas y se conserva un controlador canónico accesible, con Escape/foco y alternativa sin JavaScript.
- Los correctores de ciudades/Jericó se trasladaron a datos y composición del servidor. No se descargan imágenes anteriores para reemplazarlas después con JS. La autoridad de publicación sigue siendo la salida del repositorio: los JSON históricos no pueden agregar tarjetas ausentes. Se conservan identidad, enlaces de operadores y créditos vigentes.
- Tarjetas e iconos del directorio se entregan en HTML. Su JS solo gestiona el filtro. Las relaciones lugar/experiencia también llegan en HTML; JS solo revela/focaliza la tarjeta al navegar.
- Portada y Privacidad reciben los fragmentos públicos desde PHP/datos. La selección de atracciones comparte el servicio con el endpoint existente. No se personaliza HTML público con datos de sesión.
- CSS del shell con fuente única y cero `!important`; reglas históricas de ese componente se excluyen de otras hojas. Orden explícito de recursos, manifiesto de contenido y dependencias versionadas. Los estilos de administración siguen siendo privados.
- Construcción en instantánea efímera sin mutar el checkout, salida nueva/vacía, selección explícita de productor por ruta y manifiestos. Preprod/preview se adaptan a construcciones nuevas sin perder datos persistentes ni conservar copias de código acumuladas. No se ejecutaron esos controles sobre el ambiente compartido.
- Alternates absolutos y mapa de equivalencias compartido con el selector; rutas canónicas `/lugares/`. Los nuevos index PHP redirigen el acceso directo a la ruta limpia. Helpers compatibles con app privada separada del directorio público del hosting.
- AGENTS es un índice corto; todas las obligaciones anteriores permanecen en reglas canónicas y registro íntegro de trazabilidad.

## Comprobado y límites

- Pruebas PHP del componente y del paquete: una composición de cuenta, CSRF/noindex conservados, idioma inicial y recursos versionados. Prueba de varios registros de Santa Marta e identidad/publicación preservada.
- Matriz por CLI sobre fixture nueva: 51 renderizados iniciales (13 lugares × tres idiomas, portada, privacidad, contacto y acceso). Comprueba un header/footer/menú, recursos presentes y alternates absolutos. **Es renderizado del servidor, no recorrido funcional de navegador ni prueba de contratación.**
- Pasan pruebas de importación/consulta/publicación de destinos, versiones de medición, ruta de suscripciones, dominio de publicación, empaquetador SFTP y cobertura del release; pruebas nuevas de aislamiento, sustitución local, separación CSS y trazabilidad de reglas. La prueba de cobertura compara cartografía/datos por bytes y controladores JavaScript ignorando únicamente su versión de URL. Detectó y permitió corregir el podado de recursos solicitados dinámicamente; ahora se conservan desde su productor seleccionado, sin recopiados finales.
- Dos construcciones nuevas del código final producen manifiestos idénticos (951 archivos antes de la fixture privada). El paquete es un candidato reproducible, no un despliegue.
- Apache 2.4.67 acepta la sintaxis de las reglas de caché en configuración aislada, sin abrir otro servidor. No prueba el Apache de GoDaddy ni los encabezados HTTP finales de producción.
- Comparación intermedia:52vistas de referencia/candidato, móvil/escritorio. Después, recorrido visible17rutas y pasada de formularios móvil sobre candidato fijo. Se conservan capturas de antes/después. Estas revisiones no equivalen a aprobación completa de todos los módulos.
- Se conserva exactamente el contenido de las 397 rutas raster de la base. Ensayo privado sin pérdida sobre 334 identidades únicas: cero candidatas útiles con el método ensayado. No se generó IA, no hubo compresión con pérdida ni se promovió un medio. No se afirma que se ensayaran todos los codificadores ni que se haya auditado fidelidad geográfica.
- Se detectaron referencias históricas de imágenes de experiencias que no constaban por hash en el manifiesto de medios seleccionados. Se conservan sus bytes existentes, no se autoaprueban reemplazos. La auditoría retroactiva es un pendiente explícito.

## Pendiente antes de promover

1. Completar la matriz final de móvil/escritorio/idiomas, cuenta activa/administración, formularios/filtros, imágenes al desplazarse y navegación sin JS. El recorrido visible inicial fue útil para encontrar regresiones; la pasada específica de reparación no equivale a revisar todas las páginas.
2. Ejecutar Lighthouse comparable, varias veces, en móvil/escritorio; entregar bytes/bloqueos/LCP/CLS/SEO reales. No hay puntuación ni ahorro temporal final aprobado todavía.
3. Revisar cobertura completa de traducciones de los campos comerciales heredados y consolidación adicional de CSS ajeno al shell; no confundir el traslado de la traducción existente con una auditoría completa del CRM.
4. Comprobar DOM/extensiones y encabezados/caché en el entorno Apache/PHP equivalente autorizado antes de publicar. El método usa DOMDocument; no se verificó producción.
5. Reconciliar la rama con main/preprod vigentes por función. El trabajo de reparación de registro/editorial de negocios está en otra rama y puede solaparse en plantillas: conservar sus versiones aprobadas, no resolver con ours/theirs ni sustituir el checkout de ese worker.
6. Revisar el candidato completo y su commit exacto. Este documento y las pruebas locales no autorizan publicación.

## Evidencia privada

En `.local/unificacion/`: inventario de base, `comparison.json` y capturas intermedias; `image-trials-fast/index.json`; manifiestos/retiradas/productores de `delivery-exact/`; `final-cli-result.json`; `server-render-checks.json`; validación de sintaxis Apache. Son fixtures, no datos ni cifras de producción.

## Reparaciones encontradas durante el recorrido

- Sidebar administrativo colocado por debajo de la fila del shell (escritorio/tableta), manteniendo todos sus enlaces y desplazamiento propio. Los controles administrativos ya no estilizan los botones del encabezado público.
- Nombre de cuenta con ajuste de línea, sin recorte silencioso ni ocultar enlaces de cuenta/administración.
- El router de revisión reproduce también `/negocios/recuperar-clave/`. El404 anterior era del entorno de revisión; no se aceptó como revisión satisfactoria. En la fixture el envío está desactivado: la pantalla lo comunica; no se ha probado correo real ni se pretende.
- Nuevo paquete privado `delivery-reviewed/`; paquete y capturas originales se mantienen para comparar. La primera reparación de escritorio no resolvía la superposición del nombre móvil: se corrigieron las columnas y el ajuste de línea de la celda de cuenta. Pasan12vistas específicas a1440,390y320px con nombre normal/largo y dropdown sin superposición con el logo, sin desborde de documento ni menú fuera de pantalla. La comprobación adicional de tableta queda registrada aparte.

## Cierre local posterior: enlaces, administración y arrastre

- Commit de correcciones iniciales `bd169a4d`; paquete final posterior `delivery-review-final`. No integrado ni desplegado.
- Se identificaron24enlaces de ubicación perdidos(8tarjetas de SantaMarta×3idiomas). El editor ya no reconstruye tarjetas: conserva sus acciones/atributos/identidad y actualiza contenido en el nodo existente. Comparación inicial de39páginas:0acciones no multimedia perdidas frente al HTML inicial de la base. Esto NO compara el DOM después de los correctoresJS de la base.
- Se muestran enlaces de ubicación/referencia ya existentes para experiencias y sitios; URL vacía de una traducción puede reutilizar la URL registrada en español. Sin inventar coordenadas ni enlaces. Pruebas de retención de href/target/rel/texto/aria/ids/data/botones/formularios en3idiomas, y fallback en SQLite aislado.
- Administración independiente: menú propio, identidad, «Volver al portal», «Mi cuenta» cuando corresponde y cierre de sesión CSRF por los mismos handlers existentes. No carga el runtime público. Prueba local de layout/sesión personal y legado; permisos HTTP/visual nuevos NO medidos.
- `draggable=false` en HTML de imágenes de lugares/experiencias y portada. No se cambian archivos de imagen ni interacción Leaflet. La base ya desactivaba apertura de archivos/lightbox desde identidad-neutral.js; no se inventa una galería nueva. Drag/scroll/clic reales quedan pendientes de navegador.
- Cobertura no tenía traducciones equivalentesEN/PT: sus botones anteriores iban a las portadas. Ahora el mapa central distingue página equivalente de inicio del portal, rotuladoEN·Home/PT-BR·Início, sin canonical/hreflang falsos. Pruebas PHP pasan; aspecto visual nuevo pendiente.
- Guion de inventario estructural preparado para189rutas×3roles×base/candidato, con snapshots inicial/DOM, formularios/acciones/imágenes y comprobación local de enlaces. La solicitud técnica de Chromium fue RECHAZADA; NO arrancó.0páginas DOM medidas por ese guion,0Lighthouse. No se reintentó ni eludió con otra herramienta. Los enlaces externos NO se consultaron.
- Inventario estático:235HTMLcompilados analizados como texto/DOMPHP, no como navegador ni HTTP. Contiene elementos por página; no acredita ejecución deJS ni permisos.
- Dos construcciones finales con manifiestos idénticos;397rutas raster conservan bytes exactos. Balance de archivos públicos en `.local/unificacion/estructura/final-balance.json`; no equivale a transferencia de red ni tiempo de carga.

Pendientes antes de integrar: permiso y ejecución del inventarioDOM completo, comprobaciones internasHTTP/roles/activos, revisión visual del nuevo admin y selector, arrastre/clic/scroll reales, Lighthouse comparable y entorno Apache equivalente. Ninguna captura de la pasada anterior aprueba este candidato nuevo.
