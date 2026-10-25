<?php if(!isset($account)){http_response_code(404);exit;} ?>
<dialog id="business-qr-dialog" class="business-qr-dialog" aria-labelledby="business-qr-title">
  <form method="dialog" class="business-qr-heading"><h2 id="business-qr-title">Código QR de tu negocio</h2><button class="pill light" aria-label="Cerrar código QR" autofocus>Cerrar</button></form>
  <p id="business-qr-name"></p>
  <canvas id="business-qr-canvas" role="img" aria-label="Código QR del negocio" width="2048" height="2048"></canvas>
  <p><a id="business-qr-url" target="_blank" rel="noopener noreferrer"></a></p>
  <p>Descarga el PNG para imprimirlo o compartirlo. Al escanearlo se abre la página pública de tu negocio.</p>
  <p id="business-qr-status" role="status" aria-live="polite"></p>
  <button class="pill" id="business-qr-download" type="button">Descargar PNG</button>
</dialog>
