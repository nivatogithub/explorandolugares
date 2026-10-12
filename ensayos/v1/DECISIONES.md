# Propuesta visual v1 — 11 de octubre de 2026

Estado: candidata a aprobación visual. No publicada; no sustituye los históricos.

Alcance adicional solicitado: reseñas y puntuaciones por ficha, rankings de destinos/actividades y publicación de eventos. Véase `docs/CATALOGO_RESENAS_Y_EVENTOS.md` desde la raíz del repositorio. Son requisitos futuros documentados, no funciones disponibles; mantener estados vacíos hasta contar con datos y herramientas reales.

## Revisión tras comentarios del propietario

Se retira el amarillo de grandes superficies y botones, sustituido por azul grisáceo claro. Se recupera sin alterar el símbolo existente en `public/assets/logo.svg` (montañas, sol y camino); su pequeño acento dorado original se conserva. Se mantiene fondo, familias tipográficas y footer. Encabezado más amplio, marca mayor, navegación general Inicio / Destinos / Agenda / Tu negocio aquí y adaptación en varias filas para móvil.

Estructura implementada: `destinos/index.html`, `destinos/jerico/index.html`, diez páginas de categoría bajo Jericó (incluida agenda), `agenda/index.html`, `aliados/index.html` y `privacidad/index.html`. Rutas relativas compatibles con una carpeta estática. Jericó ya no es una entrada fija del encabezado; nuevos destinos podrán añadirse dentro de `destinos/`. Los accesos antiguos redirigen a la estructura nueva. No hay fichas reales; se crearán dentro del destino y su categoría cuando exista contenido confirmado. La arquitectura es una base escalable, no una afirmación de catálogo o portal completo terminado.

Verificación de revisión: enlaces, anclas y recursos de las 19 páginas HTML válidos; símbolo idéntico al original; paquete actualizado. La verificación visual en navegador sigue pendiente.

Dirección independiente: composición editorial clara, azul profundo y amarillo cálido. Interfaz con fuentes de sistema y títulos con acentos Palatino/serif. Sin fuentes externas ni estilos, contactos o recursos de Paradigma. La flecha circular y el tratamiento tipográfico son candidatos, no logo definitivo. Las fuentes pueden variar según el dispositivo.

Inicio de marca sin paisaje de Jericó como identidad permanente. Diez áreas de la economía local, con agenda separada de contenido permanente. Categorías en preparación; sin fichas ficticias ni catálogo confirmado.

Páginas: inicio, Jericó, aliados y privacidad. Participación permite preparar y copiar texto local; no hay envío, almacenamiento, contactos, pagos ni reservas. Canales y datos del operador pendientes.

Fotografía: Xemenendura, El parque Jericó, Wikimedia Commons, CC BY-SA 4.0. Recurso comprimido conservado desde el borrador y recortado con CSS. Fuente: https://commons.wikimedia.org/wiki/File:El_parque_Jeric%C3%B3.jpg ; licencia: https://creativecommons.org/licenses/by-sa/4.0/ . No representa alianzas con negocios visibles.

Verificación: cuatro páginas con un encabezado principal cada una; enlaces internos, anclas y recursos resuelven; JPEG válido; página inicial responde HTTP 200; diff sin errores. Responsive, foco visible, enlace de salto y movimiento reducido incorporados. Verificación visual y portapapeles pendientes: la herramienta de navegador bloqueó acceso local. No se afirma que esas pruebas hayan pasado.

Paquete de revisión: `.local/explorando-lugares-v1-candidata.zip`, con páginas en su raíz; conserva franja y noindex. Antes de publicar: aprobación visual, revisión móvil/escritorio, confirmar operador y privacidad, decidir lanzamiento informativo sin recepción, retirar señalización y noindex en copia de publicación, verificar dominio/SSL/cPanel. La ausencia de catálogo debe mantenerse explícita.

## Revisión de color e imágenes

El propietario pidió más contraste y altura en el encabezado, color conservador con calidez y fotografías que expresen la oferta del portal. Se sustituye la figura tipográfica con arco por un mosaico conceptual de seis escenas: pueblo, alojamiento, restaurante, bar, naturaleza y artesanía. Se mantienen logo, fuentes, fondo y footer. Encabezado salvia con franja secundaria diferenciada; botones verde apagado y acentos arcilla/arena.

