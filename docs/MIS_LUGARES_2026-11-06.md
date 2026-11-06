# Mis lugares por conocer

Implementación inicial en `proposal/home-travelers-businesses`, revisión en dev1.

- Guías publicadas: «Guardar lugar» lleva al perfil privado. Sin sesión utiliza el retorno de acceso existente; el usuario confirma el guardado con POST protegido por CSRF.
- Mi cuenta: lista privada persistente, enlace a la guía y eliminación propia. Un mismo lugar no se duplica. Las guías despublicadas no se muestran.
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
5. En Mi cuenta, abrir una guía y quitar un lugar; recargar para comprobar persistencia.

Producción no se ha modificado. Esta entrega requiere aprobación visual y el circuito de integración/migración/despliegue habitual.
