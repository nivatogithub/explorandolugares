# Jardín · entrega para integración local

Rama `lugar/CO/antioquia/jardin`. Solo integrar la versión y archivos de esta entrega; no reutilizar semillas históricas de Jardín ni datos del Jardín Botánico de Bogotá.

1. Leer `validacion.json` y verificar SHA-256 del ZIP.
2. `paquete-destino-jardin.zip` usa el formato nativo `schema_version:2`:15 WebP distintos,8 atractivos y6 experiencias, textos ES/EN/PT-BR, historia breve sin imagen,1 motivo factual por idioma requerido por el formulario aunque el diseño oculte el panel.
3. Revisar si Jardín ya existe como borrador antes de importar: actualizar ese registro conservando ID y evitando un duplicado. No publicar por efecto de importar.
4. `paquete-negocios-jardin.zip`:30 fichas de plan gratuito, origen público. Antes de importar, comparar nombres normalizados contra negocios existentes del destino. El importador nativo rechaza duplicados; no eliminar negocios ajenos para forzarlo. Nombres propios iguales en3 idiomas, sin descripciones/imágenes/contactos inferidos.
5. Aplicar las reglas visuales globales vigentes: tarjetas homogéneas Qué conocer/Experiencias,2 filas iniciales con Cargar más, directorio compacto, historia de ancho completo, sin acordeones, sin texto introductorio de secciones. No hay cambios de CSS global en esta rama.
6. Comprobar navegación ES/EN/PT-BR,15 imágenes,8+6 tarjetas,30 fichas, mapas, título/catálogo con la misma portada y tamaños móviles. La presente entrega no afirma esta QA del sitio: la realiza el coordinador al integrar en el entorno compartido.
7. Maestro PNG y alternativas480px no se suben al hosting como parte del ZIP. El paquete completo usa aproximadamente1,9MB de imágenes optimizadas+manifiesto; originales locales separados en `maestros/`.

Todas las imágenes son composiciones ilustradas independientes, con personas ficticias. Procedencia, fuentes, prompts, correcciones y hashes en `procedencia-imagenes.json` y `procedencia-portada-v1.json`. La comparación documental respalda rasgos generales, no un levantamiento arquitectónico ni el estado actual. Aprobación humana y publicación en producción siguen pendientes.
