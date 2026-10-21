# Auditoría de Zipaquirá, Jardín y Coveñas

20-10-2026. **Solo lectura: ningún contenido, paquete, imagen, DB o preproducción cambiado.** Se conservan las aprobaciones humanas finales; no se convierten observaciones históricas en rechazos nuevos.

## Estado comprobado

| Destino | Atractivos / experiencias / imágenes / fichas | Aprobación final coincide con stage | Estado |
|---|---|---|---|
| zipaquira | 7 / 3 / 11 / 30 | Sí, `c3757be91f39` | Aprobado en preprod; producción no comprobada |
| jardin | 8 / 4 / 13 / 30 | Sí, `9ac832185569` | Aprobado en preprod; producción no comprobada |
| covenas | 5 / 5 / 11 / 30 | Sí, `c120c45da512` | Aprobado en preprod; producción no comprobada |

Las tres rutas locales respondieron sin exigir sesión administrativa. Se inspeccionaron los activos actuales empaquetados y el HTML servido; esto no sustituye una prueba DOM ejecutada. Los ZIP y fuentes auditadas conservaron sus hashes. Inventario por componente, método, hash y límites en [hallazgos.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/revisiones/auditoria-2026-10-20/hallazgos.json).

## Hallazgos propuestos, sin aplicación

### ZIP-01 · P1 · La imagen minera no representa la actividad que describe la tarjeta

El ZIP aprobado y HTML local conservan descripción de picar roca salina, pero alt y activo mina-referencia-usuario-v9 muestran un túnel con espejo de agua; el alt declara que no muestra extracción. La oferta del operador sí acredita extracción, no identifica esta foto como esa actividad.

**Propuesta:** Proponer una decisión editorial: conservar la imagen como visita subterránea con texto correspondiente, o conservar la experiencia minera con referencia de esa acción. No aplicar ni retirar la aprobación vigente.

**Referencia que pedir, sin repetir aprobación:** Si se conserva extracción: foto de visitantes realizando la actividad operada, con casco/equipo y socavón identificados, URL/fecha y permiso. No otro túnel escénico.

**Retirada solo propuesta:** Solo propuesta: desacoplar esa imagen de la tarjeta de extracción si el propietario decide que no corresponde; conservar actividad y referencia en expediente.

