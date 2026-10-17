# Guía de dirección y descripción de ilustraciones

Vigente para Explorando Lugares. Estilo definitivo elegido por el propietario: **ilustración semirrealista**, versión `EL-SEMI-REALISTA-1`. Paisaje y arquitectura orgánicos y detallados, con profundidad, materiales y acabado pictórico sutil. Personas completas, expresivas y con estilización caricaturesca discreta, sin palitos/bolitas ni acabado infantil. Esta decisión sustituye la dirección general anterior de caricatura editorial exagerada; no aprueba una imagen concreta ni publicación. Para investigar y construir el prompt seguir [Instrucción del worker](../crecimiento-colombia/INSTRUCCION_WORKER_INVESTIGACION_VISUAL.md).

## 1. Elegir qué se está representando

- **Tipo de lugar o actividad:** composición original representativa, reutilizable solo en fichas compatibles. Puede construirse desde descripciones documentadas; no necesita copiar una fotografía.
- **Lugar concreto:** conservar sus rasgos distintivos documentados. No atribuirle la fachada, montaña o costa de una escena genérica.
- **Portada del destino:** partir de una foto real elegida por el propietario, con identidad y vía de uso comprobadas; conservar edificios, relieve, costa y relaciones espaciales. Cambia el tratamiento artístico, no la geografía.

La documentación pública para describir un lugar y la fotografía usada como entrada son fuentes diferentes. Se pueden consultar textos oficiales; no se reanudan búsquedas o descargas automáticas de fotos. Una imagen encontrada en una página no se envía al generador por estar visible allí.

## 2. Dirección artística común

|Elemento|Cómo representarlo|Qué rechazar|
|---|---|---|
|Personas|Personajes dibujados, con cuerpo, ropa, manos/pies cuando el encuadre lo permita, peinado, rostro y expresión; gesto y postura que expliquen la actividad. Expresión y estilización sutiles compatibles con el acabado semirrealista.|Cabeza circular sobre palitos, maniquíes, muñecos sin ropa/gesto, anatomía rota, caricaturas infantiles o personas prácticamente fotográficas.|
|Actividad humana|Acción legible y equipo correcto: caminar no equivale a correr; parapente no es paracaidismo. En una actividad protagonizada por personas, darles suficiente tamaño para que se lean.|Personas diminutas sin función, equipo intercambiado, actividad decorativa que no corresponde a la ficha.|
|Árboles y plantas|Tronco y ramificaciones naturales, masas de hojas variadas, luz y sombra; vegetación compatible con clima y entorno documentados.|Palito con tres círculos, árbol idéntico en todas las escenas, palmeras en cualquier montaña, vegetación ajena al entorno.|
|Montañas y relieve|Laderas, crestas, roca/suelo, vegetación y profundidad atmosférica; formas creíbles suavemente estilizadas.|Triángulos repetidos, nieve por decoración, volcanes, acantilados o montañas inventados detrás de un lugar concreto.|
|Edificaciones|Tipología apropiada y variedad de alturas, materiales, fachadas, balcones, cubiertas, patios y perspectiva. Volumen y detalles legibles en tarjeta.|La misma casa, iglesia o torre para todos; cubos de juguete; cúpulas o accesos inventados para un monumento real.|
|Color|Color propio de cielo, agua, piel, materiales y vegetación; contraste y sombras, con variedad entre escenas.|Filtro amarillo/sepia general, teñir el arte con el color de botones, saturación uniforme o recoloraciones contadas como escenas nuevas.|
|Composición|Profundidad, foco claro, silueta y acción legibles; detalles suficientes para enriquecer sin perder lectura pequeña.|Figuras geométricas elementales, detalles microscópicos que solo funcionan en el maestro, repetir el mismo esquema con otro color.|

El fondo crema pertenece a la interfaz, no a todas las imágenes. No incrustar marca, botones, bordes de tarjeta, títulos o etiquetas: así los recursos pueden convivir con rediseños posteriores. Esto no garantiza compatibilidad con cualquier cambio radical de dirección artística, pero evita ligarlos a una paleta de interfaz.

## 3. De documentación pública a descripción visual

1. Clasificar el componente: modo, tipo semántico, lugar y uso exacto. Para «senderismo» definir entorno; para «santuario» precisar si se representa el tipo o un edificio real.
2. Consultar primero fuentes del gestor, entidad turística local, patrimonio, parques o museo responsable. Usar otras fuentes para contraste cuando aporte evidencia. Buscar texto/documentación, sin reabrir la búsqueda fotográfica cancelada.
3. Extraer observaciones concretas: terreno, vegetación, materiales, tipos de edificio, elementos distintivos y relaciones espaciales. Guardar URL, entidad, fecha y afirmación respaldada. Parafrasear; no pegar artículos ni usar testimonios agregados como descripción del lugar.
4. Separar **hecho documentado**, **decisión artística** e **incógnita**. «Hay vegetación de manglar» necesita respaldo; «trazo expresivo» es estilo; «no se conoce el color de la fachada» es pendiente, no permiso para inventarlo.
5. Traducir los hechos al brief estructurado. No convertir importancia histórica en un objeto visual: que un fuerte tenga túneles no implica dibujarlos visibles en una vista exterior. Si falta evidencia de una forma distintiva, limitar el encuadre o conseguir la referencia elegida antes de representarla con precisión.
6. Comprobar la compatibilidad del tipo y el entorno antes de buscar variedad. Una imagen de buceo no sirve para snorkel por no repetirse. Un río no sirve como playa. Una calle caribeña con laderas imaginarias no se asigna a Cartagena porque tenga balcones.
7. Construir el prompt con los bloques de abajo, generar mediante el skill de imágenes y guardar versión/procedencia. Un brief preparado no significa imagen generada, ni una imagen generada significa aprobada.

