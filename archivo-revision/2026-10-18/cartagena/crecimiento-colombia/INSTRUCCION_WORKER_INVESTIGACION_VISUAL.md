# Instrucción del worker: investigar y generar ilustraciones fieles

Estilo elegido explícitamente por el propietario: **ilustración semirrealista**, ID `EL-SEMI-REALISTA-1`. Referencia de acabado: `design/cartagena-estilos-v2/03-semirrealista-galeria.webp`. Esa escena imaginaria es referencia de estilo, no referencia geográfica de Cartagena. Elegir estilo no aprueba ninguna portada concreta ni publicación.

## Encargo reutilizable

> Investiga visualmente [destino] y [lugar o actividad], con uso [portada/tarjeta específica/tarjeta de categoría]. Trabaja en su rama y conserva archivos y aprobaciones existentes. Aplica EL-SEMI-REALISTA-1, docs/GUIA_BIBLIOTECA_ILUSTRADA.md y el skill de imágenes del repositorio.
>
> Primero prepara una ficha de evidencia visual. Consulta documentación pública del gestor, turismo local/nacional, patrimonio, parques, museo o responsable pertinente. Identifica ubicación y nombre inequívocos; registra tipo, forma/silueta, materiales, colores documentados, relieve, vegetación, elementos distintivos y relaciones espaciales. En actividades identifica acción, postura y equipo correctos. Busca por elementos que faltan, no solo por el nombre del destino.
>
> Conserva URL, entidad, fecha y afirmación respaldada. Distingue hechos, decisiones artísticas e incógnitas. Usa referencias visuales públicas verificables para contrastar la identidad dentro del alcance autorizado; no conviertas esta investigación en lotes automáticos de fotos ni supongas que consultar una imagen permite subirla al generador. Para la portada sigue vigente la foto real elegida por el propietario. Si falta una referencia visual necesaria, prepara la solicitud precisa y continúa los otros componentes autorizados.
>
> No generes todavía con solo el nombre del lugar. Completa el brief JSON y determina si existe evidencia suficiente para el modo elegido. Si una característica esencial no está documentada, no la inventes para cerrar la imagen. Una tarjeta representativa puede describir el tipo y contexto sin reconstruir un monumento; no la presentes como una vista específica.
>
> Construye el prompt a partir de la ficha aprobable, añadiendo la dirección artística fija y las exclusiones concretas. Conserva la identidad factual; revisa la salida contra la ficha y la referencia realmente utilizada. Entrega imagen, hechos conservados, desviaciones, peso, procedencia y estado de aprobación. Sin publicación en producción.

## Búsqueda dirigida: preguntas antes de consultas

|Qué falta saber|Consulta orientativa|Qué debe aportar|
|---|---|---|
|Identidad y ubicación|`[nombre exacto] [municipio] sitio oficial ubicación`|Distinguir homónimos y evitar usar otro lugar.|
|Arquitectura|`[atractivo] patrimonio descripción arquitectura fachada materiales`|Silueta, volumen, elementos propios y materiales; no solo importancia histórica.|
|Relieve y vegetación|`[sitio] [parque/gestor] paisaje ecosistemas relieve vegetación`|Entorno compatible; confirmar si mar/montañas están en ese encuadre.|
|Forma y relación espacial|`[atractivo] documentación plano alzados ficha patrimonial`|Qué elementos están juntos y cómo se relacionan; fuente visual cuando el texto no basta.|
|Actividad|`[actividad] [lugar] [responsable] recorrido equipo`|Qué hace la persona y qué equipo corresponde; no demostrar disponibilidad comercial solo por una ilustración.|
|Accesibilidad de la fuente|Abrir la página concreta, no quedarse con un resultado|Confirmar contenido; un enlace caído no equivale a fuente verificada.|

Estas son plantillas de consulta, no instrucciones para ejecutar todas en cada caso. Investigar los huecos relevantes. Tripadvisor puede orientar descubrimiento textual; contrastar rasgos con fuentes primarias. No confundir una foto etiquetada con un lugar con una localización comprobada.

## Ficha de evidencia visual que debe devolver

- **Identidad:** destino, componente, nombre, ubicación y modo de representación.
- **Rasgos esenciales:** entre los relevantes para reconocer la escena; cada uno ligado a su fuente y confianza.
- **Entorno:** terreno, vegetación, arquitectura, costa/agua y perspectiva verificables. No asumir que algo próximo se ve desde la cámara elegida.
- **Actividad y personas:** gesto/acción, posición corporal y equipo compatibles. Figuras completas, legibles y expresivas, sin personas de palitos.
- **Referencia de estilo:** EL-SEMI-REALISTA-1, separada de toda referencia factual.
- **Material visual de entrada:** foto elegida, rol exacto, procedencia/vía de uso y hash; o composición independiente para categoría.
- **Incógnitas y exclusiones:** qué no se conoce y qué no debe aparecer.
- **Decisión:** listo para generar dentro del alcance / falta una evidencia precisa. No inventar porcentajes de fidelidad.

No hace falta multiplicar fuentes si una fuente primaria basta; contrastar rasgos dudosos y afirmaciones que condicionen la identidad. Para una portada fiel, una descripción turística breve no sustituye la foto real elegida.

## Bloque artístico fijo

> Ilustración editorial semirrealista de aspecto adulto, claramente pintada y no fotográfica. Arquitectura y paisaje con profundidad, materiales legibles, luz natural y pincelada sutil. Árboles con troncos, ramas y follaje orgánicos; montañas con relieve y profundidad atmosférica compatibles con el sitio. Personajes ilustrados completos, expresivos y de anatomía coherente, con estilización caricaturesca sutil cuando encaje; nunca bolitas/palitos ni muñecos infantiles. Colores propios del cielo, agua, vegetación, piel y materiales, sin filtro amarillo ni dependencia de la paleta de la interfaz. Sin títulos, logos, marcos ni etiquetas.

## Prompt final: orden de bloques

1. **Identidad y uso:** qué se representa y si es escena específica o tipología.
2. **Hechos:** silueta, elementos, materiales y relaciones documentadas.
3. **Acción:** actividad correcta y papel de las personas, si corresponde.
4. **Encuadre:** perspectiva respaldada y foco, sin recortar el rasgo principal.
5. **Estilo:** bloque fijo EL-SEMI-REALISTA-1.
6. **Exclusiones propias de la ficha:** errores concretos que deben evitarse.
7. **Entrada visual:** distinguir foto factual de referencia de acabado; no mezclar sus funciones.

### Ejemplo de corrección de un encargo

Insuficiente: «Dibuja Cartagena con casas coloridas y una iglesia».

Preparado: «Portada de Cartagena desde [foto real elegida]. Conservar [siluetas y disposición verificadas de esa foto], relación entre ciudad y costa y perspectiva. Aplicar EL-SEMI-REALISTA-1. No añadir montañas, campanarios, calle en pendiente ni acceso al mar ausentes de la referencia. Personajes, si aparecen, secundarios y coherentes con la escala. No copiar la geografía de la imagen de estilo».

Los corchetes se completan con evidencia; no se envían como prompt final.

## Control antes de entregar

Revisar por separado representación del tema, fidelidad del lugar cuando aplica, acción/equipo, anatomía, color, encuadre y lectura pequeña. Si el arte es atractivo pero cambia un rasgo esencial, corregirlo. Conservar maestros fuera del hosting y derivados medidos; no sacrificar fidelidad o legibilidad por un peso arbitrario. La revisión humana del resultado y la aprobación final del destino siguen vigentes.
