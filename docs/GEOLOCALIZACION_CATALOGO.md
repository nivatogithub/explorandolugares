# Catálogo georreferenciado — 24/10/2026

Entrega autorizada por el propietario: 35 referencias centrales de destinos; 183 atracciones con punto y la excepción Paisaje rural de El Retiro, sin coordenadas ni mapa. Se conservan las referencias que el propietario aprobó, distinguiendo sitio, acceso y área. Las coordenadas aproximadas de un área no certifican una entrada.

La migración identificada en `content/geolocation/approved-20261024.json` actualiza solamente coordenadas y siete enlaces aprobados, archiva reversiblemente 235 fichas públicas importadas sin punto comprobado y agrega siete alternativas Gratis. Las categorías sustituidas quedan con dos servicios y tres licoreras en Guatapé, y dos licoreras en Jericó. Las 95 combinaciones antes pobladas permanecen pobladas. Los nombres propios se conservan en los tres idiomas; no se inventan descripciones ni contactos.

No se borran registros, IDs, cuentas, relaciones, imágenes ni estadísticas. El borrador de propietario se conserva aparte. La aplicación rechaza fichas reclamadas, con membresías, planes no Gratis, identidades distintas o datos que cambiaron desde la revisión. Todo el cambio de datos es una transacción; una precondición fallida revierte esa ejecución.

Las fuentes canónicas, el catálogo inicial, los 20 snapshots territoriales y `reviewed-map-links.json` conservan los mismos cambios. Los public_id de fichas semilla antiguas se reconciliaron por destino+slug con los existentes en producción, sin cambiar IDs productivos. Los IDs numéricos locales no son identidades remotas.

## Prevención de regresiones

- `GeolocationPolicy` bloquea la publicación de sitios físicos sin el par completo de coordenadas y la reincorporación de identidades retiradas. Experiencias y excepciones documentadas sin sede fija mantienen su tratamiento aprobado.
- Administración e importación inicial de destinos exigen coordenadas al publicar; se conservan como tales los borradores incompletos. Los snapshots anteriores a esta política se rechazan; los nuevos deben contener coordenadas y no pueden marcar retirados como publicados.
- `test_geolocation_catalog.py` comprueba catálogo, snapshots, retiradas y excepción rural; `geolocation-policy.php` comprueba bloqueo por coordenadas, retiradas y alcance de excepciones. El circuito de despliegue ejecuta ambas.
- Las importaciones de negocios siguen creando borradores privados; no pueden saltarse la barrera central de publicación.

Estos controles evitan la recurrencia por los flujos cubiertos. No garantizan que nadie pueda eludirlos con SQL manual o código anterior fuera del proceso.

## Operación

1. Respaldar con `scripts/respaldo-base-datos.py --create` y verificar restauración aislada. No restaurar una base anterior sobre producción activa para revertir código.
2. Ensayar `scripts/php/ApprovedGeolocationRelease.php` dos veces sobre copia aislada; segunda ejecución sin cambios, identidades protegidas y 76 tablas no afectadas.
3. Ejecutar `scripts/migrar-geolocalizacion.py --report INFORME` para preflight transaccional con rollback. Aplicar con `--apply --approved-preprod COMMIT --preflight INFORME --backup RESPALDO --report RECIBO` únicamente desde preprod limpio. La herramienta no acepta SQL ni otros paquetes.
4. Actualizar preproducción por identidades estables, conservando cuentas y datos locales. Validar ambos ambientes; publicar incrementalmente el código y los catálogos aprobados, sin tocar imágenes.
5. Verificar después los puntos, enlaces ES/EN/PT-BR, categorías y preservación de datos protegidos. Sincronizar main solo tras validar producción y su manifiesto completo. Conservar el recibo privado fuera de Git y limpiar temporales propios.
