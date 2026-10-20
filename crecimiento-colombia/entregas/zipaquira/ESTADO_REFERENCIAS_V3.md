# Zipaquirá: correcciones de identidad y revisión local

Fecha: 2026-10-20. Aprobación humana pendiente; sin publicación en producción.

El coordinador importó siete imágenes corregidas, con referencias fotográficas identificadas usadas directamente como entrada: portada, Catedral de Sal, estación, catedral diocesana, tren, plaza de los Comuneros y museo arqueológico. `PROCEDENCIA_REFERENCIAS_V3.json` registra autores, licencias, originales, derivados, hashes, comparación y límites. `creditos-referencias-v3.html` contiene los créditos públicos de las adaptaciones.

Paquete importado: SHA-256 `dacf339e95e1349f7817b72f1bacef492634d05c46d09c4a976497b3bd6394ac`. Este cierre no lo modifica. Conserva siete atractivos, tres experiencias y treinta fichas.

Las imágenes anteriores de colegio Gabo, museo Quevedo, experiencia minera y recorrido histórico siguen referenciadas en el ZIP por exigencia de su esquema. Están rechazadas por falta de identidad visual comprobada. El overlay local las elimina del DOM sin retirar los textos ni modificar la base de datos. Estos cuatro componentes necesitan una referencia exacta con permiso de adaptación antes de tener imagen definitiva; no se sustituyen por escenas genéricas.

`runtime/install-local.py` instala exclusivamente en preproducción una carga condicional para Zipaquirá: oculta esas cuatro imágenes, muestra completa la cruz de la tarjeta vertical y enlaza los créditos. Instalado en renderer fuente y release; sintaxis PHP validada. Recibo: `RECIBO_OVERLAY_VISUAL_V3.json`. La comprobación HTTP desde este subproceso falló por conexión local, por tanto la ejecución efectiva del overlay en navegador queda por verificar por el coordinador. El coordinador había comprobado HTTP 200 ES/EN/PT del paquete importado antes del overlay.

URL de revisión: http://127.0.0.1:8088/destinos/zipaquira/

No se han aprobado imágenes ni destino en nombre del usuario. Fotografías históricas y sus adaptaciones no certifican el estado físico de 2026.
