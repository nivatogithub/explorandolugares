# Procedimiento reutilizable del worker de imágenes

Estado: proceso propuesto para validar con dos o tres destinos piloto. No autoriza generación masiva ni publicación. Propietario: Explorando Lugares. La instrucción humana más reciente prevalece. Última actualización:17/10/2026.

## Decisión vigente de estilo

El propietario eligió definitivamente ilustración semirrealista, ID EL-SEMI-REALISTA-1. Aplicar [INSTRUCCION_WORKER_INVESTIGACION_VISUAL.md](INSTRUCCION_WORKER_INVESTIGACION_VISUAL.md) antes de generar: investigar evidencia visual, resolver incógnitas y separar referencia de estilo de referencia factual. Las menciones anteriores a caricatura se conservan solo como estilización sutil de personajes, no como acabado global alternativo.

## Objetivo y alcance

Producir imágenes editoriales atractivas, ligeras y correctamente relacionadas con cada destino, atractivo o experiencia. Reutilizar ilustraciones compatibles sin asignarlas al azar ni hacer pasar una escena genérica por la vista real de un lugar.

Este documento coordina el trabajo; las instrucciones visuales y el brief viven en [Guía de biblioteca ilustrada](../docs/GUIA_BIBLIOTECA_ILUSTRADA.md), [skill de imágenes existente](../.agents/skills/imagenes-destinos/SKILL.md) y [plantilla de encargo](../content/templates/brief-ilustracion-editorial.json). No sustituir esas fuentes por prompts improvisados ni crear un skill paralelo.

## 1. Validar el procedimiento con dos o tres destinos

1. Terminar una muestra completa de Cartagena con el estilo elegido y revisado en la plantilla real.
2. Elegir un destino de otro entorno, por ejemplo una ciudad de montaña: probar vegetación, relieve, edificios y movilidad diferentes. Su elección no significa reasignar un worker ni saltar la cola territorial.
3. Si quedan dudas, usar un tercer destino de escala distinta, como un municipio pequeño. No crear ejemplos artificiales para marcar categorías como cubiertas.

Antes de ampliar la producción, comprobar en esos pilotos:

- Dirección artística suficientemente definida: personajes completos y caricaturescos, entorno orgánico y edificios variados; sin geometría infantil ni dominante amarilla.
- Encargos reproducibles desde fuentes y descripciones, con incógnitas explícitas.
- Portada fiel a una foto real elegida y permitida; tarjetas genéricas correctamente diferenciadas de vistas específicas.
- Lectura y encuadres satisfactorios en escritorio y móvil dentro del sitio.
- Tiempos, correcciones, peso y archivos por destino medidos.
- Una selección de la biblioteca que corresponda al contenido y no repita composiciones demasiado parecidas en el recorrido de prueba.
- Revisión del propietario y cierre de pendientes relevantes. Tener archivos o un ZIP no significa terminar el destino.

No declarar el proceso «validado para todos los destinos» por completar solo categorías ya probadas. Registrar los tipos aún no cubiertos.

## 2. Entrada de una tarea

Cada encargo identifica destino y componente, rama de trabajo, textos aprobados o borradores vigentes, fuentes existentes, referencias elegidas y aprobación por hash. Revisar AGENTS.md, documentos del repositorio y trabajo ajeno antes de editar.

Mantener un único responsable de imágenes por componente. Si varios workers colaboran en el mismo destino, coordinar una única rama de destino o una propuesta aislada para integración; nunca escribir simultáneamente en los mismos archivos ni duplicar generaciones. No cambiar de destino ni abrir chats adicionales por iniciativa del procedimiento.

Preparar inventario antes de generar:

|Campo|Contenido|
|---|---|
|Identidad|Destino, ID del atractivo/experiencia y rol exacto|
|Modo|Portada real, lugar específico o ilustración representativa de categoría|
|Semántica|Qué lugar o actividad debe entenderse|
|Entorno|Clima, relieve, vegetación, arquitectura y contexto documentados|
|Estado|Investigación, brief, referencia, generación, revisión, corrección, integración o finalizado|
|Material existente|Activo, prompt, fuentes y decisiones previas; reutilizable o descartado y por qué|

Confirmar la selección editorial antes de producir imágenes de atractivos todavía fuera de los ocho principales. La disponibilidad de una imagen no determina la prioridad turística.

## 3. Documentación pública y descripción visual

Consultar textos de entidades turísticas, gestores, patrimonio, parques, museos y otras fuentes pertinentes. La consulta textual está autorizada; no reabrir búsquedas o descargas automáticas de fotografías que el propietario decidió elegir manualmente.

