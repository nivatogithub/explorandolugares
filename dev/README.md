# Ambientes fijos de Explorando Lugares

Solo existen dev1 (127.0.0.1:8086), dev2 (127.0.0.1:8087), preprod (127.0.0.1:8088) y producción. Contenedores OCI compatibles con Docker; en este equipo se ejecutan con Podman ya instalado. No instalar un segundo motor ni crear otro servidor PHP/Python de revisión.

Antes de usar un dev, reservarlo con `python3 dev/ambientes.py acquire --owner <identificador-de-tarea> --wait`. El primer dev libre se asigna de forma atómica; si ambos están ocupados, espera. Para consultar usar `status`. Para liberar, `release --owner <mismo-identificador> --slot 1` (o 2). Una reserva sigue ocupada durante la revisión humana: terminar el proceso del worker no la libera. No apropiarse de reservas ajenas ni usar caducidad automática. Todos los worktrees comparten el mismo registro por equipo; `EL_ENV_STATE` solo lo cambia el coordinador para la configuración global, nunca un worker para evadir reservas.

Los puertos no cambian. Los nombres son explorando-dev1, explorando-dev2 y explorando-preprod. Cada dev tiene base, volumen, privados y medios propios; nunca monta el volumen de preprod ni usa credenciales/datos de producción. Redes internas, correo deshabilitado, noindex y escucha loopback. Liberar una reserva no borra datos ni cambia la página mientras el usuario la revisa.

Preprod se preserva. Solo el proceso aprobado actualiza preprod y producción. Crear los dos contenedores no autoriza publicar destinos. Las páginas nuevas siguen como propuestas hasta aprobación humana. Eliminar ambientes sobrantes se limita al portal, nunca a otros proyectos, repositorios, archivos fuente o datos vivos.
