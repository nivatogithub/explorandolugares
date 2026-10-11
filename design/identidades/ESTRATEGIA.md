# Explorando Lugares — cinco direcciones de identidad

## Decisión que se busca
Elegir una identidad propia para el portal, sin asociar su marca a un municipio ni trasladar la apariencia de Booking u otras plataformas. Brújula, Pasaporte, Encuentro, Atlas abierto y Bitácora son nombres de conceptos de diseño, no nombres alternativos del negocio: la marca sigue siendo Explorando Lugares.

Las cinco propuestas se muestran simultáneamente en index.html, numeradas, con portada y una muestra de la página interna de Jericó. No hay carrusel. El logo actual se conserva en monocromo como candidato, no como decisión definitiva.

## Propósito y propuesta comercial
Conectar viajeros con destinos y con negocios locales que hacen posible visitarlos: alojamientos, restaurantes, comercios, experiencias y transporte. La portada pertenece a la marca. Cada destino tiene su propia guía; los negocios pertenecen a un destino y a una categoría.

La primera etapa capta aliados y organiza información confirmada. El beneficio propuesto para el aliado es una presencia contextual dentro del destino y contacto directo; no se garantizan visitas, reservas o ventas. Las modalidades de ingresos (fichas pagas, destacados, servicios de contenido o comisiones futuras) son decisiones pendientes, no ofertas publicadas. No se inventan descuentos, reseñas, disponibilidad ni inventario.

## Qué aprendemos de las referencias, sin copiar su apariencia
La revisión previa está documentada en ../exploracion/REFERENCIAS.md.

- Plataformas de viajes: ayudan a identificar la intención y a pasar de una categoría a una opción concreta; hacen visibles las acciones para viajeros y para alojamientos. Se adopta claridad de recorrido y jerarquía de CTA. No se trasladan buscadores de reservas, precios ni la identidad cromática de Booking.
- Directorios territoriales: reúnen información local, atractivos y negocios en una misma página de municipio. Se adopta la relación destino → categoría → ficha.
- Portales de promoción turística: dan contexto e inspiración mediante contenido del lugar. Se reserva la fotografía documental del pueblo para su página interna, evitando que Jericó se convierta en la imagen de marca del portal.

## Arquitectura compartida
1. Inicio: identidad de Explorando Lugares, propósito, entrada a destinos y convocatoria de aliados.
2. Destinos: listado de lugares disponibles; inicialmente Jericó, sin otros destinos ficticiamente activos.
3. /destinos/jerico/: presentación del pueblo, información para visitarlo y categorías.
4. /destinos/jerico/hoteles/ (y comercios, restaurantes, experiencias, transporte): listado contextual.
5. Ficha del negocio: nombre, descripción, fotos autorizadas, ubicación, servicios, horarios y contacto confirmado.
6. Aliados: presentación comercial, categorías de participación, condiciones acordadas y contacto configurable.

Los prototipos muestran el inicio y el nivel destino/categoría. Las fichas reales se construirán cuando se elija la dirección y existan aliados confirmados. La navegación de categorías del boceto muestra estados vacíos honestos; no simula catálogo activo.

## Las cinco direcciones

### 01 · Brújula
Idea: elegir un camino propio a partir de la curiosidad.
Personalidad: cálida, abierta, exploradora.
Promesa verbal: “Sal de lo de siempre”.
Sistema visual: petróleo #143738, coral #CF432E, blanco cálido #FFFDF9. Georgia editorial + Arial funcional. Formas abiertas y dirección oblicua.
Conversión: descubrir un destino primero; invitación al aliado “Comparte tu lugar”.
Imágenes futuras: momentos de descubrimiento y encuentros, sin un pueblo fijo como imagen de marca.

### 02 · Pasaporte
Idea: convertir una intención de viaje en un recorrido comprensible.
Personalidad: práctica, curiosa, en movimiento.
Promesa verbal: “Tu próxima parada empieza con una idea”.
Sistema visual: tinta #1D2538, naranja #ED6636, papel #F5F5EF. Arial de titulares + monospace en señales. Billetes, líneas perforadas y etapas.
Conversión: destino → necesidades del viaje → contacto del negocio.
Imágenes futuras: objetos y momentos de viaje, composiciones tipo cuaderno de ruta; no fotografías de una localidad como marca.

### 03 · Encuentro
Idea: conocer el destino a través de sus anfitriones.
Personalidad: hospitalaria, humana y cercana.
Promesa verbal: “Un lugar. Alguien que te espera”.
Sistema visual: ciruela #512E4F, durazno #F4DDD2, frambuesa #893768. Georgia de títulos + Arial. Arcos y superficies suaves.
Conversión: propuestas locales y experiencias con personas; captación “Quiero ser anfitrión”.
Imágenes futuras: personas reales, hospitalidad y oficios, con permisos y sin aparentar alianzas inexistentes.

### 04 · Atlas abierto
Idea: ampliar la mirada y descubrir posibilidades.
Personalidad: contemporánea, activa y expresiva.
Promesa verbal: “Hay más por ahí”.
Sistema visual: berenjena #282448, lima #C7EE4B, lavanda #EDEAFF. Arial contundente + monospace. Marcadores, ritmo tipográfico y contraste.
Conversión: intereses → destino → propuestas locales. “Quiero estar en el mapa” es una invitación de visibilidad, no una función cartográfica activa.
Imágenes futuras: composición dinámica y pluralidad de lugares, sin prometer destinos disponibles que aún no existan.

### 05 · Bitácora
Idea: una guía con criterio y contexto para elegir.
Personalidad: observadora, editorial y pausada.
Promesa verbal: “Lugares que merecen una buena historia”.
Sistema visual: carbón #17191D, turquesa #00D5CB, blanco #FFFFFF. Georgia + monospace. Retícula editorial, reglas finas y notas.
Conversión: historia/guía → destino → negocio; invitación “Cuéntanos tu historia”.
Imágenes futuras: reportajes y detalles reales del lugar, asociados a cada guía y no a la marca general.

## Recursos y límites
Fuentes locales para esta comparación; una tipografía con licencia propia puede incorporarse al desarrollar la dirección elegida. Las paletas son propuestas, no identidad aprobada. La fotografía de Jericó es Xemenendura / Wikimedia Commons, CC BY-SA 4.0; crédito y licencia visibles en la comparación. Se ha comprimido y recortado. Los establecimientos visibles no se presentan como aliados.

public/ mantiene la landing anterior hasta que se elija una dirección. La nueva comparación no se publica en explorandolugares.com. Paradigma Solutions sigue como desarrollador tecnológico; no se usa como marca turística ni como destinatario de contactos.

## Verificación
Cinco propuestas visibles simultáneamente; sin carrusel ni paneles de diseño ocultos. Sintaxis de interacción comprobada. Revisión visual en navegador y prueba de cambio a Transporte en Pasaporte: el texto de categoría se actualiza sin petición externa. No se envían formularios, reservas ni mensajes desde estos bocetos.
