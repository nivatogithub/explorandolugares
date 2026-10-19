# Fidelidad visual de destinos: reglas para creación y revisión

Aplica a portadas, atractivos y experiencias de destinos nuevos, borradores y publicados. El estilo semirrealista modifica el acabado; no autoriza modificar geografía, arquitectura o condiciones del lugar.

## 1. Elegir el lugar y el encuadre antes de generar

- Registrar municipio, sector, atractivo y rol exacto. Confirmar identidad mediante fuente local/institucional y fotografía identificable. No asignar una foto titulada solo «Coveñas» a una ensenada concreta sin evidencia adicional.
- Comprobar homónimos y municipio: La Coquerita de Capurganá no acredita La Coquerita de Coveñas.
- Guardar URL de la ficha original, autor, fecha de captura cuando conste, fecha de consulta y permiso/licencia de uso como entrada. Una referencia antigua no prueba estado actual. No rellenar coordenadas o fechas desconocidas.
- Si falta identidad del sector o derecho de uso de la foto como entrada: estado bloqueado. No sustituirla con una descripción general ni reutilizar una imagen rechazada como referencia factual.

## 2. Preparar la ficha de invariantes

Antes del prompt, enumerar rasgos visibles y relaciones espaciales: costa, arena, vías, edificios, alturas, separación entre predios, infraestructura, vegetación, relieve, horizonte y punto de cámara. Precisar qué no se ve y qué sigue incierto.

Portada: justificar por qué el encuadre identifica al destino y no a cualquier playa/pueblo. No repetir la imagen ni el mismo motivo de otra tarjeta como solución de relleno.

Playa: conservar ancho variable, tramos sin arena, agua habitual, erosión/defensas, urbanización y servicios observados. No añadir ni eliminar palmeras por una regla genérica: conservar las que realmente muestre el tramo. No ordenar artificialmente torres, espolones, quioscos o carpas.

Experiencia: conservar entorno real y actividad acreditada; no inventar equipamiento, accesos, servicios, operadores o condiciones de seguridad.

## 3. Generar desde referencia, conservar composición

Usar el skill existente `.agents/skills/imagenes-destinos/SKILL.md` y el skill imagegen incorporado. Para lugares específicos, preferir transformación de una foto cuya adaptación esté permitida: mantener cámara, posiciones, siluetas, distancias relativas y relaciones entre elementos. Cambiar solo el acabado a ilustración editorial semirrealista.

Prohibido usar una lista de ingredientes («edificios, mar, palmeras») como sustituto de composición verificada. No inventar un skyline o monumento ni embellecer convirtiendo el sitio en otro paisaje. Mantener original, prompt íntegro, maestro y derivado web separados, con hashes.

## 4. Puerta de revisión antes de integrar

Comparar original y salida lado a lado, y luego el recorte real de tarjeta/portada. Para cada invariante registrar pasa/falla y evidencia; cualquier fallo de identidad impide integrar. La existencia de un elemento (por ejemplo malecón) no valida el resto de la escena.

No decir «fiel» por plausibilidad. Distinguir: archivo válido, referencia identificada, fidelidad comparada y aprobación humana. La revisión técnica no aprueba una imagen. Registrar aprobación por hash; una revisión nueva invalida solo la aprobación del activo modificado, sin borrar el historial.

Antes de pedir aprobación mostrar imagen, uso exacto y diferencias/limitaciones, con original y fuente disponibles. No generar rondas completas si una prueba de transformación aún no pasó el control de composición.

## 5. Corregir destinos existentes

Inventariar los activos realmente usados por cada destino y página de listado. Registrar referencia, licencia, antigüedad, estado de comparación y aprobación por hash. Los activos sin prueba suficiente quedan pendientes de auditoría, no aprobados por silencio.

Prioridad: observaciones del propietario y errores de identidad; luego portadas; luego atractivos y experiencias. Revisar también traducciones/alt, mapa, duplicados y caché/imagen de tarjeta exterior. Conservar versiones anteriores y evidencia.

Corregir en rama por destino, preparar paquete y revisar en preproducción. No retirar o reemplazar imágenes en producción automáticamente; publicación solo tras aprobación explícita. Una aprobación histórica se conserva como tal y no se presenta como auditoría aprobada con estas reglas nuevas.
