# Estado operativo vigente — 16/10/2026

Plan auditado en rama `plan/maestro-cobertura-auditado-20261016`, base a75d341. Ver AUDITORIA_PRIORIDADES.md y operacion.json. El archivo histórico REVISION_REPOSITORIO.md describe una revisión previa, no esta ejecución.

No iniciar workers automáticamente. Todos los lugares siguen sin iniciar, sin asignación ni aprobación. Prioridades nacionales propuestas pendientes de validar; la fuente citada para Antioquia enumera municipios sin campos de prioridad. Los primeros diez son extraídos del inventario real bajo un orden determinista recién documentado, no una clasificación histórica inventada.

1. Revisar auditoría/cola y registrar aprobación explícita de prioridades.
2. Autorizar uno o dos lugares concretos; reservar en orden, validar y solo entonces comenzar en ramas independientes.
3. Cada worker completa su lugar y entrega PR, archivos trazables y vista previa privada.
4. Usuario revisa texto/imágenes/vista previa y aprueba versión exacta. Solo entonces ese worker recibe el siguiente lugar sin iniciar.
5. Publicar requiere autorización independiente; este plan no publica.

Validar desde raíz: `python3 crecimiento-colombia/scripts/operacion.py`; pruebas: `python3 crecimiento-colombia/scripts/test_operacion.py`; regenerar métricas: `python3 crecimiento-colombia/scripts/operacion.py --report`.
