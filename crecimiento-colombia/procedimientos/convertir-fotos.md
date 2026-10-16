# Preparar fotografías reales

Procedimiento complementario al skill existente de imágenes, que se centra en ilustraciones; no se encontró un skill dedicado de conversión en el repositorio. No afirmar que ese conversor ya existe. Antes de ejecutar, leer `docs/GUIAS_VISUALES_DESTINOS.md` y las restricciones de `.agents/skills/imagenes-destinos/SKILL.md` sobre procedencia y revisión.

1. Aceptar solo maestro autorizado con manifiesto; validar MIME real, tamaño y dimensiones, rechazar contenido ejecutable o descompresión excesiva. Conservar hash SHA-256 y maestro en almacenamiento autorizado.
2. Ajustar orientación y color sRGB. Eliminar GPS y metadatos personales del derivado, conservar autor/licencia en manifiesto. No borrar procedencia del maestro.
3. Generar WebP con herramienta disponible comprobada (Pillow/ImageMagick por configurar): portada hasta 1600 px de ancho, tarjetas hasta 800 px, sin ampliar original. Calidad inicial 82, reajustar tras inspección. No forzar relación de aspecto sin comprobar recorte en la plantilla real.
4. Presupuesto objetivo: portada 300 KB, tarjeta 120 KB; comparar fidelidad al 100% y en móvil/escritorio. No distorsionar monumentos ni eliminar elementos para embellecer evidencia.
5. Registrar herramienta/versión, comando o parámetros reproducibles, origen, salida, dimensiones, bytes, hashes y transformaciones. Traducir alt descriptivo, no publicitario.
6. Verificar crédito y enlace a licencia en el render final. QA inspecciona encuadre, posición y legibilidad; entregar borrador. La conversión no otorga derechos ni autoriza publicar.
