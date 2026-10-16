# Lanzamiento nacional por lotes

## Jerarquía y universo

País `CO` → unidad departamental → lugar. Bogotá D.C. se conserva separada de Cundinamarca y se marca `capital_district`, no departamento. Cada lugar tiene ID estable independiente del slug, tipo `municipality`, `district`, `corregimiento` o `autonomous_destination`, y padre opcional. Cauca Viejo tiene ficha editorial propia y padre Jericó; no se presenta como municipio ni se duplica un negocio en ambas fichas: un negocio conserva un ID y varias relaciones de ubicación/descubrimiento. Un corregimiento puede tener código de centro poblado validado; el destino autónomo conserva código oficial nulo cuando no corresponde. Límites y condición administrativa se verifican antes de publicar.

Inventario v1: catálogo editorial de candidatos en todas las unidades, más los destinos piloto locales. No pretende inventariar todos los municipios. Antes de declarar cobertura administrativa nacional completa, importar una versión oficial DANE, registrar fecha, licencia, hash y conteos; reconciliar distritos, centros poblados y destinos autónomos con una tabla de equivalencias revisada. No borrar candidatos al cambiar denominadores: versionar el inventario.

## Tres grupos de prioridad, sin cupos

Departamentos: distribuir todas las unidades en P1, P2 y P3. Destinos: distribuir todos los lugares en P1, P2 y P3, conservando su clasificación propia. Son dos agrupaciones independientes: no son tres departamentos ni tres destinos. No hay un máximo de integrantes por grupo. Los departamentos asignados en v1 son una propuesta provisional que debe validarse; que actualmente haya tres en P1 no define un cupo ni una selección acordada con el propietario.

P1 es el grupo del MVP rápido; P2 es la siguiente expansión; P3 es desarrollo posterior. Un destino P2 puede pertenecer a un departamento P1, como Sonsón en Antioquia. La cantidad trabajada simultáneamente es una decisión operativa distinta de la clasificación: dividir P1 en lotes no baja de prioridad a quienes esperan.

El plan previo atribuye la clasificación de Antioquia al propietario el 11/10/2026 y cita `backend/app/contact-options.json`; la auditoría actual confirma que ese archivo enumera municipios pero no contiene categorías P1/P2/P3. Ver `AUDITORIA_PRIORIDADES.md` para el alcance real de la evidencia. Se restauran sus 21 municipios P1. Sonsón conserva P2. No se reemplaza esa decisión por una estimación del agente. Los destinos nacionales sin clasificación previa siguen siendo propuestas para validar. El catálogo histórico tiene cuatro categorías; no se altera: si se incorpora completo al nuevo plan de tres grupos, categorías históricas 3/4 se mapearán a P3 conservando la categoría original y dejando constancia para revisión.

La puntuación actual mide PREPARACIÓN: reutilización 35%, facilidad de fuentes 25%, oportunidad editorial 20%, facilidad de permisos 10%, facilidad operativa 10%; cada dimensión de 0 a 5. Total = suma(peso × dimensión / 5). Es estimación interna, no demanda turística, y NO determina automáticamente P1/P2/P3. `priority_basis` identifica si la prioridad viene del propietario o es una propuesta editorial. Usar puntuación y bloqueos para ordenar trabajo dentro del grupo, sin cambiarlo. Antes de proponer una clasificación turística nacional definitiva, contrastar importancia turística, diversidad territorial, fuentes y prioridades del propietario; no inventar cifras de afluencia.

WIP máximo inicial: dos lugares en curso, incluyendo revisión y correcciones; tercero únicamente con métricas y aprobación de capacidad. Los workers son roles que pueden ejecutarse en serie; no se exige contratar ni conectar agentes para lanzar. Presupuesto y disponibilidad del equipo pendientes. Estimaciones: 1–2 jornadas por lugar reutilizable, 2–4 por lugar nuevo, sin incluir espera de permisos; medir, no prometer fechas.

## Hitos con salida comprobable

| Hito | Dependencias / salida | Responsable propuesto |
|---|---|---|
| H0 · base | Inventario y contratos válidos, responsables editor/revisor asignados, privacidad/contacto reales antes de captar datos | Propietario + técnico |
| H1 · piloto útil | Jericó revisado con fotos reales, 3 atractivos, información práctica, directorio básico trazable y ES/EN/PT-BR; PR revisado y versión aprobada | Editor + revisor |
| H2 · MVP del grupo P1 | Grupo P1 aprobado y publicado progresivamente, con avances por lotes documentados; publicación autorizada uno a uno, HTTP/medios/SEO y navegación comprobados; medición respetuosa configurada | Técnico + propietario |
| H3 · expansión | Lotes P2 de 5; correcciones atendidas, relación Cauca Viejo/Jericó probada; cuentas según gates del roadmap | Editor + técnico |
| H4 · retención y negocio | Guardados/listas y reclamación seguros, recomendaciones evaluadas; monetización experimental aprobada | Producto |
| H5 · Instagram | Estrategia separada después de contenido y medición; calendario y permisos propios; ningún contacto/envío automático | Por asignar |

