# Revisión turística nacional — 16/10/2026

**Alcance vigente:** [ALCANCE.md](ALCANCE.md) y `alcance.json` prevalecen sobre las fases posteriores descritas aquí: P4 excluida; P1–P2 como techo operativo; P3 solo referencia futura. Cola de 97 candidatos, 3 diferidos, cero workers iniciados.

Se revisaron las 33 unidades territoriales (32 departamentos y Bogotá D.C.) y se reordenaron **100 candidatos editoriales**, incluidos 22 añadidos a los 78 anteriores. No se inspeccionaron todos los municipios del país ni se afirma un censo completo. El catálogo original y la cola anterior están en historico/CO-editorial-v2/; el portal y su contact-options.json no se modificaron.

## Qué significa este orden

Es una **priorización editorial turística propuesta**, sustentada en la oferta oficial y señales disponibles, no una tabla oficial de los lugares más visitados. MinCIT permite contrastar concentración departamental de extranjeros, pero no obtuvimos una serie homogénea de turismo total para todos los candidatos. No equiparar pasajeros aéreos, extranjeros, RNT o presencia en una guía con visitantes totales. Las posiciones exactas y diferencias de pocos puntos no tienen significación estadística.

Se elimina completamente del orden la facilidad de reutilizar textos, fotos o código. `inventario.score` conserva únicamente preparación. `turismo.json` registra evaluación turística, argumentos, fuentes, límites y categorías anteriores; `operacion.json` fija la cola.

## Método explícito

Cada dimensión se evalúa de 0 a 5 por juicio editorial, según la evidencia disponible:

| Dimensión | Peso | Anclas de valoración |
|---|---:|---|
| Alcance turístico | 40% | 0 sin evidencia; 1 local; 2 emergente/nicho; 3 regional; 4 nacional; 5 referente nacional/internacional. No es conteo de visitas. |
| Producto turístico | 30% | 0 sin producto documentado; 1 incipiente; 2 limitado; 3 producto definido; 4 varios productos/circuito; 5 atractivo distintivo con proyección nacional. |
| Ecosistema turístico | 20% | 0 sin evidencia; 1 incipiente; 2 pequeño; 3 oferta identificada; 4 oferta consolidada; 5 gran diversidad urbana. No sustituye un inventario RNT. |
| Acceso a mercado turístico | 10% | 0 sin información; 1 muy restringido; 2 acceso especializado; 3 conexión regional; 4 corredor consolidado; 5 gran centro emisor/receptor cercano. No garantiza seguridad, rutas abiertas ni tiempos actuales. |

Puntuación = suma(peso × entrada/5). P1 ≥70, P2 ≥45 y <70, P3 <45. Pesos, umbrales y entradas son decisiones editoriales revisables; no resultados de una encuesta. Incertidumbre o fuente solo histórica se marca `editorial_baja`; no se asigna cero por ausencia de datos estadísticos. Los valores medios siguen siendo estimaciones, no verificación factual del destino para publicación.

Departamentos: se clasifican por concentración y consolidación de productos turísticos nacionales/regionales; evaluación cualitativa documentada, no por cantidad de candidatos en el inventario incompleto. P1 reúne unidades con productos de alcance nacional; P2 oferta regional/especializada; P3 desarrollo especializado y evidencia comparativa limitada. No hay cupos: 22 P1, 8 P2 y 3 P3. No se trasladan automáticamente las prioridades departamentales a sus lugares.

Cola estricta por bloques: (departamento P1, lugar P1), (P1,P2), (P1,P3), (P2,P1), (P2,P2), (P2,P3), (P3,P1), (P3,P2), (P3,P3). Dentro de cada bloque: puntuación turística del lugar descendente; desempate editorial declarado en `tie_order`; orden turístico departamental; ID estable. Se permite alternar departamentos del mismo bloque para no hacer todos los pueblos de Antioquia antes de Cartagena. La jerarquía solicitada implica que un lugar P1 de departamento P2 espere a los tres bloques de departamentos P1: se conserva y se hace visible esa consecuencia.

Los empates se resuelven por decisión editorial explícita; por ejemplo Cartagena/Bogotá/Medellín comparten 100 y su orden no afirma que Cartagena reciba más visitantes que Bogotá. El informe MinCIT enlazado como junio contiene datos de julio 2026: en extranjeros no residentes Bogotá está delante de Antioquia y Bolívar. No se usa ese indicador parcial como único criterio vacacional.

## San Jerónimo frente a Támesis

San Jerónimo queda P1 y antes de Támesis, que pasa a P2. La presentación municipal describe un corredor de descanso con oferta de alojamiento y conexión con Medellín; el circuito occidental oficial complementa esa señal. Támesis conserva valor de naturaleza y una oferta local descrita en el Plan 2024–2027; ese mismo plan identifica necesidades de promoción, organización y articulación. No se oculta la evidencia favorable a Támesis ni se afirma que carezca de turismo. La diferencia es una evaluación de consolidación para esta cola, **no una comparación numérica de afluencia**, que sigue pendiente.

## Corrección de la auditoría anterior

Fue incorrecto afirmar que contact-options.json no contenía categorías: `municipalityCatalog` incluye 125 municipios de Antioquia y `pilotCategory` 1–4. `classificationSource` registra que es clasificación interna del propietario del 11/10/2026. Támesis y San Jerónimo eran ambos categoría 1, sin orden relativo explícito. Se conserva copia en historico/clasificacion-antioquia-propietario.json y la categoría anterior en turismo.json. La petición actual autoriza revisar esa clasificación; no significa aprobación humana de cada posición resultante.

## Estado y validación

Ningún worker iniciado, asignado ni publicado. El usuario aún debe revisar esta propuesta antes de ejecutar lugares. Lugar 1/Lugar 2/Lugar 3 y máximo inicial dos se conservan. El validador comprueba cobertura de evaluación, fórmula, fuentes, correspondencia de prioridades y cola, hashes, bloques y evidencia de aprobación. También se prueban San Jerónimo antes de Támesis y la independencia del orden frente a la preparación.

Las carpetas de los 22 nuevos candidatos son plantillas sin contenido editorial completo. Parques/atractivos se vinculan al municipio, sin fingir municipios nuevos: Tatacoa→Villavieja, Parque del Café→Montenegro. Minca→Santa Marta, Palomino→Dibulla, Capurganá→Acandí; no duplicar evidencia o negocios padre/hijo. La prioridad no concede permiso de acceso a áreas protegidas.

El detalle reproducible completo está en ORDEN_TURISTICO.md y turismo.json. Evidencia consultada el 16/10/2026; fuentes promocionales y diagnósticos tienen límites y fechas propios. Próxima mejora estadística: descargar serie municipal común, separar turismo doméstico/extranjero y ocio/negocios, revisar RNT activo y documentar período/cobertura antes de cambiar método. No se fingen esas mediciones en esta versión.
