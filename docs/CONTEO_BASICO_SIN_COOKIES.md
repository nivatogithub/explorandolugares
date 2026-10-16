# Conteo básico agregado sin cookies

Implementación preparada el 16 de octubre de 2026. No desplegada ni activada en producción por esta tarea. La versión publicada sigue siendo v1.2.0. No se crean visitas sintéticas en producción para probarla.

## Decisión de producto

La medición anterior dependía completamente del permiso y por tanto no representaba toda la actividad. Separar dos indicadores reales:

- **Páginas vistas sin cookies:** peticiones válidas del contador básico. Un refresco puede sumar otra vista. No es número de personas, visitantes únicos ni sesiones.
- **Medición con permiso:** sesiones, visitantes estimados, país de conexión, origen e interacciones de negocios. Representa únicamente esa muestra; no se extrapola al tráfico completo.

Las cuentas viajeras verificadas se cuentan por separado a partir del registro. No inferirlas a partir de accesos o formularios comerciales. No sumar páginas básicas y páginas consentidas: son dos observaciones de actividad que se superponen.

## Datos y funcionamiento

El script compartido envía una sola petición básica por documento, también si se rechaza la medición opcional. Usa `credentials: 'omit'`, sin ID de evento, cookies, almacenamiento local o fingerprint para el conteo básico. Aceptar después no vuelve a ejecutar el contador básico. La preferencia de medición opcional sigue en almacenamiento local y solo su aceptación permite cookies/identificadores estadísticos opcionales.

`api/actividad.php` no inicia sesión, no lee cookies y no emite `Set-Cookie`. Verifica origen, HTTPS en producción, tamaño y forma exacta del payload. Solo acepta un `path`, sin query/fragmento, para resolver una página HTML pública existente o un destino/negocio realmente publicado. Descarta el path una vez resuelto. Las rutas privadas, inexistentes, destinos no publicados, bots reconocibles y señales DNT/GPC no suman. No existe correlación entre dos peticiones del contador básico.

`el_basic_page_daily` contiene exclusivamente `page_day`, `scope_code`, `page_views`. Un acceso de destino puede incrementar CO y su departamento en la misma transacción; un informe de CO consulta solo la fila CO, nunca suma ambas. `el_basic_settings` contiene activación, inicio y conservación, sin datos de visitantes. No se guardan país, IP, user-agent, URL, referencia, cuenta, negocio concreto, hora individual ni recorrido. El SQL actualiza un contador; no crea filas de eventos individuales.

Las páginas generales y el mapa cuentan como portal. Seleccionar un departamento no crea una vista básica adicional ni atribuye una visita a ese departamento. Los ámbitos departamentales corresponden a páginas públicas de sus destinos/negocios. No se incluye país en el módulo básico: el desglose de país pertenece a la medición con permiso.

## Calendario y presentación

Totales y series: últimos 30 o 90 días de calendario de Bogotá, incluido hoy marcado como parcial. Comparaciones: dos ventanas de 30 o 90 días completos que terminan ayer. La comparación no utiliza el día parcial ni se habilita con historia incompleta; porcentaje sobre cero permanece sin dato. Inicio de cada módulo y métodos se explican en “Cómo medimos”. La tarjeta básica ocupa una fila y reutiliza los tokens del portal. La consulta del informe no se almacena en caché; la página espera el envío básico y vuelve a consultar tras su confirmación para evitar mostrar un contador anterior al refresco.

Antes de activación, la tarjeta muestra “—” y estado pendiente; no ceros ni historia inventados. Después de activación, cero significa que no hay páginas recibidas en el ámbito/periodo. No se reconstruye tráfico anterior a partir de cookies ni se mezcla historia de métodos distintos.

## Límites explícitos

