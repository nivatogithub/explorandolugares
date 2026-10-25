# QR descargable de una página comercial

Rama `feature/qr-negocios`, desde main publicado `f4c242aa`.

En Mis negocios, una columna QR muestra un icono «Generar QR» para Página Comercial y Premium vigentes y publicadas. La comprobación utiliza el repositorio público existente: no habilita páginas de planes Gratis/Destacado, borradores, archivados ni destinos privados. Solo se recorren las filas que la sesión puede administrar.

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
