# Explorando Lugares — instrucciones del repositorio

- Regla estricta: main representa la última versión funcional validada de producción. Integrar main en preprod antes de aprobar; resolver conflictos por función sin ours/theirs global, reset ni force-push; árbol limpio, pruebas y paquete completo revisados; aprobación humana del commit exacto; respaldo/migraciones cuando corresponda; despliegue incremental; validación productiva y paridad completa de hashes; solo entonces fast-forward de main al mismo commit. No declarar paridad por nombres de ramas ni por una comparación parcial.
- Si el propietario propone saltarse el proceso, recordarle brevemente esta regla y el riesgo concreto. Preparar un hotfix mínimo por el mismo circuito. La urgencia o una aprobación anterior no aprueban otro candidato. Un alcance parcial que omite cambios desplegables del candidato bloquea la publicación: separar fuentes o revisar el alcance completo. No prometer riesgo cero.
- Usar `scripts/sincronizar-main-produccion.py` para comprobar la promoción de main: exige candidato publicado, informe funcional ligado al manifiesto y reconstrucción completa idéntica. No hacer cherry-picks selectivos después de publicar ni crear otro commit de código para anotar la publicación; conservar recibos operativos privados. Consultar el proceso actualizado en `docs/REGLAS_OPERATIVAS_DESPLIEGUE.md`.

- Producción recibe tráfico real. Las estadísticas acumuladas son datos protegidos: nunca reiniciar, purgar ni sustituir contadores activos durante despliegues, limpiezas, migraciones o recuperación de código. La recolección normal debe continuar. Solo leer o respaldar; una recuperación de desastre se ensaya en una base distinta y exige conservar/reconciliar todo dato recuperable posterior. En esta entrega de cuentas no se publican cambios de lugares ni imágenes.

- Flujo obligatorio desde el 16 de octubre de 2026: ramas de trabajo → `preprod` → revisión local y aprobación humana → producción desde el commit aprobado de `preprod` → validación de producción → merge de ese mismo commit a `main`. Leer `preprod/README.md` y `docs/PREPRODUCCION.md`. No desplegar directamente desde otras ramas ni integrar lugares no aprobados.

- Este repositorio pertenece al negocio independiente Explorando Lugares.
- Las URL locales y de producción deben tener las mismas rutas desde `/`; solo cambia dominio/puerto. No añadir prefijos `/v1` ni `EL_SITE_BASE` a enlaces o formularios. `ensayos/v1` es una carpeta interna, no una ruta pública. Probar el paquete preparado para hosting según `docs/ENTORNO_LOCAL.md`.
- Leer `PROYECTO_INDEPENDIENTE.md` antes de trabajar. Es el contexto vigente de producto; las instrucciones posteriores del usuario prevalecen.
- Consultar `docs/CONTEXTO_PRODUCTO_Y_HOJA_DE_RUTA.md` para decisiones de negocio, arquitectura conceptual y elementos futuros; distinguir lo implementado de lo propuesto.
- Antes de un despliegue completo, leer `docs/PARIDAD_PRODUCCION.md` y verificar el paquete contra el manifiesto remoto confirmado; no publicar desde fuentes desactualizadas ni retirar archivos por omisión.
- Antes de cambiar presentación pública, leer `docs/GUIA_ESTILO_PORTAL.md`, reutilizar sus tokens y revisar todos los estados de la página; no añadir parches de color aislados.
- No importar contexto, contactos, fuentes, paletas, componentes, textos comerciales ni estilo visual de Paradigma Solutions. Su eventual rol es proveedor tecnológico, no marca turística.
- Tratar `design/pasaporte-local/` como borrador inicial v0.1, no identidad final aprobada. Revisar especialmente la tipografía y ampliar el alcance de categorías.
- `public/`, los ZIP y las exploraciones anteriores son históricos hasta que el usuario apruebe su sustitución. No confundirlos con el portal completo.
- La marca no debe asociarse visualmente a Jericó; Jericó es una página de destino dentro de una arquitectura ampliable.
- Cubrir toda la economía local según el documento de contexto, incluyendo cultura, vida nocturna, agenda y servicios.
- No inventar negocios, testimonios, disponibilidad, precios, eventos, contactos o capacidades de reserva/pago.
- No simular envíos de formularios sin backend. Explicar cualquier alternativa de contacto configurada.
- Conservar créditos y licencias de recursos. Mantener secretos y datos privados fuera del repositorio.
- Para crear o revisar ilustraciones de destinos, seguir `.agents/skills/imagenes-destinos/SKILL.md` y `docs/PRODUCCION_IMAGENES_IA.md`; mantener la procedencia aunque una ilustración no requiera crédito fotográfico público.
- Aplicar `docs/GOBERNANZA_AGENTES.md` a cualquier agente o automatización: propuestas privadas y borradores únicamente; ninguna publicación, contacto comercial, cambio en producción ni despliegue sin aprobación humana explícita.
- Al investigar prospectos locales, incluir solo información empresarial pública con fuente y fecha; marcarlos como pendientes de verificar y no contactarlos automáticamente.
- Mostrar alternativas de diseño de forma visible y numerada cuando se solicite comparar; evitar carruseles que oculten opciones.
- No desplegar ni reemplazar el sitio de producción al guardar un borrador. Mantener las versiones y decisiones documentadas.
- Para pruebas, preparación y publicación, aplicar `docs/REGLAS_OPERATIVAS_DESPLIEGUE.md`: una autorización por tarea concreta cubre los pasos rutinarios de esa tarea; detenerse solo ante los límites de riesgo allí definidos o un bloqueo técnico que exija aprobación.