Imagen `assets/vida-local-concepto-v1.png`: generada con la herramienta integrada de imágenes, explícitamente conceptual, no documentación de comercios reales. Prompt: fotografía editorial natural, mosaico 3×2 de pueblo andino colombiano, patio de alojamiento, comida local, bar cálido, sendero de montaña y artesanías; verde salvia, arcilla suave, marfil y madera; sin textos, marcas o establecimientos identificables. Se muestra una aclaración visible bajo la imagen. Reemplazar o complementar con fotografías autorizadas de destinos y negocios reales cuando se incorporen al catálogo.

## Nueva pasada: aventura y vida local

Se conserva marca, familias tipográficas, fondo, footer y arquitectura. La portada abre con “Sal de la rutina. Encuentra tu lugar.”; aventura y paisaje tienen una imagen principal con cascada, parapente, rafting y trekking. Una segunda sección presenta descanso, gastronomía, bares, comercio y cultura mediante las escenas conceptuales previas y enlaces de categorías. Sin carruseles ni destinos ficticios. Los enlaces llevan a categorías aún en preparación y no implican disponibilidad de esas actividades en Jericó.

Activo añadido: `assets/naturaleza-aventura-concepto-v2.png`, generado con la herramienta integrada de imágenes. Prompt: composición fotográfica editorial 3:2, panorama luminoso de valle andino con cascada en los dos tercios superiores; tres paneles inferiores con parapente tándem, rafting con equipo de protección y caminantes; colores naturales, sin operadores o lugares reales identificables. Etiqueta conceptual visible.

Verificación: enlaces, anclas y recursos locales de 19 páginas válidos; un h1 en portada; imagen PNG válida. Verificación visual completa en navegador y aprobación del propietario siguen pendientes.

## Portada compacta en tarjetas

Se reemplazan los dos grandes bloques de imagen por diez tarjetas con escenas encuadradas mediante CSS a partir de los activos conceptuales existentes: pueblos, alojamiento, restaurantes, cascadas, parapente, rafting, trekking, bares, comercio y naturaleza. Cada tarjeta tiene nombre y enlace propio. No se generaron diez fotografías nuevas; se reutilizan las escenas de los dos mosaicos. Cinco columnas y dos filas en escritorio, tres columnas en móvil. Se reduce tamaño del título, altura de fotos y espaciado; se retira el segundo mosaico grande y los bloques editoriales se organizan en tres columnas. Créditos y aviso conceptual conservados.

Verificación: enlaces/anclas de 19 páginas, imágenes CSS y diez tarjetas correctos; paquete actualizado. Revisión visual en navegador sigue pendiente.

## Apertura ilustrada y disponibilidad por destino

Petición vigente: descripción a la izquierda y una escena única a la derecha, sin menú sobre la imagen. Se incorpora `assets/mundo-explorando-ilustracion-v1.png`, generado con la herramienta integrada. Prompt: ilustración editorial contemporánea, paisaje andino ficticio continuo con hotel, restaurante, bar, comercio, personas, montañas, cascada, parapente, rafting y caminantes; cálida y sobria, sin paneles, textos o marcas. Se retiran tarjetas de la portada y categorías vacías de Jericó.

El catálogo local usa `assets/catalogo.json`, actualmente vacío. Cada publicación requiere destino, nombre, categoría, registroConfirmado=true, informacionVerificada=true y estado=publicado. Las categorías se forman solo a partir de publicaciones que cumplen todo. No hay sistema de registro, backend ni revisión automática: el archivo debe mantenerse editorialmente con datos reales autorizados. Las páginas de categorías en preparación siguen existiendo como base interna, pero no se ofrecen en el menú de Jericó.

Pruebas: catálogo vacío y registros pendientes no muestran categorías; solo publicación confirmada y verificada aparece, filtrada por destino. Datos de prueba en memoria, sin guardar fichas ficticias. Enlaces y recursos de 19 páginas válidos. Verificación visual de navegador pendiente.

## Contacto comercial, sin cuentas

