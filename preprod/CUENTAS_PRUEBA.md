# Cuentas persistentes de prueba

Solo para el entorno local `http://127.0.0.1:8088/`. No se publican ni se crean en producción.

| Perfil | Correo inicial | Qué permite probar |
|---|---|---|
| Viajero o turista | `viajero@pruebas.invalid` | Inicio/cierre de sesión, perfil de viajero, recuperación de contraseña y rechazo de acceso administrativo. |
| Negocio | `negocio@pruebas.invalid` | Portal comercial con cuatro negocios ficticios asociados, suscripciones, soportes, estadísticas propias y solicitudes de cancelación cuando haya una suscripción paga vigente. |
| Administrador | `administrador@pruebas.invalid` | Panel completo, revisión de solicitudes y pagos, costos, métricas, sistema y base de datos. |

Los correos `.invalid` son ficticios y no reciben mensajes reales. Las pruebas de recuperación usan el buzón local. La cuenta personal `admin@explorandolugares.com` se conserva por separado y este mecanismo no la modifica.

## Ver los accesos de este equipo

Las contraseñas se generan una vez por equipo y se guardan, junto a los correos actuales, en:

- `.local/preprod/CUENTAS_PRUEBA.md`: tabla de accesos para abrir y consultar.
- `.local/preprod/test-accounts.json`: configuración privada para scripts.

Ambos archivos tienen permisos 0600, están ignorados por Git y quedan fuera del directorio público y del paquete de producción. No es necesario reutilizar la contraseña ni el hash de producción. El repositorio contiene el mecanismo y la documentación, no las claves de ningún equipo.

## Crear o comprobar

`subir.sh` y `actualizar.sh` preparan las cuentas al terminar el arranque. También puedes ejecutar:

```sh
./preprod/cuentas-prueba.sh
```

Repetirlo no duplica cuentas ni los negocios, no cambia contraseñas existentes y no borra las pruebas realizadas. Si una cuenta ficticia fue eliminada, vuelve a crearla con la clave local documentada. Usa identificadores reservados de estas cuentas, y rechaza colisiones con otros registros.

Apagar/encender o reconstruir el contenedor conserva las cuentas en el volumen de MariaDB. En otro equipo, tras instalar el entorno, el mismo comando crea los tres perfiles con nuevas claves locales. Si se elimina deliberadamente el volumen, el comando los recrea desde cero.

## Restablecer acceso y permisos

```sh
./preprod/cuentas-prueba.sh --restablecer
```

Restaura correo, nombre, tipo de cuenta, estado activo, permisos y la contraseña local documentada **solo de estos tres perfiles ficticios**. Invalida sus enlaces pendientes de recuperación. No modifica otras cuentas, no elimina costos, pagos, documentos ni negocios y no restablece el contenido o la suscripción dlos negocios de prueba. Tampoco cierra por fuerza las sesiones PHP existentes.

Si cambias una contraseña desde el portal, el archivo conserva la clave inicial hasta restablecerla con este comando. Si cambias el correo, ejecutar el comando sin opciones actualiza el correo mostrado en la documentación local.

## Negocio de prueba

Se conservan cuatro negocios ficticios de Jericó, asociados al mismo perfil Negocio como propietario:

| Negocio | Suscripción inicial | Frecuencia | Estado inicial |
|---|---|---|---|
| Negocio Ficticio De Prueba — Preproducción | Gratis (`free`) | No aplica | Pendiente de publicación |
| Negocio Ficticio Destacado — Preproducción | Destacado / Tarjeta Ampliada (`featured`) | Mensual | Pendiente de revisión de pago |
| Negocio Ficticio Página Comercial — Preproducción | Página Comercial (`page`) | Mensual | Pendiente de revisión de pago |
| Negocio Ficticio Premium — Preproducción | Premium (`page_premium`) | Mensual | Pendiente de revisión de pago |

Los cuatro comienzan en borrador. Las tarifas pagas se toman del catálogo vigente: no se inventan precios ni se marcan pagos como revisados. La preparación normal conserva cualquier cambio posterior de suscripción o contenido que hagas; no lo deshace en cada arranque. Es un dato sintético: no representa un comercio real, ni una autorización o documento auténtico.

El administrador puede modificarlo y probar otros niveles de suscripción. Para probar cancelaciones debe existir primero una suscripción paga vigente; la preparación no simula pagos, cobros ni renovaciones. Mantenerlo en borrador salvo que la prueba local requiera publicarlo; los cambios del negocio se conservan entre arranques.

Si se asigna ese negocio ficticio a otra cuenta durante una prueba, el restablecimiento no la reemplaza silenciosamente: se detiene para revisar esa asociación.

## Comprobación automatizada

```sh
python3 preprod/probar-cuentas-persistentes.py
```

Verifica los tres inicios de sesión, los permisos, los cuatro negocios asociados y que preparar las cuentas de nuevo conserva sus identificadores. No elimina estas cuentas ni envía correos. A diferencia de las pruebas de integración aisladas, estos perfiles quedan disponibles para uso manual.
