# Cierre operativo y referencias de identidad — 20 octubre 2026

Aplicar a destinos nuevos, pendientes y auditorías de publicados. No reabre aprobaciones ni autoriza publicación.

## Investigar identidad no equivale a reutilizar una foto
Consultar turismo municipal y nacional, administradores, Tripadvisor, fotos públicas, cartografía, relieve y Street View para identificar el inmueble, tramo y punto de vista. Registrar qué prueba cada fuente. Una foto pública puede aportar observaciones verificables sin que exista permiso para subirla al generador. No bloquear toda la investigación porque falta ese permiso; bloquear solamente su uso como entrada y la publicación derivada.

Si no hay fotografía autorizada de entrada, preparar ficha de rasgos y relaciones contrastados con fuentes independientes: silueta, número y posición de puertas/ventanas, materiales, alturas, esquinas, accesos, vía, orientación, terreno y entorno. Una descripción genérica no habilita generar: debe haber identidad y estructura comprobables. Una composición desde hechos documentados se declara aproximada y se compara con evidencia antes de integrar; no se afirma copia exacta.

No subir fotografías protegidas al generador sin permiso. Conservar fuente, autor, licencia y cambios cuando se adapte una imagen autorizada.

## Ferrocarril y rutas
Separar edificio de estación, lado de vías/andén, patio y recorrido. Buscar la fachada ferroviaria además de la fachada hacia la plaza. No insertar tren arbitrariamente frente al edificio. Una locomotora colombiana no acredita cualquier tramo: verificar ruta, relieve, uso del suelo, entorno urbano/rural y fecha. Si no se geolocaliza el tramo, no atribuirlo a Zipaquirá ni prometer condiciones actuales.

## Trabajo acotado y entrega
1. Antes de generar, definir referencia, encuadre e invariantes por componente.
2. Una prueba y una corrección dirigida por encuadre. Si falla identidad, cambiar de método o declarar el bloqueo concreto; no repetir variantes genéricas.
3. Un worker y rama por destino; coordinador integra secuencialmente. Avisar inmediatamente si hay permiso pendiente o error; espera no es ejecución.
4. Distinguir investigado, generado, comparado, empaquetado, importado, comprobado en página y aprobado. Un ZIP o maestro no es un cambio visible.
5. Entregar conjuntamente las correcciones acordadas para revisión, con lista cerrada de pendientes. No mandar al usuario repetidamente a una versión anterior.
6. Verificar archivo servido y resultado visual, no solo HTTP200. No afirmar retiro de imagen por existir un script sin comprobar su efecto.
7. Nunca declarar el destino completo con componentes rechazados o bloqueados. Aprobación humana y producción son pasos distintos.

## Referencias elegidas por el propietario
El propietario puede aportar las referencias visuales por componente antes de generar. El worker comprueba identidad, conserva procedencia, adapta estilo y formato, optimiza e integra el lote completo para revisión. No generar alternativas genéricas para componentes pendientes de referencia. No iniciar nuevos destinos automáticamente ni prometer un ritmo sin medir investigación, generación y revisión.

## Fuente única y limpieza
Aplicar `docs/FUENTE_UNICA_Y_LIMPIEZA_DESTINOS.md`: contenido aprobado e imágenes por hash deben alimentar la construcción; los overrides y semillas antiguas no pueden prevalecer. Limpieza solo por inventario de referencias activas, incluyendo base y borradores.
