# Demostración comercial local grabada

Estado: primer recorrido local de 12 pasos completado el 21 de octubre de 2026, resultado `passed`, sin errores JavaScript. Video WebM de 6 min 05 s, 1440×1000; revisadas capturas de páginas, fotograma del mapa completo con marca roja y página comercial final. Cada nueva ejecución requiere su propio resultado y revisión.

Solo entorno local desechable. No usa credenciales productivas, proveedores de correo, pagos reales ni datos vivos. Se prepara una copia de la base de demostración editorial local y recursos del paquete local. Los documentos y la transferencia son sintéticos. No valida generación de imágenes con IA ni traducción automática: selecciona material de prueba existente y textos de prueba preparados.

## Preparación

Instalar Playwright en `.local/playwright-video` mediante `npm install --prefix .local/playwright-video playwright` y descargar su Chromium con el ejecutable local `playwright install chromium`.

Ejecutar `python3 scripts/demos/preparar-escenario.py`. Devuelve una carpeta nueva bajo `.local/playwright-video/escenario-*`, que contiene configuración privada, una base copiada y archivos de prueba. No usar este preparador con bases productivas. Los recursos se enlazan físicamente para ahorrar disco y no deben editarse desde el entorno de demostración.

Iniciar PHP en un puerto local libre, con documento raíz `CARPETA/public`, router `CARPETA/router.php`, `EL_PREPROD_LOCAL=1` y funciones externas deshabilitadas (`mail,curl_exec,fsockopen,pfsockopen,stream_socket_client`). No exponer a la red: escuchar en `127.0.0.1`.

Ejecutar `node scripts/demos/recorrido-comercial.cjs CARPETA http://127.0.0.1:PUERTO`.

## Verificación y evidencias

Primero se comprueban seis páginas con estilos, imágenes, respuestas HTTP y ancho sin desbordamiento. La prueba aborta si falla la comprobación previa. Los rótulos y marcadores de clic se agregan únicamente al contexto de navegador de prueba.

La escritura usa 95 ms por carácter y un tiempo máximo proporcional a la longitud. Hay pausas entre campos, clics y pasos. Se graba el contenido del navegador a 1440×1000, sin audio ni escritorio del usuario. Resultado: `recorrido.webm`, capturas por paso y `resultado.json`. Un video que termina en error no constituye evidencia de éxito. Conservar el motivo de fallo; no ocultarlo mediante edición.

La sesión cambia explícitamente entre comerciante, administrador y visitante. El pago solo confirma un comprobante sintético; no ejecuta transferencias. La revisión editorial requiere selección de activo preparado y traducciones; el comerciante aprueba la versión exacta y el administrador publica por separado. La verificación de correo sí usa el token emitido por el backend local; la entrega se simula en el buzón privado.

Limitaciones: no demuestra entrega real de correos, abono bancario, generación de imágenes ni traducción automática. El aviso editorial se comprueba por el evento mail_simulated y se navega a la versión privada correspondiente; no se abre una bandeja de correo externa. No cubre todos los planes, navegadores ni dispositivos. La aprobación visual del propietario y la integración/despliegue siguen pendientes.
