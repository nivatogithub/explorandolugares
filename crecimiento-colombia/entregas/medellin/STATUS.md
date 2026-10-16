# Lugar 3 · Medellín · entrega privada de investigación

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
