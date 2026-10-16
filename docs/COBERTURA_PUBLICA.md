# Página pública de cobertura de Colombia

Ruta prevista: `/cobertura/colombia/`. Rama: `feature/ampliacion-cobertura-colombia`. Implementación preparada; este documento no confirma publicación.

## Funcionamiento

HTML/CSS/JavaScript sin librerías ni mapas externos en tiempo de visita. Mapa SVG nacional de 33 unidades, detalle municipal por departamento, selección por mapa/listas, búsqueda de destinos, filtros publicado/en preparación/pendiente y URL compartible de departamento/municipio. Adaptación a móvil y teclado; listas equivalentes si falla el mapa y aviso si falta JavaScript.

La API `backend/public/api/cobertura.php` consulta únicamente slugs, departamento y estado de destinos de Colombia. `CoverageService.php` cruza esos datos con un catálogo de campos permitidos derivado del plan. Los borradores ajenos al catálogo no se revelan; no se exponen textos de borradores, notas, contactos, responsables, evidencias privadas ni prioridades individuales. Nunca modifica datos. Sin login ni sesión. Caché pública de 60 segundos; si falla la consulta, porcentajes nulos y aviso, no falso 0%.

Publicado significa que hay una guía con estado público en la base, no calidad total aprobada, negocio verificado o alianza. En preparación procede de un borrador ya conocido en el catálogo o de trabajo señalado en el manifiesto. El destino autónomo Cauca Viejo se asocia al municipio Jericó, pero no hereda su publicación.

## Conteos y alcance nacional

Corrección vigente: se cuentan todas las guías públicas de Colombia de la base, no únicamente las que coinciden con los 78 candidatos iniciales. El alcance está definido: todos los municipios colombianos y destinos turísticos con identidad propia. La reconciliación numérica del inventario sigue siendo trabajo técnico pendiente; no se muestra porcentaje ni fracción publicada contra el listado inicial. El catálogo inicial solo organiza investigación y el detalle del mapa, nunca representa el inventario turístico completo del país.

La cobertura ponderada por prioridades/gates permanece como herramienta privada del plan. No se publica como cobertura nacional hasta definir un universo completo y verificable. Los municipios sin destinos en el listado se muestran fuera del catálogo de seguimiento.

## Audiencia y participación comercial

Sección entre los conteos y el mapa: sesiones sin login, visitantes únicos estimados, viajeros registrados/activos, vistas de fichas y clics de contacto. Periodos 30/90 días, contexto por departamento/municipio, espacios de países y evolución. Cuentas registradas describen la comunidad global, no se atribuyen al municipio seleccionado. La API v2 declara `audience.status=not_configured`, métricas/serie/países nulos. No hay colector de visitas, integración de analítica ni cómputo de cuentas conectados: se muestra “Medición aún no habilitada”, sin datos ficticios ni cero inventado. Los campos solo reservan la interfaz; la instrumentación se implementará como tarea propia con fuente, privacidad y pruebas.

Invitación a pautar conduce al formulario existente de contacto; no contrata pauta ni cobra. La ficha gratuita conserva un enlace separado. No se afirma crecimiento hasta tener series comparables. Visual actualizado a acento naranja y azul oscuro a partir del acento `#d84e27` del portal más reciente revisado, con mapa azul/ámbar sin verdes.

## Cartografía y licencia

DANE, MGN 2018, copia adaptada de `caticoa3/colombia_mapa`, con redondeo a cuatro decimales. 33 unidades departamentales y 1122 unidades de la capa municipal histórica; no se presenta como censo administrativo vigente. Antioquia contiene 125 geometrías. Créditos, URLs de origen, modificaciones y hashes en `backend/public/assets/cobertura/procedencia.json`. Datos DANE bajo condiciones CC BY 4.0 del Geoportal: https://geoportal.dane.gov.co/acerca-del-geoportal/licencia-y-condiciones-de-uso/ . La procedencia DANE de la copia está documentada en https://github.com/caticoa3/colombia_mapa .