Flujo vigente: navegación pública para todos. “Conecta tu negocio” permite solicitar contacto mediante un formulario breve, sin perfiles, usuarios, marca ni contratación de pauta. Incorporación manual tras conversación. Panel privado para consultar y dar seguimiento. Los detalles del hosting siguen pendientes y el envío continúa deshabilitado en la vista estática.

## Pueblo / Ciudad y versión bilingüe

Se conserva la ilustración de pueblo y se suma `assets/mundo-explorando-ciudad-v1.png`, generada con la herramienta integrada. Prompt: ilustración editorial de una ciudad colombiana contemporánea ficticia, hotel, restaurantes, bar, comercios, parque, ciclistas, visitantes y montañas; escena continua cálida, sin marcas ni texto. El selector cambia solamente la ilustración; no filtra ni anuncia oferta publicada. Logo, tipografía y footer conservados.

Páginas públicas disponibles en español e inglés, con ES/EN y enlaces a la página equivalente. Inglés estático bajo `en/`, assets compartidos, metadatos e idioma HTML correctos, enlaces alternativos hreflang. Generación reproducible: `python3 scripts/generar-ingles.py`, diccionario `content/en.json`; editar fuentes españolas y regenerar. Mensajes de contacto: `content/messages.en.json`, copias de ejecución en assets y backend. Administración funcional privada continúa en español; no hay cuentas de visitantes ni de negocios.

Antes de activar el contacto en inglés, configurar `privacy.retention_en` además del plazo en español para presentar la información de privacidad en el idioma elegido. Negocios y eventos futuros necesitarán contenido propio en ambos idiomas, no una traducción automática de datos confirmados.

Pruebas: 40 páginas con enlaces, anclas y assets válidos; selección de pueblo/ciudad y clics rápidos probados en ambos idiomas con entorno DOM simulado. Revisión visual de la composición en navegador sigue pendiente. No se ha desplegado ni añadido un destino de ciudad ficticio.

## Contacto centrado en Antioquia

