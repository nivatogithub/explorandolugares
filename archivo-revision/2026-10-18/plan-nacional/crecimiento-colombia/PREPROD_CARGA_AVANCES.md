# Carga de avances en preproducción — estado real

Autorización: solicitud del propietario para cargar cada worker y recibir observaciones, sin aprobación editorial ni publicación en producción. Paquetes independientes preparados: Cartagena454f54c (8atractivos/9fotografías), Bogotá d3cd5fc(3/4), Medellín f7a464d(2/3), SantaMarta a5e7ee9(3/4). Tres idiomas; fotografías provisionales, sin IAfinal.

Entorno compartido existente: `../preprod`, rama preprod con cambios ajenos sin commit. Se preservaron esos cambios; no se integró ninguna rama de destino. Los paquetes se copiaron al estado privado `.local/preprod/worker-review`. Respaldo MySQL `before-workers.sql` y snapshots por destino permanecen allí, fuera de Git.

Faltaba extensiónPHPZIP. Se añadió `php-zip` en Containerfile compartido (cambio puntual no commit ajeno). Reconstrucción completa falló por procesoMySQL postinst terminado durante construcción; no afectó base vigente. Reparación incremental `infra/preprod/Containerfile.zip` añadió ZIP a imagen local existente; arranque completado conservando volumen.

Primera importación detectó error real del servicio: INSERT de destino nuevo no proporcionaba internal_research_json requerido por MySQL estricto. Corregido en DestinationAdminService del coordinador y fuente/runtime local preprod, sin alterar otros archivos fuente. Script de carga también corrige conservación de IDs entre primer guardado borrador y segundo guardado local visible. No se cambió esquema ni se borraron destinos.

Última ejecución para completar carga fue rechazada por el usuario en diálogo de permisos. Se detuvo: NO repetir por otro mecanismo. Cartagena/Bogotá/SantaMarta no fueron creados en el intento fallido; Medellín recibió contenido parcial en borrador antes del segundo guardado fallido. Media provisional quedó en almacenamiento local. No afirmar que las cuatro páginas estén listas ni publicar URLs como verificadas.

Pendiente: permiso para ejecución local requerida, ejecutar cargas seriales con script, verificar HTTP/idiomas/imágenes y diseño en navegador interno, actualizar checklist y avisar individualmente. Cualquier estado published que se utilice es exclusivamente en MySQLloopback de preprod; nunca aprobación editorial ni autorización de producción. No usar Chrome.

## Revisión tras cambio de motor

La fuente nueva configura PHP8.3.33/MariaDB10.11.19, volumen MariaDB separado y extensiónZIP compilada en PHP. No repetir receta incremental Ubuntu/MySQL sobre la imagen oficialPHP. En la consulta de esta revisión el contenedor activo aún devuelve PHP8.3.6/MySQL8.0.46; ZIP/ZipArchive disponibles. Endpoint nuevo de paridad devuelve404. Por tanto el cambio de motor está en configuración, sin ejecución confirmada en esta comprobación. Ver PREPROD_REVISION_MARIADB.json.

La corrección de DestinationAdminService sigue proporcionando explícitamente internal_research_json al crear destino; no depende del nombre del motor. El contenedor activo tiene campoNOTNULL sinDEFAULT y modoestricto. La migración005 define LONGTEXT NOT NULL sin DEFAULT. Se conserva corrección; compatibilidad real de guardado en nuevoMariaDB debe validarse cuando ese contenedor estéactivo. El controlador PDO y DSN mysql también son normales para MariaDB; no cambiarlos solo por nombre. No se reconstruyó entorno, cambió volumen ni reintentaron importaciones en esta revisión.

## Actualización: entorno MariaDB arriba, cargas completadas

Tras confirmación del usuario se verificaron MariaDB 10.11.19, PHP 8.3.33 y ZIP habilitado. Se respaldó la base local y se importaron los cuatro paquetes provisionales: Cartagena 8 atractivos, Bogotá 3, Medellín 2 y Santa Marta 3. Las 12 páginas ES/EN/PT-BR y sus imágenes pasaron comprobación HTTP; revisión visual en navegador interno. Los recibos y hashes están en PREPROD_DESTINOS_CARGADOS.json. No se modificó producción ni se aprobaron contenidos. La incidencia previa queda como antecedente resuelto.

## Bogotá v3: ocho atractivos e historia independiente

Cargado paquete b38de58fc0f334cef4a14b05df03d753f9ebd26c05cf9eb26292b86fcc4daa37 el17oct04:24UTC. Ocho tarjetas, portada Colpatria y Monserrate aéreo. Imagenhistoria por sidecarlocal con hashes/verificación; usa rolehistory ya existente, sin migraciónBD ni cambio del esquemaZIPv2. Importador conserva historiaexistente al recargar sin sidecar. Diez imágenes distintas, tresidiomas, bytesHTTP y renderizadoverificados. Hubo reinicio del entorno por trabajosajenos; prueba finalsin cortes e imagenhistoria decodificada900x1200. No producción ni aprobación editorial.

## Actualización 17/10/2026 · Medellín y Santa Marta

Sustituye los recuentos anteriores de dos y tres tarjetas: ambos destinos tienen ocho principales importados. Portada, ocho fichas e historia con diez fuentes visuales distintas por destino; comprobación de ES/EN/PT-BR, HTTP y hashes en verificacion-preprod-v3.json (Medellín) y v4 (Santa Marta), dentro de sus entregas. Navegador interno: Cargar más lugares despliega las ocho, imágenes decodificadas. Fotografías reales provisionales, ninguna aprobación de originales ni ilustración inferida. Medellín conserva limitación de panorámica histórica de baja resolución; Acuario Santa Marta requiere mejor referencia. No se modificó producción. Recibos exactos en PREPROD_DESTINOS_CARGADOS.json.
