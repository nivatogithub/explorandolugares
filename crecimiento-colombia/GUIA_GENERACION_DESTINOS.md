# Creación de destinos — procedimiento vigente

Actualización del lote Zipaquirá, Santa Fe de Antioquia, Jardín y Coveñas, 19 de octubre de 2026. Aplicar junto a AGENTS.md y los skills de imágenes. Las instrucciones posteriores del propietario prevalecen sobre documentos históricos.

## Un destino por worker y rama

Cada worker conserva su destino y rama `lugar/CO/departamento/destino` hasta terminar preparación, integración y correcciones. El coordinador integra secuencialmente en el ambiente local compartido, sin sobrescribir trabajos ajenos. No reasignar ni empezar otro destino mientras el actual siga pendiente. Una entrega de investigación o un ZIP no significa destino terminado.

## Investigar antes de ilustrar

Consultar primero turismo municipal/departamental, instituciones responsables y portales nacionales. Tripadvisor y portales locales sirven para contrastar importancia y candidatos; no presentar sus listas como un ranking oficial universal. Registrar URL, fecha, afirmación respaldada y dudas. Distinguir sitio, actividad y comercio.

Seleccionar hasta ocho atractivos según relevancia turística real. Comparar candidatos excluidos: no completar cupos repitiendo sectores de un mismo atractivo ni añadiendo iglesias o museos solo para variar categorías. Declarar límites municipales y excursiones vecinas. Registrar si el orden es editorial, oficial o pendiente; no inventar puntajes del sistema nacional original.

Cada experiencia debe describir una acción concreta y no prometer operador, horario, acceso, seguridad, reserva ni disponibilidad no confirmados. No inventar eventos o contactos.

## Textos y fichas

Preparar ES, EN y PT-BR antes del ZIP. Títulos directos, una frase breve por atractivo/experiencia e historia breve. El esquema exige motivos por idioma aunque la presentación no muestre un panel adicional. No repetir nombre y título del destino.

El directorio del lote usa 30 establecimientos documentados por destino. Incluir fuente y fecha por ficha. Sitio propio, vitrina empresarial y listado secundario tienen diferente fuerza; ninguna fuente documental demuestra por sí sola operación actual, representación o alianza. Conservar vigencia pendiente y no inventar teléfonos, redes o ubicación exacta. Ficha gratuita compacta: icono de categoría y nombre, sin descripción ni foto.

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

1. Leer manifiestos con DestinationPackage y BusinessPackage reales. Comprobar traducciones, motivos, límites, archivos, MIME, dimensiones y hashes únicos; portada no duplicada en tarjetas.
2. Validar también DestinationAdminService: un esquema válido no garantiza que el formulario permita guardar/publicar localmente.
3. Importar secuencialmente en MariaDB local, preservando registros existentes y evitando negocios duplicados. Guardar recibo con hashes exactos. No reiniciar estadísticas ni bases ni importar configuración de producción.
4. Montar las rutas reales de preproducción. La visibilidad local sin sesión fue autorizada para revisión de este lote; no supone aprobación editorial ni publicación en hosting. Conservar esa distinción en los recibos.
5. Probar HTTP200 en los tres idiomas, cada imagen referenciada, directorio, ausencia de duplicados y recursos rotos. Revisar navegador integrado, ancho de escritorio y pequeño, historia, tarjetas, filtros, Cargar más y ausencia de enlaces activos a imágenes. Registrar el ancho efectivo, no asumir que coincide con el solicitado al navegador.
6. Actualizar checklist con evidencia real: investigación, textos, imágenes, ZIP, importación, QA y revisión humana son estados separados.
7. Entregar URL utilizable. «Completo para revisión en preproducción» no equivale a aprobado o publicado en producción. El propietario revisa imágenes, textos y diseño antes de autorizar producción.

Mientras haya ejecución, comunicar avances comprobables y bloqueos; no generar reportes vacíos de actividad. Si una decisión bloquea un componente, continuar los demás del mismo destino. El coordinador no cierra la tarea al recibir solo el ZIP de un worker.
