# Lugar 3 · Medellín · cierre textual sin fotos

Cierre actual: `CIERRE_CONTENIDO_SIN_FOTOS.md/.json`, texto ES/EN/PT y roles revisados sin fotografías nuevas. Se conservan los 8 lugares físicos. Metro/Metrocable es experiencia de movilidad; la auditoría anterior con Metro como puesto 3 queda histórica y requiere nuevo corte. Hay 9 experiencias visibles, 2 antecedentes externos ocultos y 0 eventos actuales verificados. Búsqueda/descarga de fotos detenida; propietario enviará URLs. Lotes V7 no ejecutados y protegidos con salida inmediata. Sin IA/BD/preproducción/producción.

Propuestas separadas V6: Plaza Botero y Metro/Metrocable con ES/EN/PT, cuatro originales completos y derivados inspeccionados. Vista `propuestos-top8-v6.html`; dos opciones principales revisables y dos limitadas colapsadas. No aplicadas a selección física, textos vigentes, preview principal, paquete ni aprobaciones. Licencia del fotógrafo y tratamiento de obras/personas diferenciados; no IA.

Auditoría más reciente: `AUDITORIA_TOP8.md` / `.json`,39 candidatos y31 comparaciones con el8º. Propone sustituir Catedral/Pies por Plaza Botero y Metro/Metrocable; no aplicada, con disputas de corte explícitas. No hay ranking medido de toda la ciudad ni aprobaciones nuevas.

Fecha: 17/10/2026. Rama exclusiva `lugar/CO/antioquia/medellin`. Subtarea de corrección de referencias y traducciones preparada para revisión; destino completo pendiente.

Se retiraron de la revisión vigente la portada con Coltejer diminuto, el bosque genérico de Arví y las viviendas de Comuna 13. Cinco originales completos nuevos fueron descargados por el coordinador e inspeccionados por este worker. `referencias-propuestas-v4.json` conserva autor, fuente, licencia comercial/adaptación, condiciones de IA, encuadre y hashes de originales/derivados.

Tres propuestas aparecen primero en `preview.html`: portada 8 (Coltejer completo reconocible con ciudad, vertical), Arví 1 (estación) y Comuna 13 1 (escaleras eléctricas). Arví 2 (cable pequeño) y Comuna 13 2 (oscura/borrosa) quedan colapsadas como limitadas. Personas y murales visibles exigen decidir el uso concreto antes de transformar. No se seleccionó ninguna referencia ni se generó IA. Los derivados son reducciones técnicas sin recorte ni ampliación, inspeccionadas visualmente.

Identidad y movilidad ES/EN/PT-BR incorporan Metro/Metrocable con fuentes oficiales del operador: J San Javier–La Aurora; L Santo Domingo–Arví. Se mantiene exactamente ocho atractivos, sin novena tarjeta. Las diez actividades (incluidos dos antecedentes ocultos de Jericó) y diez fichas de directorio tienen textos en tres idiomas. Ocho actividades son propuestas visibles: seis autónomas editoriales y dos ofertas documentadas con vigencia pendiente. Cinco tienen referencia de lugar; tres carecen de fotografía específica de actividad/servicio. Una de esas cinco referencias existentes es un detalle parcial de Coltejer, insuficiente para aprobar recorrido o portada. Ninguna foto de lugar prueba un servicio de operador.

`preview.html` muestra la revisión v4, controles por componente/idioma/hash y exportación local. El ZIP/JSON preprod v3 se preserva como antecedente técnico importado local anteriormente: contiene tres referencias ahora rechazadas y no debe confundirse con paquete visual aprobado. No se regeneró ni reimportó esa versión, no se tocó BD/preprod/producción. Los sidecars de historia normalizados por el coordinador se conservaron.

Pendientes: elección/aprobación individual de referencias y uso; revisión de textos e idiomas; tratamiento de derechos adicionales; fotografías autorizadas de servicios; vigencia y datos de negocios/operadores; ilustraciones solo tras autorización y posterior revisión final; nuevo paquete/integración una vez elegidas referencias. QA de navegador móvil/escritorio por coordinador pendiente; la inspección de originales y derivados sí fue realizada. Cobertura nueva aprobada/publicada: cero. PR remoto pendiente (sin `gh`); no se publicó ni tomó otro destino.

