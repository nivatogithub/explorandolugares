> Revisión vigente:19/10/2026,8 lugares y4 experiencias con oferente;13 imágenes. SHA-256 `1783e4d8e4b78000e00de70185c0863fd533bc07439f957982e79aa6bca83169`. El recibo anterior15/8/6 es histórico. Reimportar datos de esta revisión preservando IDs; retirar de la vista actual `sabores-jardinenos` y `recorrido-en-chiva` sin borrar historial.

# Jardín · entrega para integración local

Rama `lugar/CO/antioquia/jardin`. Solo integrar la versión y archivos de esta entrega; no reutilizar semillas históricas de Jardín ni datos del Jardín Botánico de Bogotá.

1. Leer `validacion.json` y verificar SHA-256 del ZIP.
2. `paquete-destino-jardin.zip` usa el formato nativo `schema_version:2`:13 WebP distintos,8 atractivos y4 experiencias, textos ES/EN/PT-BR, historia breve sin imagen,1 motivo factual por idioma requerido por el formulario aunque el diseño oculte el panel.
3. Revisar si Jardín ya existe como borrador antes de importar: actualizar ese registro conservando ID y evitando un duplicado. No publicar por efecto de importar.
4. `paquete-negocios-jardin.zip`:30 fichas de plan gratuito, origen público. Antes de importar, comparar nombres normalizados contra negocios existentes del destino. El importador nativo rechaza duplicados; no eliminar negocios ajenos para forzarlo. Nombres propios iguales en3 idiomas, sin descripciones/imágenes/contactos inferidos.
5. Aplicar las reglas visuales globales vigentes: tarjetas homogéneas Qué conocer/Experiencias,2 filas iniciales con Cargar más, directorio compacto, historia de ancho completo, sin acordeones, sin texto introductorio de secciones. No hay cambios de CSS global en esta rama.
6. Comprobar navegación ES/EN/PT-BR,13 imágenes,8+4 tarjetas,30 fichas, mapas, título/catálogo con la misma portada y tamaños móviles. La presente entrega no afirma esta QA del sitio: la realiza el coordinador al integrar en el entorno compartido.
7. Maestro PNG y alternativas480px no se suben al hosting como parte del ZIP. El paquete completo usa aproximadamente1,70MB de imágenes optimizadas+manifiesto; originales locales separados en `maestros/`.

Todas las imágenes son composiciones ilustradas independientes, con personas ficticias. Procedencia, fuentes, prompts, correcciones y hashes en `procedencia-imagenes.json` y `procedencia-portada-v1.json`. La comparación documental respalda rasgos generales, no un levantamiento arquitectónico ni el estado actual. Aprobación humana y publicación en producción siguen pendientes.