- No estima personas ni sesiones sin cookies. Un mismo navegador puede aportar muchas vistas.
- Sin leer cookies de autenticación, no se puede excluir con certeza a un administrador que visite páginas públicas. Se excluyen rutas privadas; el tráfico público del equipo puede formar parte del agregado.
- El filtro de user-agent no garantiza excluir todos los robots. Origen y validación de rutas reducen errores, no certifican tráfico humano ni impiden todo abuso. No usar estas vistas como clientes potenciales únicos garantizados.
- JavaScript bloqueado, errores de red, DNT/GPC y páginas no instrumentadas quedan fuera. “Sin cookies” no equivale a contar absolutamente todos los accesos HTTP.
- El alojamiento recibe información de conexión y puede conservar access logs. No se ha inspeccionado ni alterado su configuración privada. No afirmar que todo el hosting deja de tratar datos personales.
- Conservación objetivo de agregados: 180 días. Limpieza diaria al recibir actividad válida y comando privado de retención. Programar y comprobar retención diaria del hosting para mantener el plazo también sin tráfico.

## Privacidad y fuentes consultadas

La Ley 1581 distingue datos vinculados o asociables a personas y regula autorización, finalidad y derechos. El diseño evita una base de recorridos identificables y mantiene consentimiento para los identificadores opcionales. Esto es una decisión de minimización; no una certificación ni una afirmación de que todo sistema “sin cookies” está exento de autorización en Colombia.

Fuentes oficiales contrastadas en la consulta del 16-10-2026:

- [Ley 1581, texto de referencia de Función Pública](https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=49981).
- [Ley 1581, referencia normativa de la Procuraduría](https://apps.procuraduria.gov.co/gd_1952/docs/l1581012.htm).
- [SIC: autorización previa e informada](https://sedeelectronica.sic.gov.co/publicaciones/boletin-juridico/concepto/para-el-adecuado-tratamiento-de-los-datos-es-indispensable-la-autorizacion-expresa-previa-e-informada).
- [SIC, Resolución 14015 de 2021: cookies que recogen datos personales](https://sedeelectronica.sic.gov.co/sites/default/files/estados/072022/RE14015-2021.pdf).

La consulta recuperó referencias y fragmentos indexados oficiales; varias aperturas del texto completo devolvieron 403/502/timeout. No se declara verificado el texto consolidado íntegro ni una excepción legal general. Antes de activar, el responsable debe confirmar política publicada y tratamiento/retención de logs del hosting, conservando el consentimiento para datos opcionales. Las políticas en ES/EN/PT describen ambos módulos y los límites anteriores.

## Activación y entrega

1. Revisar delta del paquete desde main y autorizar su despliegue. Este cambio no se publica automáticamente.
2. Publicar servicio, API, cliente, página, políticas y administración juntos.
3. Un administrador autenticado puede usar “Habilitar conteo básico agregado” en `/admin/medicion.php` (POST con CSRF), o ejecutar `php app/analytics/install-basic.php` mediante CLI privada si existe acceso. No se presupone acceso CLI en este hosting.
4. La activación crea solo dos tablas nuevas, de forma idempotente, y registra el inicio. Ningún endpoint público crea tablas ni activa medición.
5. Verificar estado, ausencia de cookies y contador privado con navegación legítima; no fabricar audiencia. Configurar retención. No declarar persistencia comprobada antes de ese paso.
6. Un despliegue de código conserva los contadores y las tablas opcionales. Revertir código no elimina la base de datos.

## Validación

- `php backend/tests/basic-audience.php`: esquema sin identidad, activación, refresco, rutas públicas, ámbitos, Bogotá, historia y comparación sin día parcial.
- `python3 backend/tests/basic-audience.integration.py`: SQLite y PHP locales; no `Set-Cookie`, ni tabla de sesiones/eventos; rechazos por origen, datos extra, rutas privadas, bots y DNT/GPC.
- Prueba de navegador en la vista local con aviso de datos de prueba: aceptar suma un evento opcional sin duplicar el básico; rechazar y refrescar suma una página básica sin nuevos eventos opcionales.
- Suite completa de predespliegue, estilos y paquete completo. No se invocan endpoints de escritura de producción.