- Para crear o modificar destinos, leer `crecimiento-colombia/GUIA_GENERACION_DESTINOS.md` y `docs/POLITICA_EXPERIENCIAS_DE_OPERADORES.md`: hasta ocho atractivos sin cuota mínima; experiencias con evidencia interna y texto público neutral. Los oferentes solo aparecen mediante presencia comercial pagada y aprobada. Aplicar también al revisar pendientes y publicados.

## Regla obligatoria de cierre de destinos (20/10/2026)

- No declarar un destino listo por verlo en la base local: el paquete reconstruido desde Git debe incluir el mismo contenido y todas sus imágenes aprobadas, verificadas por SHA-256.
- Una única versión vigente por destino; no mantener selecciones contradictorias en JSON, DB y JavaScript. Cambios compartidos de estilos se integran una sola vez al portal.
- Cada cambio de imagen actualiza procedencia, referencia y manifiesto en la misma entrega. La construcción falla si falta un archivo o no coincide su hash.
- Limpiar medios obsoletos solo tras comprobar referencias de páginas, código, DB activa y borradores; conservar maestros/procedencia fuera del público e historial en Git. No limpiar por omisión ni tocar uploads ajenos o datos vivos.


### Conservación de identidad y analítica al actualizar destinos

- Actualizar registros por identidad estable; conservar IDs y public_id existentes. Nunca recrear destinos, lugares o negocios para actualizar contenido.
- Resolver negocios por public_id, destino y slug; usar nombre normalizado como comprobación adicional. Ante identidad ambigua, detener esa migración sin insertar duplicados.
- No importar IDs locales ni reemplazar la base de producción. No borrar, reiniciar ni sobrescribir visitas, eventos, sesiones, trazas, cuentas o registros usados en reportes.
- Antes del despliegue, validar la migración dos veces en una copia aislada y comprobar que la segunda ejecución no añade registros, y que las tablas protegidas conservan su contenido.
- Comparar analítica antes y después de producción considerando tráfico concurrente: los eventos nuevos son válidos; no exigir igualdad de contadores mientras hay visitas. Conservar evidencias y relaciones históricas.


## SEO obligatorio para cada lugar nuevo o actualizado

- Ningún lugar puede declararse listo para publicar sin completar y validar el SEO de sus páginas públicas en español, inglés y portugués. Esta regla aplica a nuevos lugares y modificaciones de los existentes.
- Título y descripción específicos por lugar e idioma, basados en información verificable y contenido real de la página. No redactarlos interpretando imágenes ni acumular palabras clave de relleno.
- Una URL pública canónica, enlaces internos coherentes, alternates hreflang recíprocos y x-default. Al cambiar una ruta, preparar redirección permanente a la nueva URL, conservar parámetros pertinentes y evitar cadenas o bucles; las redirecciones temporales solo se permiten durante pruebas locales.
- Sitemap actualizado con páginas publicadas, indexables y que respondan 200. Robots y directivas de indexación coherentes; borradores, previews y páginas privadas no se incluyen ni se vuelven indexables.
- HTML accesible al rastreador con encabezados descriptivos, imágenes con texto alternativo fiel y recursos sin enlaces rotos. Si existen datos estructurados, deben corresponder al contenido real; nunca inventar valoraciones, precios o servicios.
- Completar además las vistas previas sociales Open Graph y Twitter: título, descripción, URL absoluta e imagen apropiada con texto alternativo. En un lugar usar su portada aprobada; en páginas generales una imagen de marca pertinente. Las imágenes no son fuente para redactar el contenido SEO.
- Validar el paquete reconstruido y las respuestas HTTP: canonical, idiomas, sitemap, indexación, redirecciones y disponibilidad de imágenes. Distinguir validación de metadatos de una vista previa realmente comprobada en una plataforma externa.
- Conservar IDs e historial de analítica al cambiar URLs; comprobar continuidad de los reportes sin borrar ni reiniciar métricas. Registrar evidencia antes de declarar el cierre.
- Los controles automatizados pertinentes deben pasar antes de publicar. Una comprobación pendiente impide declarar SEO completo; no prometer posiciones ni indexación inmediata en Google.
