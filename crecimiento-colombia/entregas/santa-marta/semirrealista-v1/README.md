# Santa Marta · propuesta semirrealista v1

Entrega privada para revisión. 18 ilustraciones independientes generadas con la herramienta integrada: portada de centro/bahía histórica, ocho lugares y nueve experiencias. Ninguna imagen de historia. Resultado y publicación pendientes de aprobación humana; las decisiones fotográficas anteriores siguen intactas.

## Contenido vigente de esta propuesta

Orden editorial: Tayrona, Teyuna/Ciudad Perdida, Minca, El Rodadero, Quinta de San Pedro Alejandrino, Taganga, centro histórico, Playa Blanca. `DECISION_TOP8_V1.json` resuelve el octavo pendiente del expediente anterior; no afirma un ranking estadístico. La caminata a Teyuna y la excursión a Playa Blanca son actividades relacionadas, sin sumar lugares ni cobertura. La visita al Acuario se mantiene en experiencias.

`contenido-cierre-semirrealista-v1.json`: borrador compatible con research-draft-1, destino, ocho lugares y nueve experiencias `review_ready`/`display_translations` ES/EN/PT. `santa-marta-semirrealista.json`: datos breves para presentación. Los once prospectos trilingües del directorio siguen en `../directorio.json`; este trabajo no inventa fotos ni contactos de negocios. Moto acuática es información por verificar, sin proveedor o ubicación de embarque confirmados, reserva ni promesa de disponibilidad. Su fuente es una guía turística de 2024, no una oferta vigente comprobada.

## Integración serial por coordinación

Copiar únicamente los archivos nuevos de `backend/public/assets/santa-marta-semirrealista/`, `santa-marta-compacta.js` y `santa-marta-compacta.css`. Cargar CSS y JS (defer, después del marcado del destino) **solo en preprod** para Santa Marta y sus idiomas. El JS comprueba slug/ruta antes de aplicar cambios. No cambia la base de datos: usa JSON local para portada, ocho tarjetas y nueve experiencias, incluso si el marcado anterior no tenía imágenes/figuras. Las imágenes quedan etiquetadas como ilustraciones.

Importar serialmente el borrador de contenido si procede mediante el mecanismo ya usado por coordinación; no usar el ZIP histórico de referencias como si fuera esta propuesta. `MAPA_INTEGRACION.json` da rol/key/slug y rutas exactas. El ZIP `integracion-preprod.zip` conserva rutas relativas de los assets; no contiene maestros ni autoriza despliegues.

Se reutilizó el patrón de contenido compacto de Cartagena, historia completa sin foto, filas completas para cargar más y tokens existentes. El paginador local sirve a las dos cuadrículas reconstruidas; el directorio mantiene sus fichas, filtros y enlaces y debe conservar los scripts comunes `cargar-mas-tarjetas.js` y `tarjetas-negocios-compactas.js` de la integración compartida. No cargar dos compactadores distintos para Santa Marta. No eliminar créditos de fotos de terceros que continúen en otras secciones.

Previews HTML ES/EN/PT incluidas: servir la raíz del checkout por HTTP; no dependen de PHP/BD. No se afirma validación visual del sitio integrado: coordinación debe revisar escritorio/móvil, tres idiomas, botón de cargar más y directorio tras copiar. Esta entrega no tocó preprod compartida, producción ni BD.

## Procedencia y revisión

Maestros PNG en `maestros/`, fuera del hosting. WebP: portada 640/1200; lugares/experiencias 480/800. Solo reducción técnica con Pillow, sin retoques creativos ni recortes. `GENERACIONES-FINALES.json` registra prompts completos, herramienta/proveedor y salida; el modelo concreto no fue expuesto por la herramienta y no se inventa. Sin fotografías ni referencia de estilo subidas al generador. El acabado elegido se comparó visualmente con la muestra local EL-SEMI-REALISTA-1; su alto realismo queda sujeto al juicio humano.

Cuatro salidas rechazadas se conservan fuera del hosting: portada y centro con dos torres ficticias, velero con mástil cortado, lancha con capitán sin chaleco. Se generaron sustitutos y se inspeccionaron: campanario único asimétrico, velero completo y chalecos en toda la lancha. Las composiciones son ilustraciones representativas; no reconstruyen fielmente cada edificio, recorrido ni infraestructura actual. El Acuario tiene exhibiciones y túnel marino según su web oficial consultada el 17-10-2026: https://centrodevidamarina.com/; la escena no documenta un ventanal específico ni promete fauna concreta.

`MANIFIESTO.json` conserva fuentes, vía independiente, hashes, tamaños y estados. No se afirma ausencia universal de derechos adicionales ni exclusividad. No hay aprobación editorial ni de publicación implícita por usar generación IA.

## Validación

`validar-paquete.py`: 137 verificaciones de traducciones, ocho/nueve roles, límites de oferta, unicidad, archivos, dimensiones y hashes. Sintaxis de JS validada con Node. `QA-contacto-final.jpg` permite inspección de los 18 componentes; es un archivo privado de QA, no un collage para publicar. Pendientes: integración serial y revisión humana del resultado en contexto real.

Corrección de integración: la experiencia usa `caminata-ciudad-perdida`; el lugar conserva `ciudad-perdida`. Ambos comparten ubicación relacionada, pero sus identificadores son distintos para respetar UNIQUE(destination, slug). El activo de la experiencia conserva `exp-ciudad-perdida`.
