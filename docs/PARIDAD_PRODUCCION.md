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

## Defecto previo conservado deliberadamente

La página publicada de cobertura contiene un enlace de footer a `creditos-fotograficos/index.html`, pero ese recurso ya no aparece en el paquete público. No se elimina el enlace durante esta reconciliación para conservar exactamente el HTML publicado. La prueba de enlaces registra únicamente esta excepción, en esta ruta y con SHA-256 fijo; cualquier cambio a la página invalida la excepción. Debe resolverse como corrección posterior y verificada, sin eliminar procedencia ni atribuciones obligatorias.

Las pruebas resuelven correctamente rutas que comienzan en `/` desde la raíz pública y distinguen las flechas decorativas `aria-hidden` del texto accesible de los enlaces. No se modifica el menú ni las flechas de producción para adaptar las fuentes a una prueba antigua.