Reproducir revisión vigente: `preparar-revision-v4.py`, después `validar.py`, con Python/Pillow del runtime. No ejecutar preparadores V1/V2/V3 para regenerar revisión vigente: son antecedentes y pueden restaurar fotos rechazadas.

## Subtarea independiente V5 preparada para revisión · 17/10/2026

Diez fuentes oficiales reconsultadas y evidencia de oferta pública guardada en `verificacion-vigencia-v5.json`: seis negocios comerciales y cuatro instituciones culturales. No equivale a diez negocios independientes, cupos confirmados o registro vigente. Tres restaurantes del mismo grupo/sede permanecen identificados así. El operador City Tour publica RNT 213192; registro oficial sin resultado verificable en el lector web, no se marcó validado. EPM sigue documentado mediante programa histórico 2024 sin calendario actual comprobado.

Fotos de las cuatro actividades antes faltantes: MAMM fachada 2016, Andrés Molina Mesa CC BY2.0: original completo y reducción800×532 inspeccionados y asignados solo a visita autónoma. Créditos visibles; carteles históricos no se anuncian como agenda. Manifiesto `imagen-actividad-mamm-v5.json`. Avenida Jardín sin licencia comercial/adaptable comprobada; EPM visita guiada y City Tour sin permiso fotográfico específico. No se reemplazan por imágenes genéricas ni fotos de otro operador. Búsquedas y condiciones en `busqueda-fotos-actividades.json`. Coherencia ES/EN/PT y límites en `coherencia-idiomas-v5.json`.

QA IAB local intentado en URL8061: herramienta devuelve `Browser is not available: iab`; inventario sin navegadores. No se abrió Chrome ni reinició servicio. `qa-local-v5.json` registra pendiente preciso de QA móvil/escritorio. Verificación estática sí disponible.

---

<details><summary>Historial de entregas anteriores (puede contener pendientes ya resueltos y referencias rechazadas)</summary>

# Lugar 3 · Medellín

## Entrega vigente V3 para revisión local · 17/10/2026

El ZIP está preparado y pasó el importador PHP real: ocho atractivos y nueve fotografías diferentes por archivo, hash y fuente. Orden: Parque Explora, Pueblito Paisa, Jardín Botánico, Comuna 13, Museo de Antioquia, Arví, Catedral Metropolitana y Pies Descalzos. Los textos están en ES, EN y PT-BR. Se eligieron por relevancia y variedad, no por disponibilidad de imágenes. El inventario amplio de 28 sitios permanece secundario. Coltejer no es una tarjeta.

La portada propuesta 7 es un panorama distante de 2005 de Fabioj, CC BY-SA 3.0. Incluye la ciudad y la silueta pequeña de Coltejer, identificada también mediante la anotación de la fuente. Solo se recuperó la miniatura oficial de 960 × 202: no ampliar; no es una portada definitiva aprobada. La vista de estadio de 2011 y la foto interior del museo de 2010 fueron rechazadas y se conservan como antecedentes.

Las nueve referencias y sus derivados fueron inspeccionados. Cuatro fuentes se recuperaron como miniaturas oficiales (Catedral, Explora, panorama y fachada del museo) porque los originales devolvieron HTTP 429. No se afirma que esos cuatro maestros completos estén descargados. Las URL originales, variantes, autores, licencias y hashes quedan en los manifiestos. Todas las fotografías son históricas y provisionales; no prueban condiciones actuales. Las tarjetas pesan menos de 120 KB y la portada menos de 300 KB, sin recorte ni ampliación.

El archivo opcional `historia-sidecar-preprod.json` propone una foto de Medellín desde Picacho en 2016, distinta de las nueve fotos del ZIP, con relato en tres idiomas. Es contexto del paisaje urbano actual, no representación de la fundación de 1675. Su integración privada está pendiente del coordinador.

`preview.html` presenta portada, historia y los ocho principales; inventario secundario y actividades están separados. Las decisiones locales se pueden exportar por componente e idioma. No hay aprobaciones. Para reproducir esta versión ejecutar `preparar-v3.py` y `validar.py`; los preparadores V1/V2 son antecedentes y no deben sobrescribir V3.