Se reemplaza municipio libre por los 125 municipios de Antioquia en orden alfabético y la categoría por 29 opciones agrupadas. Fuente territorial: [Gobernación de Antioquia](https://www.antioquia.gov.co/images/pdf/anuario2014/es-CO/capitulos/generalidades/aspectos/divisiones/cp-1-1-3-2.html), consultada el 11 de octubre de 2026. No representa cobertura ni negocios publicados en esos municipios.

La lista compartida del servidor está en `backend/app/contact-options.json`; las opciones estáticas ES/EN coinciden con ella. El servidor rechaza municipios o categorías ajenos a la lista. Se conserva el nombre del municipio en los contactos; no se modifica el esquema ni registros históricos.

«Nombre del negocio o marca» es texto libre, sin directorio ni pregunta separada sobre marcas. La descripción se concreta como «¿Qué ofrece tu negocio? (Opcional)». No se pregunta interés comercial: el propósito explícito es recibir contacto. Las categorías son una clasificación de trabajo ampliable, no una enumeración universal definitiva.

## Ciudad moderna — imagen v2

A petición del propietario, Ciudad utiliza ahora una escena metropolitana al anochecer con torres de oficinas, hoteles, autopistas, restaurante gourmet, terrazas y vida nocturna. Se conserva Pueblo y el selector. Recurso y brief: `docs/IMAGEN_CIUDAD_V2.md`. Actualizado también en inglés.

## Hilo conductor y tres niveles

Se aplica la orientación al turista para conectar las partes de su viaje, sin limitarlo a una duración. Portada → destino → participante; categorías dentro del destino, sin nivel obligatorio intermedio. Primera landing de destino: Jericó. Plantilla y generación de fichas preparadas, sin participantes ficticios. Se retiran rótulos de revisión y anotaciones de imágenes del contenido público; se conservan créditos de licencia. Ver `docs/ARQUITECTURA_DEL_VIAJE.md`.

## Propósito explícito en portada

Se restituye el texto completo acordado en el bloque principal: descubrir destinos, reunir experiencias y servicios, y diseñar un viaje a medida. Debajo de la llamada a explorar se explica la finalidad de reunir información para planear con confianza. La siguiente sección continúa el propósito con «Las partes de tu viaje. Un mismo lugar para empezar». No se promete un planificador automático ni cobertura completa ya disponible.

## Opciones de contacto disponibles sin servidor

Corregido el bloqueo del fieldset que impedía abrir municipio y categoría en la vista estática. Los campos se pueden completar desde el inicio; solo el botón de envío espera confirmación de disponibilidad y tokens del servidor. La selección no guarda ni envía datos. Tras un envío exitoso, el formulario permanece bloqueado para impedir reenvíos.

## Consentimiento y teléfonos

Privacidad enlazada desde el checkbox obligatorio y en una pestaña nueva. Contacto exige correo o teléfono válido. Selector fijo/celular colombiano; indicativos +57 o +57 604 no editables y recalculados por el servidor. Validaciones y mensajes ES/EN comprobados; detalles en `docs/REGISTRO_NEGOCIOS.md`.

## Alcance territorial del piloto

El formulario limita municipios a los 21 de Categoría 1 suministrados por el propietario. Clasificación completa de los 125 conservada internamente; selector, JSON público y servidor coinciden. Categorías de negocio y prefijos telefónicos se conservan. Ampliación futura mediante configuración, con filtro/buscador pendiente para cuando aumente el listado.

## Eventos dentro del destino

Se retira Agenda del menú global y se usa Eventos dentro de cada municipio. Jericó enlaza a su propia sección `#eventos`; rutas anteriores redirigen. Posibilidades y límites de fuentes públicas, enlaces, Google Calendar e importación futura documentados en `docs/EVENTOS_POR_DESTINO.md`. No se han activado iframes sin una fuente municipal adecuada y soporte confirmado.

## Tipos generales de servicio

Se reduce el formulario de 29 opciones a 11 clasificaciones globales. Se usa la misma clasificación para contactos, agrupaciones del destino y ficha de participante. Las variantes van en la descripción y los servicios, sin multiplicar formularios ni diseños. Plantilla común con «Conoce su propuesta». Alcance y diferencias por tipo en `docs/CLASIFICACION_SERVICIOS.md`; datos compartidos en `content/service-categories.json`.

## Invitación clara a negocios de Jericó

Se reemplaza el cierre ambiguo «Hagamos nuevas conexiones» por una invitación explícita a participar en el portal. Pregunta directa sobre el negocio, ejemplos locales sin metáforas como «una mesa», beneficio de presentar la propuesta a visitantes y botón «Quiero participar» hacia el formulario. Composición en dos bloques, apilados en móvil, sin prometer incorporación automática.

## Eventos sin asumir la etapa del visitante

El bloque usa «Eventos en Jericó» y describe ferias, festivales y actividades culturales. Se elimina «También hay razones para volver», que suponía una visita previa o una partida. El mensaje sirve para quien planea viajar, quien acaba de llegar y quien ya está en el municipio.

## Directorio de Jericó

Se cambia el bloque «Encuentra tu lugar dentro del destino» por directorio con pestañas por tipo de servicio y tarjetas con foto, descripción y enlace a la ficha. No se muestran categorías sin publicaciones. Catálogo vacío conservado; se prueba el filtrado con datos sintéticos únicamente en memoria. Sin cambios a las fichas históricas ni publicación de negocios ficticios.

## Planear y explorar en una sola sección

Se retiran los cuatro bloques largos de preparación y se integran sus necesidades como agrupaciones del directorio. Búsqueda por palabras, filtro por tipo, orden alfabético, preparación para calificación y paginación de seis tarjetas. La reputación permanece deshabilitada hasta contar con reseñas publicadas reales. Mapa conservado junto al directorio; navegación hacia `#lugares` actualizada. Arquitectura y contrato futuro de reseñas documentados en `docs/ARQUITECTURA_DEL_VIAJE.md`.

## Alcance informativo de primera etapa

Se fija como prioridad explicar cada destino y captar interesados comerciales. Jericó incorpora historia, motivos para visitarlo y cuatro lugares verificados para conocer, con enlaces a mapas. Datos prácticos y eventos se compactan en desplegables. Se elimina el filtro público de calificación; reseñas, reservas, pagos y agendamiento quedan fuera de esta entrega. Invitación comercial también cerca del inicio. Alcance vigente: `docs/ALCANCE_PRIMERA_ETAPA.md`.
