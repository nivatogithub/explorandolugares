# Cierre de contenido sin fotografías · Santa Marta

**QA de borradores completada; destino aún no terminado ni aprobado.** Rama `lugar/CO/magdalena/santa-marta`. No se modificaron fotografías, decisiones, paquete, base de datos, preprod ni producción.

- **9 experiencias, 11 fichas de directorio y 3 borradores geográficos** en ES/EN/PT, con fuente y relaciones verificadas.
- **29 comprobaciones automáticas PASS**: idiomas, campos completos, IDs únicos, fuentes, relaciones, tipología y ausencia de precios/horarios/eventos inventados.
- **108 archivos fotográficos/de decisiones intactos por hash**, además de campos de aprobación conservados respecto al commit anterior.

## Correcciones realizadas

Playa Blanca como lugar describe la playa y su entorno; la excursión en lancha aparece solo como experiencia. Minca tiene ficha de corregimiento, separada de la salida de naturaleza/café. Teyuna tiene ficha de sitio arqueológico, separada del trekking de varios días. La visita al Centro de Vida Marina es experiencia; el establecimiento queda en directorio y es distinto de Mundo Marino. Se conserva el nombre propio en inglés para facilitar su identificación.

La moto acuática tiene borrador ES/EN/PT, sin prestador ni zona concretos inventados y sin oferta reservable. Las actividades no aumentan el conteo de lugares. No se creó agenda: las salidas a Minca son tours, no eventos con fecha.

La auditoría vigente mantiene **7 lugares propuestos y el octavo pendiente**, con motivos específicos de comparación y fuentes. Se eliminaron frases residuales que presentaban Playa Blanca como octavo resuelto o insinuaban aprobaciones de fotos. No se rellenan ocho por cuotas. Palomino queda diferenciado de Santa Marta con fuente municipal de Dibulla.

## Límites reales de los borradores

La guía institucional respalda el concepto de moto acuática, pero no demuestra un prestador actual ni una zona autorizada concreta. La excursión Playa Blanca no tiene operador asignado. Los directorios conservan estado de borrador gratuito no reclamado y no acreditan alianzas, cupos u operación vigente. Estos límites son de verificación factual: una aprobación editorial no los sustituye.

## Decisiones humanas pendientes

1. Resolver el corte del octavo lugar y revisar el orden de los siete propuestos.
2. Revisar textos ES/EN/PT y enviar correcciones o aprobación editorial.
3. Enviar URLs elegidas por componente; no es necesario esperar un paquete completo. Portada H7-03 y Tayrona conservan su estado pendiente. No se reanudará búsqueda automática de fotos.
4. Más adelante, aprobar imágenes finales y revisión del destino integrado antes de publicar.

Las URLs pendientes no impidieron terminar esta QA textual. No se solicita permiso para las verificaciones ya autorizadas; este cierre documenta el resultado concreto y sus decisiones pendientes.

## Archivos para revisión

- `experiencias-es.md`, `experiencias-en.md`, `experiencias-pt-br.md`: 9 actividades.
- `directorio-es.md`, `directorio-en.md`, `directorio-pt-br.md`: 11 fichas.
- `lugares-ampliados-es.md`, `lugares-ampliados-en.md`, `lugares-ampliados-pt-br.md`: Teyuna, Playa Blanca y Minca.
- `AUDITORIA_TOP8.md` y `CLASIFICACION_LUGARES_EXPERIENCIAS.json`: prioridad, tipología y cobertura sin dobleconteo.
- `QA_CONTENIDO_SIN_FOTOS.json` y `validar-contenido-sin-fotos.py`: verificaciones reproducibles sin red ni procesamiento fotográfico.
