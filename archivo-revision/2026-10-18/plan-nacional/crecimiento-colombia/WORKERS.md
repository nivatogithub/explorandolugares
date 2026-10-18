# Workers fijos por destino

**Revisión vigente:** [PROCEDIMIENTO_REVISION_CHAT.md](PROCEDIMIENTO_REVISION_CHAT.md). Checklist a cargo del coordinador; imágenes foto a foto en chat; prueba PHP/base de datos únicamente cuando el usuario la solicite.

Procedimiento de investigación: [PROCEDIMIENTO_DESTINOS_V2.md](PROCEDIMIENTO_DESTINOS_V2.md). Autorización del propietario: [cuatro workers](decisiones/2026-10-16-cuatro-workers.md). La categoría 4 se conserva para medición y queda fuera de producción; P1–P2 es el alcance operativo vigente, P3 referencia futura. Inventario y orden: `alcance.json`, `turismo.json` y `operacion.json`.

| Worker | Destino asignado | Rama independiente |
|---|---|---|
| Lugar 1 | Cartagena | lugar/CO/bolivar/cartagena |
| Lugar 2 | Bogotá | lugar/CO/bogota-dc/bogota |
| Lugar 3 | Medellín | lugar/CO/antioquia/medellin |
| Lugar 4 | Santa Marta | lugar/CO/magdalena/santa-marta |

Cada worker conserva un único destino durante investigación, revisión y correcciones. Solo recibe el siguiente tras completar y obtener aprobación del anterior. Reservar el primero sin iniciar de la cola global; no saltar ni duplicar. Una reserva no inicia producción. La autorización vigente permite estos cuatro destinos, no publicación automática ni ilustración antes de revisión de originales/textos.

## Entrega completa

Investigación amplia, fuentes oficiales/locales, textos ES, historia, inventario amplio y selección visible de máximo ocho atractivos principales, con imágenes aptas, experiencias clasificadas y verificadas, directorio gratuito con negocios reales, fotografías autorizadas y originales trazables, textos EN/PT-BR, revisión de referencias, ilustraciones mediante skill después de aprobación, integración y previsualización local. Fuentes, imágenes y QA son componentes del mismo worker. Cada destino se entrega mediante PR exclusivo; rama subida no equivale a PR.

Experiencias: diferenciar propuesta editorial, oferta documentada de operador y candidato oculto. No inferir alianza, disponibilidad, permisos o reservas. Un componente sin evidencia no está completo. Mantener las categorías de economía local del contexto de producto y explicar faltantes.

## Revisiones y versiones

Revisión 1: opciones de portada numeradas y visibles, originales identificados por ubicación final y textos por idioma, licencias y hashes. Revisión 2: ilustraciones generadas con el skill y referencias aprobadas, comparación con original y recortes reales. Revisión de integración por versión. Aprobación final no equivale a autorización de publicación.

Conservar manifiesto de archivos y SHA-256. Cambios invalidan la aprobación del componente afectado. Las selecciones locales son borradores hasta comunicar las decisiones. No marcar gates aceptados ni cobertura completada sin evidencia. No publicar ni contactar negocios automáticamente.

## Imágenes

Aplicar `.agents/skills/imagenes-destinos/SKILL.md`, `docs/PRODUCCION_IMAGENES_IA.md` y `docs/GUIAS_VISUALES_DESTINOS.md`. Para preparar miniaturas técnicas de referencias aplicar `procedimientos/convertir-fotos.md`: originales, autor, URL/licencia, derechos de adaptación y uso, hashes, herramientas, dimensiones, calidad, créditos y alt traducidos. Una miniatura no sustituye la ilustración final ni su aprobación. Conservar originales, maestros y derivados; no presentar ilustraciones como fotos reales.

## Métricas

Registrar eventos UTC de producción, solicitud/inicio/fin de revisión, corrección y aprobación. Producción/corrección: suma de intervalos; revisión: suma de sesiones; cola: solicitud→inicio por ronda. Sin observaciones usar null, nunca cero inventado. Informar muestras y medianas; la mayor fase sugiere cuello de botella, no prueba causa. Medir espera de asignación y ciclo total al contar con fechas.

Separar publicabilidad y profundidad: sitios inventariados/investigados, textos por idioma, originales licenciados/aprobados, ilustraciones generadas/aprobadas, integración y publicación. No aumentar cobertura por añadir candidatos o aprobar una portada.

Las entregas y aprobaciones fluyen independientemente por worker/componente; no hay espera por tanda. El máximo8 corresponde a tarjetas principales, no a candidatos históricos ni a las actividades. Ver actualización del procedimiento.
