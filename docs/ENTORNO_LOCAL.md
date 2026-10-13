# Entorno local y sincronización

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

Abrir http://127.0.0.1:8000/ y detener con Ctrl+C. Si el puerto está ocupado, usar `--port 8001`. El servidor escucha solo en el equipo local, sirve únicamente `ensayos/`, bloquea listados de directorios y enlaces simbólicos hacia fuera, y envía instrucciones para evitar indexación y caché. Es una herramienta de desarrollo, no un servidor de producción.

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
