# Prueba integral Premium en preproducción

Fecha: 4 de noviembre de 2026. Entorno local `127.0.0.1:8088`, código servido y MariaDB de preproducción. Negocio y soporte ficticios: `Prueba Premium Integral 2026` (`c0cd1478-6a85-40a1-a6e9-f3ce97d154af`). No se envió dinero ni se actuó sobre producción.

- Registro en navegador: portada y cinco productos con título, descripción y archivo diferente; el formulario impidió añadir un sexto. Se guardaron los canales ficticios y se envió la solicitud.
- MariaDB: cinco productos y sus cinco ranuras de imágenes, más portada, presentes en solicitud y borrador editorial.
- Orden y comprobante simulado: enviados desde el navegador. La revisión del comprobante y del soporte ficticio se ejecutó con los servicios reales de la aplicación en el contenedor local; no se verificó esa parte mediante clics en el panel.
- Paquete editorial: exportación real; importación por HTTP autenticado al panel. Las cinco imágenes importadas coincidieron por SHA-256 con los archivos del registro. Los textos traducidos y la procedencia son datos de simulación, no una aprobación de material real.
- Previsualización autenticada administrativa en los tres idiomas y del propietario en español; preparación por HTTP, notificación registrada como `mail_simulated`, aprobación del propietario y publicación por HTTP.
- Ficha pública local: cinco productos, imágenes, textos y canales consultados en español, inglés y portugués; se comprobó respuesta de imágenes y se revisó la presentación en escritorio y móvil.
- Correcciones: almacenamiento persistente para imágenes editoriales de negocios en preproducción; campo administrativo para el enlace de mapa revisado; prioridad del enlace revisado sobre la creación de uno a partir de coordenadas; exclusión de dos fichas ficticias concretas del exportador local a producción.

La ficha ficticia permanece publicada solo en preproducción para inspección y repetición de pruebas. El enlace de mapa y los canales son ficticios y no se verificaron como destinos reales. La regresión de planes pasó; el conjunto público general mantuvo pendientes las pruebas de operador y las dos pruebas generales de navegador. Esta prueba no certifica la configuración de medios ni la entrega de correo real en producción.
