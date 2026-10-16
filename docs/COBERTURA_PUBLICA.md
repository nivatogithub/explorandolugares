# Página pública de cobertura de Colombia

Ruta prevista: `/cobertura/colombia/`. Rama: `feature/ampliacion-cobertura-colombia`. Implementación preparada; este documento no confirma publicación.

## Funcionamiento

HTML/CSS/JavaScript sin librerías ni mapas externos en tiempo de visita. Mapa SVG nacional de 33 unidades, detalle municipal por departamento, selección por mapa/listas, búsqueda de destinos, filtros publicado/en preparación/pendiente y URL compartible de departamento/municipio. Adaptación a móvil y teclado; listas equivalentes si falla el mapa y aviso si falta JavaScript.

La API `backend/public/api/cobertura.php` consulta únicamente slugs, departamento y estado de destinos de Colombia. `CoverageService.php` cruza esos datos con un catálogo de campos permitidos derivado del plan. Los borradores ajenos al catálogo no se revelan; no se exponen textos de borradores, notas, contactos, responsables, evidencias privadas ni prioridades individuales. Nunca modifica datos. Sin login ni sesión. Caché pública de 60 segundos; si falla la consulta, porcentajes nulos y aviso, no falso 0%.

Publicado significa que hay una guía con estado público en la base, no calidad total aprobada, negocio verificado o alianza. En preparación procede de un borrador ya conocido en el catálogo o de trabajo señalado en el manifiesto. El destino autónomo Cauca Viejo se asocia al municipio Jericó, pero no hereda su publicación.

## Porcentajes

Universo inicial: 78 candidatos editoriales, no el total de municipios colombianos. Departamento = peso de destinos publicados / peso de destinos de su catálogo. País = media de porcentajes departamentales ponderada por los pesos departamentales. Pesos P1/P2/P3 = 3/2/1. Se indica conteo absoluto y versión del catálogo. Los municipios del mapa sin destinos del catálogo se muestran fuera del catálogo, no se inventan como tareas pendientes ni se suman al denominador editorial. Publicar una guía no colorea todo un departamento como completo; el color significa que tiene al menos una guía disponible y el porcentaje precisa su avance.

La preparación ponderada por gates sigue siendo una métrica privada del plan, diferente de la publicación observada. Esta versión no publica porcentajes de calidad sin auditoría.

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

`python3 scripts/preparar-cobertura.py --config /ruta/sftp.json --manifest /ruta/deployment-manifest.json` valida y prepara 42 archivos (aprox. 568 KB) en `.local/coverage/release`. No se conecta ni modifica hosting. La configuración es la del proyecto, fuera de Git; el paquete no contiene secretos ni `private/config.php`.

Solo tras autorización concreta, el mismo comando con `--deploy` realiza transferencia incremental acotada: requiere rama sin cambios pendientes y manifiesto remoto idéntico al local; conserva todos los archivos ajenos y no elimina, migra, modifica configuración, cambia DNS o reemplaza `.htaccess`, portada, destinos ni administración. Reescribe únicamente las rutas del nuevo endpoint al backend privado. La página HTML se sube al final. No utiliza el despliegue completo histórico de esta base.

Verificación después de una publicación autorizada: HTTP 200 de página/API/CSS/JS/geometrías; consulta de estado real y porcentajes; selección Antioquia/Jericó; enlaces anónimos a guías publicadas; aviso ante fallos; métodos POST rechazados; comprobación de portada/destino/admin conservados. No anunciar publicación funcional por el mero éxito de SFTP.

Acceso inicial por enlace directo. Integrar el enlace en el pie/índice del portal requiere aplicar un cambio mínimo sobre su versión vigente, no reemplazar páginas productivas con los HTML antiguos de `main`. Se mantiene fuera de esta entrega acotada.
