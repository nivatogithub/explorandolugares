# Lugar 1 · Cartagena · borrador privado

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
- QA responsive en navegador pendiente: Playwright no encontró su navegador instalado. La vista HTML existe, pero no se afirma que haya sido renderizada o comprobada a 390/1440 px.
- Revisión humana de hechos, textos, traducciones, originales/licencias y composición visual. Ninguna aprobación inferida de la autorización de investigar.
- Integración con paquete importador v2 y previsualización PHP del sitio, que requiere revisión de esquema y recursos completos. `borrador.json` declara `importer_compatible=false`; no intentar importarlo como paquete v2.
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

Inspeccionados visualmente los cinco derivados: panorama La Popa, San Felipe, Getsemaní y fachada MUHCA conservan su identidad y proporción sin recorte. Murallas de 2006 rechazadas por desenfoque del original; no se intenta arreglar con generación ni se usa como evidencia visual aprobada. Diez derivados generados, nueve no significa nueve aprobados: la aprobación humana permanece pendiente para todos.
