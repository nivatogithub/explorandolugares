# Plan maestro de crecimiento de Colombia

Versión 1 · 16 de octubre de 2026 · propuesta operativa local de Explorando Lugares.

Objetivo: lanzar pronto un portal útil y confiable, ampliar por lotes pequeños y atraer visitantes que regresen. Directorio básico gratuito permanente; sin membresía obligatoria para viajeros o comercios. Esta decisión de la solicitud actual reemplaza, para este plan, las hipótesis históricas de suscripción. No modifica precios ni planes del backend.

Para empezar: [ARRANQUE.md](ARRANQUE.md) detalla el estado, los 21 P1 de Antioquia y la primera entrega.

## Cómo usarlo

1. Leer [PLAN_MAESTRO.md](PLAN_MAESTRO.md), [WORKERS.md](WORKERS.md) y [ROADMAP_TECNICO.md](ROADMAP_TECNICO.md).
2. Elegir el siguiente lugar P1 en `inventario.json`; P1 es el MVP rápido, no una lista de todos los destinos populares.
3. Completar su `PROCESO.md` y `lugar.json` en `colombia/departamentos/<departamento>/lugares/<lugar>/` siguiendo [procedimientos/crear-lugar.md](procedimientos/crear-lugar.md).
4. Validar con `python3 crecimiento-colombia/scripts/validar.py`; recalcular con `python3 crecimiento-colombia/scripts/cobertura.py` desde la raíz del repositorio.
5. Entregar PR de un lugar con evidencia; aprobación humana y publicación son pasos separados.

## Archivos de control

- `inventario.json`: 32 departamentos y Bogotá D.C. como unidad especial, candidatos y puntuaciones trazables; catálogo editorial inicial, no censo exhaustivo de municipios.
- `schemas/`: JSON Schema 2020-12 de inventario, lugar y entregas de workers.
- `plantillas/PROCESO.md`: proceso repetible; `plantillas/entrega.json`: contrato vacío de worker.
- `COBERTURA.md`: reporte recalculable del universo versionado; no es porcentaje de territorio, población ni turismo colombiano.
- `FUENTES_OFICIALES_Y_PRIVACIDAD.md`: evidencia consultada y límites de las integraciones.
- `AUDIENCIA_Y_VALOR_PARA_NEGOCIOS.md`: métricas propuestas de visitas, comunidad viajera, países e interacción con comercios; medición todavía no habilitada.
- `REVISION_REPOSITORIO.md`: capacidades observadas y límites de esta tarea.

No se modifican portal, base de datos, configuración, contactos, precios ni contenido histórico. No se generan imágenes ni negocios ficticios. Las carpetas de candidatos no equivalen a páginas públicas. Instagram queda en fase independiente.
