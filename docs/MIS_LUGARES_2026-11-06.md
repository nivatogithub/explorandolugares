# Mis lugares por conocer

Implementación inicial en `proposal/home-travelers-businesses`, revisión en dev1.

- Guías publicadas: «Guardar lugar» lleva al perfil privado. Sin sesión utiliza el retorno de acceso existente; el usuario confirma el guardado con POST protegido por CSRF.
- Catálogo de Lugares: filtro «Mis lugares por conocer» que reutiliza las tarjetas públicas, búsqueda, región/departamento y paginación. Mi cuenta conserva únicamente el acceso a esa vista. El guardado y la eliminación redirigen al catálogo filtrado. Un mismo lugar no se duplica. Las guías despublicadas no se muestran.
- La portada invita a crear cuenta para guardar lugares; navegar sigue siendo libre.
- Controlador, servicio, selector, escritor y vista separados. Sin JavaScript adicional ni cookies analíticas.
- Migración aditiva obligatoria antes de promover: `063-account-saved-places` para el motor correspondiente. No importa ni modifica estadísticas, cuentas, negocios, imágenes ni catálogo. Relación por IDs estables. Cerrar cuenta conserva la lista privada con el resto de información asociada.

## Validación

`php backend/tests/saved-places.php`: SQLite, migración ejecutada dos veces, deduplicación, separación entre cuentas, borradores y eliminación.

Dev1 MariaDB: tabla creada dos veces, cuentas existentes conservadas; guardar/listar/quitar ensayado dentro de una transacción revertida. Guía de Jericó HTTP 200; sin sesión, acceso al guardado devuelve 303 al inicio de sesión. Constructor público completo ejecutado.

## Recorrido humano

1. Abrir `/lugares/jerico/` en dev1 y elegir Guardar lugar.
2. Iniciar sesión o crear/verificar una cuenta e iniciar sesión.
3. Confirmar Guardar Jericó en Mi cuenta.
4. Volver a otra guía y guardar otro lugar.
5. Abrir «Lugares», activar «Mis lugares por conocer», abrir una guía y quitar un lugar; recargar para comprobar persistencia.

Producción no se ha modificado. Esta entrega requiere aprobación visual y el circuito de integración/migración/despliegue habitual.

## Revisión posterior

- «Guardar lugar» se presenta con un marcador SVG accesible; sin JavaScript adicional.
- `php backend/tests/saved-place-directory.php`: aislamiento entre cuentas, parámetros de identidad ignorados, búsqueda combinada, idiomas, enlaces de paginación, rechazo anónimo y borradores ocultos. Directorio público normal: pruebas de 96 comprobaciones y de 100 lugares sin regresiones.
- Recorrido real en el túnel HTTPS: inicio de sesión con cuenta sintética, confirmación de guardar Jericó, catálogo privado con tarjetas y eliminación de Jericó añadido por la prueba. Se conservó Agustín Codazzi, guardado por el propietario.
- El error de HTTPS se resolvió solo en Apache de dev1, confiando en la cabecera HTTPS únicamente para el hostname temporal explícito de Cloudflare. La aplicación y la protección HTTPS de producción permanecen intactas. Si cambia el enlace, habilitar el nuevo hostname con `python3 dev/enable-review-tunnel.py --host HOST.trycloudflare.com --container explorando-dev1`.
- Monumento Las Guitarras, Agustín Codazzi: `assets/editorial-38/QC-40-2.webp` ya contiene bandas blancas laterales dentro del archivo. No se modificó ni regeneró la imagen aprobada; requiere revisión de encuadre independiente.
