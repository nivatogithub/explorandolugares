# Paridad de main, cobertura y producción

Reconciliación del 16 de octubre de 2026. No crea etiquetas ni despliega cambios.

## Qué se corrigió

El primer merge solo incorporó cobertura. Aunque `main` y la rama de ampliación apuntaban al mismo commit, el empaquetador completo todavía utilizaba fuentes anteriores a producción: faltaban los cambios de negocios, no copiaba la integración de menú/medición y el limpiador retiraba cartografía. La equivalencia del paquete incremental no demostraba equivalencia de todo el portal.

Se integra la base de `negocios-dinamicos-phm` que genera el resto de archivos publicados. El empaquetador aplica al final la integración verificada y conserva la entrega de cobertura, sus tokens, créditos y cartografía. Se admiten específicamente la base GeoIP privada y su licencia. Las rutas PHP se adaptan al mismo directorio privado del hosting.

No se cambian datos, configuración privada, cookies, cuentas, base de datos, ni contenido público durante esta reconciliación. Las migraciones SQL ya publicadas se empaquetan como archivos privados; no se ejecutan automáticamente.

## Comprobación reproducible

`verificar-paridad-produccion.py` construye el paquete completo y compara SHA-256, rutas nuevas y rutas faltantes con un manifiesto confirmado. Puede construir también otra referencia Git en una copia temporal. No conecta al hosting ni publica.

```text
python3 scripts/verificar-paridad-produccion.py --config RUTA_CONFIG_LOCAL --manifest RUTA_MANIFIESTO_CONFIRMADO --compare-ref feature/ampliacion-cobertura-colombia --report .local/coverage/paridad-main.json
```

El resultado esperado en esta reconciliación es **400 archivos, cero modificados, cero nuevos y cero faltantes**, tanto contra el inventario remoto como entre ambas ramas. El informe se conserva localmente sin credenciales. La comparación se limita al código y recursos inventariados; no incluye datos vivos de la base de datos ni archivos manuales fuera del manifiesto.

La protección futura exige:

- Una omisión del paquete no autoriza borrar archivos publicados. El despliegue se bloquea; un retiro intencional requiere cada ruta exacta con `--allow-remove`.
- Antes de escribir se descarga el manifiesto remoto. Si difiere del local, se detiene la publicación para reconciliar cambios de otros workers.
- La prueba `test_full_release_coverage.py` comprueba que el paquete completo conserve los archivos de integración, cobertura, assets dinámicos, GeoIP y licencia; excluye configuración privada.
- Las validaciones previas comprueban también tokens, versiones y diseño de cobertura.

Estas comprobaciones corresponden a las revisiones comparadas y al hosting configurado. Una rama anterior, otro script de publicación o cambios futuros requieren repetir la comparación. No se garantiza el comportamiento de fuentes que no contengan estas protecciones.

## Corrección posterior incluida en v1.2.0

Se retira de cobertura el enlace de footer a la página de créditos que ya no existe en el portal, conservando las fuentes cartográficas, el crédito de DB-IP y la procedencia/licencias privadas. Las flechas de navegación interna de pauta y guía se cambian a `→`, de acuerdo con las reglas del portal. Se elimina la excepción por SHA-256 de las pruebas de enlaces; la validación vuelve a ser estricta para todas las páginas y flechas, incluidas las decorativas.

Esta corrección cambia únicamente dos archivos desplegables respecto a la producción comprobada: `public/cobertura/colombia/index.html` y `public/assets/cobertura/cobertura.js` (incluido su hash de versión en el HTML). Las dos ramas conservan el mismo paquete. La equivalencia de los 400 archivos descrita antes corresponde a la reconciliación previa; la versión corregida requiere publicar esos dos archivos para actualizar producción. Crear la etiqueta no publica automáticamente.


## Cambio posterior: conteo básico sin cookies

La preparación posterior a v1.2.0 añade el módulo documentado en `CONTEO_BASICO_SIN_COOKIES.md`. Main y la rama de ampliación deben conservar la misma revisión; la etiqueta v1.2.0 permanece inmutable en la versión ya desplegada. El candidato nuevo tiene diferencias intencionales respecto al manifiesto de producción y no se considera publicado por pasar las pruebas locales. Construir ambos paquetes, revisar el delta y activar el módulo básico por separado antes de afirmar que funciona en producción.


## Integración de encabezado, footer e idiomas · 19/10/2026

La rama `idiomas-y-registrarse` contiene diez commits propios de interfaz sobre una base que también heredó auditoría y trabajo de lugares no publicados. Para integrar únicamente la interfaz publicada en main se reproduce esa serie desde main en `integracion/header-footer-idiomas-publicados`, sin marcar como integrados los commits heredados de la auditoría. La rama original se conserva; no se reescribe su remoto.

Serie de origen: 5829d7c, 3a8f524, 682725a, a6b0952, 5467d0e, 04535ee, 032a68d, 0ebc5fb, c024d59 y 7dae6e5. Los conflictos de referencias de recursos en renderer/cobertura se resuelven conservando el contenido de main y actualizando solo versiones. Se sincroniza el hash del script de cobertura con el contenido de main, que aún no incluye la auditoría pendiente.

La comprobación directa de producción contrasta diez recursos (encabezado, menú, acceso, tokens, footer/medición, navegación de idiomas, estilos y .htaccess) contra sus bytes remotos y el manifiesto productivo 0401c73. Las cabeceras y pies generados coinciden con los servidos por HTTPS en inicio español, inglés y portugués. No se publica código ni se modifica la base de datos durante esta integración. Evidencia local privada: `.local/header-main/produccion.json` y `verificacion.log`. Esto acredita este alcance de interfaz, no una paridad completa de preprod con producción.

Los ajustes de cuentas, formulario nacional, upgrades y migraciones 043/044 siguen preparados en preprod y requieren su propia publicación. No se incorporan a main por esta integración.

## 19/10/2026 · Integración de la primera auditoría publicada

Producción recibió el paquete acotado de 38 archivos desde preprod `8c8bb66`, con migraciones aditivas 043 y 044 aplicadas. La validación de producción comprobó 21 rutas, carga de clases y cotización de consulta; conservó cinco cuentas, 319 suscripciones y los registros de las ocho tablas de medición. Respaldo previo `db-20261019-221312-e6b2f82e.sql.gz`, restauración aislada verificada. La contingencia temporal de código se cerró tras contrastar los bytes remotos.

Main incorpora selectivamente `5aaf5d50`, `a1067faa`, `b4b052fc`, `1139fe1a` y `79bc07c3`, preservando el encabezado/footer/idiomas integrado previamente. La construcción de main coincide por SHA256 con los 38 archivos del paquete publicado. No se integra toda la historia de preprod: las revisiones editoriales, lugares y trabajos posteriores siguen separados. Las diferencias de caché de cobertura se resolvieron conservando la página vigente de main. Esta integración no implica un nuevo despliegue ni declara cerrados los demás hallazgos pendientes.
