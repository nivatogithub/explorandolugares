# Cierre de pendientes — 15 de octubre de 2026

Rama `negocios-dinamicos-phm`. Código publicado `333bfaf`, con la implementación `d8e295e` y la administración previa `f1507c0`. Este informe actualiza los pendientes observados en `DESPLIEGUE_2026-10-15_CANALES.md`.

## Canales de Jericó

Importación privada completada por el panel autenticado con el paquete `jerico-canales-99-negocios.PRIVADO.json`: 99 negocios, 285 registros de canal/fuente añadidos y cero registros previos coincidentes. No se reimportaron fichas, no se crearon cuentas y no se modificó su publicación. Consulta productiva posterior: 199 negocios, 99 con canales y 100 sin canales. El panel de correos muestra 42 negocios con correo, 48 combinaciones de negocio/correo y cero habilitadas para campañas. Las fuentes duplicadas no se presentan como negocios adicionales.

## Sonsón

Importado el ZIP aprobado de `content/paquetes/sonson/`, con cuatro atractivos y cinco imágenes. Guardado como borrador, ID 23. El total real es 22 destinos; el ID no representa el número de destinos y puede tener saltos tras una transacción fallida. Se corrigió el importador: JavaScript externo compatible con CSP, índices de archivos por atractivo y carga de textos alternativos. No se debilitó la política de seguridad.

Verificado en producción: listado de 22 destinos con fila Colombia / Antioquia / Sonsón / Borrador. Vista previa autenticada con los cuatro atractivos y cinco rutas de imagen bajo `assets/destinos/23/`. Consulta pública sin sesión devuelve 404; Sonsón no quedó publicado. Jericó sigue devolviendo 200.

## Campañas Brevo

Publicado `/admin/campanas.php`, incluido en la navegación. Migración aditiva 029 aplicada y lista en MySQL. Permisos por correo, borradores con audiencia deduplicada, aprobación de versión, preparación por pasos en la API de marketing, confirmación separada de envío, bajas nativas de Brevo y consulta manual de estadísticas. Ver `CAMPANAS_BREVO.md` para alcance y contratos.

Configuración privada mediante el panel, sin reescribir `private/config.php`. Llave cifrada y campo no revelado al volver a abrir la pantalla. Verificado en producción que todavía no existe una llave guardada; conexión y envíos deshabilitados. El propietario debe introducir su llave y remitente/dominio verificado. No se marcaron permisos de los correos reales como obtenidos. No se crearon listas o campañas en Brevo ni se enviaron mensajes a terceros.

## Validación y despliegue

74 archivos PHP y 38 Python revisados; pruebas de servicios (incluido Brevo con transporte simulado), 57 pruebas de navegación/generación/despliegue e integración HTTP de participación y administración aprobadas. Los ensayos de campañas comprueban CSRF, revisión, contenido escapado, configuración cifrada, proveedor excluido, lista alterada, remitente, preparación sin envío y ausencia de reintentos ante fallos inciertos.

Entregas incrementales: corrección inicial del importador (2 archivos), cierre principal (13 archivos) y ajuste de remitente/respuesta (1 archivo). Sin eliminaciones remotas; configuración existente conservada. Aplicación de 029 verificada en MySQL desde el panel del propietario.

HTTPS final sin sesión: Sonsón 404, Jericó 200 sin errores PHP visibles, campañas 403. La validación real con la API de Brevo permanece pendiente de la conexión y de un envío concreto autorizado por el propietario; el transporte simulado no demuestra entregabilidad, dominio o credenciales reales.
