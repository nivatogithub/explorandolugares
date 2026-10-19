# Creación de destinos — procedimiento vigente

Actualización del lote Zipaquirá, Santa Fe de Antioquia, Jardín y Coveñas, 19 de octubre de 2026. Aplicar junto a AGENTS.md y los skills de imágenes. Las instrucciones posteriores del propietario prevalecen sobre documentos históricos.

## Un destino por worker y rama

Cada worker conserva su destino y rama `lugar/CO/departamento/destino` hasta terminar preparación, integración y correcciones. El coordinador integra secuencialmente en el ambiente local compartido, sin sobrescribir trabajos ajenos. No reasignar ni empezar otro destino mientras el actual siga pendiente. Una entrega de investigación o un ZIP no significa destino terminado.

## Investigar antes de ilustrar

Consultar primero turismo municipal/departamental, instituciones responsables y portales nacionales. Tripadvisor y portales locales sirven para contrastar importancia y candidatos; no presentar sus listas como un ranking oficial universal. Registrar URL, fecha, afirmación respaldada y dudas. Distinguir sitio, actividad y comercio.

Seleccionar de cero a ocho atractivos según relevancia turística real. Ocho es un techo, nunca una meta ni un mínimo: si solo hay cuatro, cinco o menos candidatos que cumplan, se publican esos y no se sustituyen cupos con relleno. La existencia de ocho tarjetas no acredita calidad ni completitud. Comparar candidatos excluidos: no completar cupos repitiendo sectores de un mismo atractivo ni añadiendo iglesias o museos solo para variar categorías. Declarar límites municipales y excursiones vecinas. Registrar si el orden es editorial, oficial o pendiente; no inventar puntajes del sistema nacional original.

### Criterio obligatorio de inclusión de lugares y experiencias

- **Lugar para conocer:** sitio físico identificable, fuente que acredita identidad/ubicación y un propósito de visita concreto. Puede ser una reserva administrada privadamente, pero comprobar cómo se visita; no confundir existencia con acceso libre, guía incluido o funcionamiento actual.
- **Experiencia:** servicio o actividad organizada con oferente identificable y oferta pública concreta comprobada. Registrar nombre del operador/anfitrión, URL propia de la oferta, fecha y alcance. Una actividad imaginable en el destino no es evidencia de que se ofrezca.
- Una página que solo enumera ideas, una reseña de un viajero o la frase «consulta operadores locales» no basta para acreditar un tour. Tampoco una reserva que admite visitantes acredita automáticamente un recorrido guiado. Verificar expresamente la modalidad: autónoma, acceso administrado, guiada o servicio contratado.
- Caminar por calles, probar comida, ver el paisaje o visitar comercios por cuenta propia son sugerencias autónomas; no presentarlas en la sección de experiencias operadas sin oferta comprobable. Si no existe una sección apropiada, retirarlas de esa sección y conservar la investigación; no inventar un tour para llenar tarjetas.
- Visita a finca cafetera, aviturismo, apicultura/meliponicultura y talleres de oficios son candidatos válidos cuando un productor, reserva u operador realmente los ofrece. Buscar el servicio concreto además de los atractivos famosos. No trasladar una oferta de Jericó a Jardín ni a otro municipio por analogía.
- Una fuente del oferente acredita una oferta documental, no disponibilidad para una fecha, convenio con Explorando Lugares, registro vigente, pago de publicidad ni operador recomendado. Los anuncios/enlaces de planes comerciales siguen sus reglas propias; no simular suscripciones.
- No prometer operador, horario, acceso, seguridad, reserva ni disponibilidad no confirmados. No inventar eventos, contactos, nombres comerciales ni paquetes.
- Guardar `criterios-editoriales.json` como sidecar del paquete: decisión por componente, finalidad, fuentes, fecha y, para experiencias incluidas, oferente y oferta exacta. No añadir campos incompatibles al manifiesto ZIP. Ejecutar el validador editorial además del lector técnico.
- Aplicar este filtro tanto al crear como al modificar destinos existentes. Los anteriores no quedan aprobados por haber pasado una validación de archivos.

## Textos y fichas

Preparar ES, EN y PT-BR antes del ZIP. Títulos directos, una frase breve por atractivo/experiencia e historia breve. El esquema exige motivos por idioma aunque la presentación no muestre un panel adicional. No repetir nombre y título del destino.

El directorio del lote usa 30 establecimientos documentados por destino. Este objetivo del directorio no impone ocho atractivos ni una cantidad mínima de experiencias. Incluir fuente y fecha por ficha. Sitio propio, vitrina empresarial y listado secundario tienen diferente fuerza; ninguna fuente documental demuestra por sí sola operación actual, representación o alianza. Conservar vigencia pendiente y no inventar teléfonos, redes o ubicación exacta. Ficha gratuita compacta: icono de categoría y nombre, sin descripción ni foto.

