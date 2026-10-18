# Procedimiento de investigación de destinos — revisión ampliada

**Flujo de revisión vigente:** `PROCEDIMIENTO_REVISION_CHAT.md` prevalece sobre casillas/exportaciones, aprobación global anticipada y arranque de pruebas descritos aquí. Coordinador mantiene checklist; imágenes una a una en chat; PHP/base de datos solo a solicitud explícita del usuario.

Decisión del propietario, 16/10/2026. Prevalece sobre límites visuales antiguos de tres/cuatro atractivos, generación previa a aprobación y documentos anteriores de capacidad. Cuatro workers autorizados, uno por destino y rama; no reasignar antes de aprobación del anterior. Esta corrección no autoriza publicar ni generar ilustraciones antes de aprobar las referencias.

## Investigación y profundidad

El inventario de investigación no tiene máximo; la selección principal visible sí tiene techo de ocho atractivos según la actualización del propietario al final de este documento. Una fila de tarjetas es una decisión de diseño, no un inventario completo. Explorar patrimonio y arquitectura, museos, parques y espacios públicos, barrios, cultura, naturaleza, gastronomía, vida nocturna, compras/oficios y actividades con operadores. No imponer categorías inexistentes ni llenar cuotas con sitios irrelevantes.

Mantener inventario amplio con nombre, categoría, ubicación/municipio real, fuentes y fecha, inclusión/exclusión y motivo, estado de texto, fotografía y traducciones. Distinguir destino urbano de excursiones cercanas; no atribuir al municipio sitios de otra jurisdicción. Un faltante queda visible en revisión, nunca se resuelve omitiendo silenciosamente la ficha.

Separar tres clases de experiencias:

- Propuesta editorial autónoma: paseo o visita informativa; no promete operador ni reserva.
- Oferta documentada de operador: URL propia y fecha, actividad y ubicación confirmadas, canales públicos. Declarar aparte vigencia, registro cuando corresponda, alianza, disponibilidad, tarifas y permisos; una página pública no los prueba todos.
- Candidato por validar: borrador oculto para el público; motivo de bloqueo explícito. No mostrar como oferta activa ni como aliado.

Referencia existente: `content/destinos-dinamicos.json`, experiencias draft de Jericó helicóptero Medellín–Jericó, parapente Puerto Arturo y finca cafetera; tienen condiciones y enlaces comerciales pendientes. No copiar su actividad a otro destino por analogía.

## Revisión 1: referencias y textos

Presentar varias portadas numeradas, visibles juntas, con motivo de representatividad, encuadre móvil/escritorio, fecha de fotografía, autor, licencia y original trazable. Rechazar encuadres que obstruyan el atractivo. La foto de Monserrate con cables no es candidata aprobada para portada; conservar el antecedente de rechazo sin borrar la fuente.

Cada foto identifica su destino final: portada, historia, ficha concreta de lugar o ficha concreta de experiencia. Cada texto se aprueba por componente e idioma ES/EN/PT-BR. Separar elección de portada, aprobación de referencia fotográfica, texto y aprobación final. El agente documenta y verifica licencia/adaptación; no trasladar al usuario una casilla administrativa de licencia como bloqueo rutinario. Mostrar faltantes y si el componente está integrado o solo investigado.

Decisiones humanas vinculadas al ID y hash de contenido. Nada preseleccionado como aprobado. Las decisiones locales son borradores hasta comunicarlas al coordinador. Toda modificación relevante invalida la aprobación del componente afectado. No solicitar una aprobación global ambigua de galería.

## Producción de imágenes después de revisión 1

Solo con aprobación explícita de originales/usos y textos correspondientes, aplicar `.agents/skills/imagenes-destinos/SKILL.md`, `docs/PRODUCCION_IMAGENES_IA.md` y guías visuales. Crear ilustración editorial claramente ilustrada, fiel al atractivo; no una supuesta fotografía retocada. Preservar originales, autores, permisos de adaptación, fuentes factuales, prompt, maestro y derivados optimizados con hashes.

Una miniatura WebP técnica para revisar una referencia no equivale a la ilustración final ni sustituye este paso. No introducir gente, servicios o arquitectura no verificados. No reutilizar fotografía de otro sitio para cubrir un faltante.