Se intentó descargar DIVIPOLA MGN 2025 desde su servicio oficial; devolvió errores 500/403. No se sortearon restricciones. La versión 2018 se identifica tanto en la página como en la procedencia; sustituirla por cartografía actualizada exige descarga autorizada, reconciliación de códigos y nuevas pruebas. El mapa es de orientación, no de delimitación jurídica.

## Actualizar y probar

- Cambios de publicación/draft en destinos ya presentes se reflejan al consultar la página, con hasta 60 segundos de caché. Sincronizar solo el catálogo en Git no publica ninguna guía.
- Al añadir lugares o cambiar sus relaciones, ejecutar `python3 scripts/generar-catalogo-cobertura.py`, revisar asociaciones con la cartografía y desplegar `app/content/coverage-plan.json` junto al cambio aprobado. No enviar el inventario ni los manifiestos completos al navegador.
- Para renovar mapas: `python3 scripts/importar-cartografia-cobertura.py`; conserva procedencia y hashes. La opción `--cached` reproduce la adaptación desde las descargas locales.
- Pruebas: `php backend/tests/coverage-service.php`, `python3 backend/tests/test_coverage_package.py`, `node backend/tests/coverage.browser.cjs` con servidor de prueba activo. El módulo Playwright se puede indicar mediante `PLAYWRIGHT_MODULE` y el origen mediante `COVERAGE_BASE_URL`; las pruebas del navegador usan datos sintéticos.
- Vista previa: `EL_COVERAGE_TEST_CONFIG=/ruta/config-aislada.php php -S 127.0.0.1:8036 scripts/preview-cobertura.php`. El override solo se acepta en el servidor local. Se muestra aviso de datos de prueba. Nunca usar base productiva para la vista previa.

## Entrega preparada

`python3 scripts/preparar-cobertura.py --config /ruta/sftp.json --manifest /ruta/deployment-manifest.json` valida y prepara 43 archivos (aprox. 575 KB) en `.local/coverage/release`. No se conecta ni modifica hosting. La configuración es la del proyecto, fuera de Git; el paquete no contiene secretos ni `private/config.php`.

Solo tras autorización concreta, el mismo comando con `--deploy` realiza transferencia incremental acotada: requiere rama sin cambios pendientes y manifiesto remoto idéntico al local; conserva todos los archivos ajenos y no elimina, migra, modifica configuración, cambia DNS o reemplaza `.htaccess`, portada, destinos ni administración. Reescribe únicamente las rutas del nuevo endpoint al backend privado. La página HTML se sube al final. No utiliza el despliegue completo histórico de esta base.

Verificación después de una publicación autorizada: HTTP 200 de página/API/CSS/JS/geometrías; consulta del conteo real de publicaciones y alcance Colombia completa; selección Antioquia/Jericó; enlaces anónimos a guías publicadas; aviso ante fallos; métodos POST rechazados; comprobación de portada/destino/admin conservados. No anunciar publicación funcional por el mero éxito de SFTP.

Acceso inicial por enlace directo. Integrar el enlace en el pie/índice del portal requiere aplicar un cambio mínimo sobre su versión vigente, no reemplazar páginas productivas con los HTML antiguos de `main`. Se mantiene fuera de esta entrega acotada.

Selectores: se reutiliza `select-flotante.js` del portal, empaquetado bajo cobertura para evitar afectar componentes globales. Menús de altura acotada, desplazamiento, selección visible y teclado; título departamental solo en el panel del mapa.

La sección pública explica la estrategia de ampliación: tres grupos independientes de departamentos/destinos, trabajo por lotes, preparación y revisión de cada guía, publicación progresiva y actualización. “Pendiente de incorporar” reemplaza “Fuera del catálogo” en los municipios aún sin destinos en el seguimiento inicial.
