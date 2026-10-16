# Workers fijos por lugar

Los únicos workers operativos son **Lugar 1**, **Lugar 2**, **Lugar 3**. Cada uno completa UN lugar: investigación, fuentes oficiales y hechos trazables, textos ES e historia, sitios de interés, experiencias verificadas, directorio gratuito con negocios reales, fotos reales autorizadas y originales trazables, derivados, EN/PT-BR, integración y previsualización. Fuentes, fotos, redacción y QA son componentes del trabajo, no workers separados. No hay workers lanzados ni asignados en esta entrega.

Un worker conserva el lugar durante revisión y correcciones. Solo puede recibir el siguiente cuando el anterior esté completo y aprobado por el usuario. Se reserva siempre el primer lugar sin iniciar de la cola global; no saltar bloqueados ni duplicar lugares. Una reserva NO inicia producción. El inicio necesita evidencia de autorización humana del lugar concreto. Rama independiente `lugar/CO/<depto>/<lugar>` y PR exclusivo por entrega; crear la rama al iniciar trabajo autorizado. PR y vista previa son requisitos para revisión, no prueba de aprobación ni despliegue.

Máximo inicial: dos lugares en curso, incluyendo revisión/correcciones. Lugar 3 permanece deshabilitado hasta que tres revisiones consecutivas evidencien revisión ≤24h, revisión no mayor a producción y espera en cola de revisión ≤24h, con aprobación explícita del usuario para aumentar capacidad. Estos umbrales son política propuesta, no métricas observadas. operacion.json registra la evidencia; el validador rechaza aumento sin muestras y aprobación.

## Contrato y aprobación

Completar todos los componentes de operacion.json con evidencia, fecha y revisor; los gates originales de lugar.json siguen siendo obligatorios. Un componente sin evidencia no está completo. Experiencias sin evidencia se ocultan con motivo documentado; no inventar operadores. El directorio gratuito no prueba alianza ni operación; cada ficha conserva categoría, fuente, fecha y dimensiones separadas de verificación/reclamación.

Entregar manifiesto de archivos y SHA-256 que cubra TODO el contenido, imágenes, traducciones y vista previa. El usuario revisa textos, imágenes, licencias y previsualización y aprueba explícitamente ese hash exacto. Cambiar contenido invalida aprobación y devuelve a revisión. Aprobación de entrega no es autorización de publicación: esta requiere una decisión separada de la versión concreta y comprobación posterior. No hay comandos de despliegue ni lanzamiento automático de agentes en este plan.

## Protocolo visual

Aplicar `.agents/skills/imagenes-destinos/SKILL.md` y `docs/PRODUCCION_IMAGENES_IA.md` a ilustraciones y derivados de ilustración; verificar referencias y licencias antes de generar, conservar maestro, parámetros, salida optimizada y revisión humana. Para fotografía aplicar además `procedimientos/convertir-fotos.md`: originales autorizados, hashes, autor, URL original/licencia, permiso comercial y adaptación, créditos y alt ES/EN/PT-BR; registrar herramienta/versión, recorte, tamaño y calidad. No sustituir fotos reales por ilustraciones ni afirmar que existe un conversor especializado. Ver recorte en móvil/escritorio. Ninguna imagen pública se reemplaza sin aprobación.

## Tiempos y cuello de botella

Eventos UTC con fecha real: produccion_inicio/fin, revision_solicitada/inicio/fin, correccion_inicio/fin y aprobacion. Duración producción y correcciones: suma de intervalos; revisión: suma de sesiones; cola: suma solicitud→inicio de revisión por ronda. Aprobación requiere cierre de intervalos. Sin observaciones: null, nunca cero inventado. Reportar muestras y mediana por fase; fase de mayor mediana es candidata a cuello de botella, no diagnóstico causal. Medir también espera total desde entrada a cola hasta asignación y ciclo total por lugar cuando existan fechas. No usar métricas de tráfico como capacidad de revisión.