Por cada observación guardar afirmación, URL, entidad, fecha y qué permite representar. Separar:

- Hechos: materiales, forma, entorno, elementos distintivos y relaciones espaciales documentados.
- Decisiones de dibujo: trazo, expresividad, iluminación y composición.
- Incógnitas: detalles no comprobados que no se rellenan como hechos.

Una web en reconstrucción, un fallo de acceso o una fuente histórica no prueban el estado actual. Las opiniones de otros destinos incrustadas en una página no describen el lugar investigado.

Completar un brief JSON por componente con la plantilla vigente. Para lugares específicos no reconstruir arquitectura exacta a partir de un nombre o de una breve reseña histórica.

## 4. Elegir la vía de imagen

### A. Portada del destino

Usar una foto real elegida por el propietario. Verificar identidad, procedencia, fecha y vía permitida de transformación/publicación; inspeccionarla antes de generar. Conservar los rasgos reconocibles, costa, relieve, edificios y relaciones espaciales. No sustituirla por una escena similar.

La elección o aprobación de una referencia no es aprobación del resultado generado. No repetir una petición de referencia si ya existe una elección válida para ese mismo uso.

### B. Lugar concreto con identidad visual necesaria

Usar documentación y referencias suficientes para conservar su identidad. Si no alcanzan, dejar pendiente ese detalle o solicitar la referencia concreta; continuar los otros componentes independientes. Una caricatura puede cambiar el trazo, no inventar el edificio.

### C. Tarjeta representativa de tipo de lugar o actividad

Construir o seleccionar una composición de biblioteca adecuada al tipo y entorno. No necesita una foto de entrada si es una creación independiente. No atribuirle una fachada o perspectiva reales que no representa.

Primero filtrar por significado y compatibilidad territorial; después buscar variedad. No usar buceo para snorkel, metro para metrocable, río para playa o vegetación ajena al entorno por evitar una repetición.

## 5. Seleccionar biblioteca o preparar una variante

Registrar para cada activo: tipo, entorno, rasgos incluidos/excluidos, composición, estilo/versionado, restricciones de uso y revisión. Si se reutiliza, conservar su identidad y añadir la vinculación de uso al nuevo componente.

Si falta una alternativa compatible, producir/revisar otra variante; no rellenar con otra categoría. Variar perspectiva, distribución, acción, protagonistas, escala y entorno válido. Espejos, recortes y recoloraciones no cuentan como nuevas escenas.

El objetivo inicial de unas540imágenes es una hipótesis de planificación, no un encargo masivo ni garantía. Consultar [Plan de biblioteca](../design/cartagena-estilos-v2/PLAN_BIBLIOTECA.md). La frecuencia real de categorías y entornos determinará cuántas variantes necesita cada grupo.

Para evitar repetición exacta en veinte destinos vistos se requiere conocer esa ventana y disponer de suficientes alternativas compatibles. El mecanismo de historial todavía no está implementado. Medir también similitud compositiva; un hash diferente no prueba diferencia visual. No prometer que nadie notará reutilización por tener cierto número de archivos.

## 6. Generación

Aplicar el skill existente y el generador de imágenes disponible. Mantener el estilo/versionado elegido, sin pedir a cada worker que lo redefina.

El prompt combina: rol + hechos documentados + elementos principales + personajes/acción + vegetación/relieve + arquitectura + lenguaje visual + encuadre + elementos que deben conservarse y exclusiones.

Personas: cuerpo, ropa, gesto y rostro cuando el tamaño lo permita; expresiones y proporciones moderadamente caricaturescas, sin palitos/bolitas ni humanos fotográficos. Naturaleza: formas orgánicas, ramas, follaje, volumen y relieve compatibles. Edificios: tipos y materiales diferenciados. Color propio de la escena, sin filtro amarillo ni dependencia de botones y fondos del sitio.

No incrustar títulos, marcas, marcos ni controles. Conservar maestros separados de derivados, prompt exacto, herramienta/modelo si se informa, entradas realmente enviadas y hashes. No generar lotes nacionales antes de elegir estilo y validar pilotos.

## 7. Inspección y optimización

Revisar significado, identidad cuando aplica, anatomía, equipo, vegetación, relieve, arquitectura y encuadre. Corregir errores antes de optimizar; comprimir no arregla un sujeto equivocado.

Probar los tamaños reales de tarjeta y portada. Exportar WebP u otro formato compatible que se justifique con mediciones. No imponer un peso que destruya caras, líneas o detalles. Las muestras480×320de la galería ocuparon46–58KB; es una observación, no un máximo universal. Medir portada aparte.

