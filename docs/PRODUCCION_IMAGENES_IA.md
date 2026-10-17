# Producción de imágenes ilustradas con IA

Guía para preparar imágenes de destinos de Explorando Lugares que sean atractivas, reconocibles y honestas con el lugar representado. Aplica a este repositorio y a cualquier herramienta o proveedor de IA.

## Principio editorial

El estilo acordado es una caricatura realista editorial: trazos visibles de tinta y pintura tipo gouache, con formas simplificadas pero sin deformar ni cambiar la arquitectura o los rasgos del atractivo. No debe parecer una foto con luz o saturación retocadas. La ilustración puede estilizar un sitio, pero los rasgos que permiten reconocerlo deben corresponder al sitio real. Una imagen bonita que invente una cascada, cambie la forma de una iglesia o muestre un monumento distinto puede crear expectativas equivocadas y perjudicar al destino.

- Usa ilustración generada para dar unidad visual, no para sustituir la verificación territorial.
- Si el atractivo principal es un monumento, debe distinguirse inmediatamente y conservar sus rasgos más reconocibles.
- Si no hay referencias suficientes para representar un sitio con fidelidad, publica una foto autorizada, una vista más general claramente identificable o una tarjeta sin imagen. No rellenes con una escena inventada.
- Nunca presentes una ilustración generada como fotografía, registro de condiciones actuales, prueba de acceso, estado, horario o disponibilidad.
- No inventes negocios, personas, señalización, arquitectura, rutas, cascadas ni servicios como si existieran.

## Flujo de trabajo

1. **Definir el encargo.** Registra destino, atractivo concreto, ubicación, propósito de la imagen, proporción y tamaño de uso. Decide qué elemento debe dominar la composición y qué contexto hace reconocible el lugar.
2. **Comprobar los hechos visuales.** Consulta una fuente institucional/local y, cuando se trate de una forma concreta, una o más fotografías recientes o claramente identificadas. Confirma nombre, forma, posición, colores, pedestal, orientación y paisaje circundante. Registra URL y fecha de consulta en el manifiesto del activo.
3. **Comprobar los materiales de referencia.** Prefiere fotografías propias con permiso, material autorizado o imágenes cuya licencia permita el uso y la adaptación previstos. No descargues o entregues a un generador una foto protegida por derechos sin autorización suficiente. Una búsqueda pública no equivale a permiso de reutilización.
4. **Generar la imagen.** Especifica lugar y rasgos confirmados; pide una ilustración editorial semirrealista, de caricatura realista con trazos y pintura claramente visibles, coherente con la paleta del portal. Indica el encuadre necesario para la tarjeta o portada. Excluye texto, marcas de agua, monumentos adicionales y elementos no confirmados. No pidas copiar exactamente una fotografía ni el estilo de un artista vivo.
5. **Revisar la salida.** Compara la imagen con las referencias. Rechaza errores de identidad, arquitectura, geografía, anatomía, texto falso, vehículos o actividades improbables. Comprueba especialmente que el atractivo nombrado sea visible al tamaño real en escritorio y móvil.
6. **Ajustar y exportar.** Haz otra generación si hay errores estructurales. Recorta al formato de uso sin eliminar el atractivo. Optimiza tamaño y formato para redes lentas; conserva el archivo maestro y la salida web por separado.
7. **Guardar en borrador y pedir revisión.** La imagen queda como propuesta hasta revisión humana. No la copies a la ruta pública ni la publiques por iniciativa de una herramienta o agente. La publicación sigue el flujo editorial del destino.

## Cómo redactar el prompt

Usa instrucciones observables, no solo adjetivos. Por ejemplo:

> Ilustración editorial semirrealista del [atractivo] en [destino]. Representa [rasgos confirmados de forma, color, materiales y ubicación]. El [atractivo principal] debe ocupar aproximadamente [proporción] del encuadre y reconocerse en una tarjeta pequeña. Conserva [paisaje, edificios o contexto confirmados]. Usa una paleta natural y contenida coherente con Explorando Lugares, luz [condición]. Formato [proporción]. No agregues [elementos falsos específicos], no incluyas texto ni marcas de agua. Usa las referencias solo para respetar los rasgos del lugar; crea una composición ilustrada nueva.

