# Candidato aislado de presentación y rendimiento

21 de octubre de 2026. Base `f26842de`; rama `refactor/presentacion-rendimiento-20261021`.

**No aprobado para integrar ni desplegar.** El permiso para repetir Chromium sobre la versión final fue rechazado por el control del entorno, sin motivo adicional. No se sustituyó esa comprobación por otra herramienta. Las capturas de la etapa anterior no validan los cambios posteriores. Lighthouse quedó instalado localmente, pero no ejecutado.

## Implementado

- Componentes comunes PHP/JSON de encabezado, pie, menú, idiomas, contactos y recursos. Generación estática y PHP usan las mismas fuentes. Contexto por ruta/idioma; administración conserva su navegación privada y el cierre de sesión legado firmado. No se anuncian equivalentes de idioma inexistentes para administración/cobertura.
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
- Comparación intermedia: 52 vistas de referencia/candidato, móvil/escritorio. No valida la versión final; no usarla como aprobación. La revisión final de teclado, sesión, administración y diagramación queda pendiente.
- Se conserva exactamente el contenido de las 397 rutas raster de la base. Ensayo privado sin pérdida sobre 334 identidades únicas: cero candidatas útiles con el método ensayado. No se generó IA, no hubo compresión con pérdida ni se promovió un medio. No se afirma que se ensayaran todos los codificadores ni que se haya auditado fidelidad geográfica.
- Se detectaron referencias históricas de imágenes de experiencias que no constaban por hash en el manifiesto de medios seleccionados. Se conservan sus bytes existentes, no se autoaprueban reemplazos. La auditoría retroactiva es un pendiente explícito.

## Pendiente antes de promover

1. Permitir la repetición de Chromium y ejecutar la matriz final de móvil/escritorio/idiomas, cuenta activa/administración, formularios/filtros, imágenes al desplazarse y navegación sin JS. Revisar en particular disposición del panel administrativo y campos de cuentas.
2. Ejecutar Lighthouse comparable, varias veces, en móvil/escritorio; entregar bytes/bloqueos/LCP/CLS/SEO reales. No hay puntuación ni ahorro temporal final aprobado todavía.
3. Revisar cobertura completa de traducciones de los campos comerciales heredados y consolidación adicional de CSS ajeno al shell; no confundir el traslado de la traducción existente con una auditoría completa del CRM.
4. Comprobar DOM/extensiones y encabezados/caché en el entorno Apache/PHP equivalente autorizado antes de publicar. El método usa DOMDocument; no se verificó producción.
5. Reconciliar la rama con main/preprod vigentes por función. El trabajo de reparación de registro/editorial de negocios está en otra rama y puede solaparse en plantillas: conservar sus versiones aprobadas, no resolver con ours/theirs ni sustituir el checkout de ese worker.
6. Revisar el candidato completo y su commit exacto. Este documento y las pruebas locales no autorizan publicación.

## Evidencia privada

En `.local/unificacion/`: inventario de base, `comparison.json` y capturas intermedias; `image-trials-fast/index.json`; manifiestos/retiradas/productores de `delivery-exact/`; `final-cli-result.json`; `server-render-checks.json`; validación de sintaxis Apache. Son fixtures, no datos ni cifras de producción.
