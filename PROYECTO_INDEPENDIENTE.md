# Explorando Lugares — contexto para un proyecto independiente

Este documento permite comenzar un proyecto o conversación nueva sin depender del historial de Paradigma Solutions. Es la referencia vigente para la intención de producto y las preferencias del propietario.

> Contexto fundacional: identidad, independencia y alcance siguen vigentes. Las afirmaciones de estado del lanzamiento (precios aún no definidos, ausencia de catálogo/acceso al hosting) son antecedentes, no describen el producto actual. Para decisiones posteriores y reglas vigentes consultar `docs/CONTEXTO_PRODUCTO_Y_HOJA_DE_RUTA.md`, `docs/REGLAS_PROYECTO.md` y el código/paquete verificado; este documento no autoriza retirar funcionalidades implementadas.

## Identidad y separación

- Negocio y marca: **Explorando Lugares**.
- Dominio: **explorandolugares.com**.
- Repositorio: https://github.com/nivatogithub/explorandolugares
- Es un negocio independiente de turismo, descubrimiento territorial y economía local.
- Paradigma Solutions puede actuar como proveedor de desarrollo tecnológico. Esa relación no define la marca, el diseño, las fuentes, los mensajes, el modelo de negocio ni la audiencia de Explorando Lugares.
- No utilizar archivos, conversaciones, contactos, paletas, tipografías, componentes corporativos, plantillas ni instrucciones comerciales de Paradigma para diseñar o redactar este proyecto.
- La independencia aquí es una decisión de producto y marca; no se afirma una estructura societaria, registro de marca o razón social aún no proporcionada.

## Qué quiere construir el propietario

Un portal completo que conecte al visitante con la vida y la economía de cada pueblo. No debe limitarse a hoteles y experiencias turísticas: debe dar lugar a los comercios, actividades, espacios culturales y servicios de quienes viven y trabajan allí.

Dos públicos igualmente importantes:

1. Turistas y visitantes que quieren descubrir un destino, organizar su visita, encontrar opciones y contactar negocios locales.
2. Comerciantes, hoteleros, restauranteros, anfitriones, productores, gestores culturales y prestadores de servicios que quieren participar y presentar su propuesta.

La marca debe sentirse moderna y fácil de usar, con calidez, confianza y detalles tradicionales o de pueblo. Debe atraer al turista y ser reconocible y cercana para los negocios locales. No debe parecer una empresa de tecnología ni una copia de una agencia de reservas.

## La marca no es Jericó

Jericó, Antioquia, es el destino inicial, no la identidad de la empresa. No usar sus paisajes, arquitectura o símbolos como imagen permanente del portal, ni limitar el discurso de la marca a ese municipio.

La portada de Explorando Lugares presenta el negocio y permite descubrir destinos. Cada pueblo tiene su propia página con imágenes, información y propuestas locales. La arquitectura debe poder incorporar otros destinos sin rediseñar la marca.

## Alcance de un destino

Taxonomía inicial de trabajo, ampliable y pendiente de validar con contenido real:

- **Alojamiento:** hoteles, hostales, fincas, cabañas, apartamentos y otras opciones.
- **Gastronomía:** restaurantes, cafés, panaderías, heladerías y comida local.
- **Bares, rumba y vida nocturna:** bares, discotecas, música en vivo y espacios para salir.
- **Cultura y patrimonio:** teatros, museos, galerías, bibliotecas, arquitectura, espacios y actividades culturales.
- **Experiencias y naturaleza:** recorridos, talleres, caminatas, visitas a productores y actividades.
- **Comercios, productores y productos locales:** tiendas, mercados, artesanías, moda, emprendimientos y oficios.
- **Transporte y movilidad:** cómo llegar, transporte local, traslados y alquileres.
- **Servicios del pueblo:** bienestar, belleza, salud, servicios profesionales y otros negocios locales.
- **Agenda:** fiestas, ferias, mercados, espectáculos y eventos con fechas confirmadas.
- **Información práctica:** orientación para visitantes, accesibilidad y contactos útiles verificados.

El portal debe admitir toda la economía local. Esta visión amplia no significa inventar fichas, tener todas las categorías activas el primer día ni prometer reservas o pagos desde el lanzamiento.

## Arquitectura del producto

Inicio → Destinos → Pueblo → Categoría → Ficha del negocio, actividad o servicio.

La agenda tiene una entrada visible dentro de cada destino. La navegación debe distinguir contenido permanente (lugares y negocios) de eventos con fecha.

Una ficha futura puede incluir: nombre, categoría, descripción, fotos autorizadas, ubicación, horarios, servicios y canales de contacto confirmados. Definir campos específicos para alojamiento, gastronomía, vida nocturna, cultura, transporte y eventos, sin forzar todos a una ficha hotelera.

Ejemplo de rutas de trabajo, aún no implementadas como portal completo:

- `/destinos/`
- `/destinos/jerico/`
- `/destinos/jerico/bares-y-vida-nocturna/`
- `/destinos/jerico/cultura/`
- `/destinos/jerico/agenda/`
- `/destinos/jerico/comercios/nombre-del-negocio/`
- `/aliados/`

## Modelo comercial: lo conocido y lo pendiente

El objetivo inicial es captar aliados y construir información local útil, con presencia contextual por destino y contacto directo.

Invitación de trabajo: **“¿Tienes un negocio, un espacio cultural o una actividad en tu pueblo? Haz parte de Explorando Lugares.”**

