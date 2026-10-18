# Experiencias por ubicación

El simulador exige País → Departamento → Lugar → Experiencia. Solo ofrece experiencias publicadas de destinos publicados y del lugar seleccionado. Cambiar un nivel borra los niveles siguientes y el complemento anterior. Gratis mantiene el complemento deshabilitado.

Implementación local: `scripts/preprod-filtro-experiencias.py` aplica el cambio puntual a las fuentes y la versión preparada de preproducción, conservando cambios ajenos. Controles disponibles en ES/EN/PT. No cambia precios ni realiza compras.

Validación 2026-10-17: navegador local comprobó Medellín (9 experiencias), Jericó (3) y Santa Marta (9); al cambiar lugar el complemento pasa a cero. Destacado + Metro y Metrocable mostró 79.800 COP y experiencia 154; al cambiar a Jericó regresó a 49.900 COP y experiencia 0. Vaciar país deshabilita todos los niveles dependientes. JavaScript y PHP sin errores de sintaxis. No se envió compra.

Evidencia: `evidencias-densidad/simulador-filtro-lugar.png`. Solo preproducción; no publicado en producción.