## Ilustraciones

Usar `.agents/skills/imagenes-destinos/SKILL.md`, `docs/PRODUCCION_IMAGENES_IA.md` y skill imagegen incorporado. Estilo: editorial semirrealista, tinta/gouache visible, personas expresivas, edificios y naturaleza detallados, colores naturales variados. No geometría elemental ni dominante amarilla. No sacrificar identidad por estética.

Portada: un rasgo reconocible del destino con geografía comprobada. Atractivos: foco en el lugar anunciado, diferente archivo/composición de la portada. Experiencias: acción concreta. Una llamada por activo, sin atlas recortado ni repetición arbitraria. Generar composiciones independientes a partir de hechos comprobados; no subir fotografías ajenas sin permiso. Las referencias fotográficas elegidas por el propietario conservan sus condiciones y aprobación específica; la IA no elimina derechos.

Inspeccionar cada salida antes de empaquetar. Corregir torres, remates, cruces, relieves, banderas, caminos, costas o montañas inventados. Si falta evidencia de una fachada/colección exacta, no inventarla: documentar el límite y usar una escena temática identificada como tal para revisión, o solicitar la referencia concreta que falta. No llamar fiel a una escena contextual.

Conservar maestros fuera del ZIP web, prompts, fuentes factuales, versiones rechazadas, hashes, herramienta, dimensiones y aprobación pendiente. Optimizar WebP1200/640; el ZIP solo lleva los archivos efectivamente referenciados. Nunca sustituir imágenes por placeholders para superar una validación.

La autorización actual permite generar borradores independientes y montar estos cuatro destinos en preproducción. No pedir permiso repetido por pasos rutinarios de esa tarea. El resultado generado no hereda aprobación de la referencia ni autoriza producción.

## Presentación compartida

Aplicar el diseño de Cartagena/Jericó: cuerpo neutro, marco lino claro, separadores finos y poco espacio vertical. Historia de ancho completo, sin imagen ni panel lateral. Secciones abiertas, sin acordeones ni párrafos introductorios redundantes. Atractivos y experiencias con las mismas tarjetas y proporción 3:2; títulos/descripciones breves.

Cargar más conserva dos filas de tarjetas con foto y cuatro filas de fichas pequeñas según columnas reales, con filtros y cambio de ancho. Usar controles compartidos; no copiar parches de una ciudad que sustituyen contenidos de otra. Pulsar una ilustración no abre imagen, ampliación ni pestaña nueva.

## Empaquetar, integrar y comprobar

1. Leer manifiestos con DestinationPackage y BusinessPackage reales. Comprobar traducciones, motivos, límites, archivos, MIME, dimensiones y hashes únicos; portada no duplicada en tarjetas. No fijar conteos mínimos de atractivos/experiencias en pruebas. Validar cada experiencia incluida contra su oferente y oferta documental.
2. Validar también DestinationAdminService: un esquema válido no garantiza que el formulario permita guardar/publicar localmente.
3. Importar secuencialmente en MariaDB local, preservando registros existentes y evitando negocios duplicados. Guardar recibo con hashes exactos. No reiniciar estadísticas ni bases ni importar configuración de producción.
4. Montar las rutas reales de preproducción. La visibilidad local sin sesión fue autorizada para revisión de este lote; no supone aprobación editorial ni publicación en hosting. Conservar esa distinción en los recibos.
5. Probar HTTP200 en los tres idiomas, cada imagen referenciada, directorio, ausencia de duplicados y recursos rotos. Revisar navegador integrado, ancho de escritorio y pequeño, historia, tarjetas, filtros, Cargar más y ausencia de enlaces activos a imágenes. Registrar el ancho efectivo, no asumir que coincide con el solicitado al navegador.
6. Actualizar checklist con evidencia real: investigación, textos, imágenes, ZIP, importación, QA y revisión humana son estados separados.
7. Entregar URL utilizable. «Completo para revisión en preproducción» no equivale a aprobado o publicado en producción. El propietario revisa imágenes, textos y diseño antes de autorizar producción.

Mientras haya ejecución, comunicar avances comprobables y bloqueos; no generar reportes vacíos de actividad. Si una decisión bloquea un componente, continuar los demás del mismo destino. El coordinador no cierra la tarea al recibir solo el ZIP de un worker.

## Corrección solicitada al revisar Jardín

El 19 de octubre de 2026 el propietario aclaró que no desea completar ocho lugares a la fuerza ni presentar paseos libres o gastronomía genérica como tours. Jardín se corrige primero; los otros destinos quedan sujetos al mismo filtro durante su revisión. Hasta comprobar una oferta exacta no se añade un tour apícola por analogía con Jericó. Ver `revisiones/2026-10-19-criterios-experiencias.json` para el seguimiento, sin tratarlo como auditoría ya terminada de todos los destinos.

