# Entorno local y sincronización

## Entorno vigente de preproducción

Usar la copia fija `trabajo/preprod`, rama `preprod`, y `python3 scripts/preprod-local.py up`. Revisión en http://127.0.0.1:8088/. Arranque, parada, persistencia y proceso de aprobación: `PREPRODUCCION.md`. El preview SQLite descrito abajo sigue siendo una herramienta auxiliar; no sustituye la aprobación del paquete Apache/MySQL.

## Rutas vigentes: iguales a producción

Desde el 16 de octubre de 2026, el portal se prueba desde la raíz del servidor
local, sin prefijo `/v1`. Solo cambia el origen:
`http://127.0.0.1:8011/destinos/jerico/` corresponde a
`https://explorandolugares.com/destinos/jerico/`.

Para probar PHP, destinos, negocios y panel con SQLite local:

```sh
PORT=8011 sh scripts/preview-destinos-dinamicos.sh
```

El arranque prepara `.local/preview-portal/public` con el empaquetador del hosting
y sirve esa raíz con un router local que reproduce sus rutas amigables. La base,
configuración y medios de prueba permanecen locales. No modifica producción.
Las plantillas y el panel ya no detectan `/v1` ni usan `EL_SITE_BASE` para construir
enlaces. El alias antiguo no se mantiene como otra versión del portal.

`ensayos/v1` sigue siendo una ubicación interna de fuentes históricas utilizadas
por los generadores; su nombre no se publica en las direcciones. El visor estático
`scripts/ensayar.py` también sirve esa carpeta directamente en `/`, pero no ejecuta
PHP y no sustituye la comprobación completa anterior.

Los apartados siguientes conservan el historial inicial del entorno. La regla
vigente es probar el portal en la raíz y no crear iteraciones con URL diferentes.

Preparado el 11 de octubre de 2026 desde `origin/main`, commit `82c27c1bee64537e215bf9c87f021d6e114279b6`.
Repositorio: https://github.com/nivatogithub/explorandolugares
Rama inicial de trabajo: `trabajo/entorno-local`.

## Referencias y límites

Leer primero `PROYECTO_INDEPENDIENTE.md`, `AGENTS.md` y `design/pasaporte-local/BORRADOR.md`. El espejo `sources/` del proyecto ChatGPT permanece intacto; trabajar en esta copia Git. `/mnt/data/PROYECTO_INDEPENDIENTE.md` no existe en este equipo; la referencia se encontró en el espejo y también en la raíz del repositorio.

`design/pasaporte-local/` y la etiqueta `borrador-inicial-v0.1` conservan una exploración no aprobada. `public/` y los ZIP son históricos. No se ha elegido una identidad nueva ni configurado despliegue. No reutilizar contexto, estilos, contactos o recursos de Paradigma Solutions.

## Probar en este equipo

Desde la raíz de esta copia, con Python 3.9 o posterior:

```sh
python3 scripts/ensayar.py
```

Abrir http://127.0.0.1:8000/ y detener con Ctrl+C. Si el puerto está ocupado, usar `--port 8001`. El servidor escucha solo en el equipo local, sirve únicamente `ensayos/v1/`, bloquea listados de directorios y enlaces simbólicos hacia fuera, y envía instrucciones para evitar indexación y caché. Es una herramienta de desarrollo, no un servidor de producción.

Crear cada iteración en `ensayos/`, documentarla allí y agregar su enlace a la entrada. El servidor no expone `.git`, documentos de la raíz ni la entrega histórica. Para consultar el borrador, abrir su archivo local por separado.

## Sincronizar sin perder trabajo

Antes de comenzar:

```sh
git status --short --branch
git remote -v
git fetch origin
git log --oneline --left-right HEAD...origin/main
```

Confirmar que `origin` es el repositorio indicado arriba. Si hay cambios locales, revisarlos y guardarlos en un commit antes de integrar cambios remotos. Para otra tarea, crear una rama propia desde una base actualizada; evitar trabajar directamente sobre `main`.

Con el árbol limpio, integrar `origin/main` en la rama de trabajo con `git merge origin/main`. Si aparecen conflictos, resolverlos y revisar el resultado antes de continuar. No usar `reset --hard`, `clean` ni push forzado para solucionar divergencias.

Para guardar y compartir una iteración, revisar `git diff`, agregar archivos concretos con `git add`, crear un commit y, cuando se decida compartirlo, ejecutar `git push -u origin trabajo/entorno-local`. La subida requiere permisos de escritura en GitHub, todavía no comprobados. Este entorno no guarda credenciales y no realiza subidas automáticas.

Revisar la propuesta mediante un pull request antes de integrarla en `main`. Integrar código no constituye aprobación de identidad ni autorización para publicar. No subir ZIP históricos al hosting como resultado de un ensayo.
