# Métricas por destino y negocio

Preparadas en código, no desplegadas ni activadas en producción. La versión publicada sigue siendo v1.2.0.

## Qué mide cada contador

|Métrica|Definición|Condición|
|---|---|---|
|Páginas de destino|Guía y subpáginas públicas; también páginas de sus negocios, separables en el informe|Destino publicado|
|Página propia de negocio|Carga o refresco de su URL pública|Negocio/destino públicos y permiso de página propia vigente|
|Aparición normal de tarjeta|Al menos 50% visible durante un segundo continuo, según el navegador|Tarjeta pública; sin derecho vigente a destacado|
|Aparición destacada|La misma regla de visibilidad|Derecho vigente `highlighted_card`|
|Clic a página propia|Clic real hacia la URL del negocio desde su tarjeta; máximo uno por tarjeta/documento|Derecho vigente a página propia|
|Clic de contacto|WhatsApp/teléfono/correo/web registrado por el módulo opcional, deduplicado 30 segundos|Solo navegación con permiso; muestra parcial|

Los primeros cinco son agregados diarios sin cookies de visitantes. Un refresco puede sumar otra página y otra aparición si vuelve a cumplir visibilidad. No equivalen a personas únicas ni a ventas. Cargar HTML o filtrar una tarjeta fuera de pantalla no basta para contar su aparición. Una pestaña oculta pausa el tiempo de visibilidad. Si el navegador no soporta IntersectionObserver, no se inventan apariciones.

Un clic rápido puede ocurrir sin cumplir el segundo de visibilidad: los clics no se fuerzan artificialmente a tener una impresión correspondiente. No hay CTR ni conversiones de ventas prometidas. La medición del navegador y los filtros básicos no certifican tráfico humano ni eliminan todo abuso.

## Publicación y derechos comerciales

`PublicAudienceTargets.php` comprueba en servidor el negocio y destino publicados, su estado de operación/revisión, traducción pública y la última suscripción activa dentro de fechas. Consulta `subscription_plans` y `subscription_plan_features`; no acepta etiquetas de pago o posición enviadas por el navegador.

Página, destacado y Premium se distinguen por sus derechos reales: el plan Página no se trata automáticamente como destacado, y Destacado no habilita una página propia por sí solo. Suscripciones pendientes, futuras o vencidas no habilitan páginas/posiciones pagas. Un negocio vencido puede seguir como ficha gratuita publicada; sus apariciones se clasifican como normales si ya no tiene derecho a destacado.

Tener una ficha gratuita no significa pagar, estar verificado o ser aliado. Los contadores no comprueban un pago bancario ni conceden insignias; registran derechos activos según el sistema comercial existente. El directorio gratuito conserva su funcionamiento y puede medir tarjetas normales.

## Atribución sin duplicar audiencia

Una página de negocio suma una vez al portal y a su departamento, una vez a su destino y una vez a ese negocio: son dimensiones de la misma página, no visitas adicionales que deban sumarse entre sí. Apariciones y clics no aumentan el contador general de páginas.

`el_basic_entity_daily` almacena día de Bogotá, tipo/ID de entidad pública, destino de atribución, métrica, posición y total. Conserva la atribución del momento y no reclasifica impresiones históricas según el plan actual. Un destino autónomo y su municipio conservan slugs separados; no se colapsan sus contadores. No se almacenan ID de visitante, sesión, IP, país, hora individual, cuenta visitante ni recorridos. Los totales caducan con la misma retención de 180 días del módulo básico.

El módulo básico completo requiere tres tablas. `install-basic.php` añade la tabla de entidades y su índice de consulta de manera idempotente, conserva los contadores existentes y registra un inicio propio para entidades. No reconstruye historia previa. La activación exige un administrador o CLI privada; ningún endpoint público instala tablas.

## Consultas y permisos

- Administración: páginas por destino en `/admin/medicion.php`, y detalle de entidades mediante API privada.
- Comercio: sección “Visibilidad de tus negocios” en su espacio autenticado, con totales y desglose diario. Solo aparecen negocios con membresía de administración activa para esa cuenta; esta membresía es autorización de representación, no una membresía comercial de pago.
- `/api/audiencia-entidad.php`: GET con sesión vigente. Administración puede consultar destinos/negocios; una cuenta comercial activa solo su negocio autorizado. Un UUID conocido no permite consultar cifras ajenas. Respuesta privada, sin caché, sin perfiles de visitantes.
- El informe añade por separado contactos/vistas del módulo opcional cuando está disponible, etiquetados `consent_only`; no los suma a los totales básicos. Las ventanas opcionales son móviles, mientras los básicos usan calendario de Bogotá con hoy parcial.

El conteo sigue respetando DNT/GPC. Las vistas previas privadas de destinos no cargan medición. Las políticas en ES/EN/PT explican los IDs de entidades públicas y el acceso restringido. Los access logs del alojamiento son un tratamiento distinto que este cambio no modifica.

## Validación

Pruebas de negocio/destino, página estándar/Premium, destacado sin página, vencimiento, inicio futuro, pago pendiente, negocio cerrado, atribución sin sumar tarjetas a páginas, visibilidad 50%/1 segundo, pausa por pestaña oculta, deduplicación local, ausencia de cookies/referrer/IDs de visitante y exclusión de previews privadas.

Pruebas HTTP con cuentas ficticias: titular autorizado obtiene su informe; otro negocio, destinos y visitantes sin sesión reciben 403. Suite completa de backend, enlaces y empaquetado. Vista privada revisada en navegador local con datos ficticios; no se generan eventos ni pagos de prueba en producción.


## Entrega y caché

Los cargadores compartidos y directos del medidor se versionan por hash de contenido. El empaquetador completo actualiza también las referencias al menú compartido en todas las páginas generadas, después de aplicar la integración. Esto evita mantener `medicion.js?v=1` o un menú antiguo que cargue código anterior. No cambia las etiquetas ni los enlaces de navegación.

Para entregar esta versión usar `scripts/desplegar-sftp.py` desde main, revisar su delta y autorizar el despliegue y la activación del módulo básico. El preparador acotado de cobertura detecta cambios fuera de su alcance y bloquea `--deploy` si dejaría páginas o módulos anteriores. Ninguna etiqueta ni merge publica o activa estadísticas automáticamente.

[Evidencia privada local](../design/portal/evidencias/metricas-negocio-privadas.png) y [registro de validación](../design/portal/verificacion-metricas-entidades.json). Los nombres, pagos y cifras de esa captura pertenecen a cuentas ficticias de pruebas; no son datos de producción.
