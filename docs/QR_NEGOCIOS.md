# QR descargable de una página comercial

Rama `feature/qr-negocios`, desde main publicado `f4c242aa`.

En Mis negocios, las acciones de cada fila incluyen un icono «Generar QR» para Página Comercial y Premium vigentes y publicadas. Reutiliza el control compacto de 32 px en escritorio y 44 px táctiles, sin columna adicional ni otro listado del negocio. La comprobación utiliza el repositorio público existente: no habilita páginas de planes Gratis/Destacado, borradores, archivados ni destinos privados. Solo se recorren las filas que la sesión puede administrar.

El icono abre un `dialog` modal nativo con el resto de la interfaz bloqueado, vista previa, enlace público y descarga PNG. Cerrar/Escape devuelve el foco y restaura el desplazamiento. El QR contiene el enlace estable por public_id; la página ya resuelve el alias Premium vigente. La descarga es PNG 2048×2048, negro sobre blanco, módulos enteros y margen mínimo de cuatro módulos. No requiere IA, API, nueva tabla ni almacenamiento de archivos en el servidor.

Generador local: Project Nayuki, QR Code generator v1.8.0, ES6, MIT; licencia íntegra en el archivo. Fuente: https://github.com/nayuki/QR-Code-generator/releases/tag/v1.8.0. SHA-256: `6a1116192ed1dd67fa1bf31e77f5817103d71c23bbac24c382e698b7668bdd01`.

Lector independiente solo para pruebas: jsQR 1.4.0, Apache-2.0, https://registry.npmjs.org/jsqr/-/jsqr-1.4.0.tgz. Su código y licencia están en backend/tests/vendor y no se empaquetan para producción. La prueba decodifica los píxeles generados y verifica el destino, la descarga y el comportamiento del modal. La prueba PHP verifica publicación y vigencia.

Esta implementación no autoriza desplegar a producción. Los dos dev están reservados por otros trabajos; no sustituirlos ni usar preprod como tercer dev para la revisión visual.

## Validación de esta rama

- PHP: los dos planes con página admiten QR; Gratis/Destacado, borradores, archivos, vigencia vencida y destinos privados quedan excluidos.
- Node: un lector QR independiente decodificó los píxeles 2048×2048; verificadas descarga PNG, margen, opacidad, errores de exportación, devolución del foco y cierre durante una descarga pendiente.
- Recorrido HTTP de registro, pago, revisión editorial y publicación: aprobado desde fuentes y desde paquete construido; el QR aparece en la cuenta del propietario tras publicar Premium. Sin correos reales ni acceso a datos productivos.
- Paquete: recursos versionados; biblioteca de generación con hash de origen; lector de pruebas excluido.
- Pendiente: revisión visual en escritorio y celular cuando un dev autorizado esté disponible. No se ha integrado en preprod ni desplegado a producción.

## Integración en flujo de registro — 24/10/2026

- Incorporado el commit específico `cc3eb3386a68ae644f0bac59b8bfa6fef1a298a9` de `feature/qr-negocios` mediante cherry-pick con procedencia registrada. No se fusionan sus antecedentes de catálogo/geolocalización ni la versión anterior del registro.
- Conservado el grid único actual, borradores recuperables, pagos, cupones por lugar y aprobación del propietario. El conflicto de dashboard se resuelve por función: el QR se ofrece dentro de las acciones existentes, no restaura paneles antiguos ni estadísticas duplicadas.
- La biblioteca y licencia del generador permanecen idénticas al commit original; el lector independiente queda limitado a pruebas. No se necesita una migración adicional ni escrituras en los negocios para generar o descargar el QR.
- Esta integración de código no autoriza ni realiza un nuevo despliegue de Dev2, preprod o producción. Se conservan los cambios ajenos en ensayos sin incorporarlos al commit.
