# Registro de negocios — 11 de octubre de 2026

El propietario indicó que le gusta la aproximación visual con descripción a la izquierda y escena ilustrada a la derecha. Se conserva como base visual; no implica despliegue. Nueva ampliación: visitantes navegan sin cuenta; negocios/marcas presentan una solicitud privada.

Implementado: selección de perfil, formulario de tipo de negocio, municipio, persona, correo y/o teléfono, marca, descripción, interés de participación/pauta/información, solicitud opcional de contacto y autorización obligatoria de tratamiento. Sin contratación, pagos, contacto automático ni publicación en catálogo.

Backend PHP con PDO; esquema MySQL para hosting. Panel protegido con inicio/cierre de sesión, listado paginado y filtro por estado; seguimiento nuevo/revisado/contactado/archivado. Configuración y credenciales fuera de la raíz web. Recepción deshabilitada por defecto y bloqueada sin datos del responsable, canal de privacidad, conservación, clave de aplicación y tabla disponible.

Verificación completada con PHP 8.5.11 y SQLite temporal: recepción deshabilitada, validación, consentimiento, CSRF, persistencia real, idempotencia, acceso privado, escape HTML, seguimiento, cierre, limitación de intentos y bloqueo al retirar datos de privacidad. Datos sintéticos y credenciales de prueba eliminados al terminar. PHP y JavaScript sin errores de sintaxis; enlaces de 20 páginas comprobados.

Pendientes: confirmar soporte PHP/PDO MySQL y base de datos del cPanel, configurar valores privados y responsable real, revisar política de conservación, comprobar el funcionamiento en MySQL/HTTPS del hosting y realizar revisión visual en navegador. No existe conexión con el hosting ni se ha enviado información real. La prueba con SQLite no valida el motor MySQL. No se han subido cambios a GitHub.

Preparación e instrucciones: `backend/README.md` y `python3 scripts/preparar-hosting.py`. Vista estática del formulario: `ensayos/v1/negocios-locales/index.html`; vista preliminar sin datos del panel: `ensayos/v1/admin/vista-previa.html`. El panel funcional es `backend/public/admin/index.php` y requiere el servidor PHP configurado. La vista estática mantiene el envío deshabilitado y no ejecuta PHP.

## Alcance simplificado vigente

El propietario pospone el registro de usuarios. Todos, incluidos los comercios, navegan públicamente sin cuenta ni elección de perfil. El único formulario inicial es “Quiero que me contacten”: nombre, negocio, municipio, categoría, al menos correo o teléfono, mensaje opcional y autorización para responder. Se retiraron marca, interés en pauta y selector de perfil. El servidor trata la solicitud como contacto; no crea cuenta ni publica fichas. El panel privado conserva su función de consulta y seguimiento. Conexión al hosting y datos de privacidad siguen pendientes.

## Catálogo JSON de opciones

Municipios y categorías se mantienen en `backend/app/contact-options.json`, sin tablas de base de datos para estas listas. `scripts/generar-opciones-contacto.py` publica una copia en `assets/contact-options.json` y genera un respaldo equivalente en el HTML. `assets/opciones-contacto.js` carga y rellena las listas en español o inglés, conservando cualquier selección previa. Si falla la descarga, se conservan las opciones de respaldo. El servidor valida contra el mismo catálogo JSON. Preparar hosting ejecuta esta generación automáticamente. Una futura base de datos podrá reemplazar la fuente de opciones conservando los valores de categorías y el comportamiento del formulario.

## Consentimiento y contacto — validaciones

El enlace a privacidad está junto al checkbox, con apertura en una pestaña nueva y `noopener noreferrer`. Checkbox obligatorio en HTML, comprobación explícita en JavaScript y validación del servidor. Se exige correo o teléfono; si se proporcionan ambos, ambos deben tener formato válido. El correo se valida mediante HTML `type=email` y PHP `FILTER_VALIDATE_EMAIL`; no se confirma existencia ni titularidad del buzón.

Teléfono colombiano: selector celular/fijo. Celular: 10 dígitos comenzando por 3 y prefijo +57. Fijo de Antioquia: 7 dígitos y prefijo +57 604. El prefijo se muestra como texto no editable y se calcula otra vez en el servidor, ignorando cualquier prefijo o teléfono completo enviado junto a los campos nuevos. Se almacena el número completo en el campo existente `phone`, sin migración. No se confirma titularidad, disponibilidad de WhatsApp ni existencia de la línea. El municipio seleccionado es el del negocio; dentro del alcance inicial de Antioquia todos usan el indicativo fijo 604, por lo que no varía al cambiar de municipio.

Fuente: [CRC, Circular 127 de 2020](https://normograma.crcom.gov.co/crc/compilacion/docs/circular_crc_0127_2020.htm) y [marcación única nacional](https://www.crcom.gov.co/es/noticias/comunicado-prensa/desde-1-septiembre-cambia-forma-hacer-llamadas-en-colombia), consultadas el 11 de octubre de 2026.

Pruebas: bloqueo sin consentimiento/contacto, formato de correo y teléfono, celulares/fijos sin correo, prefijo manipulado ignorado, persistencia normalizada, traducciones ES/EN y enlace junto al checkbox. No se utilizaron datos reales.

## Piloto: 21 municipios de Categoría 1

La clasificación interna del propietario se conserva completa en `backend/app/contact-options.json`: categorías 1/2/3/4 con 21/54/28/22 municipios. No se presenta como clasificación oficial ni como valoración pública. «La Ceja» se asocia con el nombre ya existente «La Ceja del Tambo», sin duplicarlo.

`enabledMunicipalityCategories: [1]` habilita solo la primera categoría. Al preparar hosting, el generador deriva `municipalities` desde esa configuración para la validación del servidor, el JSON público y el respaldo HTML ES/EN. La lista respeta el orden suministrado, empezando por Jericó. No se publican las categorías internas en el JSON del navegador. Un contacto con municipio fuera del piloto se rechaza; no se alteran registros históricos ni se habilitan fichas de destino automáticamente.

Para ampliar, modificar la selección de categorías habilitadas y preparar nuevamente la entrega. Un futuro filtro por subregión o buscador puede reutilizar este catálogo; no se han añadido controles extra en esta etapa. Actualiza este texto de alcance y su traducción al ampliar la cobertura.