H1 y H2 no dependen de OAuth, API DIAN, pagos, recomendaciones personalizadas ni experiencias comerciales. Experiencias sin operador/permiso se ocultan con estado y motivo internos; no se inventan para completar una sección. Una fotografía para historia y tres para atractivos forman parte del mínimo; se admite reutilizar una foto solo si representa ambos contextos y se registra el motivo.

## Calidad mínima para publicar

Todos los gates son obligatorios: `identidad`, `fuentes`, `texto`, `fotos`, `idiomas`, `directorio`, `tecnica`, `privacidad`, `revision`. Cada gate tiene evidencia relativa al repositorio o HTTPS, fecha y revisor. No basta cambiar un booleano.

Identidad: tipo, padre, límites, mapa y nombre sin ambigüedad. Fuentes: al menos dos fiables, una oficial; afirmaciones históricas/operativas ligadas a evidencia; contradicciones resueltas o retiradas. Texto: introducción, historia, cómo llegar y 3 atractivos verificados; no prometer seguridad, disponibilidad ni horarios sin fecha. Fotos: reales y localizadas, portada/historia/atractivos, licencia apta para web comercial, crédito, permiso de transformación y recorte, sin material dudoso. Idiomas: ES aprobado y EN/PT-BR revisados sobre el mismo hash ES, sin inventar matices. Directorio: lote inicial orientativo de 10 fichas en al menos 3 categorías; si no existen suficientes fuentes, documentar investigación y alcance real, sin rellenar. Ninguna insignia de aliado o verificado por mera importación.

Técnica: móvil/escritorio, enlaces y medios sin error, HTML escapado, accesibilidad de teclado/alt/contraste, canonicals/hreflang/sitemap y noindex para borradores. Objetivos locales: portada ≤300 KB, tarjetas ≤120 KB, dimensión explícita y carga diferida salvo portada; medir con dispositivo/red declarados, investigar si LCP >2,5 s. Privacidad: datos personales y documentos fuera de git/web, canal real de corrección y política aplicable. Revisión: persona aprueba hash exacto de entrega. Contenido incompleto permanece en borrador.

## Estados y publicación

`planned → researching → draft → review → approved → published`; `blocked` conserva motivo, responsable y siguiente acción. Cualquier modificación después de aprobar devuelve a `review` y anula la aprobación previa. `approved` exige gates y evidencia de aprobación, `published` además URL pública, commit, evidencia de autorización y comprobación HTTP fechada. PR mergeado no significa desplegado. Registrar fallo y revertir solo versión autorizada; una base de datos necesita plan propio.

## Cobertura ponderada

Lugar preparado = suma de gates aprobados: identidad 5%, fuentes 10%, texto 20%, fotos 20%, idiomas 15%, directorio 10%, técnica 10%, privacidad 5%, revisión 5%. Gate parcial puntúa cero. Peso de lugar: P1=3, P2=2, P3=1. Departamento = media ponderada de sus lugares. Peso departamental P1=3/P2=2/P3=1; país = media ponderada de los departamentos, incluido Bogotá. Una unidad sin lugares vale cero, no desaparece del denominador.

Publicación comprobada usa el mismo denominador, pero aporta cero salvo estado `published` y evidencia completa. Reportar además cumplimiento del grupo P1 (MVP), conteos absolutos, versión del universo y pendientes de verificar; nunca llamar a esta métrica «Colombia completa». Evitar doble conteo de contenido padre/hijo: sus gates necesitan evidencia específica aunque compartan fuentes o negocios. `scripts/cobertura.py` calcula los resultados; `COBERTURA.md` conserva la última ejecución local.

## Control operativo vigente

`operacion.json` fija cola, hash del inventario, componentes, estados y workers Lugar 1/Lugar 2/Lugar 3. `WORKERS.md` reemplaza la división anterior por especialidad. `AUDITORIA_PRIORIDADES.md` documenta la fuente y el orden completo. `scripts/operacion.py` valida y calcula cobertura multinivel, profundidad y tiempos. Toda reserva/inicio requiere evidencia humana; no hay lanzamiento automático.