Pendientes: importación serial y comprobación visual en PHP/MySQL local por el coordinador; revisión humana de textos, originales y usos; recuperación de maestros completos; ilustraciones posteriores autorizadas y segunda revisión; profundidad restante y actividades. El worker no tocó la base compartida, contenedores, Chrome ni producción. No generó IA ni publicó. El ZIP válido no significa destino aprobado o terminado.

Los apartados siguientes son antecedentes y pueden contener recuentos superados.

## Revisión V3 en preparación: selección turística de exactamente ocho

Nueva instrucción prevalece: ocho principales elegidos por relevancia y variedad; Coltejer exclusivamente portada distante con ciudad y silueta completa. `seleccion-principal-v3.json` selecciona Explora, Pueblito Paisa, Jardín Botánico, Comuna13, Museo de Antioquia, Arví, Catedral Metropolitana y Pies Descalzos. Vida de El Poblado/centro ampliado y agenda quedan secundarios. Macarena no promete conciertos sin agenda confirmada.

Lote de ocho nuevas referencias originales verificadas documentalmente en `descargas-v3.json`; `descargar-v3.py` debe ejecutarlo el coordinador, no el subagente con permisos de red ocultos. `preparar-v3.py` contiene textos ampliados ES/EN/PT y prepara ZIP con nueve fotos distintas después de comprobar inspección visual. Mientras no existan originales e `inspeccion-visual-v3.json` veraz, **ZIP anterior de dos tarjetas es antecedente, no entrega V3 lista**. No se suplanta la selección principal por fotos disponibles. Red, descargas/inspección y nuevo paquete final siguen pendientes al guardar este avance.

## Avance autorizado para preproducción local

`paquete-preprod.zip` v2 preparado y validado con `DestinationPackage::readZip` real de `../preprod/backend/app/DestinationPackage.php`, usando PHP CLI local8.5.11. Contiene portada reversible Coltejer2006(no aprobada) y **dos tarjetas con imágenes específicas**: Coltejer(detalle arquitectónico2013) y Jardín Botánico(Orquideorama). No se llenan ocho cupos con fotos ajenas. Otros26sitios permanecen en inventario secundario y están excluidos explícitamente del ZIP por faltante visual apto. Actividades separadas no se importan como ofertas.

`reporte-preprod.json` registra hashZIP, archivos, exclusiones y límites. `preparar-preprod.py` reproduce ZIP y validación PHP. Esta autorización permite revisión visual local y correcciones; no es aprobación editorial ni publicación. Coordinador importa serialmente: worker no tocó BD, contenedores, Chrome, fuentes de preprod ni producción. Validación en PHP/MySQL del entorno e importación quedan pendientes del coordinador. No se generó IA final.

## Actualización vigente: revisión ampliada V2

Corrección de presentación: la entrada muestra primero Coltejer2006 (Propuesta4, con límite de resolución), Picacho(Propuesta5) y Orquideorama(Propuesta6). Las propuestas1/2/3 obstructiva o de detalle están colapsadas en «Descartadas/solo referencia, no portada», sin control de elección de portada. Se conservan IDs y numeración originales para no invalidar silenciosamente trazabilidad. Aptitud y limitaciones aparecen antes de cada imagen. Validación comprueba este orden y ausencia del selector de portada descartada.

La revisión ampliada prevalece sobre los recuentos de la primera entrega conservada abajo. Se leyó `crecimiento-colombia/PROCEDIMIENTO_DESTINOS_V2.md` desde la carpeta compartida del coordinador. Se mantienen rama/destino exclusivos, sin generar ilustraciones ni publicar.