## Revisión 2 e integración

Mostrar original aprobado junto a ilustración propuesta en el componente y recorte reales. Aprobar fidelidad, acabado, traducciones y visualización local. La aprobación del original no aprueba automáticamente la ilustración. Mantener borradores y registrar pendientes de integración/PR. Publicación requiere autorización separada de versión exacta.

## Medición y finalización

Registrar total de sitios identificados, investigados, con textos verificados por idioma, referencias visuales autorizadas, referencias aprobadas, ilustraciones generadas/aprobadas, integración y publicación. Reportar actividades por las tres clases anteriores. Publicabilidad y profundidad son dimensiones distintas: una portada aprobada no completa un destino amplio. Los nuevos indicadores no cambian automáticamente el denominador nacional ni marcan cobertura como completada.

Las revisiones son independientes por worker y componente. No esperar a que termine una tanda: cada worker entrega, recibe correcciones y continúa los componentes aprobados según el alcance autorizado. Solo la reasignación de ese worker exige completar y aprobar su destino. El exportado de Cartagena recibido el16/10/2026 contiene decisiones parciales; no es aprobación de destino ni publicación.

## Entorno de revisión autorizado

Usar servidor local y navegador interno de la aplicación. No usar Chrome, tampoco headless, para pruebas de estos workers. Una autorización de consultas web o descargas no modifica esta preferencia.

## Selección principal: máximo ocho atractivos

Instrucción del propietario al revisar Cartagena: presentar los atractivos MÁS importantes, máximo8, con variedad representativa del destino. Prevalece sobre el anterior listado visible sin límite. El inventario de investigación puede ser amplio y se conserva para trazabilidad; no se muestra entero como selección principal ni obliga al usuario a aprobar decenas de fichas secundarias.

Priorizar relevancia turística respaldada por fuentes, identidad territorial, complementariedad de experiencias y variedad. No es un ranking estadístico medido ni se inventan cifras. Agrupar paradas de un mismo recorrido cuando corresponda, explicando su identidad; evitar múltiples iglesias/museos que desplacen playas, islas, naturaleza o barrios esenciales. La ausencia de foto obliga a conseguir referencia adecuada del atractivo prioritario, no a sustituirlo por otro menos importante con una imagen fácil.

Cada tarjeta principal presentada como lista para revisión debe tener referencia visual apta, local y trazable, nombre/texto y ubicación. Sin imagen: trabajo pendiente fuera de tarjetas listas, con bloqueo visible en seguimiento. Máximo8 es techo, no cuota a rellenar. No repetir la imagen principal como foto de tarjeta de otro uso; buscar encuadre específico para el atractivo. Actividades y directorio son componentes separados, no suman tarjetas de atractivos ni rellenan el límite.

El agente verifica licencias y permisos de adaptación; no requiere aprobación administrativa repetida del usuario si la evidencia ya los permite. Crear IA a partir de una fotografía no elimina derechos de referencia. El usuario elige y aprueba referencia/uso y textos; luego revisa ilustración nueva. Conservar aprobaciones previas porhash; mover una ficha a secundarias no borra su aprobación, ni la extiende a una ficha combinada/modificada.

Cartagena: evaluar explícitamente Islas del Rosario y Barú/Playa Blanca; diferenciar archipiélago, playa e interior urbano. Centro y San Diego son barrios/sectores del conjunto histórico: explicarlo en lenguaje claro. Sustituir foto Getsemaní por mejor referencia. No iniciar ilustraciones de Cartagena antes de resolver la selección que el propietario pidió revisar primero.

## Corrección del 17/10/2026: ciudades con ocho principales

Aplicar `GUIA_GENERACION_DESTINOS.md`: Cartagena, Bogotá, Medellín y Santa Marta requieren ocho atractivos principales por su oferta amplia. Portada identificadora no obliga a crear una tarjeta del símbolo: Coltejer queda solo como portada candidata, visto con ciudad. Separar originales de portada, historia y cada atractivo; no repetir mediante recortes. Mantener secundarios e inventario para medición. Preproducción local ya autorizada para estos avances.
