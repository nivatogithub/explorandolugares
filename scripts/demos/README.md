# Demostración comercial local grabada

Antecedente, no entrega actual: primer recorrido local de 12 pasos completado el 21 de octubre de 2026, resultado `passed`, sin errores JavaScript. Video WebM de 6 min 05 s, 1440×1000; revisadas capturas de páginas, fotograma del mapa completo con marca roja y página comercial final. Cada nueva ejecución requiere su propio resultado y revisión.

Solo entorno local desechable. No usa credenciales productivas, proveedores de correo, pagos reales ni datos vivos. Se prepara una copia de la base de demostración editorial local y recursos del paquete completo seleccionado. Se precargan instrucciones bancarias ficticias, marcadas como prueba, exclusivamente en esa copia. Los documentos y la transferencia son sintéticos. No valida generación de imágenes con IA ni traducción automática: selecciona material de prueba existente y textos de prueba preparados.

## Preparación

Instalar Playwright en `.local/playwright-video` mediante `npm install --prefix .local/playwright-video playwright` y descargar su Chromium con el ejecutable local `playwright install chromium`.

Ejecutar `python3 scripts/demos/preparar-escenario.py --package .local/PAQUETE_ACTUAL`. El argumento debe ser un paquete completo construido de la versión a validar, con `build-manifest.json`; no se mezclan archivos sueltos de otra versión. Devuelve una carpeta nueva bajo `.local/playwright-video/escenario-*`, que contiene configuración privada, una base copiada y archivos de prueba. No usar este preparador con bases productivas. Los recursos se enlazan físicamente para ahorrar disco y no deben editarse desde el entorno de demostración.

Iniciar PHP en un puerto local libre, con documento raíz `CARPETA/public`, router `CARPETA/router.php`, `EL_PREPROD_LOCAL=1` y funciones externas deshabilitadas (`mail,curl_exec,fsockopen,pfsockopen,stream_socket_client`). No exponer a la red: escuchar en `127.0.0.1`.

Ejecutar `node scripts/demos/recorrido-comercial.cjs CARPETA http://127.0.0.1:PUERTO`.

## Verificación y evidencias

Primero se comprueban seis páginas con estilos, imágenes, respuestas HTTP y ancho sin desbordamiento. La prueba aborta si falla la comprobación previa. Los rótulos y marcadores de clic se agregan únicamente al contexto de navegador de prueba.

La escritura usa 95 ms por carácter y un tiempo máximo proporcional a la longitud. Hay pausas entre campos, clics y pasos. Se graba el contenido del navegador a 1440×1000, sin audio ni escritorio del usuario. Resultado: `recorrido.webm`, capturas por paso y `resultado.json`. Un video que termina en error no constituye evidencia de éxito. Conservar el motivo de fallo; no ocultarlo mediante edición.

La grabación muestra únicamente al comerciante y su consulta pública final. La revisión interna se ejecuta en otro navegador sin grabación, mientras la pantalla del comerciante explica la espera. Nunca se muestran administración ni cambios de rol. Antes de elegir se abren los ejemplos de las tres modalidades pagas en un panel. El soporte de titularidad se valida y elimina físicamente fuera de cámara, conservando auditoría. El pago solo confirma un comprobante sintético; no ejecuta transferencias. La revisión editorial requiere selección de activo preparado y traducciones; el comerciante aprueba la versión exacta y esa misma operación la publica, sin otra espera administrativa. Si una comprobación falla, no se confirma parcialmente la aprobación ni se modifica la publicación anterior. La verificación de correo sí usa el token emitido por el backend local; la entrega se simula en el buzón privado.

Limitaciones: no demuestra entrega real de correos, abono bancario, generación de imágenes ni traducción automática. El aviso editorial se comprueba por el evento mail_simulated y por el mensaje/enlace generado en el buzón privado; se abre ese enlace exacto. No se abre una bandeja de correo externa. No cubre todos los planes, navegadores ni dispositivos. La aprobación visual del propietario y la integración/despliegue siguen pendientes.

## Ajustes del recorrido del 22 de octubre

El registro exige soporte de titularidad y, para una página Comercial/Premium nueva, al menos una fotografía. La revisión de titularidad elimina el archivo privado y conserva metadatos de auditoría; una solicitud en disputa no puede bloquear las publicaciones del responsable actual. Antes de preparar la prepublicación se comprueban también pago, titularidad y contenido. Al aprobar la versión exacta, el comerciante publica dentro de la misma transacción; repetir esa aprobación no duplica la publicación ni reinicia la vigencia.

El vídeo centra los campos antes de escribir/cargar archivos y comprueba los límites visibles del mapa y sus botones. Conserva capturas específicas del documento, la fotografía y el mapa completo. Se rechaza el resultado si el navegador grabado visita administración.

Próximo despliegue: aplicar 050 (Página Comercial: 79.900/199.750/415.480/799.000 COP para mensual/trimestral/semestral/anual) y 051 (auditoría de retiro de documentos) antes de habilitar el código nuevo. 050 cambia únicamente el catálogo mediante una sentencia atómica; no modifica suscripciones ni pagos existentes. Este trabajo no se ha desplegado a producción.