## 4. Plantilla del encargo

Usar `content/templates/brief-ilustracion-editorial.json`. Mantener los campos de hechos, decisiones artísticas e incógnitas separados. Se puede ampliar el detalle cuando la escena lo necesite; no llenar campos irrelevantes con información inventada.

### Prompt base reutilizable

> Crea una ilustración editorial semirrealista EL-SEMI-REALISTA-1 de aspecto adulto para [uso]. Representa [tipo de lugar o actividad] en [entorno compatible]. Sujeto principal: [qué debe entenderse al mirar la tarjeta]. Conserva estos hechos documentados: [observaciones y relaciones espaciales]. Personajes: [acción, expresiones, ropa y proporciones estilizadas sutiles], con anatomía coherente y estilización sutil, no cabezas circulares sobre palitos. Vegetación: [tipos/formas compatibles], con ramas, follaje y volumen. Relieve: [rasgos documentados], con profundidad y formas orgánicas creíbles. Arquitectura: [tipologías/materiales/elementos pertinentes], sin convertir todos los edificios en el mismo modelo. Usa trazo expresivo, pintura e iluminación con profundidad; colores propios de la escena, sin baño amarillo ni dependencia de la paleta de interfaz. Composición [formato y foco], legible a tamaño de tarjeta. Sin texto, logos, marco ni etiquetas. Evitar acabado infantil, iconos geométricos, maniquíes y fotorealismo humano. No introducir elementos que contradigan [hechos/ausencias conocidas].

Para una **imagen específica**, añadir: «No sustituir ni deformar los rasgos identificables [lista]. No añadir relieve, edificios, accesos ni servicios no documentados». La estilización se concentra en trazo, luz, materiales y personajes; no deforma el monumento para hacerlo caricaturesco.

Para una **portada desde foto real**, añadir: «La imagen adjunta es referencia factual autorizada para [destino]. Conserva [siluetas, edificios principales, relieve, costa y perspectiva] y cambia únicamente al lenguaje editorial descrito. No sustituirla por una escena parecida». Inspeccionar antes la referencia y registrar exactamente cuál se envió.

## 5. Ejemplo documentado de preparación

En `content/cartagena/brief-centro-historico-editorial.json` se separan los datos de patrimonio del tratamiento ilustrado. UNESCO describe la ciudad fortificada, arquitectura civil/religiosa/residencial y barrios; eso permite orientar el contenido, pero no reconstruye por sí solo una calle o una perspectiva específica. La geometría exacta de una portada sigue necesitando la foto elegida.

Fuentes consultadas el17/10/2026:
- UNESCO: https://whc.unesco.org/en/list/285/ — conjunto urbano y fortificado; tipos y ubicación general. No se descargaron fotos.
- ProColombia: https://colombia.travel/es/cartagena/el-castillo-de-san-felipe — fortaleza sobre el cerro de San Lázaro y túneles; no prueba una fachada completa ni un encuadre exterior.
- El portal local de La Boquilla respondió «Sitio en reconstrucción» durante esta consulta: no extraer de esa respuesta una descripción visual ni darla por verificación actual.

## 6. Revisión de la salida

- ¿Se entiende el lugar/actividad correcto, no solo una escena bonita?
- ¿Los personajes son caricaturas completas y expresivas, con acción y anatomía coherentes?
- ¿Árboles, montañas y edificios tienen formas orgánicas y volumen, sin vocabulario visual de palitos/círculos/triángulos?
- ¿Las tipologías y el entorno corresponden a la documentación? ¿Se conservaron las incógnitas sin inventar rasgos específicos?
- ¿Tiene colores naturales y contraste sin dominante amarilla? ¿Funcionaría con otra interfaz sin cambiar el arte?
- ¿La composición es suficientemente diferente de las variantes ya aprobadas? No contar espejos, recortes o recoloraciones como variedad real.
- ¿Se lee a480px y al tamaño real móvil? Comparar el maestro y el WebP optimizado; medir bytes y artefactos antes de fijar una calidad. El rango46–58KB observado en las muestras no es obligación ni garantía para toda escena.
- ¿Quedaron vinculados fuentes, referencia realmente usada, prompt, maestro, derivado, hashes y decisiones? Mantener la imagen en borrador hasta revisión del resultado; producción requiere aprobación final.

La regla de no repetición se aplica solo entre alternativas semántica y territorialmente válidas. Si no hay otra compatible, falta producir/revisar una variante: no escoger otra cosa al azar.
