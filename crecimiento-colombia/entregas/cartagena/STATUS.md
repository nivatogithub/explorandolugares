# Lugar 1 · Cartagena · revisión ampliada reabierta

## Estado vigente después de corrección del propietario

Se conservó el commit8330423 y su revisión de41 componentes en `historial/revision-41-componentes.json`. La revisión nueva tiene116 componentes independientes; ninguna decisión previa se aplica automáticamente. Se leyó el procedimiento coordinador `crecimiento-colombia/PROCEDIMIENTO_DESTINOS_V2.md` y el modelo oculto de experiencias Jericó en `content/destinos-dinamicos.json`. No se copiaron actividades Jericó a Cartagena.

- Inventario ampliado de4 a17 sitios, sin máximo editorial artificial. Cinco sitios tienen referencia fotográfica candidata (no aprobada); doce siguen sin foto apta.
- Nueve actividades: dos propuestas editoriales autónomas, cuatro ofertas documentadas en páginas propias de operadores y tres candidatos ocultos. Todas sin alianza, disponibilidad, registro operativo, tarifas o permisos certificados. Ninguna se muestra como oferta pública activa.
- Búsqueda visual específica para bus, buceo/snorkel, catamarán y pesca documentada en `imagenes/propuestas-actividades.json`. Galerías propias sin licencia abierta permanecen no usables y no descargadas. Foto de bus común de2012 rechazada para CitySightseeing.
- Nuevo original Bernard Gagnon, barcos en La Boquilla2020, CC BY-SA4.0: conservado con hash y dos derivados técnicos, inspeccionado. Sirve como contexto local, NO como prueba de pesca ni del servicio IMPULSE. Las nueve actividades siguen con gate fotográfico pendiente.
- Dos opciones de portada numeradas visibles conjuntamente, elección separada del original/uso, licencia y textos por idioma. Originales/textos se aprueban antes de cualquier ilustración; después habrá nueva revisión de ilustración. Cero ilustraciones generadas o aprobadas.
- ZIPv2 actual sigue siendo parcial (tres atractivos con foto apta); no representa todo el inventario ampliado, no se importó al panel y no se publicó.
- QA Chrome390/1440:116 componentes, dos opciones, cero aprobaciones preseleccionadas, sin elección de portada inferida, sin imágenes rotas ni desbordamiento. Capturas conservadas.

Lo que sigue es el antecedente de la entrega inicial y no reemplaza los pendientes ampliados anteriores.


Rama exclusiva: `lugar/CO/bolivar/cartagena`. ID: `CO-13-cartagena`. Investigación web: 16/10/2026. No se ha tomado otro destino.

## Realizado

- Leídos AGENTS.md, contexto independiente, contexto producto, gobernanza, WORKERS y autorización de cuatro workers.
- Aplicados skill existente `.agents/skills/imagenes-destinos/SKILL.md`, guías de imágenes y procedimiento complementario de fotografías. El skill es de ilustración: no se fingió un conversor existente ni se sustituyeron fotos por IA.
- Fuentes oficiales/institucionales consultadas: UNESCO, ProColombia, Alcaldía, MUHCA y gestor de fortificaciones; trazabilidad por componente en JSON.
- Textos originales ES con historia, cuatro atractivos, dos experiencias y orientación práctica; traducciones EN y PT-BR pendientes de revisión editorial humana.
- Diez fichas empresariales/culturales de cuatro categorías para directorio gratuito. Todas son borrador PUBLICO pendiente de verificar, sin reclamación, sin alianza y con operación no confirmada; ningún contacto realizado.
- Cinco originales fotográficos autorizados conservados (cuatro candidatos aceptables, uno rechazado por desenfoque), con licencia comercial/adaptación, hashes, fecha de captura y derivados WebP reproducibles. Créditos y ShareAlike permanecen asociados al activo.
- `preview.html` privada con los tres idiomas. No conectada a producción ni panel.

## Gates todavía pendientes

- Sustituir la fotografía de murallas de 2006: se inspeccionó y rechazó por desenfoque. Se conserva como evidencia, marcada no utilizable y excluida de la previsualización; Centro histórico sigue sin imagen asignada.
- Previsualización PHP del sitio e integración administrativa pendientes. La vista HTML privada sí se comprobó en Chrome a 390/1440 px: sin desbordamiento ni imágenes rotas; capturas y observaciones conservadas.
- Revisión humana de hechos, textos, traducciones, originales/licencias y composición visual. Ninguna aprobación inferida de la autorización de investigar.
- Integración y previsualización PHP del sitio pendientes. Se preparó `cartagena-borrador-v2.zip` con `paquete-destino.json`, portada y tres atractivos con fotografías aptas; el validador real `DestinationPackage::validateManifest/readZip` lo acepta (cuatro imágenes). No se ha importado ni guardado en panel. `borrador.json` sigue siendo investigación extendida incompatible directamente con importador; el ZIP es el paquete preparado.
- Horarios, precios, disponibilidad, rutas aéreas concretas, agenda y accesibilidad detallada no auditados; se omiten y se pide confirmar con el gestor. No se ha certificado la vigencia presencial de negocios.
- PR remoto pendiente: `gh` no está disponible en esta sesión. No se ha hecho push, merge, publicación ni despliegue.

## Hallazgos de control

- ProColombia conserva una lista aérea que menciona VivaAir e Interjet: no se trasladó a contenido vigente. Su recomendación de carruajes tampoco se trasladó.
- Rechazada foto Commons `01 Castillo de San Felipe.jpg`: pertenece a Almería, España, a pesar del nombre coincidente.
- Las fotografías de años anteriores no certifican el estado presente del lugar. Fechas y limitación visibles en manifiesto y vista previa.
- Algunas URLs intentadas fallaron al consultar (Carmen, antiguo dominio de Museo Histórico, Museo Naval, Serrezuela): no se usaron como evidencia de fichas.

## Reproducibilidad y alcance

Ejecutar `preparar.py` con Python y Pillow 12.3.0 para regenerar textos/JSON/vista previa/derivados desde los originales; ejecutar `validar.py` para comprobar referencias, gates y hashes. `SHA256SUMS.json` cubre todos los archivos de esta entrega salvo sí mismo. Toda modificación requiere nuevo manifiesto y nueva revisión.

El validador comprueba integridad técnica; no sustituye inspección geográfica, verificación factual o aprobación del usuario. Publicabilidad y publicación siguen en falso. Lugar 1 conserva Cartagena durante correcciones y revisión.

## Inspección fotográfica del agente

Inspeccionados visualmente los cinco derivados: panorama La Popa, San Felipe, Getsemaní y fachada MUHCA conservan su identidad y proporción sin recorte. Murallas de 2006 rechazadas por desenfoque del original; no se intenta arreglar con generación ni se usa como evidencia visual aprobada. Diez derivados generados: ocho candidatos aptos y dos rechazados. La aprobación humana permanece pendiente para todos.

## Validación comprobada

- `validar.py`: coherencia del destino, tres idiomas, diez fichas, fuentes, hashes, formatos, metadatos y presupuestos WebP.
- Validador real PHP del repositorio: manifiesto v2 y lectura del ZIP aceptados, cuatro imágenes. No se guardó en base de datos.
- Chrome instalado, Playwright con canal chrome: 390/1440 px sin desbordamiento horizontal ni imágenes rotas; tres secciones de idioma presentes. Capturas inspeccionadas visualmente.
- QA visual del agente no equivale a aprobación humana. No se ha creado PR remoto ni publicado.