Evidencia: [paquete-destino-referencia-mina-v9.zip](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/paquete-destino-referencia-mina-v9.zip), [aprobacion-destino.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/aprobacion-destino.json), [criterios-editoriales.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/criterios-editoriales.json).
Fuentes: [Fuente 1](https://www.catedraldesal.gov.co/atracciones/ruta-del-minero/).

### ZIP-02 · P1 · El alt del tren llama fotografía a una ilustración IA actual

El activo servido es tren-referencia-usuario-v8.webp con origen ai_generated; alt ES/EN/PT heredado de V5 dice fotografía histórica del patio de Zipaquirá. El manifiesto V8 dice referencia aportada sin ubicación exacta/fecha documentadas.

**Propuesta:** Proponer corregir únicamente alt/metadatos a ilustración del recorrido ferroviario desde Bogotá, sin atribuir punto exacto ni fecha. No cambiar la imagen aprobada.

**Referencia que pedir, sin repetir aprobación:** No hace falta otra imagen para corregir el alt; para geolocalización, URL original y localización del puente/tramo de la referencia ya elegida.

Evidencia: [TREN_REFERENCIA_USUARIO_V8.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/TREN_REFERENCIA_USUARIO_V8.json), [paquete-destino-referencia-mina-v9.zip](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/paquete-destino-referencia-mina-v9.zip).

### ZIP-03 · P2 · Seis referencias del propietario carecen de ficha original completa

Estación, colegio, Quevedo, arqueológico, tren y mina actuales derivan referencias suministradas por el propietario. La aprobación final coincide con SHA actual. Metadatos mantienen autor/URL/fecha/permiso pendientes; los manifiestos V8 apuntan a rutas temporales de capturas. No se localizó copia original permanente identificada por hash en la entrega.

**Propuesta:** Proponer completar procedencia de las referencias existentes, conservar original durable y hash; registrar por separado aprobación visual e identificación documental. No es un nuevo rechazo ni una conclusión jurídica sobre la captura.

**Referencia que pedir, sin repetir aprobación:** Solicitar las URLs/ficheros originales de esas seis imágenes ya elegidas, autor o fuente y fecha/punto cuando se conozcan; no pedir repetir selección.

Evidencia: [ESTACION_REFERENCIA_USUARIO_V8.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/ESTACION_REFERENCIA_USUARIO_V8.json), [COLEGIO_REFERENCIA_USUARIO_V8.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/COLEGIO_REFERENCIA_USUARIO_V8.json), [QUEVEDO_REFERENCIA_USUARIO_V8.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/QUEVEDO_REFERENCIA_USUARIO_V8.json), [ARQUEOLOGICO_REFERENCIA_USUARIO_V8.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/ARQUEOLOGICO_REFERENCIA_USUARIO_V8.json), [TREN_REFERENCIA_USUARIO_V8.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/TREN_REFERENCIA_USUARIO_V8.json), [INTEGRACION_REFERENCIAS_USUARIO_V8.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/INTEGRACION_REFERENCIAS_USUARIO_V8.json), [aprobacion-destino.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/aprobacion-destino.json).

### JAR-01 · P2 · Las trece ilustraciones están aprobadas como composición, no contrastadas contra una foto exacta

Procedencia declara generación independiente desde descripciones, sin fotografía de entrada, para portada y doce tarjetas. Casa Museo Clara Rojas tiene patio/fuente documentados pero disposición interpretada; La Garrucha interpreta cabina y cañón. No hay evidencia para llamarlas reproducciones exactas ni para afirmar que sean falsas.

**Propuesta:** Conservar aprobación vigente. Proponer distinguir aproximación documentada de geometría fotográfica verificada. Solo si se exige exactitud adicional, comparar los componentes arquitectónicos primero.

**Referencia que pedir, sin repetir aprobación:** Opcional, no requisito para volver a aprobar: patio actual de Casa Museo Clara Rojas mostrando fuente/corredores, y cabina/andén real de La Garrucha en un encuadre identificado.

Evidencia: [procedencia-imagenes.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-jardin/crecimiento-colombia/entregas/jardin/procedencia-imagenes.json), [procedencia-portada-v1.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-jardin/crecimiento-colombia/entregas/jardin/procedencia-portada-v1.json), [aprobacion.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-jardin/crecimiento-colombia/entregas/jardin/aprobacion.json).
Fuentes: [Fuente 1](https://mcrpjardin.blogspot.com/p/historia-de-la-casa-museo-clara-rojas.html), [Fuente 2](https://jardin.antioquia.in/Sitios-Turisticos/la-garrucha).

### JAR-02 · P2 · La oferta de aves tiene evidencia propia pero tarifas publicadas vencidas

Página propia de Montañeros describe guía en La Herrera y reserva, pero marca tarifas válidas hasta31-01-2026. No demuestra cierre ni disponibilidad actual. El expediente ya reconoce ese límite; el sitio no copia esas tarifas.

**Propuesta:** Proponer marcar vigencia comercial por confirmar en registro interno antes de prometer una salida concreta. Conservar la experiencia documentada; no inferir inexistencia.

Evidencia: [criterios-editoriales.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-jardin/crecimiento-colombia/entregas/jardin/criterios-editoriales.json).
Fuentes: [Fuente 1](https://montaneros.co/tours-en-jardin-antioquia/avistamiento-de-aves-la-herrera/).

### COV-01 · P2 · El museo naval usa una colección temática no acreditada como su colección real

Prompt y activo actuales muestran ancla, maqueta naval y objetos en sala inventada como evocación; alt sí la califica temática y no registro del recinto. Fuente Armada acredita referencia histórica al museo, no estas piezas/sala; hoy respondió502. Aprobación de destino permanece vigente.

**Propuesta:** Proponer contraste de identidad del museo si se quiere representar un lugar real en vez de evocación. El texto ya advierte confirmar acceso. No convertir una caída HTTP en cierre del museo.

**Referencia que pedir, sin repetir aprobación:** Exterior identificable del museo o sala/pieza con rótulo verificable, fuente/fecha y condición de acceso; evitar ancla o barco genéricos.

**Retirada solo propuesta:** Solo propuesta, previa decisión: sustituir imagen evocativa por imagen identificada o ficha sin imagen. No retirar el museo ni la aprobación automáticamente.

Evidencia: [procedencia-imagenes.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-covenas/crecimiento-colombia/entregas/covenas/procedencia-imagenes.json), [aprobacion-destino.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-covenas/crecimiento-colombia/entregas/covenas/aprobacion-destino.json), [criterios-editoriales.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-covenas/crecimiento-colombia/entregas/covenas/criterios-editoriales.json).
Fuentes: [Fuente 1](https://www.armada.mil.co/es/content/11-anos-reviviendo-gesta-heroica-los-infantes-marina).

### COV-02 · P2 · Referencias aprobadas de playas no conservan identificación original suficiente del tramo

Primera, Segunda y Coquerita actuales corresponden a referencias directas del propietario y hashes aprobados. Manifiesto declara autor/URL/fecha/permiso no documentados. La portada aprobada tiene maestro/hash pero no se localizó un registro que enlace ese maestro con referencia original; las fotos CC de SajoR constan como pruebas previas y no acreditan por sí mismas el activo actual.

**Propuesta:** Conservar aprobación. Proponer vincular originales y sector/punto de vista a cada hash aprobado, sin reutilizar el rechazo de versiones V2/V3 para las actuales.

**Referencia que pedir, sin repetir aprobación:** URLs originales de las tres playas y portada ya escogidas, con sector/punto de vista y fecha si existe. No pedir cuatro fotografías nuevas ni reabrir su elección.

Evidencia: [referencias-directas-manifiesto.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-covenas/crecimiento-colombia/entregas/covenas/referencias-directas-manifiesto.json), [aprobacion-imagenes-2026-10-19.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-covenas/crecimiento-colombia/entregas/covenas/aprobacion-imagenes-2026-10-19.json), [ATRIBUCIONES.md](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-covenas/crecimiento-colombia/entregas/covenas/referencias-v4/ATRIBUCIONES.md), [aprobacion-destino.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-covenas/crecimiento-colombia/entregas/covenas/aprobacion-destino.json).

### COV-03 · P3 · La exclusión de kayak está desactualizada respecto de una oferta propia encontrada

Criterios excluyen kayak por no haberse comprobado oferente. Web propia de Suplovers hoy enumera alquileres de kayak, junto a rutas de Coveñas. Esto abre un candidato verificable; no demuestra prioridad superior ni obliga a sumar tarjetas.

**Propuesta:** Proponer actualizar únicamente motivo/evidencia del candidato en futura edición y evaluar si añade finalidad distinta; no incorporarlo para llenar una cuota.

Evidencia: [criterios-editoriales.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-covenas/crecimiento-colombia/entregas/covenas/criterios-editoriales.json), [prioridades.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-covenas/crecimiento-colombia/entregas/covenas/prioridades.json).
Fuentes: [Fuente 1](https://suplovers.co/).

### COM-01 · P2 · Estados históricos contradicen la aprobación final actual

Recibos de importación editorial_approved:false y manifiestos por activo pendientes/no integrados sobreviven a aprobación-destino.json cuyo SHA coincide exactamente con stage actual. Lo mismo ocurre en Jardín y Coveñas; criterios de Coveñas conservan rechazos de versiones anteriores.

**Propuesta:** Proponer resolver estado por hash y cronología; archivar notas históricas como tales sin borrarlas. La auditoría toma las tres aprobaciones finales como vigentes, no como pendientes.

Evidencia: [aprobacion-destino.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/aprobacion-destino.json), [INTEGRACION_REFERENCIAS_USUARIO_V8.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/INTEGRACION_REFERENCIAS_USUARIO_V8.json), [checklist.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/entregas/zipaquira/checklist.json).

### COM-02 · P3 · No hay evidencia estadística para llamar el orden una clasificación objetiva de popularidad

Coveñas declara ranking_validated:false; Jardín y Zipaquirá registran selección editorial con motivos y exclusiones. Hay finalidad turística, pero no conteos comparativos. Ninguna cantidad por sí misma demuestra relleno:7/8/5 atractivos y3/4/5 experiencias con ofertas trazadas.

**Propuesta:** Conservar selección aprobada y distinguir prioridad editorial de demanda medida. No reordenar ni eliminar lugares por inferir popularidad sin datos.

Evidencia: [prioridades.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-covenas/crecimiento-colombia/entregas/covenas/prioridades.json), [criterios-editoriales.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-covenas/crecimiento-colombia/entregas/covenas/criterios-editoriales.json).

## Comprobaciones positivas

- Los tres SHA de stage coinciden con sus aprobaciones finales.
- Experiencias con oferta propia: Zipaquirá3, Jardín4, Coveñas5. Existencia documental no promete cupos ni vigencia para fecha concreta.
- Jardín tiene8atractivos con motivos/exclusiones; Coveñas5 conserva excluidos por solapamiento/acceso, Zipaquirá7. No se demuestra relleno por cuotas.
- Cispatá se presenta en San Antero y San Bernardo como excursión regional. Suplovers confirma traslado desde Coveñas a San Antero; agencia de islas diferencia salidas Coveñas/Tolú.
- Tour cafetero, caminata Cueva y urbano de Jardín siguen documentados en fuentes propias consultadas; no se sustituyen por consumo autónomo.

## Límites

- No se inspeccionó DOM ejecutado móvil/desktop: solo HTML servido y activos de ZIP.
- No se consultó producción remota ni se verificó si un despliegue posterior contiene esos SHA.
- No se verificaron cupos, operación real de hoy, acceso militar ni identidad del ejecutor aliado de Montañeros.
- Fotografías históricas, referencias del usuario y aprobación visual no certifican fecha/punto exacto, inventario museístico o geometría completa.
- Caída502 de fuente Armada no prueba cierre; precios vencidos de aves no prueban cese.

No se propone borrar ningún lugar aprobado ni iniciar nuevas imágenes. Las dos incompatibilidades más concretas son el vínculo imagen/texto de minería y el alt del tren de Zipaquirá. Cualquier modificación posterior requiere un encargo separado.
