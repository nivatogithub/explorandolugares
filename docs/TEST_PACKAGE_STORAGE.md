# Almacenamiento de pruebas locales

Regla operativa desde el 4 de noviembre de 2026. Aplica a todos los workers de Explorando Lugares. No modifica producción, contenido, imágenes aprobadas, cuentas ni estadísticas.

## Pruebas desechables

Para una comprobación que no requiere revisión humana, usar el ejecutor que construye, prueba y elimina **solo su propia carpeta temporal**:

```sh
python3 scripts/run-test-package.py -- python3 scripts/probar-seo-paquete.py --package '{package}' --output .local/seo-results.json
```

En las ramas con nombres técnicos actualizados el comprobador es `scripts/check-package-seo.py`. El ejecutor reconoce el constructor vigente de esa rama. Cada argumento `{package}` se sustituye sin shell; también se expone `EL_TEST_PACKAGE`. Los informes que se quieran conservar deben escribirse fuera del paquete. La carpeta se retira al terminar, fallar o recibir una interrupción normal. SIGKILL, un fallo del sistema o un corte eléctrico no permiten ejecutar esa limpieza: no prometer limpieza absoluta ante esos casos.

No crear un paquete permanente por cada comparación ni copiar un ambiente completo como respaldo rutinario. Si una prueba ya usa `TemporaryDirectory`, conservar ese ciclo de vida. No ejecutar el constructor interno directamente para eludir los controles.

## Candidatos que sí deben conservarse

`prepare-hosting.py --output ...` (o `preparar-hosting.py` en ramas anteriores) conserva su salida para revisión. Desde esta versión, cada checkout admite hasta **tres paquetes nuevos retenidos**, con un umbral de **1 GiB** antes de admitir otro. Los paquetes existentes antes de esta regla no se borran ni se incorporan como si fueran desechables. El registro solo contabiliza construcciones nuevas; si el cupo está lleno, usar el ejecutor temporal o retirar explícitamente los candidatos propios que ya no sean necesarios.

No borrar automáticamente candidatos de otro worker, entregas servidas, fuentes, ZIP de entrada/salida editorial, originales, soportes, credenciales, bases ni respaldos de datos. No usar enlaces duros para compartir imágenes entre paquetes modificables: una edición podría afectar a otra versión. Este control no es una tarea programada ni un recolector global de archivos.

## Espacio y concurrencia

Antes de copiar, el constructor exige espacio para una estimación conservadora de la construcción más **2 GiB libres**, tanto en el destino como en el volumen temporal. Los constructores actualizados comparten un bloqueo entre worktrees: esperan hasta 60 segundos y, si sigue ocupado, se detienen sin terminar el proceso ajeno. Esto reduce copias pesadas simultáneas; otros programas o herramientas antiguas siguen pudiendo consumir disco.

Una construcción fallida elimina su salida parcial en una carpeta temporal propia. La salida final solo se coloca al completar el paquete; una carpeta no vacía nunca se reemplaza. La política no reinicia contenedores, cambia reservas de Dev1/Dev2, detiene procesos de otros workers ni despliega nada.

## Comprobación

```sh
python3 -m unittest discover -s scripts/tests -p test_build_storage.py -v
python3 -m unittest discover -s scripts/tests -p test_isolated_build.py -v
```

Los cambios locales en ramas activas deben conservar esta política al integrar o renombrar el constructor. Los paquetes creados mediante copias manuales, constructores antiguos o herramientas externas no quedan sujetos automáticamente a este límite.