- Inventario: **28 sitios municipales propuestos**, cuatro excursiones/candidatos externos u ocultos separados, 24 fuentes primarias. Incluye Coltejer, Pueblito Paisa/Cerro Nutibara, Pies Descalzos, Edificio EPM, Catedral Metropolitana, La Candelaria, Parque Lleras, Parque El Poblado, Avenida Jardín, Plaza Botero, Palacio de la Cultura, parques Bolívar/Berrío, Junín, Provenza, Vía Primavera, Manila, El Hueco, Ciudad del Río, Planetario, Casa de la Memoria, Comuna13, El Castillo y Arví, además de los cuatro iniciales. Hay textos ES/EN/PT-BR en borrador, ubicación descriptiva, motivos y faltantes por ficha. Coordenadas/entradas exactas siguen pendientes: no se inventaron GPS ni garantías de acceso.
- Se omiten alturas/superlativos de Coltejer: las fuentes consultadas presentan discrepancias. Catedral Metropolitana y La Candelaria se distinguen; Pueblito es recreación, Avenida Jardín no es municipio Jardín. Arví exige verificar jurisdicción de cada sendero. Guatapé, Piedra y San Félix se separan como excursiones externas, no atractivos municipales de Medellín.
- **Siete originales y miniaturas técnicas reales** con hashes, licencias y crédito; **seis propuestas visuales numeradas visibles**: cuatro Coltejer y alternativas Picacho/Orquideorama. La Coltejer2007 se rechaza editorialmente para portada por palma obstructiva; 2013/2018 se conservan como detalles arquitectónicos; 2006 muestra silueta reconocible pero resolución640x480 insuficiente para portada grande. Son alternativas evaluadas con limitaciones explícitas, no seis portadas aprobables finales. Se inspeccionaron visualmente los nuevos archivos, sin recorte ni ampliación. No se creó ilustración.
- Diez actividades registradas: seis propuestas editoriales autónomas, dos ofertas documentadas de operador y dos candidatos ocultos. Las fichas de visita editorial al Orquideorama y observación exterior Coltejer muestran fotografía real del espacio, con alcance explícito; no prueba de guía/taller/tour comercial. EPM y Medellín Turística tienen oferta publicada, pero su disponibilidad, vigencia, registros y permisos no se consideran comprobados. Fotos específicas de servicios siguen faltantes porque no se comprobó licencia; búsquedas y motivos en `busqueda-fotos-actividades.json`.
- Se inspeccionaron las experiencias draft ocultas de Jericó (helicóptero/parapente). Enlaces comerciales vacíos y condiciones pendientes: no se copiaron como ofertas activas en Medellín, no se reutilizaron ilustraciones genéricas como fotos reales.
- `preview.html`, servido por el coordinador en `/medellin/`, abre con propuestas numeradas e inventario ampliado; primera entrega colapsada como historial. Incluye controles de revisión por componente/idioma/hash y exportación de decisiones locales. Nada se preselecciona aprobado. EN/PT-BR faltantes de nuevas actividades se indican y no ofrecen aprobación de texto inexistente.
- `revision-componentes.json` mantiene elección de portada, referencia, licencia, textos, ubicación, ilustración e integración independientes y pendientes. Exportar una decisión local no cambia gates ni autoriza generación/publicación. Referencias/textos aprobados deben preceder ilustración; después habrá segunda revisión.
- Profundidad medida en `medicion-profundidad.json`; cobertura aprobada/publicada añadida sigue en cero. No se modificó cola, backend o inventario nacional.

**Pendientes vigentes:** fotografías específicas de casi todos los28 sitios y servicios de operadores; actualizaciones/ubicaciones exactas; traducciones de actividades ampliadas; revisión humana independiente; ilustraciones posteriores autorizadas; segunda revisión; integración plantilla real/ZIP/PR. Destino no terminado ni publicable. La interfaz de revisión conserva borradores locales; falta aceptación de decisiones por coordinador. Reproducir en orden `preparar.py`, `ampliar.py`, `validar.py` con Python del runtime/Pillow.

## Antecedente: primera entrega

Fecha de consulta: 16/10/2026. ID CO-05-medellin. Rama exclusiva `lugar/CO/antioquia/medellin`.

## Preparado y comprobado

