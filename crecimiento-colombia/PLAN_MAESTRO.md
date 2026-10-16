# Lanzamiento nacional por lotes

## Jerarquía y universo

País `CO` → unidad departamental → lugar. Bogotá D.C. se conserva separada de Cundinamarca y se marca `capital_district`, no departamento. Cada lugar tiene ID estable independiente del slug, tipo `municipality`, `district`, `corregimiento` o `autonomous_destination`, y padre opcional. Cauca Viejo tiene ficha editorial propia y padre Jericó; no se presenta como municipio ni se duplica un negocio en ambas fichas: un negocio conserva un ID y varias relaciones de ubicación/descubrimiento. Un corregimiento puede tener código de centro poblado validado; el destino autónomo conserva código oficial nulo cuando no corresponde. Límites y condición administrativa se verifican antes de publicar.

Inventario v1: catálogo editorial de candidatos en todas las unidades, más los destinos piloto locales. No pretende inventariar todos los municipios. Antes de declarar cobertura administrativa nacional completa, importar una versión oficial DANE, registrar fecha, licencia, hash y conteos; reconciliar distritos, centros poblados y destinos autónomos con una tabla de equivalencias revisada. No borrar candidatos al cambiar denominadores: versionar el inventario.

## Tres prioridades y puntuación

Cada dimensión de 0 a 5 es una estimación interna: reutilización de contenido 35%, facilidad de fuentes 25%, oportunidad editorial 20%, facilidad de permisos 10%, facilidad operativa 10%. Total = suma(peso × dimensión / 5), entre 0 y 100. Facilidad alta significa menor esfuerzo. No son estadísticas de visitantes ni rankings oficiales. Cada fila guarda vector, razón, confianza y fecha. Recalibrar tras dos lugares terminados usando horas y rechazos reales.

- P1: puntuación ≥80. MVP de Antioquia, Quindío y Bolívar; solo Jericó, Jardín, Sonsón, Salento y Cartagena. Primero Jericó; después Jardín y Sonsón por reutilización; Salento y Cartagena prueban expansión fuera del piloto. Si un permiso bloquea, avanzar el siguiente P1, sin reducir el criterio de calidad.
- P2: 55–79. Expansión por corredores y reutilización; incluye Cauca Viejo como prueba de relación padre/hijo. Un lugar puede estar P2 dentro de un departamento P1. No implica prioridad de publicación de todos sus lugares.
- P3: <55. Investigación y preparación posterior, nunca una valoración de importancia cultural. Elevar prioridad al conseguir material autorizado y fuentes útiles.

WIP recomendado: dos lugares en preparación y uno en revisión. Los workers son roles que pueden ejecutarse en serie; no se exige contratar ni conectar agentes para lanzar. Presupuesto y disponibilidad del equipo pendientes. Estimaciones: 1–2 jornadas por lugar reutilizable, 2–4 por lugar nuevo, sin incluir espera de permisos; medir, no prometer fechas.

## Hitos con salida comprobable

| Hito | Dependencias / salida | Responsable propuesto |
|---|---|---|
| H0 · base | Inventario y contratos válidos, responsables editor/revisor asignados, privacidad/contacto reales antes de captar datos | Propietario + técnico |
| H1 · piloto útil | Jericó revisado con fotos reales, 3 atractivos, información práctica, directorio básico trazable y ES/EN/PT-BR; PR revisado y versión aprobada | Editor + revisor |
| H2 · MVP nacional acotado | Los 5 P1 aprobados; publicación autorizada uno a uno, HTTP/medios/SEO y navegación comprobados; medición respetuosa configurada | Técnico + propietario |
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

Publicación comprobada usa el mismo denominador, pero aporta cero salvo estado `published` y evidencia completa. Reportar además cumplimiento del MVP (P1), conteos absolutos, versión del universo y pendientes de verificar; nunca llamar a esta métrica «Colombia completa». Evitar doble conteo de contenido padre/hijo: sus gates necesitan evidencia específica aunque compartan fuentes o negocios. `scripts/cobertura.py` calcula los resultados; `COBERTURA.md` conserva la última ejecución local.
