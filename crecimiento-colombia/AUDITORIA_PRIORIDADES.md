# Auditoría de prioridades — 16 de octubre de 2026

Base inspeccionada: `a75d341e088ac9734e60f4ca260e673522bd0a1e`. GitHub HEAD/main y HEAD local coinciden. Rama original inspeccionada: main; ramas locales/remotas listadas antes de crear rama aislada. Árbol original con cambios ajenos en backend, docs, scripts y evidencias, preservados.

## Fuente original y límites

`crecimiento-colombia/inventario.json` fue añadido en `4a56b1e`. Contiene prioridades explícitas por departamento y lugar. No existe una clasificación nacional histórica aprobada demostrada por los archivos revisados: las prioridades departamentales y fuera de Antioquia se identifican como propuestas.

`backend/app/contact-options.json`, también inspeccionado en su commit inicial `f002c19`, enumera 21 municipios de Antioquia, pero NO tiene campos P1/P2/P3 ni fórmula turística. El plan previo atribuye esos 21 P1 al propietario el 11/10/2026; esa atribución es documental, no demostrable a partir del JSON de contactos solo. Sonsón P2 se conserva por instrucción registrada en ESTADO_Y_PENDIENTES.md. No se alteraron categorías originales. La referencia a un catálogo histórico de cuatro categorías no aporta un archivo original verificable: no se aplicó ningún mapeo 3/4 → P3.

## Fórmula y orden

Preparación = 35×reuse/5 + 25×sources/5 + 20×editorial/5 + 10×rights/5 + 10×operations/5. Entradas 0–5, resultado 0–100. No determina prioridad ni demanda; valores departamentales uniformes 85/60/35 sugieren estimación editorial, no medición externa.

Orden operativo documentado AHORA: prioridad departamental ascendente, prioridad del lugar ascendente, puntuación departamental descendente, posición departamental original, puntuación del lugar descendente, posición del lugar original. Así se agrupa cada departamento dentro de cada bloque, con desempate estable y sin inventar ranking histórico. Bloques: (1,1),(1,2),(1,3),(2,1),(2,2),(2,3),(3,1),(3,2),(3,3). Cola propuesta bloqueada hasta aprobación de prioridades y autorización de inicio.

## Departamentos

P1 (3): Antioquia, Bolívar, Quindío.
P2 (17): Atlántico, Bogotá D.C., Boyacá, Caldas, Cauca, Cundinamarca, Huila, La Guajira, Magdalena, Meta, Nariño, Risaralda, Santander, Tolima, Valle del Cauca, San Andrés, Providencia y Santa Catalina, Amazonas.
P3 (13): Caquetá, Cesar, Córdoba, Chocó, Norte de Santander, Sucre, Arauca, Casanare, Putumayo, Guainía, Guaviare, Vaupés, Vichada.

Lugares: P1=23, P2=37, P3=18; total 78. Universo editorial CO-editorial-v2: 32 departamentos + Bogotá D.C.; no representa todos los municipios del país.

## Primeros 10 P1 reales del inventario, bajo el orden operativo documentado

| Orden | Lugar | Departamento | Depto/lugar | Preparación | Puntero JSON |
|---|---|---|---|---:|---|
| 1 | Jericó | Antioquia | P1/P1 | 94.0 | `/departments/0/places/0` |
| 2 | Jardín | Antioquia | P1/P1 | 85.0 | `/departments/0/places/1` |
| 3 | Guatapé | Antioquia | P1/P1 | 60.0 | `/departments/0/places/4` |
| 4 | Santa Fe de Antioquia | Antioquia | P1/P1 | 60.0 | `/departments/0/places/5` |
| 5 | Medellín | Antioquia | P1/P1 | 60.0 | `/departments/0/places/6` |
| 6 | El Peñol | Antioquia | P1/P1 | 60.0 | `/departments/0/places/7` |
| 7 | Támesis | Antioquia | P1/P1 | 60.0 | `/departments/0/places/8` |
| 8 | San Jerónimo | Antioquia | P1/P1 | 60.0 | `/departments/0/places/9` |
| 9 | Sopetrán | Antioquia | P1/P1 | 60.0 | `/departments/0/places/10` |
| 10 | San Rafael | Antioquia | P1/P1 | 60.0 | `/departments/0/places/11` |

Los diez están sin iniciar, sin worker, sin PR ni aprobación. Puntuación de preparación no es porcentaje de contenido realizado. Fuente de las filas: inventario.json en la base indicada; operacion.json fija hash y punteros.

## Imágenes y contexto inspeccionado

Leídos AGENTS.md, PROYECTO_INDEPENDIENTE.md, CONTEXTO_PRODUCTO_Y_HOJA_DE_RUTA.md, GOBERNANZA_AGENTES.md, REGLAS_OPERATIVAS_DESPLIEGUE.md, GUIAS_VISUALES_DESTINOS.md, PRODUCCION_IMAGENES_IA.md y .agents/skills/imagenes-destinos/SKILL.md. El skill existente gobierna ilustraciones, maestros, derivados y revisión; no es un conversor fotográfico. Para fotos se complementa con procedimientos/convertir-fotos.md. No se generó imagen ni se ejecutó investigación de un destino.

Las ramas de backend concurrentes no se fusionaron. El acceso remoto comprobado fue de lectura; no se comprobó escritura. No se verificó producción ni vigencia de páginas históricas.