Si hay varias referencias, explica cuál sirve para cada cosa: una foto para la forma del monumento, otra para el entorno, y una guía visual del portal para el acabado. No dejes que una referencia de estilo cambie los hechos del sitio.

## Revisión antes de aprobar

- ¿La imagen se ve claramente ilustrada —no como una foto retocada— sin deformar el lugar?
- ¿Se reconoce el sitio o atractivo sin leer el título?
- ¿El elemento anunciado es el foco y se ve en el recorte real de la página?
- ¿Coinciden la silueta, proporciones, ubicación, orientación, materiales y colores que lo identifican?
- ¿El pueblo, paisaje, edificio y camino corresponden al destino y no a otro lugar?
- ¿Hay señales, textos, personas, negocios, servicios o condiciones inventados?
- ¿La ilustración podría hacer creer que muestra una foto o el estado actual del lugar?
- ¿La tarjeta informa que es ilustración cuando esa distinción sea relevante?
- ¿El peso, dimensiones, formato, texto alternativo y nombre del archivo son adecuados?
- ¿La procedencia y aprobación están registradas y el estado sigue siendo borrador hasta aprobarse?

## Procedencia y fuentes: qué conservar

La IA no elimina la necesidad de conservar procedencia; cambia qué tipo de registro hace falta.

- Para una imagen generada sin una fotografía concreta como referencia, normalmente no se necesita una atribución fotográfica pública a un fotógrafo. Aun así, guarda internamente el prompt, herramienta/modelo si se conocen, fecha, responsable, destino, estado de revisión y fuentes usadas para verificar los hechos representados.
- Si se usó una fotografía concreta como entrada o referencia visual, registra esa imagen, su autor, origen, licencia y permiso. No supongas que generar una versión estilizada elimina condiciones de licencia o derechos; confirma que el uso y la adaptación están permitidos antes de publicar.
- Las páginas públicas de créditos pueden seguir mostrando fotografías y obras de terceros que exijan atribución. Las ilustraciones generadas se pueden identificar en una nota editorial propia o en el manifiesto; no se deben mezclar con los créditos de fotografía como si fueran fotos licenciadas.
- No afirmes que conocemos las fuentes de entrenamiento del modelo si el proveedor no las documenta.
- Las fuentes que confirman datos del destino son distintas de los créditos visuales. Mantén enlaces de verificación aunque la imagen no use directamente esas fuentes como referencia gráfica.

El portal puede simplificar la página pública de atribuciones para que incluya solo recursos de terceros cuyo crédito corresponda. El repositorio y la administración deben conservar un manifiesto breve de todos los activos, incluidas las imágenes IA. Por eso, la ilustración puede reducir créditos visibles, pero no debe borrar la trazabilidad editorial.

## Registro mínimo del activo

Usa [`content/templates/procedencia-imagen-ia.json`](../content/templates/procedencia-imagen-ia.json). Si se crea o modifica un activo mediante una herramienta externa, su formato, peso, dimensiones, descripción alternativa, idioma, referencias visuales, fuentes factuales, permiso, prompt y revisión deben quedar asociados al destino y al archivo final.

La procedencia guardada no publica la imagen automáticamente. La persona responsable marca el activo aprobado y, por separado, publica el destino o la nueva versión.

## Alcance de permisos para la salida IA · 17/10/2026

Aplicar `crecimiento-colombia/GUIA_GENERACION_DESTINOS.md`, sección Publicabilidad de resultados IA. Se admite permiso específico para transformación y publicación del resultado sin exigir por separado publicar el original intacto. Alternativamente crear una composición independiente a partir de hechos verificados, sin subir/copiar una fotografía restringida. IA, cambio de estilo o ausencia de coincidencias no eliminan restricciones. Registrar condiciones aplicables y procedencia; no prometer exención de derechos ni evadir detección.
