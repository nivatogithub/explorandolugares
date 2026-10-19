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

## Vistas de calle y panorámicas

Registrar punto/URL, fecha de captura (separada de fecha de publicación), proveedor y tramo observado. Distinguir recorrido Google Street View de foto360deun colaborador. Comprobar fechas disponibles; una captura antigua no acredita infraestructura actual ni permite negar obras posteriores. No generalizar un tramo a toda la playa. Usar para contraste factual; comprobar permiso antes de adaptar o subir capturas a un generador.

## Cartografía, relieve y vistas de calle antes de generar

Regla obligatoria para crear o corregir imágenes que representen un lugar específico, incluidos destinos ya publicados. El mapa define la estructura espacial; las fotografías y vistas de calle verifican cómo se ve. Ninguna fuente sustituye a las otras.

1. **Ubicar el tramo.** Guardar fuente cartográfica/URL, fecha de consulta, coordenadas verificadas cuando estén disponibles, escala o extensión aproximada, norte y límites del encuadre. No confundir el punto de una ficha comercial con los límites de una playa o barrio.
2. **Leer la geografía.** Revisar forma y orientación de costa/bahía/isla, desembocaduras, cuerpos de agua, relieve y pendientes. Consultar terreno/topografía cuando el relieve afecte la escena; no agregar montañas, acantilados o islas ausentes. La cartografía no acredita por sí sola color del agua, arena o vegetación.
3. **Leer la ocupación.** Contrastar vías y accesos, relación vía–playa–mar, muelles/espolones, manzanas, huellas y separación de edificios y espacios abiertos. Usar vista satelital/aérea si aporta evidencia y está disponible; registrar su antigüedad si consta. No deducir alturas o fachadas de un mapa plano.
4. **Cruzar con Street View y fotos.** Revisar puntos orientados hacia el mismo tramo; conservar fecha de captura, proveedor y dirección de mirada. Verificar alturas, fachadas, vegetación, arena y mobiliario. Distinguir captura histórica, imagen reciente y obra documentada posterior. Si no hay cobertura, registrar esa ausencia y emplear fotos identificadas; no inventar la vista de calle.
5. **Fijar la cámara.** Escribir antes del prompt dónde está el observador, hacia dónde mira y qué queda delante/detrás/izquierda/derecha. No combinar puntos de vista incompatibles ni comprimir distancias para hacer caber atractivos. Conservar geometría y relaciones comprobadas; registrar incertidumbres.
6. **Comparar la salida.** Comprobar orientación, curva de costa, relieve, ubicación relativa de vías, accesos y edificios contra mapa y referencias. Rechazar inversiones, costas ficticias y redistribuciones aunque el resultado sea bonito. Guardar evidencia de la comparación y aprobación por hash.

Registrar en la ficha cartografía, relieve, cobertura de vistas de calle, cámara, rasgos confirmados y límites de evidencia. Una actividad temática sin sitio preciso debe declararse como tal; no se le atribuye fidelidad geográfica. Si representa un fondo identificable, también aplica esta regla.

Consultar mapas y panorámicas para verificar hechos no concede permiso para copiarlos o subir sus capturas al generador: mantener procedencia y condiciones de cada material. Esta regla exige revisión de los publicados; no autoriza reemplazarlos automáticamente.

## Muestreo de 5 a 8 puntos de vista

Para representar un destino o sector específico, consultar como objetivo **5 a 8 puntos distintos de Street View o panorámicas identificadas**, si existe cobertura suficiente. Elegirlos por información útil: acceso, centro del tramo, extremos, borde costero/plaza/atractivo y entorno construido. Girar en un mismo punto aporta orientación, pero no cuenta como otro punto de captura.

No recorrer puntos al azar ni mezclar sectores solo para completar el número. Mantener un área coherente con el encuadre; registrar por punto URL/ubicación, orientación, fecha de captura, proveedor, rasgos confirmados y contradicciones. Evitar repetir tomas que no aportan evidencia. Si hay menos cobertura, documentar cuántos puntos se pudieron inspeccionar y completar con fotos identificadas y cartografía, sin afirmar realizadas las consultas faltantes.

Sintetizar rasgos compartidos y variaciones reales antes del prompt. Resolver diferencias debidas a fecha, marea, obras o ubicación; no construir un collage de edificios y paisajes de puntos incompatibles. Conservar un único punto de cámara plausible. El muestreo mejora la evidencia, pero no garantiza exactitud del resultado: comparar mapa, referencias y salida, rechazar cambios de identidad y mantener aprobación humana por hash.