No están definidos precios, tarifas de publicación, comisiones, publicidad, planes, reservas ni servicios de pago. Cualquier modelo de ingresos debe proponerse y acordarse explícitamente. No garantizar ventas, reservas, ocupación, visitas ni resultados comerciales.

## Preferencias de diseño expresadas por el propietario

- La ilustración inicial del paisaje y la primera combinación verde/crema/terracota no convencieron.
- El símbolo de logo existente puede servir como candidato; no está aprobado de forma definitiva.
- Se prefirieron **Pasaporte** y **Encuentro** entre cinco direcciones de diseño.
- Pasaporte resultó demasiado oscuro.
- Encuentro gustó por su calidez, pero se percibió conservador y muy pastel.
- La mezcla más reciente gustó un poco más: se busca un equilibrio entre moderno, conservador, tradicional y pueblo.
- El propietario señaló que las fuentes y el estilo aún le recuerdan a Paradigma. **Revisar la identidad tipográfica desde cero con justificación propia para Explorando Lugares. No tratar Arial/Georgia, el logo, la paleta ni la composición del borrador como decisiones aprobadas.**
- Los nombres Brújula, Pasaporte, Encuentro, Atlas abierto, Bitácora y Pasaporte local describen exploraciones; no sustituyen el nombre Explorando Lugares.
- Si se ofrecen alternativas, mostrarlas visibles, numeradas y comparables. No esconderlas en un carrusel; anteriormente el propietario solo veía una opción.

## Cómo utilizar las referencias de mercado

Booking, Despegar, Expedia, Puebliando por Antioquia y portales turísticos públicos se consultaron para entender propósito, estructura, captación comercial y navegación. No son referentes a copiar visualmente.

Analizar tareas del visitante, entradas por destino/categoría, información de fichas y recorrido para aliados. Diseñar una identidad propia en lugar de trasladar colores, tipografías o componentes de competidores. La revisión histórica está en `design/exploracion/REFERENCIAS.md`, con sus limitaciones documentadas.

## Estado de los artefactos

### Borrador inicial de referencia: v0.1

`design/pasaporte-local/index.html` conserva el último ensayo visual como **borrador inicial**, no diseño final aprobado.

También contiene:
- `propuesta.fragment.html`: fuente de la propuesta presentada en conversación.
- `DIRECCION.md`: razonamiento del ensayo original, conservado como histórico.
- `BORRADOR.md`: estado, límites y pendientes vigentes.

Este ensayo todavía muestra cinco categorías: hoteles, restaurantes, comercios, experiencias y transporte. **Faltan** vida nocturna, cultura, agenda, servicios y las otras áreas del alcance completo. La documentación de alcance no equivale a haber implementado esas secciones.

`public/` y los ZIP existentes corresponden a una landing anterior. **No son el diseño actual aprobado ni el portal completo.** `docs/IDENTIDAD.md` y exploraciones previas son materiales históricos; sus fuentes, paletas y mensajes no tienen autoridad sobre las preferencias de este documento.

### Restricciones técnicas conocidas

- Primera entrega publicable deseada: sitio responsive en español, SEO-friendly, estático y compatible con el cPanel existente.
- Dominio con carpeta raíz independiente de otros sitios.
- No presentar formularios como funcionales si no hay backend. Se puede preparar un mensaje y continuar por correo/WhatsApp configurados, explicando el comportamiento.
- Correo, WhatsApp, responsable del negocio y datos de privacidad reales siguen pendientes. No reutilizar los contactos de Paradigma ni inventarlos.
- No hay acceso confirmado al cPanel ni despliegue verificado del portal.
- No hay reservas, pagos, catálogo real, reseñas ni aliados confirmados incorporados.
- Los recursos de terceros deben conservar autoría, licencia y permisos; no presentar comercios visibles en una foto como aliados.

## Próximo trabajo en el proyecto nuevo

1. Leer este documento, `AGENTS.md` y `design/pasaporte-local/BORRADOR.md`.
2. Revisar el borrador v0.1 como punto de partida de conversación, sin convertirlo en identidad aprobada.
3. Proponer una identidad tipográfica independiente y refinar el equilibrio moderno/tradicional para turistas y negocios locales.
4. Ampliar la arquitectura del destino a toda la economía local, con vida nocturna, cultura, agenda y servicios.
5. Desarrollar portada general, página de destino, categorías y ficha de negocio de manera coherente.
6. Separar categorías en preparación de contenido confirmado; completar contacto y datos reales antes de publicar.
7. Mantener código, recursos, decisiones y versiones en este repositorio.

## Texto para iniciar una conversación nueva

Trabaja exclusivamente en Explorando Lugares, un negocio independiente cuyo repositorio es https://github.com/nivatogithub/explorandolugares. Lee PROYECTO_INDEPENDIENTE.md, AGENTS.md y design/pasaporte-local/BORRADOR.md antes de proponer cambios. Usa el último ensayo como borrador de referencia, no como identidad aprobada. No uses contexto, fuentes, colores, contactos ni estilo de Paradigma Solutions. Refina una identidad propia que combine claridad moderna con calidez y carácter tradicional de pueblo, atractiva para turistas y negocios locales. El portal debe abarcar toda la economía del destino, incluyendo bares, vida nocturna, teatros, cultura, agenda y servicios. Jericó es el primer destino y tiene su propia página; no define la identidad de la marca. Continúa con propuestas concretas y visibles, documenta decisiones y conserva los artefactos en GitHub.
