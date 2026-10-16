# Estado operativo vigente — 16/10/2026

**Alcance vigente:** [ALCANCE.md](ALCANCE.md) y `alcance.json` prevalecen sobre las fases posteriores descritas aquí: P4 excluida; P1–P2 como techo operativo; P3 solo referencia futura. Cola de 97 candidatos, 3 diferidos, cero workers iniciados.

Plan auditado en rama `plan/maestro-cobertura-auditado-20261016`, base a75d341. Ver REVISION_TURISTICA_NACIONAL.md y operacion.json. El archivo histórico REVISION_REPOSITORIO.md describe una revisión previa, no esta ejecución.

No iniciar workers automáticamente. Todos los lugares siguen sin iniciar, sin asignación ni aprobación. Nueva clasificación turística nacional propuesta CO-turismo-v3: 100 candidatos de las 33 unidades, con antecedentes conservados. El archivo original de Antioquia sí contiene pilotCategory 1–4; se corrigió la auditoría anterior. Ver ORDEN_TURISTICO.md para cola vigente y fuentes. Preparación excluida del orden turístico.

1. Revisar auditoría/cola y registrar aprobación explícita de prioridades.
2. Autorizar uno o dos lugares concretos; reservar en orden, validar y solo entonces comenzar en ramas independientes.
3. Cada worker completa su lugar y entrega PR, archivos trazables y vista previa privada.
4. Usuario revisa texto/imágenes/vista previa y aprueba versión exacta. Solo entonces ese worker recibe el siguiente lugar sin iniciar.
5. Publicar requiere autorización independiente; este plan no publica.

Validar desde raíz: `python3 crecimiento-colombia/scripts/operacion.py`; pruebas: `python3 crecimiento-colombia/scripts/test_operacion.py`; regenerar métricas: `python3 crecimiento-colombia/scripts/operacion.py --report`.

Validación automática preparada en `.github/workflows/plan-cobertura.yml`: cambios del plan ejecutan validadores, pruebas y comprueban reportes regenerados. No lanza workers ni publica. Su ejecución en GitHub está pendiente de subir la rama; solo las pruebas locales están comprobadas.

Pruebas turísticas: `python3 crecimiento-colombia/scripts/test_turismo.py`. Regenerar orden legible: `python3 crecimiento-colombia/scripts/turismo.py --report`.
