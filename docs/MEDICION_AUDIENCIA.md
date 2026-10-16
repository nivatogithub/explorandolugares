# Medición propia y cobertura persistente

La implementación incorpora PHP/PDO y siete tablas nuevas `el_measurement_*`/`el_coverage_*`. No reemplaza cuentas, destinos, negocios ni configuración privada. El catálogo inicial se importa de forma idempotente; “En preparación” se administra desde `/admin/medicion.php`. La publicación procede del estado real de destinos, nunca de una edición del contador.

## Indicadores y origen

|Indicador|Cálculo|Alcance|
|---|---|---|
|Visitas sin iniciar sesión|Sesiones anónimas distintas con eventos consentidos|Periodo/lugar|
|Visitantes estimados|Identificadores propios distintos; no personas identificadas|Periodo/lugar|
|Viajeros registrados|Cuentas traveler activas con correo confirmado|Portal completo, existencia actual|
|Viajeros activos|Cuentas confirmadas con página vista, ficha vista o contacto|Periodo/lugar|
|Vistas de negocio|Evento de ficha publicada, validado contra base|Periodo/lugar|
|Contactos|Clics WhatsApp/teléfono/correo/web en negocios publicados; deduplicados 30 segundos|Intención, no ventas|
|País|Consulta local de IP en DB-IP Country Lite; IP descartada|Conexión aproximada, no residencia|
|Evolución|Vistas por día de Bogotá desde inicio de medición|Comparación solo con dos periodos completos|
|Adquisición|Clasificación de origen inicial de sesión: buscador/red/campaña/referencia/directo desconocido|Sin URL de referencia ni UTM almacenados|

Una sesión vence tras 30 minutos sin actividad. Cookie visitante: hasta 90 días desde última actividad. Se cuentan visitas consentidas; no se extrapolan al tráfico completo. Cambiar dispositivo/borrar cookies puede multiplicar visitantes estimados. Bots conocidos, sesiones administrativas y cuentas comerciales se excluyen; no se garantiza detectar todo tráfico automatizado. Umbral público: cinco visitantes estimados por selección; países pequeños se agrupan. Valores ausentes son `null`; ceros solo tras activar medición. No hay cifras retroactivas antes de activación.

No se recopilan formularios, búsquedas, emails, contenido de mensajes, IPs ni parámetros de URL en eventos. Los identificadores se firman en cookies HttpOnly y se guardan en base como HMAC. Las peticiones requieren mismo origen, consentimiento y versión. La elección puede rechazarse/retirarse en el pie de página; DNT/GPC se respetan. Retirada impide eventos futuros y elimina cookies, sin alterar datos históricos automáticamente. Eventos caducan a los 180 días; mantenimiento diario al recibir actividad. Para un plazo estricto incluso sin tráfico, programar `php app/analytics/retention.php` diariamente en el hosting.

## Operación

`php backend/tests/analytics-service.php` prueba idempotencia, privacidad de eventos, replay, ventanas, conteo de cuentas confirmadas y deduplicación de contactos. Las pruebas usan SQLite aislado. PHP de producción usa su PDO/MySQL existente.

Preparación/despliegue incremental: `scripts/preparar-cobertura.py`. Los nueve archivos públicos de integración conservan la versión realmente descargada y comprobada contra manifiesto; `baseline.json` bloquea una entrega si cambió su versión remota. Solo se instrumentan páginas públicas que cargan menú compartido y los renderizadores dinámicos destino/negocio. Páginas de administración y cuentas quedan excluidas. Inventariar nuevas páginas al crearlas y añadir el cargador si no usan el menú.

Activación preferida: administrador autenticado `/admin/medicion.php` o CLI `app/analytics/install.php`. Si el hosting solo permite SFTP, `scripts/activar-medicion-sftp.py` instala únicamente estas tablas mediante autorización aleatoria de un uso, expiración de diez minutos, enviada en cabecera HTTPS. El permiso privado se consume antes de instalar; endpoint y permiso temporales se eliminan al terminar. No admite SQL, comandos, rutas ni configuración enviados por el cliente. No crea acceso administrativo ni descarga credenciales. No ejecutar activación antes de validar el paquete desplegado.

La inscripción pública y autenticación de viajeros no se habilitan por esta entrega. Mientras ese flujo no exista, cuentas viajeras reales pueden ser cero. No inferir viajeros registrados a partir de formularios comerciales. Tampoco hay residencia consentida por país en el modelo actual; se mantiene pendiente. El conteo de anunciantes exige definir y consultar suscripciones de pauta vigentes: no usar número de fichas gratuitas como número de anunciantes.

## Fuentes comprobadas 2026-10-16

- SIC: https://sedeelectronica.sic.gov.co/sites/default/files/estados/072022/RE14015-2021.pdf — tratamiento de datos mediante cookies sujeto al régimen de datos personales. Esta entrega elige consentimiento para medición opcional y minimización; no pretende sustituir una revisión jurídica integral.
- DB-IP oficial: https://db-ip.com/db/download/ip-to-country-lite — datos Lite, CC BY 4.0 y atribución; ver `backend/app/analytics/geoip/PROVENANCE.md`. Actualizar mensualmente. No usar API externa para enviar IP.
- MaxMind lector oficial: https://github.com/maxmind/MaxMind-DB-Reader-php/releases/tag/v1.14.0 — lector Apache 2.0, licencia incluida.

## Verificación de publicación

2026-10-16: tablas activadas en producción sin shell ni credenciales administrativas. APIs de cobertura y medición respondieron 200; la publicación se consulta desde la base real. Una visita de navegador aceptando medición cambió el estado público a `collecting_baseline`, confirmando persistencia sin exponer grupos pequeños. Se comprobaron portada, Jericó, privacidad y recolector. Esta validación incluye una visita real de prueba; no se generó tráfico masivo ni histórico sintético.

Inventario adicional: 76 HTML públicos administrados en el manifiesto; 61 ya cubiertos por cargador compartido/directo, 12 redirecciones y 3 páginas adicionales que necesitan cargador directo. Ver `backend/integration-medicion/inventory.json`. Los renderizadores PHP de destino y negocio tienen inclusión explícita. Nuevas páginas públicas deben mantener esta inclusión.