Guardar dimensiones, formato, bytes, parámetros y hash por derivado. Servir solo tamaños necesarios, mantener espacio reservado para evitar saltos y carga diferida fuera de la primera pantalla. No subir PNG maestros, alternativas descartadas, capturas o archivos de investigación al hosting. Evitar duplicar físicamente el mismo activo por destino: vincular una URL común estable cuando la integración lo soporte. Esa integración compartida debe comprobarse, no darse por implementada.

## 8. Revisión del propietario

Presentar en este chat la imagen con destino, rol exacto, referencia/procedencia y versión. Cuando haya alternativas, numerarlas y mostrarlas visibles. No exigir formularios de aprobación ni exportaciones.

Registrar por separado: referencia elegida, estilo elegido, resultado generado aprobado y revisión final del destino. Una expresión de agrado por la galería no aprueba una portada concreta ni la publicación.

Conservar decisiones por hash. Si cambia una imagen o su uso, no trasladar automáticamente la aprobación anterior. No volver a pedir la misma aprobación intacta. Mientras se decide un componente, continuar otro trabajo autorizado del mismo destino.

## 9. Integración en preproducción

Preparar el paquete de la versión revisada en su rama independiente. Antes de importar, comprobar la aplicación y base actuales: el entorno local usa MariaDB10.11, pero puede haber cambiado por otro worker. No restaurar un estado anterior ni sobrescribir cambios ajenos.

Conservar destino como borrador y usar la vista privada real, con imágenes, historia, ocho atractivos cuando corresponda, experiencias y directorio. Verificar ES/EN/PT, enlaces, recursos, recortes, lectura móvil/escritorio y peso de imágenes. La traducción de alt/textos no duplica el archivo de imagen.

Guardar recibo de carga, hashes, capturas e informe de pendientes. El usuario revisa contenido, imágenes y diseño juntos en el sitio antes de aprobar el destino completo. No publicar en producción, mergear ni desplegar por el hecho de importar una prueba.

## 10. Seguimiento y criterio de finalización

El coordinador mantiene un checklist por componente con estos estados distintos: pendiente, investigando, brief preparado, referencia pendiente/elegida, generado, en revisión, corrección, aprobado, integrado en preprod, QA comprobado y aprobación final del destino. Registrar «no aplica» con motivo; no convertirlo en componente completado artificialmente.

Medir tiempo de investigación, producción, espera de revisión, correcciones, integración y cola; tamaño final y reutilización. No sumar espera humana como producción ni declarar progreso porque se envió una instrucción.

Mientras haya ejecución real, comunicar avances comprobables en este chat según lo solicitado; si no hay actividad ni entrega nueva, guardar silencio. El seguimiento automático permanece pausado: este documento no lo reactiva.

Un destino está listo para aprobación final solo cuando los componentes requeridos están integrados y comprobados, con los pendientes relevantes resueltos. Un destino aprobado no equivale a publicado: la publicación requiere autorización explícita de esa versión. Asignar otro destino únicamente según la cola y las autorizaciones vigentes.

## Entrega mínima del worker

- Inventario y checklist actualizado; trabajo pendiente y bloqueos concretos.
- Briefs y fuentes por componente; biblioteca y vínculos de reutilización.
- Prompts, maestros archivados fuera del hosting, derivados y procedencia/hashes.
- Decisiones de referencia, estilo y arte final preservadas.
- Paquete/preprod e informe de QA real; limitaciones explícitas.
- Rama, commit/PR si existe y rutas exactas; no afirmar PR o despliegue inexistentes.

## Instrucción reutilizable para iniciar el worker

> Trabaja únicamente en el destino asignado y su rama. Lee AGENTS.md y crecimiento-colombia/PROCEDIMIENTO_WORKER_IMAGENES.md. Audita primero archivos, fuentes, ilustraciones y aprobaciones existentes. Prepara los briefs desde documentación pública, aplicando docs/GUIA_BIBLIOTECA_ILUSTRADA.md y el skill existente. Selecciona biblioteca por tipo y entorno; nunca al azar. Para portada usa la foto real elegida; no busques nuevas fotos automáticamente. Genera solo el alcance autorizado, optimiza derivados e informa peso/procedencia. Presenta decisiones visuales en el chat y conserva aprobaciones por hash. Continúa componentes independientes mientras otro espera revisión. Integra únicamente en preproducción local autorizada, sin sobrescribir trabajo ajeno ni publicar en producción. Entrega checklist, QA, rama/commit y pendientes reales. No reasignes destinos ni declares terminado lo que no comprobaste.
