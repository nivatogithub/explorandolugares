# Explorando Lugares — entrega estática para cPanel

## Contenido
- public/: archivos para publicar (HTML, CSS, JavaScript y SVG).
- docs/IDENTIDAD.md: propósito, propuesta de valor, colores, tipografías y recursos.
- explorando-lugares-cpanel.zip: contenido de public, listo para extraer en la raíz del dominio.

No requiere instalación, compilación, base de datos ni WordPress. No se ha desplegado en tu cuenta.

## Datos que debes completar
1. Edita public/config.js: correo real del negocio y/o WhatsApp internacional solo con dígitos. Los valores vacíos dejan el contacto en modo “próximamente”; el formulario solo prepara/copía un mensaje. Nunca pongas contraseñas ni claves aquí. Los datos de contacto serán públicos.
2. Define quién opera Explorando Lugares: nombre o razón social, canal para consultas sobre datos y aviso de tratamiento de mensajes recibidos. Completa public/privacidad.html antes de habilitar recepción; esta página explica únicamente el comportamiento técnico de la versión entregada.
3. Confirma el dominio explorandolugares.com. Si cambia, actualiza canonical, Open Graph, JSON-LD, robots.txt y sitemap.xml.
4. Revisa la propuesta comercial: tarifas, condiciones y establecimientos aún no están definidos. No hay aliados, testimonios, reservas ni resultados inventados.
5. La imagen social assets/social.png (1200×630) está lista para compartir; social.svg conserva el original editable.

Si editas public después de descargar el ZIP, vuelve a comprimir su contenido o sube los archivos modificados directamente: el ZIP entregado conserva los contactos vacíos.

## Publicar en cPanel
1. En GoDaddy abre tu alojamiento y cPanel. En Dominios crea explorandolugares.com como dominio adicional con carpeta raíz propia. Si aparece “Compartir raíz del documento”, desmárcalo. Confirma la ruta asignada; no uses la carpeta de otro sitio. No cambies el dominio principal.
2. En Administrador de archivos abre ESA carpeta. Si hay archivos, respáldalos antes. Sube explorando-lugares-cpanel.zip y extráelo allí. index.html y assets/ deben quedar directamente en esa raíz, no dentro de una carpeta public/ o explorando-lugares/.
3. Edita config.js con tus contactos reales; completa privacidad.html. Si ya existe index.php o una página de bienvenida en esa raíz, revisa cuál se sirve y respalda antes de retirarla.
4. Conecta el dominio a la IP del hosting mediante DNS, siguiendo los valores que muestra tu proveedor. Configura también www según tu panel. Conserva los registros de correo MX/TXT existentes. No se incluyen IP ni nameservers inventados.
5. Activa/verifica SSL para el dominio y www en tu hosting. Una vez emitido, activa la redirección HTTPS desde el panel si está disponible. La entrega no fuerza HTTPS antes de tener certificado.
6. Abre https://explorandolugares.com/ y prueba menú, imágenes y cada CTA en móvil y escritorio. Comprueba ausencia de contenido mezclado y que www resuelve a la versión elegida. Si www se usa como alias, configura una redirección al dominio sin www para coincidir con la URL canónica.
7. Prueba con un mensaje propio: preparar no envía; correo abre tu aplicación y WhatsApp abre la conversación, donde debes confirmar el envío. Revisa destinatarios antes del lanzamiento.

Guías oficiales consultadas:
- https://help-center-east.dc-aws.godaddy.com/help/create-addon-domains-or-subdomains-in-web-hosting-cpanel-8877
- https://help-center.dc-aws.godaddy.com/help/upload-files-using-my-web-hosting-cpanel-file-manager-3239
- https://www.godaddy.com/en-in/help/what-is-my-websites-root-directory-in-my-web-hosting-cpanel-account-16187

## Comportamiento y mantenimiento
El formulario no envía peticiones de red ni almacena datos. Prepara texto local y ofrece copia; con contactos configurados agrega mailto y WhatsApp. El usuario completa el envío fuera del sitio. Sin JavaScript, la información y los enlaces de navegación siguen visibles; el mensaje no se prepara. No se incluye backend ni captación automática.

No hay cookies propias ni analítica externa. El servidor puede mantener registros de acceso. Si incorporas analítica, reservas o backend, revisa textos de privacidad y funcionamiento.

Accesibilidad: idioma español, estructura semántica, etiquetas visibles, foco de teclado, enlace para saltar al contenido, movimiento reducido y respuestas anunciadas. SEO: metadatos, canonical, Open Graph, WebSite JSON-LD, robots y sitemap; no garantiza posicionamiento.

Para ampliar destinos: reemplaza o agrega contenido editorial y recursos reales con permisos, conservando la convocatoria abierta. Cada ficha real debe tener información confirmada y contacto autorizado.
