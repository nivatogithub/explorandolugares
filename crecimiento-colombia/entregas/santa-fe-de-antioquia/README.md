# Santa Fe de Antioquia · entrega editorial 1

Fecha: 19/10/2026. Worker Lugar 2. Rama `lugar/CO/antioquia/santa-fe-de-antioquia`, base `main` 8ff9dde.

## Estado comprobado

Borrador ES/EN/PT-BR con introducción directa, historia de un párrafo sin imagen, ocho atractivos y cuatro experiencias. Directorio de 30 fichas gratuitas documentadas: 16 hospedajes, ocho comercios de filigrana y seis transportes (ver conteo automático; no confundir listado con operación verificada). Todos quedan en borrador y sin canales inferidos. La guía municipal contiene establecimientos históricos: comprobar vigencia antes de publicación; no venderlos como afiliados ni activos verificados.

`paquete-destino.borrador.json` cumple el validador real de manifiestos v2 de `backend/app/DestinationPackage.php`. **No es un ZIP importable todavía:** faltan los 13 archivos visuales; no se usaron rellenos, fotos antiguas ni aprobaciones heredadas. `directorio-preprod-30.json` es el paquete separado del importador de negocios vigente, no un campo improvisado del manifiesto v2.

No se importó en MariaDB, no se modificó preprod ni se desplegó. No hay imágenes generadas o aprobadas. La revisión visual y la aprobación integral están pendientes.

## Auditoría del contenido previo

`content/destinos-piloto.json` contenía Centro histórico, La Chinca y Puente de Occidente, solo ES/EN, y cuatro fotos antiguas en `content/destinos-fotografias.json`. Constituyen antecedentes; no son la entrega nueva ni aprobaciones. La historia previa era demasiado abreviada para explicar la unión de las poblaciones. La nueva evita reducir la cronología a una fundación única del emplazamiento actual en 1541.

## Decisiones de selección

Ver `PRIORIDADES.json`: orden editorial, no clasificación oficial ni afluencia medida. El núcleo patrimonial supera alternativas comerciales sin operación/relevancia contrastada. Plaza y catedral son roles distintos: plaza como espacio urbano, templo como edificio/interior; no repetir encuadres ni archivo. No sumar una novena ficha llamada Centro histórico que duplique ese conjunto. El octavo (Jesús Nazareno) tiene confianza media y necesita comparación final; no declarar definitivo el corte solo por completar ocho.

El Puente de Occidente une Santa Fe de Antioquia y **Olaya**; su inclusión como excursión no convierte Olaya ni Sopetrán en barrios de este municipio. Festivales y Semana Santa son agenda: no se publica una fecha o disponibilidad futura por aparecer en una revista de 2025. Experiencias son ideas para hacer, no operadores contratados ni reservas garantizadas.

## Encargo visual siguiente

Seguir `.agents/skills/imagenes-destinos/SKILL.md` y la guía archivada vigente, con tinta/gouache semirrealista. Propuesta de portada: calle del centro con escala baja, fachadas mayormente blancas, aleros de teja y puertas de madera; contexto propio de la ciudad cálida del Occidente antioqueño. Antes de generar cotejar una vista completa del tramo, perspectiva, pavimento y edificios; no dibujar una iglesia genérica ni insertar el puente en el casco urbano. No se genera aún porque el mapa municipal y el texto turístico no bastan para verificar una fachada concreta.

Las fuentes factuales no son licencias fotográficas. No se descargaron nuevas fotos de referencia ni se usaron fotografías restringidas como entrada. Conservar diferencia entre composición independiente, revisión de fidelidad, aprobación de imagen y autorización final.

## Pruebas

Ejecutar `python3 crecimiento-colombia/entregas/santa-fe-de-antioquia/validar.py`: exige traducciones, 8+4 componentes, 30 nombres únicos, fuentes y estado draft, roles visuales diferentes, y registra los faltantes. `VALIDACION.json` conserva hashes. Validador PHP de manifiesto también pasó; no se ensayó importación ZIP ni HTTP porque faltan medios.

## Integración futura

UI vigente neutral y compacta; historia fullwidth sin imagen, sin subtítulos de sección ni acordeones, tarjetas de atractivos/experiencias iguales y Cargar más según filas. Directorio gratuito solo nombre/icono; no fotos ni descripciones. Integrar únicamente desde esta rama hacia preprod mediante el coordinador y conservar revisión humana. Nada de producción sin autorización explícita.