## Neutralidad editorial y aparición comercial de experiencias

Ajuste expreso del propietario: la comprobación de un oferente es **interna**. La tarjeta pública describe la actividad de forma neutral, sin dar publicidad gratuita al negocio investigado.

- Usar nombres genéricos: Tour cafetero, Avistamiento de aves, Cueva del Esplendor, Tour urbano patrimonial. Mantener el nombre de un atractivo físico cuando identifica el lugar; no convertir el título de la experiencia en el nombre de una empresa, finca, familia o producto comercial.
- No trasladar a la tarjeta variantes particulares de un proveedor: «tour corto/largo», paquetes, horarios, tarifas, inclusiones o puntos de recogida. Describir de forma breve qué se hace, sin asumir servicios de un paquete específico.
- Descripciones públicas sin nombre de oferentes, hoteles, familias, fincas comerciales, referencias de investigación, recomendaciones ni atribución promocional. La tarjeta no es una ficha publicitaria gratuita.
- Conservar operador/anfitrión, URL de la oferta, fecha y evidencia en `criterios-editoriales.json`/auditoría interna. No colocarlos en `external_url`, `external_label` o fuentes comerciales públicas del manifiesto editorial para suplir un anuncio.
- La aparición pública de un negocio **dentro de una experiencia** requiere contratación del módulo comercial correspondiente, revisión y autorización. Se utiliza el sistema de operadores anunciados existente; investigar un proveedor no lo convierte en anunciante, aliado ni suscriptor. No inventar relaciones comerciales ni activar anuncios por defecto.
- El listado gratuito del directorio conserva su alcance propio. Esta regla impide publicidad gratuita dentro de las experiencias y no elimina las fichas gratuitas permitidas del directorio.
- Aplicar esta regla al crear sitios y al revisar los pendientes y publicados. Auditar títulos, descripciones, enlaces, llamadas a la acción y campos alternativos de imágenes. No afirmar revisados todos los sitios por haber corregido Jardín.

En Jardín, Los Correa identifica la familia/oferta cafetera y La Herrera el recorrido; no se usan como marcas de las tarjetas genéricas. La evidencia de esas ofertas permanece interna. Sigue vigente el máximo de ocho atractivos sin mínimo ni relleno y la exigencia de oferta real para incluir experiencias.


## Playas y fidelidad del paisaje costero

- No convertir todas las playas en una misma postal virgen de arena amplia y palmeras. La ilustración semirrealista debe conservar el carácter real del sector.
- Antes de generar o aprobar, identificar playa, tramo y punto de vista; documentar fuentes y referencias visuales fechadas. Una descripción promocional genérica no acredita su apariencia.
- Verificar por separado ancho y continuidad de arena (incluidos tramos mínimos o sin playa), erosión, borde con mar, vías, muros/malecón, construcciones y servicios, ocupación, vegetación real, relieve y color habitual del agua. No añadir palmeras, arena, montañas ni eliminar urbanización por estética.
- Comparar playas del mismo destino lado a lado: cada imagen debe mostrar diferencias verificadas; no basta cambiar colores, cámara o distribución de las mismas palmeras. Si no hay evidencia diferenciadora, investigar o dejar pendiente; no inventar rasgos para diferenciarlas.
- Registrar por imagen: sector, fuentes/fecha, rasgos confirmados, incertidumbres, elementos prohibidos, motivo de elección del encuadre y resultado de revisión. La aprobación técnica de un archivo no equivale a fidelidad geográfica ni aprobación editorial.
- Aplicar a destinos nuevos, borradores y revisión de publicados. Ante objeción de identidad, marcar la imagen pendiente de corrección; no declararla lista ni propagarla como ejemplo.

- Coveñas, revisión 2026-10-19: confirmar que existe un malecón no valida su forma ni autoriza imaginarlo. Antes de regenerar costas construidas, identificar referencias visuales del tramo y verificar separación/altura de edificios, espacios entre predios y relación vía-arena-mar. No trasladar fachadas mediterráneas ni comprimir edificaciones por estética.

- Portadas de ciudades costeras: representar su identidad urbana y territorial verificable; una playa genérica no basta. Comprobar tejido construido, separación y altura de edificaciones, infraestructura y relieve. No sustituir la ciudad por un paisaje virgen ni añadir edificios aleatorios para aparentar identidad.

Regla obligatoria de creación y auditoría retroactiva: consultar `docs/REGLAS_FIDELIDAD_VISUAL_DESTINOS.md`. No generar un lugar específico desde ingredientes genéricos ni integrar una salida sin comparación de composición con referencia identificada.

Antes de generar imágenes de sitios específicos, comprobar cartografía/geografía, relieve y Street View o fotografías identificadas, y fijar cámara y relaciones espaciales según `docs/REGLAS_FIDELIDAD_VISUAL_DESTINOS.md`. Aplicar también al auditar destinos publicados.