- Lectura de AGENTS, contexto, gobernanza, WORKERS y decisión de cuatro workers; aplicado skill existente `.agents/skills/imagenes-destinos/SKILL.md` y procedimiento `crecimiento-colombia/procedimientos/convertir-fotos.md`. No se generó ilustración ni sustituyó fotografía por IA.
- Diez fuentes primarias leídas, con fecha, editor y hechos observados en `fuentes.json`. Redacción propia ES, EN y PT-BR, historia, cuatro atractivos, cuatro propuestas de visita respaldadas por operadores reales e información práctica en `contenido.json` y `experiencias.json`. Sin horarios/precios/eventos futuros inventados.
- Diez fichas gratuitas propuestas: tres alojamientos, tres restaurantes y cuatro instituciones culturales. Las instituciones son servicios culturales, no comercios afiliados. Los tres restaurantes de Elcielo comparten grupo/sede; no son tres alianzas independientes. Todas las fichas conservan fuente y fecha, sin contacto ni reclamación ni vigencia confirmada.
- Tres fotografías reales de Commons: panorámica desde Picacho (Luis90f, 2016, CC BY-SA 4.0), Orquideorama (Jorge Láscar, 2010, CC BY-SA 3.0) y El Poblado (CONOCER, 2009, dominio público). Se revisaron descripción, autor y licencia en páginas originales. Fuentes de texto no conceden licencias de sus imágenes. No se usaron fotos de Alcaldía. Las fotos históricas no prueban condiciones actuales.
- Originales descargados con permiso de red, hashes y derivados WebP reproducibles con Pillow 12.3.0: orientación EXIF, reducción sin ampliar ni recortar, calidad 82, método 6; GPS/EXIF omitidos en derivados. Crédito, licencia y declaración de cambios visibles en `preview.html` y `imagenes.json`.
- Inspección visual de los tres derivados realizada: corresponden a panorámica urbana, estructura del Orquideorama y edificios de El Poblado; sin distorsión por recorte. WebP bajo los presupuestos de 300 KB portada y 120 KB tarjetas.
- Previsualización HTML local privada en tres idiomas y paquete JSON editorial basados en contrato v2, conservando borrador. No se tocó producción, panel, cola, inventario general ni backend.

## Pendientes reales

- Fotografía específica autorizada para Museo de Antioquia, Parque Explora y MAMM e imagen de historia vinculada al relato. No asignar una panorámica como foto de un museo. Se conserva candidato histórico Plaza Botero fuera del paquete hasta revisar también los derechos de las obras representadas.
- El JSON `.borrador.json` no se declara paquete v2 importable final: los atractivos sin imagen no satisfacen aún la entrega visual completa. ZIP final e integración en la plantilla pública/panel pendientes; no se conectó al panel.
- Revisión de traducciones, profundidad editorial, representación de vida nocturna/agenda/comercios/servicios, accesibilidad y vigencia de los negocios. La muestra de diez registros no constituye censo de economía local.
- QA visual de la plantilla final en móvil/escritorio pendiente; el HTML local no prueba integración en el sitio.
- Aprobación humana de textos, imágenes y hash exacto: pendiente. Publicación: no autorizada ni realizada.
- PR remoto pendiente: `gh` no está instalado; no se buscó acceso secreto ni se hizo push. Commit local contiene exclusivamente esta entrega.

**Estado: investigación y borrador preparados, entrega completa del destino aún pendiente.** Lugar 3 mantiene Medellín y no recibe siguiente destino. Cobertura publicada/aprobada añadida: cero. Las duraciones de revisión/correcciones son desconocidas; no se registran ceros ficticios.

Reproducir: ejecutar `preparar.py` con el Python del runtime que incluye Pillow, después `validar.py`. `SHA256SUMS` cubre todo el directorio excepto sí mismo y se regenera después de cambios; no representa aprobación.

## Verificación coordinador 17/10/2026

Avance importado únicamente en preproducción local. Ocho tarjetas, portada e imagen de historia distintas; ES/EN/PT-BR, archivos HTTP y hashes comprobados. Revisión visual en navegador interno: botón Cargar más lugares muestra las ocho tarjetas y sus imágenes decodificadas. Fotografías provisionales, sin aprobación editorial ni producción. Ver reporte-preprod.json y verificacion-preprod-v3.json.

## Nueva política icónica · 17/10/2026

Arví y Comuna 13: referencias actuales reabiertas por ser genéricas; no aptas para imagen final. Coltejer debe distinguirse al tamaño real. Pendiente incorporar Metro/Metrocable en identidad/movilidad ES/EN/PT con fuente del operador. No se sustituyeron fotos ni se importaron textos por esta actualización de políticas.

</details>

## Decisión humana Arví · cable y paisaje

Propietario prefiere cabinas con paisaje del parque. Estación2011 retirada como recomendación principal; no aprobada. Cable2021 conservado contextual por cabinas pequeñas; buscar mejor encuadre verificando LíneaL. No generar hasta elegir referencia. Ver decision-referencia-arvi-cable-paisaje.json.
