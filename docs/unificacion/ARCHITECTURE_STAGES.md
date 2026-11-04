# Etapas verificables de arquitectura

Rama de trabajo: `refactor/presentacion-rendimiento-20261021`. Revisión local: dev1, `http://127.0.0.1:8086/lugares/jerico/`. Fechas en America/Bogota. Las etapas no autorizan integración, push ni despliegue a producción.

## Etapa 1 — autorización de previews — verificada el 2 de noviembre de 2026

Problema reproducido en una copia SQLite sintética del paquete anterior: una cuenta desactivada conservaba una bandera administrativa en su sesión y recibía el contenido de borrador con HTTP 200. La respuesta esperada era 403.

Cambio: administración y preview componen las mismas comprobaciones mediante `AccountSessionValidator`. Se verifica credencial, estado de cuenta, identidad, rol y caducidad antes de consultar el borrador. Los fallos de validación invalidan la sesión. El modo administrativo antiguo se conserva únicamente cuando la configuración efectiva permite cuentas no unificadas; los visitantes públicos no adquieren consultas de validación de sesión.

Evidencia:

- Pruebas unitarias de sesión, autorización, recuperación de contraseña y cierre de cuenta.
- Nueve escenarios con el controlador real, sesiones PHP reales y copias SQLite desechables: administrador válido, visitante, sesión vencida, cuenta desactivada, rol retirado, contraseña cambiada, identidad incoherente, bandera administrativa sin cuenta y borrador solicitado como página pública.
- Siete escenarios de validación en MariaDB dentro de transacciones revertidas; cuentas, roles e historial conservados.
- 24 pruebas Python del flujo de lugares y fronteras de autorización/consultas; 72 respuestas públicas, 69 páginas SEO y 19 controladores privados renderizados.
- Dev1 Apache/MariaDB: 60 rutas públicas con HTTP 200; 49 alias con respuesta y protección conservadas, 23 recursos con los mismos bytes.

Paquete: `.local/architecture-stage-1-candidate/`. Informes y comprobante del manifiesto: `.local/architecture-stage-1/`. Se conservó un respaldo independiente de código antes de actualizar dev1. No se añadieron migraciones ni se modificaron datos/configuración del entorno.

## Etapa 2 — políticas de publicación — verificada el 2 de noviembre de 2026

Cambio: `PlacePublicationPolicy` recibe datos normalizados y expresa cuatro decisiones: alta inicial en borrador, rechazo de avisos editoriales internos al publicar, traducciones necesarias de atractivos publicados y contenido mínimo del lugar. `PlaceAdminService` conserva la coordinación y delega esas decisiones. La geolocalización conserva sus validaciones y excepciones existentes. Condiciones, orden de comprobación y mensajes preservados.

El paquete cambia únicamente dos archivos ejecutables frente a la etapa 1 verificada: `app/PlaceAdminService.php` y `app/PlacePublicationPolicy.php`. No cambian consultas, escrituras, archivos de imágenes ni transacciones.

Evidencia:

- 93 casos de política, incluidos avisos en español/inglés/portugués, contenido incompleto por idioma y ausencia de atractivos publicados.
- Las mismas operaciones administrativas ejecutadas contra los paquetes de las etapas 1 y 2 en bases SQLite en memoria: listado/edición por ID, creación en borrador, validación de publicación, archivado y borrado seguro.
- Regresión del traslado de negocios: IDs y relaciones, rechazo de identidades no autorizadas, colisiones, rollback y protección contra repetición.
- 32 pruebas Python; 72 respuestas públicas, 69 páginas SEO, 19 controladores privados y los nueve casos de autorización del controlador.
- Dev1: las 60 rutas siguen respondiendo 200; los 60 modelos públicos y 60 previews, administración, listados, portada y sitemap conservan sus datos en MariaDB. Los siete casos de sesión de la etapa 1 siguen pasando.

Paquete: `.local/architecture-stage-2-candidate/`. Informes y comprobante del manifiesto: `.local/architecture-stage-2/`. Se conservó el código de la etapa 1 antes de actualizar dev1. Producción y preproducción intactas.

## Etapa 3 — almacenamiento de imágenes — verificada el 2 de noviembre de 2026

Cambio: `PlaceImageStorage` valida y guarda subidas reales y mantiene el registro de archivos nuevos de una operación. `PlaceAdminService` coordina transacción y metadatos; ante un fallo revierte la base y solicita limpiar solo esos archivos nuevos. La limpieza es repetible y registra cualquier fallo de compensación sin ocultar el error original. Las imágenes anteriores y las de operaciones exitosas nunca entran en esa limpieza.

El paquete cambia únicamente `app/PlaceAdminService.php` y añade `app/PlaceImageStorage.php` frente a la etapa 2. Conserva mensajes, orden de validación, rutas históricas, referencias, traducciones y bytes de imágenes. Las escrituras SQL siguen en el servicio, pendientes de la siguiente etapa.

Evidencia:

- 20 escenarios por versión, 40 peticiones multipart reales en el Apache de dev1, con SQLite y medios propios desechables: PNG/JPEG/WebP, varias imágenes, procedencia/licencias, nombre engañoso, formato/tamaño/dimensiones inválidos, subida parcial y metadatos incompletos.
- Fallos provocados después de mover archivos, al insertar medios/relaciones y al registrar historial tras dos subidas: rollback de filas e identidades y eliminación exclusiva de archivos nuevos. Imágenes previas y sus bytes conservados. Resultados y metadatos idénticos entre versiones.
- Pruebas del adaptador: origen HTTP obligatorio, extensión ejecutable rechazada y limpieza repetible sin tocar archivos ajenos. Control automático que impide añadir operaciones de archivos al servicio o SQL/variables HTTP al adaptador.
- Las mismas operaciones administrativas pasan con ambos paquetes. Continúan pasando los 93 casos de publicación, traslado de negocios y validación de sesiones.
- 33 pruebas Python; 72 respuestas públicas, 69 páginas SEO, 19 controladores privados, nueve escenarios de sesión con el controlador real y comparación de datos/consultas con el paquete anterior.
- Dev1 actualizado: 60 rutas públicas con HTTP 200; 60 modelos públicos y 60 previews, administración, listados, portada y sitemap conservados en MariaDB. Siete casos de autorización con cuentas, roles e historial intactos después del rollback.

Incidencia del entorno: la primera comprobación final coincidió con la terminación de MariaDB por SIGKILL. Supervisor lo reinició y MariaDB completó recuperación. La causa no quedó confirmada; las comprobaciones finales se repitieron secuencialmente y pasaron. No se modificó configuración para resolverla.

Paquete: `.local/architecture-stage-3-candidate/`. Informes y comprobante: `.local/architecture-stage-3/`. Se conservó respaldo del código anterior antes de actualizar dev1. La pasarela temporal de pruebas y su biblioteca se retiraron; no forman parte del paquete. Sin migraciones, cambios de configuración ni acceso a producción o preproducción.

## Etapa 4 — escrituras de lugares — verificada en dev1 el 2 de noviembre de 2026

Cambio: `PlaceWriter` concentra las escrituras de lugares, traducciones, párrafos, atractivos, fuentes, investigación y medios. Usa la misma conexión del servicio y no controla transacciones. `PlaceAdminService` conserva validaciones, pertenencia de atractivos, existencia de medios, protección del borrado, orden de operaciones, transacción y compensación de imágenes; no ejecuta SQL. Las lecturas permanecen en `PlaceAdminSelector`. Las secciones de párrafos tienen un catálogo cerrado dentro del writer.

El paquete modifica únicamente `app/PlaceAdminService.php` y añade `app/PlaceWriter.php` frente a la etapa 3. No añade migraciones ni cambia IDs, rutas, contenido, idiomas, contratos persistidos o archivos de imágenes.

Evidencia:

- Quince fallos de persistencia provocados en SQLite: once fases de edición y cuatro de creación. Se conservan todas las filas e identidades, incluida la secuencia de IDs. Las mismas pruebas pasan contra los paquetes anterior y candidato.
- Veinte escenarios de subidas HTTP por versión (40 solicitudes): formatos, metadatos, referencias, IDs, rollback tras guardar archivos y conservación de imágenes previas; resultados idénticos. Pasarela y bibliotecas temporales retiradas al terminar.
- Dialecto MySQL en copias temporales de MariaDB, para ambas versiones: creación, edición, traducciones, medios, identidades de lugar/atractivo/historia, archivado, borrado y rollback temprano de edición/tardío de creación. Informes idénticos; sin escribir filas originales.
- 34 pruebas Python y control automático que prohíbe SQL en el servicio y transacciones propias/HTTP/archivos en el writer. Pasan también los 93 casos de publicación, traslado de negocios y validación común de sesiones.
- Regresión del paquete: 72 respuestas públicas, 69 páginas SEO, 19 controladores administrativos y nueve escenarios de sesión real. Comparación de modelos y número de consultas conservada.
- Tras actualizar dev1 con respaldo: 60 rutas canónicas responden HTTP 200. Modelos públicos/privados y listados comparados en MariaDB; datos anteriores conservados.

Límite de las pruebas MariaDB: las copias temporales `LIKE` no conservan claves foráneas. Además, el entorno local reproduce, sin código de la aplicación, la pérdida de una fila al borrar/reinsertar la misma clave temporal y hacer rollback. La primera prueba de rollback tardío de edición detectó ese comportamiento tanto con el servicio anterior como con el candidato. Se conservó una sonda independiente en el comprobador y el resultado consta en los informes; ese escenario no se declara aprobado en MariaDB. Se cubre con SQLite en ambas versiones y queda para la validación de integración previa a producción con tablas MariaDB ordinarias aisladas y claves foráneas. No se modificó el código de aplicación para compensar esta limitación de la prueba.

Paquete: `.local/architecture-stage-4-candidate/`. Informes y comprobante: `.local/architecture-stage-4/`. Dev1 conserva respaldo del código anterior. Sin cambios de configuración ni acceso a producción/preproducción; no hay commit, push ni despliegue productivo.

## Etapa 5 — contrato de lectura de lugares — verificada en dev1 el 2 de noviembre de 2026

Cambio: `PlaceReader` define las ocho lecturas que necesita `PlaceRepository`. `PlaceSelector` implementa esa interfaz y el constructor del repositorio acepta el contrato. Una implementación alternativa no necesita heredar del selector SQL. El punto de entrada `fromDatabase(PDO)` conserva la composición actual para los consumidores existentes; directorio y operadores todavía son concretos.

El paquete cambia solo `app/PlaceRepository.php`, `app/PlaceSelector.php` y añade `app/PlaceReader.php`. No cambian SQL, transacciones, reglas, plantillas, datos, nombres persistidos ni configuración. La proyección interna conserva las claves históricas por compatibilidad; esta interfaz aún no es un contrato de API ni una conexión remota.

Evidencia:

- La implementación alternativa con respuestas capturadas entrega exactamente los mismos seis modelos de idioma/preview y directorios. Se comprueban llamadas y parámetros, orden, normalización, duplicados, ausencias y propagación de fallos sin consultas adicionales al faltar un lugar.
- Los mismos escenarios pasan leyendo MariaDB sintético, con biblioteca candidata aislada y una transacción de solo lectura revertida. Sin modificar filas ni probar una migración de motor.
- Pruebas de alcance público/preview, contenido privado, entrada de slug, lotes vacíos y consultas: 16 antes y después de añadir 24 experiencias en la fixture aislada.
- 35 pruebas Python y control automático que exige la interfaz en el consumidor y prohíbe dependencias de infraestructura/HTTP en el contrato.
- Paquete: 72 respuestas públicas, 69 páginas SEO, 19 controladores privados y nueve escenarios de sesión real. Modelos públicos/previews, administración, listados, portada y sitemap conservados frente a la referencia.
- Dev1 actualizado con respaldo: 60 rutas públicas responden HTTP 200; comparación de modelos en MariaDB conservada.

Paquete: `.local/architecture-stage-5-candidate/`. Informes y comprobante: `.local/architecture-stage-5/`. Sin migraciones, configuración privada, producción, preproducción, commit ni push.

Objetivo ampliado por el propietario: poder separar presentación, backend y base entre servidores conservando PHP/MariaDB. La guía registra los límites y verificaciones pendientes de API, composición, transacciones, medios, sesiones, operación y carga. Esta etapa no declara lograda la separación distribuida ni una capacidad determinada de usuarios.

## Reglas y siguiente entrega

La guía vigente es [Arquitectura](../ARQUITECTURA.md), enlazada desde `AGENTS.md`. Las pruebas de fronteras exigen reutilizar la validación común antes de recuperar datos privados y prohíben dependencias de HTTP/almacenamiento en la nueva política de publicación. Estos controles acompañan las pruebas de comportamiento; no las sustituyen.

La siguiente entrega ampliará los contratos de lectura a directorio y operadores y separará la composición concreta del módulo público, manteniendo el punto de entrada compatible. Después se ampliarán los contratos a escritura/transacciones sin separar conexiones de una misma operación; más adelante se simplificará el controlador y se prepararán explícitamente sus datos de presentación, conservando autorización, campos, errores y estados de formulario. Después se retirarán las transformaciones restantes de HTML por familias de páginas. Los nombres persistidos y contratos históricos mantienen su etapa de migración independiente.

## Aclaración de alcance — 2 de noviembre de 2026

El propietario prioriza mantener la solución sencilla y facilitar una separación futura entre servidores, sin construir ahora una arquitectura distribuida. Continuar con una aplicación PHP modular y MariaDB. No añadir infraestructura ni una API interna anticipadamente; cada interfaz debe resolver un acoplamiento real del módulo actual. La referencia de despliegue separado es una guía para decisiones futuras, no una lista de implementaciones obligatorias en estas etapas. Se mantienen regresiones y entregas pequeñas, y el siguiente cambio debe justificar qué dependencia existente simplifica.

## Etapa 6 — rollback MariaDB ordinario y vista administrativa — verificada en dev1 el 2 de noviembre de 2026

Se cerró el pendiente de la etapa 4 con tablas ordinarias del MariaDB existente en dev1: esquema aislado, 21 claves foráneas y ninguna fila del portal copiada o escrita. Siete escenarios por versión (14 solicitudes multipart reales) conservan filas, IDs, relaciones e imágenes existentes ante fallos de persistencia, incluido el fallo final de clave foránea después de mover dos archivos. Los archivos nuevos se limpian. Coinciden el servicio anterior a separar el writer y el actual. Los posibles huecos de autoincremento de MariaDB se admiten; no se recrean entidades para recuperarlos.

La primera ejecución se interrumpió cuando MariaDB dejó de responder. Se retiró su fixture exacta y se repitió con el esquema necesario y los casos relevantes; pasó. La limitación histórica de las tablas temporales sigue registrada como tal, pero el rollback tardío ya está comprobado con tablas ordinarias y claves foráneas. El helper guarda un recibo para recuperar su limpieza ante interrupciones; no introduce infraestructura en la aplicación.

Cambio de aplicación: se extrajo `app/views/admin/places.php` desde `public/admin/places.php`. El controlador prepara filtros, opciones, CSRF y avisos; la vista recibe datos en un ámbito limitado. El servicio, autorización, acciones POST, transacciones y persistencia conservan su comportamiento. Los recursos de la plantilla usan el helper de hashes ya existente en el panel. El layout administrativo compartido mantiene sus dependencias anteriores y queda fuera de esta extracción.

Evidencia:

- 24 casos comparados del controlador real antes/después: listado, filtros, formulario nuevo/edición, ausencias, pestañas, avisos, entrada escapada, errores, CSRF, visitante y sesión vencida. HTML, campos y bytes de recursos equivalentes; versiones de recursos comprobadas contra sus archivos reales.
- 36 pruebas Python y barrera que prohíbe servicio/SQL/peticiones/sesión directos en la plantilla. Las quince inyecciones de fallo administrativas y los traslados de negocios continúan pasando.
- Paquete final: 72 respuestas públicas, 69 páginas SEO, 19 controladores privados y nueve escenarios de autorización de sesión. Comparación de modelos y consultas conservada.
- Dev1: seis pantallas privadas comprobadas por HTTP real con sesión autorizada y MariaDB; sesión sintética retirada. Rutas públicas comprobadas después de actualizar con respaldo.

El delta ejecutable frente a la etapa 5 contiene únicamente `public/admin/places.php` y la nueva vista `app/views/admin/places.php`. Paquete: `.local/architecture-stage-6-final/`. Informes: `.local/architecture-stage-6/` y `.local/architecture-mariadb-ordinary/`. Sin migraciones ni configuración del portal, producción, preproducción, commit o push.

Siguiente ámbito candidato: retirar las lecturas y decisiones de contexto del layout administrativo compartido, con su propia comparación de pantallas y autorización. Mantener la aplicación modular sencilla; no añadir interfaces o infraestructura sin un acoplamiento real que resolver.

## Etapa 7 — contexto del layout administrativo — verificada en dev1 el 2 de noviembre de 2026

Cambio: la preparación de navegación/cuenta se separó de las vistas compartidas. `AdminLayoutContext` calcula los datos de presentación sin PDO/HTTP/sesión; `AdminAccountSelector` ejecuta la misma consulta mínima del nombre. `admin_layout_context()` los compone en el límite HTTP después de los controles existentes, y los controladores entregan el resultado al layout. El menú usa el identificador técnico `places` manteniendo los enlaces y HTML anteriores.

Los 27 controladores con navegación directa solo añaden una llamada antes de renderizar; se verificó esa igualdad contra el paquete anterior. Lugares entrega el contexto a su vista aislada; el editor compartido lo prepara antes de incluir el menú. Las vistas `_navigation.php` y `_admin-layout.php` ya no leen variables globales HTTP/sesión ni ejecutan consultas. El render de cuenta conserva nombres escapados, fallback «Administrador», enlaces, CSRF y los dos circuitos de salida. La autorización sigue siendo responsabilidad de los controles existentes, no del contexto.

Evidencia:

- 38 contextos de ruta, datos de cuenta/salida y cinco proyecciones de nombre de cuenta; render de cuenta sin acceso a base/sesión.
- 114 comparaciones de HTML/recursos del layout anterior y candidato: rutas/pestañas, cuenta actual, administración antigua, cuenta ausente y editor compartido. Nombres con caracteres de HTML escapados y firmas CSRF conservadas.
- 24 comparaciones del controlador de lugares y 38 pruebas Python, incluidas barreras que impiden dependencias HTTP/SQL en las vistas/contexto y exigen preparar los datos en cada consumidor.
- Paquete: 72 respuestas públicas, 69 páginas SEO, 19 controladores privados y nueve escenarios reales de sesión para previews.
- HTTP real en dev1/MariaDB: 14 pantallas con elemento activo, nombre y salida correctos; visitante, caducidad, prueba de credencial inválida e identidad incoherente rechazados. Un caso adicional verifica el modo antiguo permitido por la configuración efectiva de dev1: 19 casos en total, sin modificar esa configuración. Sesiones sintéticas retiradas.
- Siete casos de autorización en MariaDB con rollback, cuentas/roles/historial intactos. Rutas públicas comprobadas después de actualizar con respaldo.

Paquete: `.local/architecture-stage-7-candidate/`. Informes y delta: `.local/architecture-stage-7/`. El delta ejecutable tiene 34 archivos: dos componentes nuevos, composición en bootstrap, vistas compartidas y llamadas de preparación en sus consumidores. Sin migraciones ni cambios de datos/configuración del portal, producción, preproducción, commit o push.

Siguiente ámbito candidato: retirar las transformaciones de HTML que todavía realiza `_page.php` en el editor compartido y sustituirlas por composición explícita, con comparación de los modos administrativo y de propietario. No añadir infraestructura ni interfaces sin una necesidad actual.

## Etapa 8 — layout administrativo sin transformaciones HTML — 2 de noviembre de 2026

Se eliminó `public/admin/_page.php` y su buffer con reemplazos de head, body, main y enlaces. El editor compartido prepara el contexto y declara directamente sus recursos, clases y menú. La navegación compartida usa enlaces explícitos desde `/admin/`, conservando los destinos de todas las páginas existentes y del editor en `/business/`.

El delta de ejecución frente a la etapa 7 tiene tres archivos: editor, navegación y eliminación del parcheador. Cambia además el inventario generado `public-producers.json`. No cambian consultas, servicios, reglas, acciones POST, formularios, autorización, datos, configuración ni recursos visuales. El modo del propietario conserva su composición anterior y sus transformaciones quedan pendientes, junto con las de otras familias.

Evidencia:

- 86 comparaciones del controlador editorial real: cuatro planes, siete estados, administrador/propietario, administración antigua, idiomas, tarjeta/página de preview, versiones ajenas/ausentes, errores, campos, escapado, rechazo de sesión/CSRF y ausencia de escrituras. Copias sintéticas aisladas por CLI, sin servidor adicional ni correo/publicación reales.
- 114 comparaciones del layout y 24 del controlador de lugares, conservando estructura/textos, cuentas, logout firmado y bytes de recursos. Los enlaces se comparan por destino; ya no dependen del directorio del consumidor.
- 39 pruebas Python y nueva barrera contra la reintroducción del parcheador; 38 contextos administrativos y pruebas PHP de presentación, sesión y circuito editorial conservadas.
- Paquete: 72 respuestas públicas iniciales, 69 páginas SEO, 19 controladores privados y nueve escenarios reales de sesión para previews.
- HTTP/Apache/MariaDB de dev1: 20 casos antes y después. El editor conserva layout, menú, recursos, cuenta y CSRF. La fixture no tiene suscripciones: se verifica su aviso HTTP 503 anterior, no un formulario comercial completo sobre MariaDB. La comparación del documento sin el token sintético debe permanecer idéntica.
- Comparación HTTP final: el documento del editor sin el token sintético es idéntico antes/después; 60 rutas públicas de dev1 responden 200 con idioma, estructura, mapa y JSON-LD correctos.

Dev1 actualizado con respaldo `architecture-stage-8-backup-20261102`, manteniendo la raíz montada y los privados. Los reportes generados de construcción que habían quedado de entregas antiguas se conservaron en ese respaldo y se actualizaron a los del paquete; todo archivo ejecutable previo coincidía con la etapa 7. Paquete: `.local/architecture-stage-8-candidate/`. Informes y comprobante: `.local/architecture-stage-8/`. Sin migraciones, datos/configuración del portal, producción, preproducción, commit o push.

Siguiente ámbito candidato: retirar la recomposición y traducción posterior del HTML de cuenta en el editor, preparando explícitamente su layout/idioma. Otras páginas de cuenta y `PortalPageRenderer` requieren sus propias entregas; no se declara que todas las transformaciones del portal hayan desaparecido.

## Etapa 9 — cuenta del editor sin recomposición HTML — 2 de noviembre de 2026

El modo de propietario del editor dejó de llamar a `_account-page.php`. Declara su idioma/body, encabezado, pie y recursos en la posición definitiva; ya no analiza el documento para recuperar head/main/tail, eliminar marcas ni traducir sus nodos. Se retiró también la inclusión del encabezado de cuenta antiguo que el buffer descartaba antes del main. Las vistas previas y sus renderizadores no cambian.

`account_layout_context()` centraliza la misma preparación de idioma recordado, indicador de cuenta administrativa y CSRF. La usa el editor y también el helper heredado de las demás páginas de cuenta; conserva validación, precedencia y persistencia del idioma. No autoriza ni consulta la base. `PortalPresentation::assets()` admite selección de CSS/JS desde su catálogo antes de emitir etiquetas. Su salida por defecto no cambia; no se añadieron interfaces, infraestructura ni parsing de HTML para esta selección.

El delta frente a la etapa 8 tiene cuatro archivos: bootstrap, recursos de presentación, helper de cuenta heredado y editor. Se conservan consultas, servicios, acciones, permisos, versiones, formularios y recursos visuales. Corrección deliberada comprobada: un negocio llamado «Nombre» y una nota «Tu cuenta» ya no se convierten en «Name/Nome» o textos de interfaz al cambiar de idioma. Los datos de negocio permanecen almacenados y se presentan escapados.

Evidencia:

- 104 comparaciones de estructura/textos/campos y recursos frente a la etapa 8, incluidas las 86 editoriales anteriores, idiomas recordados y contacto/acceso/portal de cuenta en ES/EN/PT-BR. Se ignoran espacios de formato sin contenido; los atributos y textos visibles se conservan. Dos casos adicionales reproducen y corrigen la traducción accidental de datos. Solo fixtures desechables por CLI, ninguna fila del portal escrita.
- 114 comparaciones del layout administrativo y 24 del controlador de lugares; 39 pruebas Python y controles que prohíben volver a usar los parcheadores en el editor. Pruebas PHP de presentación, contexto, sesión y circuito editorial conservadas.
- Paquete: 72 respuestas públicas iniciales, 69 páginas SEO, 19 controladores privados y nueve escenarios de sesión real para previews.
- 23 casos HTTP/Apache/MariaDB antes y después: panel, acceso privado y editor en ambos modos. HTML administrativo y DOM del propietario en ES/EN/PT-BR coinciden sin el token sintético ni espacios de formato. La fixture no tiene suscripción ni relación de cuenta con ese negocio: se comparan sus avisos/rechazo HTTP 503, no un circuito comercial completo sobre MariaDB. Sesiones sintéticas retiradas.
- Regresión final en dev1: 60 rutas públicas HTTP 200, con idioma, estructura, mapa y JSON-LD correctos. Los hashes de todos los archivos del paquete instalado coinciden con el manifiesto candidato.

Dev1 actualizado con respaldo independiente `architecture-stage-9-backup-20261102`, manteniendo raíz montada, datos y configuración. Paquete: `.local/architecture-stage-9-candidate/`. Informes y comprobante: `.local/architecture-stage-9/`. Sin migraciones, producción, preproducción, commit o push.

El editor ya no recompone su layout mediante regex, reemplazos de documento o traducción posterior. Persisten esas técnicas en otras páginas de cuenta, en `PortalPageRenderer` y en la construcción. Siguiente ámbito candidato: acceso y recuperación de cuenta, incluidos diálogos e interfaz multilingüe explícita, conservando permisos y protección de formularios.

## Etapa 10 — acceso, recuperación y contraseña — 2 de noviembre de 2026

Se retiró `account_page(true)` de inicio de sesión, recuperación y cambio de contraseña/reactivación. Cada controlador prepara datos y delega la presentación en `AccountAuthPresentation` y vistas de cuenta. La estructura del documento y del diálogo es explícita; las etiquetas se resuelven desde el catálogo existente antes de presentarlas. No hay traducción posterior del documento ni SQL/petición/sesión dentro de las nuevas vistas. Los tres controladores conservan literalmente su código de procesamiento anterior a la presentación.

Trabajo secuencial: primero se extrajo el fondo común de portada y se comparó en los tres idiomas; después pasó inicio de sesión (28 casos), luego recuperación (28), y finalmente contraseña/reactivación dentro del conjunto final de 96 casos. La portada pública y el fondo usan una sola vista con textos por idioma; los tres documentos públicos se construyen con un marcador explícito y conservan su head. No se cambian imágenes ni recursos CSS/JS. Se mantiene explícitamente la hoja de identidad final del constructor anterior para conservar su cascada; su duplicación existente queda identificada para una revisión de estilos separada.

Evidencia final:

- 96 comparaciones del controlador real en copias sintéticas por CLI: tres idiomas, idioma recordado/inválido, cuenta personal/administración, acceso correcto, cierre, errores/escapado, recuperación disponible/no disponible y confirmación genérica, enlaces válidos/vencidos, contraseña nueva, reactivación y uso único. Se conservan campos, recursos, sesión y resultados de operaciones. No hay correo real ni servidor adicional.
- Seis comparaciones del componente/portada pública en ES/EN/PT-BR: estructura, textos, destinos efectivos de enlaces, bytes de imágenes aprobadas y head público idénticos. Un componente sustituye la extracción del main y la corrección posterior de URLs del fondo.
- 104 comparaciones del editor/cuenta y dos regresiones de traducción accidental de datos conservadas. 41 pruebas Python, incluidas barreras para las nuevas vistas; pruebas PHP de sesiones, ciclo de vida/recuperación/reactivación, activación y composición heredada conservadas. La prueba unitaria del helper heredado ahora proporciona contexto preparado; el contexto HTTP real se comprueba mediante los controladores.
- Paquete: 72 respuestas públicas iniciales, 69 páginas SEO, 19 controladores privados y nueve escenarios de sesión real para previews.
- HTTP/Apache/MariaDB: 27 casos de las tres pantallas antes/después, más 23 privados existentes. Coinciden DOM, encabezados de privacidad, diálogo, idiomas, campos y rechazo CSRF. Los POST nativos se rechazan antes de escribir; contraseñas nuevas/reactivación y acceso correcto se prueban en las copias sintéticas CLI, no en filas de dev1. Sesiones de prueba retiradas.
- Regresión final: 60 rutas públicas de dev1 responden HTTP 200 con idioma, estructura, mapa y JSON-LD correctos. Todos los archivos del manifiesto instalado coinciden con el candidato final.

Incidencias de herramientas: una construcción local se quedó sin espacio; se retiraron únicamente candidatos intermedios de esta entrega, comprobados como reproducibles y sin datos privados. Se conservaron la referencia anterior, los informes, el candidato final y todos los respaldos de dev1. El comprobador HTTP se ajustó para crear su sesión antes de emitir la salida de otro comprobador; no se cambió código de aplicación para satisfacerlo.

Delta de paquete: 14 archivos, incluidos tres HTML de portada, las tres entradas HTTP y los componentes/catálogos nuevos o extraídos. Ningún recurso de `public/assets/` cambia de bytes. Paquete: `.local/architecture-stage-10-candidate/`. Informes: `.local/architecture-stage-10/`. Dev1 conserva respaldo `architecture-stage-10-backup-20261102` y su raíz montada, privados y configuración. Sin migraciones, producción, preproducción, commit o push.

Siguiente entrega: etapa 11, registro y demás páginas de cuenta, una pantalla por cambio con su regresión. Siguen pendientes `_account-page.php` para esos consumidores, el renderizador público y transformaciones heredadas de construcción. Esta etapa no declara finalizada toda la arquitectura ni completa todas las traducciones.


## Etapa 11 — primer bloque: registro personal, verificación y acceso previo al negocio — 3 de noviembre de 2026

Estado: primer bloque verificado en dev1; la etapa 11 completa sigue abierta. Se trabajó primero el registro personal, después la verificación de correo y finalmente la pantalla «Antes de registrar un negocio», comprobando cada extracción antes de la siguiente. Sus documentos y etiquetas se presentan explícitamente con `AccountAuthPresentation`; esas tres pantallas dejaron de llamar a `account_page()`. Se retiró la plantilla pública antigua del acceso previo al negocio. La rama del wizard conserva su implementación y queda pendiente.

El formulario de residencia tiene una sola vista y un preparador sin SQL/petición/sesión: `AccountResidencePresentation`. Lo usan registro y perfil; un adaptador mínimo conserva la petición de los consumidores históricos pendientes. Los controladores personales conservan literalmente el procesamiento anterior al render. En `register.php` solo cambia el bloque de presentación del visitante: se conservan autorización, redirecciones, retorno después de verificar, borradores y el resto del wizard. No cambian servicios, consultas, reglas, migraciones, recursos visuales ni datos del portal. Se retira únicamente el HTML de una elección de tipo que no era alcanzable: el controlador ya fijaba siempre la cuenta personal.

Evidencia:

- Primero 58 comparaciones del registro; después 95 al añadir verificación; finalmente 101 al incluir el acceso previo al negocio y sus rechazos. Copias sintéticas desechables por CLI: cuenta pendiente, contraseña comprobada, residencia colombiana/extranjera, consentimiento, reutilización de cuenta, activación válida/caducada/usada, reenvío y correo simulado. Ocho comparaciones adicionales del componente compartido, valores guardados, prioridad del POST, escapado y estados de campos. Estas escrituras usan SQLite aislado; no se presentan como pruebas de escritura sobre MariaDB de dev1.
- 104 comparaciones del editor/cuentas y dos regresiones de datos almacenados. Detectaron que trasladar el script de residencia a una vista privada perdía el hash que añadía el constructor público. Se corrigió preparando explícitamente la URL con el hash de los bytes reales; se repitieron las comparaciones afectadas y pasaron.
- 96 regresiones de acceso/recuperación/contraseña, seis de portada compartida, 42 pruebas Python y pruebas PHP de residencia, presentación, sesiones, ciclo de cuenta y verificación. Barreras prohíben petición/sesión/SQL y recomposición HTML en las vistas nuevas.
- Paquete: 72 respuestas públicas, 69 páginas SEO, 19 controladores administrativos y nueve escenarios de autorización de preview. El rebuild final tras corregir la versión de residencia solo cambió su preparador y vista: se conserva prueba de igualdad de todos los demás archivos para reutilizar las comprobaciones no afectadas.
- Apache/HTTP real con MariaDB en dev1: 21 casos de registro/verificación/acceso previo al negocio, 27 de autenticación y 23 privados. Los 71 casos coinciden antes/después en DOM canónico, recursos, encabezados y resultados. No crean cuentas ni envían correo; sesiones sintéticas retiradas. Después de instalar, 60 rutas públicas responden 200 con idioma, estructura, mapa y JSON-LD correctos.

La falta de espacio local interrumpió construcciones y copias de prueba; esos resultados no se contaron como aprobados. Solo se retiraron intermedios propios, verificando manifiesto y privados sintéticos, y se repitieron los controles pendientes. Se ejecutó el constructor sin cambiar su código, con copias APFS independientes que comparten almacenamiento hasta escribir, nunca hardlinks; las pruebas de render reutilizaron el paquete verificado para evitar otra construcción completa. El manifiesto final y todos los archivos instalados fueron comprobados.

Paquete: `.local/architecture-stage-11-candidate/`. Evidencia y comprobante: `.local/architecture-stage-11/`. Dev1 actualizado con respaldo `architecture-stage-11-registration-backup-20261103`, conservando la raíz montada, datos y configuración privada. Sin preproducción, producción, commit o push.

Pendientes de la etapa 11: perfil personal, panel de negocios, wizard de registro comercial, checkout, pago, contacto y reclamación (siete archivos consumidores del helper heredado). Próxima entrega: perfil personal, con sus acciones de residencia/cierre y autorización conservadas, seguido de su regresión. `PortalPageRenderer` y las transformaciones públicas/de construcción mantienen su propia etapa; no se declara eliminado todo el procesamiento posterior de HTML del portal.


## Etapa 11 — segundo bloque: perfil personal — verificado en dev1 el 3 de noviembre de 2026

Se extrajo el documento de `public/business/traveler.php` a `app/views/account/profile.php` mediante `AccountProfilePresentation`. El controlador conserva literalmente sus consultas/procesamiento de residencia, CSRF y cierre anterior al render; prepara después los datos y el contexto. `dashboard.php` conserva todos sus bytes, incluidos autorización y dispatch entre las dos pestañas. La vista no lee petición/sesión, ejecuta SQL ni captura/recompone el HTML. Declara sus recursos con hashes, encabezado/pie y clases finales, conservando la cascada anterior.

La salida firmada usa una sola vista privada y `renderLogout()` recibe CSRF como dato. Su adaptador público conserva el guard anterior de cuenta para el panel de negocios heredado. El perfil reutiliza directamente el componente de residencia de la entrega anterior. Las etiquetas se seleccionan explícitamente del catálogo: corrección deliberada comprobada, una persona llamada «Nombre» deja de aparecer como «Name/Nome» en sus datos personales. No cambian las traducciones pendientes ni se traducen nombres/correos como interfaz.

Evidencia:

- 74 comparaciones completas del controlador y dos regresiones de nombre almacenado: ES/EN/PT-BR, idioma recordado/inválido, cuenta con/sin negocios, administración, avisos, escapado, CSRF, sesión vencida/prueba inválida/cuenta deshabilitada, residencia colombiana/extranjera, departamento/ciudad incompatibles y manipulación del ID de cuenta recibido. Cierre confirmado, contraseña incorrecta, falta de confirmación y protección del último administrador. Identidades, negocios, membresías, consentimientos y suscripciones permanecen; el cierre revoca los tres tipos de tokens, conserva la residencia y limpia/regenera la sesión. Estas escrituras usan copias SQLite sintéticas por CLI; el validador de sesión que el bootstrap omite bajo CLI se invoca explícitamente. No son pruebas de escritura de cuentas sobre MariaDB de dev1.
- 104 comparaciones de editor/cuentas y dos regresiones de datos de negocio; 101 de registro/verificación con ocho variantes del componente de residencia. 43 pruebas Python y pruebas PHP de presentación, residencia, seguridad/ciclo de cuenta. Los nuevos límites de vista/presentador tienen barreras contra petición/sesión/SQL y recomposición HTML.
- Paquete construido por el procedimiento ordinario: 72 respuestas públicas, 69 páginas SEO, 19 controladores privados y nueve escenarios de autorización de previews. El delta frente al primer bloque tiene exactamente cinco archivos de ejecución (presentador, dos vistas, controlador de perfil y adaptador de salida). No cambian bytes de CSS/JS/imágenes ni el resto del paquete.
- HTTP/Apache/MariaDB real en dev1: 30 casos de perfil/salida compartida, 21 de registro/verificación/acceso previo al negocio, 27 de autenticación y 23 privados. Los 101 casos son iguales antes/después en resultados, DOM canónico, recursos y encabezados comprobados. Incluyen rechazo de residencia, acción inválida, CSRF, visitante/caducidad/prueba inválida y el formulario de salida de la pestaña de negocios. No guardan residencias ni cierran cuentas; sesiones sintéticas retiradas. Después de instalar, 60 rutas públicas responden 200 con idioma, estructura, mapa y JSON-LD correctos.

Paquete: `.local/architecture-stage-11-profile-candidate/`. Informes y comprobante: `.local/architecture-stage-11-profile/`. Dev1 actualizado con respaldo `architecture-stage-11-profile-backup-20261103`; todos los archivos instalados coinciden con el manifiesto final y se conservan la raíz montada, privados y datos. Sin migraciones, preproducción, producción, commit o push.

La etapa 11 sigue abierta: quedan seis consumidores del helper de cuenta (panel de negocios, wizard comercial, checkout, pago, contacto y reclamación). Siguiente bloque: panel de negocios, conservando selección de pestaña, propuestas, pagos y permisos. El adaptador de salida quedará retirado cuando se extraiga su último consumidor. El adaptador público de residencia no tiene consumidores de controlador actuales y su retirada requiere actualizar explícitamente las comprobaciones históricas que aún lo utilizan; no debe recibir consumidores nuevos.


## Etapa 11 — tercer bloque: panel «Mis negocios» — verificado en dev1 el 3 de noviembre de 2026

Se extrajo la vista de `dashboard.php` a `app/views/account/business-workspace.php` mediante `BusinessWorkspacePresentation`. Se reutilizan `BusinessWorkspace` e historial de `BusinessPaymentEvidenceService`; no se crearon servicios duplicados. El controlador conserva literalmente el procesamiento anterior al render, salvo retirar dos importaciones de helpers de presentación. Mantiene autenticación, selección de pestaña, aprobación/publicación, solicitud de cambios, conflictos de versión/hash y continuación al pago. Después prepara los datos de pantalla y obtiene cada QR con la misma llamada a `BusinessQrCode::publishedPage()` que antes estaba dentro del HTML. No cambia su política de visibilidad ni se declara optimizada esa consulta por fila.

La vista recibe filas, pagos, avisos, CSRF, permisos y las etiquetas/límite ya existentes. Declara encabezado, pie, recursos, formularios, diálogos y scripts en su posición final; ya no llama a `account_page()` ni accede a petición/sesión/SQL/servicios de negocio. El diálogo QR es privado y la salida reutiliza el componente del perfil. Se retiraron los tres fragmentos públicos sin otros consumidores: `_workspace-icon.php`, `qr-dialog.php` y `profile-logout.php`. Los ocho iconos conservan exactamente sus bytes; el render no introduce imágenes ni cambia CSS/JS.

Corrección deliberada: los nombres de cuenta/negocio y mensajes de pago que coincidan con «Nombre» o «Tu cuenta» se presentan como datos escapados, sin convertirlos en etiquetas de interfaz. La traducción de contenido/interfaz que aún falta conserva su etapa propia.

Evidencia:

- 120 comparaciones del controlador completo: cuatro planes, siete estados editoriales, cuenta vacía/administradora, borrador y solicitud privada, pagos e historial, avisos/prioridades, página publicada/no publicada, suscripción vencida, lugar privado, QR permitido/prohibido, pestaña anterior, CSRF, caducidad/prueba inválida/cuenta deshabilitada, negocio ajeno, versión/hash obsoletos y motivos de corrección cortos/largos. Se prueban solicitud de cambios, publicación de una actualización Gratis existente y repetición idempotente, conservando IDs, contratos y pagos; no se presenta como aceptación integral de primera publicación de todos los planes. Dos casos adicionales conservan nombres/mensajes almacenados en EN/PT-BR; ocho comparaciones verifican iconos byte a byte. Copias SQLite desechables por CLI con validador de sesión explícito, sin servidor adicional ni correo/publicación externos.
- 104 comparaciones del editor/cuentas más dos de datos de negocio; 74 del perfil más dos de nombres; 101 de registro/verificación con ocho del componente de residencia. Se generalizó el detector del traductor anterior en la prueba del perfil para conservar la regresión una vez corregido el defecto.
- 45 pruebas Python y pruebas PHP de workspace, QR, editorial, presentación y ciclo de cuenta. El test JavaScript del QR comprueba decodificación independiente, PNG, modal/foco, descarga, limpieza y errores. El comparador de SVG normaliza las dos serializaciones equivalentes de etiquetas vacías; una prueba exige conservar anidación/atributos para no ocultar cambios estructurales.
- Paquete: 72 respuestas públicas, 69 páginas SEO, 19 controladores privados y nueve escenarios de autorización de previews. El delta tiene siete archivos ejecutables (tres nuevos, controlador y tres fragmentos retirados) y el inventario público generado. Todos los demás bytes del paquete, incluidos recursos visuales, permanecen iguales.
- HTTP/Apache/MariaDB real en dev1: 42 casos nuevos del panel, más 30 del perfil, 21 de registro/verificación, 27 de autenticación y 23 privados. Los 143 casos coinciden antes/después en resultados, DOM canónico, recursos y encabezados comprobados. No publican ni cambian negocios/pagos/propuestas; las decisiones que escriben se ensayan en las copias CLI anteriores, no en la base de dev1. Sesiones sintéticas retiradas. Después de instalar, 60 rutas públicas responden 200 con idioma, estructura, mapa y JSON-LD correctos.

Paquete: `.local/architecture-stage-11-workspace-candidate/`. Informes y comprobante: `.local/architecture-stage-11-workspace/`. Dev1 actualizado con respaldo `architecture-stage-11-workspace-backup-20261103`; manifiesto y todos los archivos instalados comprobados, conservando la raíz montada, privados y datos. Sin migraciones, preproducción, producción, commit o push.

La etapa 11 sigue abierta: quedan cinco consumidores del helper de cuenta (wizard comercial, checkout, pago, contacto y reclamación). La siguiente entrega es el registro comercial, con su regresión de borradores, archivos, planes y autorización. Las transformaciones públicas/de construcción y la separación adicional de SQL/reglas conservan sus etapas propias; no se declara finalizada toda la arquitectura.

## Etapa 11 — cuarto bloque: registro comercial — verificado en dev1 el 3 de noviembre de 2026

Alcance: retirar la composición heredada del wizard de registro comercial y entregar datos explícitos a vistas privadas, conservando la lógica vigente. No se cambiaron servicios comerciales, reglas de suscripción, SQL de captura, contratos persistidos, límites de planes, imágenes ni recursos de interfaz.

- `register.php` conserva literalmente el procesamiento anterior al render y la recuperación de archivos tras un rechazo. Después prepara precios/períodos con `SubscriptionPricing`, reglas del plan y teléfono antes de llamar al presentador. Las consultas que estaban en los fragmentos de suscripción/complemento salen de la vista; no se creó otra lista de precios.
- Documento, contenido, suscripción, complemento y mapa son componentes privados. Tres fragmentos públicos comerciales sin consumidores se retiraron; las pruebas se actualizaron al contrato explícito. Teléfono y mapa tienen una única vista reutilizada por los consumidores previos mediante adaptadores pequeños.
- Los datos de lugares y formularios se escapan sin traducirse por coincidencia con etiquetas. El presentador localiza únicamente textos identificados del catálogo existente. No se declara completa la traducción del wizard.
- Recursos en su posición final, mismos bytes, orden y versiones. Continúan dos versiones literales heredadas y la hoja de identidad final; no se aprovechó la extracción para modificar la cascada o el JavaScript.

Evidencia en `.local/architecture-stage-11-registration/`:

- 115 comparaciones del controlador real anterior/candidato, con sesiones y base desechables por CLI. Tres idiomas, cuatro planes, compra seleccionada, búsqueda JSON, acceso/verificación, CSRF, borradores privados, revisiones y archivos, recuperación tras error y entradas mal formadas. Dos regresiones adicionales impiden traducir nombres almacenados.
- Cuatro casos satisfactorios crean un negocio privado por plan en cada copia, conservan la cuenta y los datos anteriores, marcan el borrador como enviado y comprueban el soporte privado y las imágenes consumidas cuando corresponden. Son pruebas SQLite de consumo de archivos temporales sintéticos; no se presentan como subidas HTTP ni escrituras MariaDB.
- Nueve comparaciones de teléfono: control anterior, adaptador nuevo y componente con modelo explícito; valores almacenados y enviados, fijo/celular, WhatsApp, prefijo, área inválida, escape y datos mal formados.
- Regresiones previas: 104 comparaciones del editor más dos de datos, 74 del perfil más dos de nombre, 120 del panel más dos de datos y ocho iconos idénticos, 101 del registro personal/verificación y ocho del componente de residencia. 46 pruebas Python de arquitectura/datos/recursos, nueve de enlaces públicos y pruebas PHP/Node de cuentas, registro, planes, editorial y QR pasan.
- Paquete final: 72 respuestas HTML iniciales, 69 páginas SEO, 19 controladores privados y nueve casos de autorización de previews. Delta: 14 archivos de ejecución más `public-producers.json`; CSS, JavaScript e imágenes sin cambios.
- Dev1/Apache/MariaDB: 30 casos nuevos más 143 anteriores, con resultados, DOM y recursos idénticos antes/después. Rechazos y lecturas sin crear cuentas, negocios, pagos o borradores; no se enviaron correos. Las 60 rutas públicas responden HTTP 200. Las sesiones de prueba se retiraron.

Paquete: `.local/architecture-stage-11-commercial-candidate-v2`. Manifiesto SHA-256: `08766ae3b06be9ecc3b7c9897b1688bab8ed24fe2480d1bac0d6c0a302058957`. Respaldo de código: `architecture-stage-11-commercial-backup-20261103`. Se conservaron la raíz montada de release, la configuración privada y los datos de dev1, verificando los 1106 archivos instalados. No se aplicaron migraciones, commit, push, preproducción ni producción.

La revisión de arquitectura/comportamiento no tiene hallazgos pendientes que impidan esta extracción. Sigue pendiente la revisión visual interactiva completa del wizard, el circuito completo en MariaDB, subida real, correo y pago/publicación. El SQL y las acciones que continúan en el controlador requieren su propia separación; no se declaran centralizados por esta entrega.

Estado actual de la etapa 11: quedan cuatro consumidores del helper de cuenta —checkout, pago, contacto y reclamación—. Continuar con checkout y luego pago, conservando contratos, importes, cupones y evidencias. Las transformaciones públicas/de construcción tienen su propia etapa y no están eliminadas globalmente.

### Ajuste de alcance posterior — pagos agrupados, 3 de noviembre de 2026

Antes de rediseñar la función de pago, contemplar el requisito registrado en `ARQUITECTURA.md` y el contrato comercial: una cuenta selecciona varios negocios y realiza una sola operación por el total desglosado, con planes, renovaciones y complementos por concepto. La extracción de las vistas existentes puede conservar el comportamiento anterior como entrega pequeña; no debe consolidarlo como contrato futuro obligatorio de «un pago por negocio». El modelo de orden agrupada requiere una entrega funcional y migración propia, con conservación de comprobantes históricos e idempotencia. No está implementado por esta anotación.

## Etapa 11 — quinto bloque: selección del negocio — verificado en dev1 el 3 de noviembre de 2026

El archivo compatible `business/checkout.php` dejó de llamar al helper de composición de HTML. Conserva literalmente el procesamiento anterior y prepara el modelo de `BusinessSelectionPresentation`; la vista privada de selección no conoce HTTP, sesión, SQL ni servicios comerciales. Se conserva selección individual como comportamiento actual, con el requisito de orden agrupada registrado por separado. No se implementó un cobro, una pasarela ni el pago de varios negocios.

Evidencia en `.local/architecture-stage-11-selection/`:

- 126 comparaciones del controlador anterior/candidato: cuatro planes, cuatro períodos, idiomas explícitos/recordados, intenciones capturadas/vencidas/ausentes, permisos de negocio y operador, sesiones, membresías revocadas, experiencia ajena, tarifa ausente y nombres escapados. Procesamiento idéntico, mismos resultados de sesión, recursos y destinos. Ninguna escritura en las bases desechables CLI/SQLite.
- El enlace canónico al simulador se declara directamente en la vista privada. Solo cambian tres archivos de ejecución: controlador, presentador y vista. Los otros 1105 archivos del paquete, incluidos CSS/JS/medios e inventario de productores, son idénticos al bloque anterior.
- Se corrigió el comparador HTTP de pruebas para conservar parámetros con `&` y resolver enlaces `?business=…` sobre el archivo actual. Tres pruebas ejercitan las funciones reales y detectan pérdida/cambio del destino. La referencia HTTP se volvió a tomar con esas correcciones antes de actualizar dev1. No se modificó el render del portal para resolver un problema del comparador.
- 47 pruebas Python de arquitectura/datos/recursos más las tres del comparador pasan. Regresiones comerciales: 115 comparaciones del registro más dos de datos, 120 del panel más dos de datos y ocho iconos. Pruebas PHP de planes, promociones/cupones, períodos/cambios y datos de transacción pasan. El paquete supera 72 respuestas HTML públicas, 69 páginas SEO, 19 controladores privados y nueve escenarios de autorización de previews.
- Apache/MariaDB: 75 casos nuevos de selección más 173 anteriores (248) con resultados, DOM y destinos idénticos antes/después, sin advertencias del comparador ni escrituras comerciales. Las 60 rutas públicas responden HTTP 200. Sesiones de prueba retiradas. La selección satisfactoria con membresía se demuestra en copias CLI; las pruebas nativas no acreditan una orden ni un pago completos.

Paquete `.local/architecture-stage-11-selection-candidate`, manifiesto SHA-256 `beff079b6dd569e8904e1f442a6a8bb08e6b98a7862887dc11c9e51feba9175f`. Dev1 conserva la raíz montada, privados y datos; los 1108 archivos instalados se verificaron. Respaldo de código `architecture-stage-11-selection-backup-20261103`. Sin migraciones, producción/preproducción, commit ni push. La comprobación visual interactiva completa no forma parte de esta evidencia automatizada.

La etapa 11 sigue abierta: quedan tres pantallas con el helper antiguo —comprobante manual, contacto y reclamación—. Próxima entrega: presentación del comprobante manual, sin consolidar un pago individual como restricción de la futura orden agrupada. Mantener la separación entre orden, pago, suscripción y publicación descrita en `ARQUITECTURA.md`.

## Etapa 11 — sexto bloque: comprobante separado y pago agrupado — verificado en dev1 el 3 de noviembre de 2026

Alcance autorizado posteriormente por el propietario: extraer la presentación manual e implementar selección de uno o varios negocios para un pago. `BusinessPaymentPresentation` y sus vistas privadas reciben datos explícitos; el controlador deja de usar `_account-page.php`. Instrucciones y términos comparten vistas privadas con adaptadores compatibles. Se agregan selector, writer y servicio de órdenes; las reglas/cotizaciones existentes se reutilizan. El contrato `PaymentEvidenceStorage` permite probar compensación de archivos independientemente del adaptador de subida HTTP real.

Una orden de la cuenta conserva conceptos por negocio, plan, período, operación, complemento, cotización e importe. Se admite un máximo de cincuenta negocios distintos y total COP calculado en centavos por el servidor. La reserva evita órdenes abiertas superpuestas. Un comprobante y un archivo por intento cubren todos los conceptos; un rechazo conserva su historia y permite otro intento. La aprobación es atómica, con permisos/bloqueos y reintentos idempotentes. Cancelar una selección sin revisión libera sus negocios, sin borrar historia ni representar reversión de una transferencia. La cotización emitida no se cambia silenciosamente al editar tarifas.

La migración aditiva 061 crea tres tablas con claves foráneas, sin alterar las anteriores. Se conserva lectura/revisión de comprobantes individuales, cupones parciales y suscripción asociada; cobertura total no genera comprobante ficticio. Workspace y preparación editorial reconocen evidencia de todos los conceptos, incluida la suscripción que no es la primera. La administración muestra un comprobante con desglose, descarga privada y confirmación conjunta; el actor de auditoría utiliza la identidad administrativa real. Se impide borrar un negocio con historia de órdenes.

Evidencia en `.local/architecture-stage-11-payment-orders/`:

- 43 comprobaciones del servicio en SQLite y las mismas en MariaDB aislado con tablas ordinarias y claves foráneas: selección mixta de tres negocios, planes/períodos y complemento, permisos, duplicados, corrección de rechazo, cupón parcial/total, cotización conservada, errores de persistencia, rollback de filas/archivos, confirmación conjunta y fechas de renovación preservadas. Migración repetida. Dos preparaciones simultáneas en MariaDB devuelven la misma orden.
- Diecinueve comprobaciones HTTP sobre el paquete final y MariaDB aislado: preparación, resumen, un solo formulario de subida, rechazo de SVG, PDF multipart real, archivo privado idéntico, repetición sin duplicar, aislamiento entre cuentas, comprobante administrativo único, descarga idéntica, aprobación de los tres conceptos, historial y CSRF. Base/usuario/archivos/sesiones/pasarela de prueba retirados al terminar; no se escribieron filas comerciales originales ni se envió correo externo o dinero.
- Las cincuenta pruebas Python anteriores pasan; el control nuevo de vista/servicio/almacenamiento pasó junto a los controles arquitectónicos y comparador HTTP (diecinueve pruebas dirigidas). Regresiones PHP de suscripciones, cambios, promociones/cupones, editorial e instrucciones y tres pruebas de textos comerciales pasan. Paquete: 72 respuestas públicas, 69 páginas SEO, 19 controladores privados y nueve casos de autorización de previews. Delta de veintidós archivos de ejecución; CSS/JS/imágenes e inventario de productores públicos idénticos.
- Después de instalar, dev1/Apache/MariaDB pasa 248 casos anteriores y siete nuevos de pago. La comparación conserva resultados, destinos y marcado, salvo el enlace intencionado de pago agrupado del workspace, también presente en tres casos de salida compartida del perfil. Las sesenta rutas públicas responden 200 con idioma, estructura, mapa y JSON-LD correctos. Las pruebas nativas sobre la base persistente son lecturas/rechazos; el recorrido que escribe utiliza la base aislada anterior.
- Antes de actualizar se respaldaron privadamente las filas protegidas y se aplicó 061 dos veces. Cuentas, negocios, suscripciones, comprobantes y canjes anteriores permanecieron iguales. El informe conserva el nombre privado del respaldo; no se incluye su contenido. Se preservaron configuración y datos privados, raíz montada del release y reserva de dev1 para revisión del propietario.

Paquete `.local/architecture-stage-11-payment-orders-final`, manifiesto SHA-256 `35b41eca41b4a0e6039db6cb38e7a42fb70a46e9a6db7943d04d8468a606dfb8`. Respaldo del código: `architecture-stage-11-payment-orders-backup-20261103`. Todos los archivos instalados se verificaron contra el manifiesto. Sin commit, push, preproducción ni producción.

Límites: dev1 no tiene medios de pago configurados; selección/resumen están disponibles y el envío del comprobante permanece bloqueado. Los medios ficticios habilitados solo pertenecieron a la base aislada de pruebas. No se acredita una pasarela, activación automática de renovaciones, reembolsos, correo externo, aceptación visual integral ni todo el circuito de registro/publicación. El driver histórico `registration-flow.integration.py` conserva supuestos del pago individual y no se ejecutó en esta entrega; requiere adaptación antes de reutilizarlo para el nuevo circuito. La confirmación del pago no publica y las fechas activas previas se conservan.

La etapa 11 sigue abierta con dos consumidores de `_account-page.php`: contacto y reclamación. Después continúan las familias públicas/construcción, idiomas, SQL/reglas aún acoplados en otros módulos y contratos de sustitución. La arquitectura completa no se declara finalizada ni se ha medido capacidad de carga por estas pruebas.

## Ajuste durante revisión del propietario — regiones y Pueblos Patrimonio — dev1, 3 de noviembre de 2026

Problema reproducido leyendo el listado productivo: las tarjetas de Barranquilla, Riohacha, Valledupar y San Bernardo del Viento carecían de etiqueta geográfica. El controlador mostraba exclusivamente `subregion`, y esos cuatro datos estaban vacíos. El listado ya era dinámico; no era una omisión de páginas independientes. No se escribieron datos de producción durante el diagnóstico.

Cambio: completar los cuatro valores vacíos con Caribe y utilizar el departamento como respaldo cuando no haya subregión. La clasificación histórica mezcla niveles geográficos y no se redefine por esta entrega. Una tabla `place_labels` guarda reconocimientos independientemente de la ubicación. Se incorporó `heritage_town` para Jardín, Jericó y Santa Fe de Antioquia, miembros verificados; Cauca Viejo conserva solo su etiqueta geográfica. No se infiere pertenencia de un nombre, una ilustración o un municipio padre.

Presentación compartida en listado y guía, con modelo explícito y texto ES/EN/PT-BR. Selector con consulta indexada y sin N+1 adicional; writer bajo la transacción del servicio. Administración ofrece una casilla y conserva una fila deshabilitada al desmarcar, incluida la procedencia. Importación inicial y paquetes reconocen `labels` opcional y rechazan códigos desconocidos; entradas antiguas sin ese campo preservan su estado. Los catálogos versionados incluyen los valores. No hay reglas de render por slug ni nuevas transformaciones de HTML para estas etiquetas.

Comprobaciones:

- 99 casos de etiquetas en SQLite y los mismos 99 sobre el paquete final en tablas MariaDB ordinarias con claves foráneas, sin modificar filas originales de dev1. Incluyen los cuatro faltantes, tres reconocimientos, caso sin herencia, departamento de respaldo, escape, idiomas, repetición de migración, deshabilitación persistente, procedencia, transacción/rollback y conservación de una región editada posteriormente. Esquema aislado retirado al terminar.
- Servicio administrativo: guardado antiguo sin etiquetas, selección/desmarcado explícito, error de código y error de persistencia con rollback completo; se mantienen los quince fallos editoriales anteriores. Pruebas de importación, ZIP/paquetes anteriores, mantenimiento, traslado e intercambiabilidad del lector pasan. Se mantiene la suite de 43 casos del pago agrupado. JavaScript administrativo con sintaxis válida.
- 38 pruebas Python dirigidas a consultas, arquitectura, publicación, geolocalización y comparador HTTP pasan. Paquete final: 72 respuestas públicas, 69 páginas SEO, 19 controladores privados y nueve casos de autorización de previews.
- Dev1/Apache/MariaDB: 255 regresiones de cuenta/negocio/administración y 67 casos nuevos: tres listados de veinte tarjetas, sesenta guías y cuatro estados del editor administrativo. Las pruebas nativas hacen lecturas y rechazos, sin guardar negocios ni pagos. El primer intento del comprobador de etiquetas no pudo crear su segunda sesión tras imprimir la salida; se corrigió el buffer del comprobador y la repetición completa pasó sin advertencias. No se modificó la aplicación por ese fallo del harness.
- Migración 062 aplicada dos veces en dev1 después de respaldo privado: solo las cuatro subregiones y las tres filas nuevas de etiquetas. Cuentas, negocios, suscripciones, comprobantes, canjes y órdenes/intentos anteriores conservados. Identidades y demás campos del lugar intactos, salvo la fecha de actualización automática correspondiente a los cuatro cambios. No se sustituyeron privados ni datos.
- Revisión visual de escritorio del listado servido: Barranquilla con Caribe; Jardín/Jericó con dos etiquetas y Santa Fe de Antioquia con su reconocimiento. Captura `.local/architecture-place-labels/labels-es.png`. La validación móvil visual completa y el guardado administrativo por HTTP quedan diferenciados de los casos del servicio/HTML anteriores.

Fuentes verificadas el 3 de noviembre: [Fontur, encuentro de la Red de Pueblos Patrimonio de septiembre de 2025](https://fontur.com.co/es/comunicados/villa-de-leyva-sede-del-xi-encuentro-nacional-de-la-red-turistica-de-pueblos-patrimonio), [programa oficial](https://www.fontur.com.co/es/programas/red-turistica-de-pueblos-patrimonio), [Mintransporte, Barranquilla y Riohacha en el Caribe](https://mintransporte.gov.co/publicaciones/12271/mintransporte-impulsa-la-conectividad-del-caribe-con-la-inauguracion-de-la-nueva-ruta-aerea-barranquillariohachabarranquilla/), [Colombia Travel, Valledupar](https://colombia.travel/es/valledupar?language=es) y [Gobernación de Córdoba, turismo](https://www.cordoba.gov.co/publicaciones/18/turismo/).

Paquete `.local/architecture-place-labels-final`, manifiesto SHA-256 `a11dbd447d45280c5e3accfdd0c0cad832f1b7fb684e4e88371a4da83349e84e`. Los 1124 archivos instalados coinciden con el manifiesto. Delta: 23 archivos; medios aprobados y CSS sin cambios; JavaScript administrativo y su versión actualizados. Respaldo `architecture-place-labels-backup-20261103`; informes en `.local/architecture-place-labels/`. Dev1 mantiene su reserva para revisión. Sin commit, push, preproducción ni producción. Este ajuste no cierra las pantallas pendientes de la etapa 11 ni el resto de la arquitectura.

## Ajuste durante revisión — filtros y paginado del listado — dev1, 3 de noviembre de 2026

El propietario detectó que el listado dinámico entregaba todo el catálogo y había perdido los controles históricos de filtro/paginación. El JavaScript anterior trabajaba sobre un listado estático completo; no formaba parte del controlador dinámico vigente. Se restauró el comportamiento como paginado real de nueve lugares en SQL, con formulario GET y enlaces utilizables sin JavaScript.

Filtros combinables: nombre, departamento, región/subregión y solo Pueblos Patrimonio. Opciones desde lugares publicados del idioma; búsqueda con/sin tilde, caracteres comodín tratados como texto y valores parametrizados. Se conservan filtros al paginar, cambiar idioma y volver por historial. Al filtrar se vuelve a la primera página. Estados vacío, rango inválido, última página y parámetros mal formados tienen comprobaciones propias.

Separación: `PlaceDirectorySelector` consulta; `PlaceDirectoryService` normaliza criterios y limita páginas; `PlaceDirectoryPresentation` prepara modelos/enlaces/textos; `views/place-directory.php` renderiza el documento explícito con header/footer/etiquetas compartidos. `PlaceSelector::listPublished()` delega al selector común y mantiene el contrato anterior de catálogo completo. Una subconsulta elige una portada por lugar sin duplicar una posición del paginado. No se incorporó una interfaz sin segundo consumidor ni se declara independencia del motor por usar estos archivos.

Evidencia en `.local/architecture-place-directory/`:

- 87 comprobaciones del servicio en SQLite y las mismas 87 en tablas MariaDB ordinarias aisladas con claves foráneas. Veinticinco filas sintéticas cubren tres páginas sin solapamiento, varias portadas del mismo lugar, borrador, traducción ausente, búsqueda con/sin tilde, comodines y entradas SQL literales, filtros combinados, reconocimiento deshabilitado, rango y URL con caracteres especiales. Fixture MariaDB retirada; sin escrituras originales. Los archivos del selector/servicio probados coinciden byte a byte con el paquete final v2.
- 42 respuestas del controlador construido comprueban los tres idiomas, filtros, páginas de 9/9/2 del catálogo de veinte, unión completa sin duplicados, formularios/enlaces sin JS, escape, metadatos, JSON-LD de tarjetas visibles, canónicas por página y `noindex` en búsquedas. Se comprueban también redirecciones y arreglos mal formados.
- Se mantienen 99 casos de etiquetas, importación y contrato de lector sustituible. 39 pruebas Python de fronteras/datos/publicación pasan. El guard arquitectónico identifica el servicio del directorio y exige vista sin petición/SQL y selector de solo lectura con límite SQL; no se desactivó la comprobación anterior.
- Paquete final v2: 72 respuestas públicas y 69 páginas SEO pasan. Las 19 pantallas privadas y nueve escenarios de autorización pasaron sobre el código del primer candidato; ese código permanece idéntico en v2. El único ajuste posterior fue la conservación explícita del recurso CSS del listado en la presentación.
- Apache/MariaDB de dev1: 255 regresiones previas, 73 comprobaciones de etiquetas (nueve páginas de listado, sesenta guías y cuatro estados administrativos) y 36 escenarios nuevos de filtros/paginación. Solo lecturas/rechazos; sin guardar cuentas, negocios, pagos ni etiquetas. Se actualizó el comprobador de etiquetas para recorrer y comparar el conjunto completo de lugares, manteniendo sus verificaciones de idioma y reconocimiento.
- Navegador: primera página 1–9, segunda 10–18, casilla patrimonial con tres resultados, filtro conservado al cambiar a inglés y estado vacío al combinar Cesar y patrimonio. Revisión visual de controles a 390 px en ES/EN/PT-BR mediante marcos temporales del dev reservado, sin servidor adicional; fixture retirada. Capturas `filters-desktop.png` y `filters-mobile.png`. La medición numérica de scrollWidth del iframe no estuvo disponible en el control de navegador; no se presenta como medida automatizada ni prueba en dispositivos físicos.

La comparación del primer paquete detectó que el limpiador retiraba `places.css` al no existir ya la referencia literal del controlador antiguo. Se mantuvo como recurso explícito del presentador: conserva las reglas del título y evita retirar el alias `assets/destinos.css` solicitado por páginas anteriores en caché. En v2 ambos archivos de compatibilidad son idénticos al paquete anterior al cambio. El CSS nuevo se limita a la familia de filtros/paginación y usa los tokens existentes.

Entrega activa: `.local/architecture-place-directory-final-v2`, manifiesto SHA-256 `73df0d14bd6025e2a2ea75e5b5fbe427c0c8da906f6840c01e4b070875e91266`, 1129 archivos. Delta frente al ajuste de etiquetas: once archivos, incluidos inventarios; imágenes, esquema, datos, JavaScript y CSS anterior intactos. Respaldo anterior a la función `architecture-place-directory-backup-20261103` y del primer candidato `architecture-place-directory-v2-backup-20261103`. Raíz montada, privados y reserva de dev1 preservados; todos los archivos del manifiesto instalados comprobados. Sin migraciones, importaciones, commit, push, preproducción ni producción. La etapa 11 y las otras familias pendientes conservan su alcance.

### Directorio: filtros compactos y aplicación automática (2026-11-03)

El buscador ocupa una fila junto al desplegable «Filtros» con contador. Departamento, región dependiente y casilla patrimonial aplican inmediatamente por navegación GET; la búsqueda espera 650 ms de pausa y admite Enter. Las etiquetas activas son enlaces generados por el presentador que quitan exactamente un criterio y vuelven a la primera página. Cambiar de departamento reinicia la región dependiente; quitar una etiqueta conserva los demás criterios. Se mantiene el paginado SQL de nueve, los enlaces de idioma, el contador y el estado vacío. No hay botón Filtrar/Limpiar en la experiencia con JavaScript. Un botón dentro de `noscript` permite el envío normal sin JavaScript.

La vista recibe filtros, etiquetas, opciones y URL explícitos. El servicio reduce las regiones al departamento seleccionado; el selector sigue concentrando SQL. El JavaScript solo gestiona controles, URL, cierre del panel, pausa al escribir, composición IME y recuperación del foco/panel tras navegar. No obtiene catálogos completos, interpreta HTML editorial, duplica las consultas ni filtra tarjetas en memoria. El panel es flotante en escritorio y se ubica abajo en móvil.

Verificación y entrega en `.local/architecture-place-auto-filter-evidence/`: 93 casos del servicio en SQLite y los mismos 93 en MariaDB ordinaria aislada con claves foráneas; esquema temporal retirado, sin escrituras originales de dev1. Las 42 respuestas del directorio comprueban además cada enlace de eliminación y conservación de otros criterios en tres idiomas. Pasan 39 pruebas Python, 72 respuestas públicas y 69 casos SEO. La regresión HTTP de Apache/MariaDB mantiene 255 casos previos, 73 de etiquetas y 36 de directorio. El test DOM del control verifica navegación automática, región dependiente, primera página, pausa, IME, limpieza de texto, URL sin cambio, Escape y recuperación de foco.

En navegador se comprobó departamento automático desde página 2, casilla automática, búsqueda por pausa, eliminación independiente, región con resultado vacío y recuperación de resultados al quitarla. Revisión visual a 390 px mediante tres marcos locales ES/EN/PT-BR, incluido panel móvil abierto; fixture retirada. Capturas `desktop.png` y `mobile.png`. No se afirma prueba en dispositivo físico. La navegación recarga la página, conservando el foco del buscador y el panel cuando corresponde.

Paquete `.local/architecture-place-auto-filter`, 1131 archivos instalados verificados; manifiesto SHA-256 `2a7e8a58d30b6611a23afbca3bb7b070ecd71b082bb9362c945f89c6a63e3e4f`. Respaldo `architecture-place-auto-filter-backup-20261103`; privados, datos y raíz montada conservados. Solo dev1; sin migraciones, producción, preproducción, commit ni push. Las etapas arquitectónicas pendientes mantienen su alcance.

### Directorio: limpieza desde el selector (2026-11-03)

Por petición del propietario, cada selector con un valor activo ofrece una × pequeña dentro del campo, con área de interacción de 44 px, nombre accesible localizado y enlace GET de respaldo. Quita solo ese criterio, conserva los demás y vuelve a la primera página. El JavaScript conserva el panel abierto y devuelve foco al selector tras navegar; cancela la búsqueda pendiente antes de seguir el enlace. La casilla se limpia desmarcándola y el buscador conserva su control nativo. Se mantienen las etiquetas para ver los criterios con el panel cerrado.

Causa comprobada del fallo anterior: `ensayos/v1/assets/places.js` filtraba/paginaba tarjetas estáticas y requería controles con IDs `destination-*`; el controlador del paquete anterior (`architecture-place-labels-final/public/places.php`) devolvía todo `listPublished()` y no emitía esos controles ni cargaba ese script. La transición a listado dinámico omitió ese comportamiento; fue una regresión funcional, no una necesidad del cambio arquitectónico. El controlador/selector nuevos y sus pruebas de límites/enlaces cubren ahora dicho comportamiento.

Verificación: test DOM actualizado, 42 respuestas del directorio ES/EN/PT-BR verificando igualdad entre enlaces de × y etiquetas, 72 respuestas públicas, 39 pruebas Python y regresión nativa dev1 de 255 casos previos + 73 de etiquetas + 36 de directorio. Navegador: quitar región conserva departamento (2 → 8 resultados), quitar departamento recupera 1–9 de 20 con paginado y panel abierto; revisión visual a 390 px con nombre largo, fixture local retirada. Evidencia en `.local/architecture-place-inline-clear-evidence/`.

Entrega `.local/architecture-place-inline-clear`, manifiesto SHA-256 `d71ef138678d6bda73e496875d8ea53d3efe930339cac09feea257849307d097`, 1131 archivos instalados verificados. Respaldo `architecture-place-inline-clear-backup-20261103`. Sin cambios de SQL/esquema/datos ni configuración privada; no se repitieron las pruebas aisladas de consultas porque esas fuentes permanecen idénticas. Solo dev1, sin preprod, producción, commit ni push.

### Directorio: casillas múltiples independientes (2026-11-03)

Corrección solicitada por el propietario: región/subregión primero y departamento después, con casillas para elegir varias opciones. Se retira la dependencia y el reinicio automático de región al elegir un departamento. Las opciones completas de ambos grupos permanecen disponibles, obtenidas del catálogo publicado del idioma. Dentro de un grupo se aplica OR; entre grupos, búsqueda y patrimonio, AND. No se presupone una jerarquía territorial que los campos actuales no normalizan: se conservan los valores de región/subregión existentes.

El selector genera listas IN con cada valor enlazado como parámetro; conteo y página comparten ámbito. El servicio normaliza selecciones, elimina duplicados, limita a cien entradas por grupo y 120 caracteres por valor, e ignora valores anidados/no textuales. Las URL escalares anteriores funcionan; varias opciones usan índices de arreglo, conservados al paginar/cambiar idioma. El presentador genera una etiqueta por valor y enlaces que quitan solo ese valor; la × del grupo borra todo ese grupo. Se mantienen nueve lugares por página, metadatos de búsquedas y GET sin JavaScript. Sin cambios de esquema ni datos.

La interfaz mantiene el panel plegable para ensayo, contador y etiquetas visibles fuera del panel. Dos columnas en escritorio; una en móvil, con altura y listas acotadas. Cada casilla aplica inmediatamente y conserva las otras selecciones, panel, foco y desplazamiento interno. Búsqueda por pausa/Enter y manejo IME siguen activos. Los nuevos textos están en ES/EN/PT-BR.

Evidencia en `.local/architecture-place-checkbox-filters-evidence/`:

- 122 casos del mismo servicio/selector en SQLite y MariaDB ordinaria aislada con claves foráneas; fixture MariaDB retirada. Incluyen OR, AND, patrimonio, repetición, espacios, arreglos anidados, literales SQL/comodines, cotas, compatibilidad escalar, URL múltiples, página y opciones independientes. Sin escrituras originales de dev1.
- 57 respuestas del directorio construido final: filtros simples/múltiples, todas las etiquetas y enlaces para limpiar grupos, conservación individual de opciones, página siguiente y enlaces de los tres idiomas. 72 respuestas públicas del paquete final y 39 pruebas de fronteras/publicación pasan. Los 69 casos SEO pasaron con el mismo código PHP del candidato; los cambios posteriores solo ajustaron el CSS y recuperación de desplazamiento JavaScript.
- Test DOM actualizado: varias casillas conservadas en URL, primera página, grupos independientes, pausa, IME, borrado, Escape, foco por nombre/valor, limpieza del grupo y posición del panel. Código final servido comprobado también con selección automática real.
- Apache/MariaDB: 255 regresiones previas + 73 de etiquetas + 48 de directorio, con consultas múltiples y siguiente página/idiomas. Este recorrido pasó sobre v2; todos los archivos PHP/app son idénticos al final, comprobados byte a byte. El último ajuste fue recuperación del desplazamiento del panel, con test DOM propio; no se repitió la suite HTTP sin cambios PHP.
- Navegador: Caribe sin departamento → 6; Caribe + Oriente → 8; al añadir Antioquia + Cesar → 3; quitar Caribe conserva otros tres valores → 2. Panel abierto y foco conservados tras marcar. Revisión visual de escritorio final y móvil a 390 px en ES/EN/PT-BR mediante marcos temporales en dev1, fixture retirada. No se declara prueba en dispositivos físicos. Capturas `desktop.png`, `mobile.png`.

Entrega `.local/architecture-place-checkbox-filters-final`, 1131 archivos instalados verificados; manifiesto SHA-256 `0037f9b339d23e59e12099b21bcaffe02bd1901419efcb9ad61fae0314d753a6`. Delta desde limpieza de selector: cinco archivos app, CSS, JavaScript e inventario de recursos. Respaldo inicial `architecture-place-checkbox-filters-backup-20261103`, candidatos posteriores separados. Configuración privada, datos, raíz montada y reserva preservadas. Solo dev1; sin producción, preprod, migraciones, commit ni push. La arquitectura restante conserva sus etapas pendientes.

### Directorio: filtros visibles y distinción de patrimonio (2026-11-03)

Por corrección del propietario, se sustituyó el panel plegable y la selección múltiple por tres controles visibles: región o subregión, departamento y Pueblos Patrimonio. La región limita los departamentos disponibles; elegir un departamento no limita las regiones. Cambiar de región limpia la selección de departamento anterior. El buscador se conserva y un único enlace limpia todos los criterios, sin etiquetas de filtros aplicados. Los controles se aplican automáticamente y mantienen envío GET sin JavaScript.

Las tarjetas de lugares marcados como Pueblos Patrimonio muestran una estrella en su esquina superior derecha y su etiqueta en color de acción. La marca proviene de los datos existentes y usa su texto traducido; no se crean reconocimientos por nombre ni cambian datos. Para el color se limita un override de `!important` al directorio porque la hoja histórica impone ese peso a todas las etiquetas.

Verificación: 96 casos del servicio en SQLite y MariaDB aislada, incluidos región → departamentos y regiones sin dependencia inversa. Test DOM de controles, 39 pruebas Python, 99 de etiquetas y 42 respuestas del directorio final pasan, junto con 72 respuestas públicas; 69 SEO y 255 + 73 + 39 regresiones HTTP pasaron sobre el candidato anterior, cuyo cambio posterior fue ubicar la estrella en la tarjeta y reforzar el color de etiqueta. En navegador se verificaron Caribe con seis departamentos, cambio a Oriente, limpieza y tres tarjetas patrimoniales. Revisión visual móvil a 390 px en tres idiomas sin dispositivo físico; fixture temporal retirada. Evidencia `.local/architecture-place-simple-filters-evidence/`.

Paquete final `.local/architecture-place-simple-filters-final`, 1131 archivos instalados verificados, manifiesto SHA-256 `be3e8a3f5a42039ff50ba516fb56fb24a267806fd9a9fb034fa947a94637fcc9`. Respaldos locales preservados, configuración privada y datos conservados. Solo dev1, sin cambios de producción ni preproducción.

El 3 de noviembre, ante el reporte de que dev1 no abría, se comprobó que la máquina de Podman se encontraba detenida y el puerto 8086 rechazaba la conexión. Se inició la máquina y el contenedor existente sin sustituir datos; se comprobó la apertura del sitio, Caribe y Pueblos Patrimonio. La máquina se mantiene activa en una sesión de trabajo para la revisión.

### Directorio: distribución compacta de filtros (2026-11-03)

A petición del propietario, búsqueda, región y departamento comparten una primera fila en escritorio; debajo quedan solo la casilla de Pueblos Patrimonio y el enlace para limpiar. En pantallas estrechas los controles se acomodan para preservar su lectura. No cambian el selector, el servicio ni la aplicación automática de los filtros.

Verificación: 42 respuestas del directorio, 72 respuestas públicas y test de controles pasan. En el navegador se comprobó la distribución y que Caribe actualiza lugares y departamentos; se revisó visualmente el ancho de 390 px en ES/EN/PT-BR. Fixture móvil temporal retirada. Capturas y resultados en `.local/architecture-place-filter-one-row-evidence/`.

Dev1 actualizado con respaldo `architecture-place-filter-one-row-backup-20261103`, sin modificar privados ni datos; 1131 archivos verificados. Manifiesto SHA-256 `9ecdda9519831498d5835845fc61c9d83f872b6c8d7990a57e841c0c7206442f`. Sin cambios en producción ni preproducción.

## Etapa 11 — séptimo bloque: vista de contacto — dev1, 3 de noviembre de 2026

`contact.php` conserva sin cambios el procesamiento previo de solicitud, CSRF, protección contra bots, límite de envíos, transacción del servicio y redirección. Prepara el contexto y los datos para `ContactPresentation`, que traduce textos individuales con el catálogo existente y entrega datos explícitos a `views/account/contact.php`. La composición utiliza los componentes compartidos de cabecera, pie y recursos; no analiza ni recompone el HTML completo. Se preservan las comprobaciones de sesión y cuenta que antes ocurrían en `_account-header.php` para no alterar su efecto durante esta extracción. Esa adaptación es deuda que debe revisarse al unificar la gestión de sesión, no se declara separación completa del controlador.

Verificación: 30 comparaciones antes/después del controlador real con datos y sesiones SQLite desechables, en ES/EN/PT-BR: visitante, cuenta, administrador, sesión expirada, referencia, rechazo de bot, error de validación, caracteres escapados, CSRF inválido y envío válido. El envío escribe solo en una copia aislada y conserva la respuesta y los datos esperados; no se envía correo. Las 32 pruebas dirigidas de arquitectura/consultas/comparación y las 72 respuestas públicas del paquete pasan.

Dev1/Apache/MariaDB: 18 casos de contacto antes/después con DOM normalizado idéntico y comprobación de cabecera, pie, token, errores y redirección CSRF; visitante y administrador en los tres idiomas. Solo lecturas y rechazos, sin guardar solicitudes, y sesiones sintéticas retiradas. Pasan además 23 regresiones administrativas y 27 de acceso. El primer intento de la prueba nativa contó también el formulario de salida en la cabecera; se acotó el selector al formulario del contenido y la repetición completa pasó. Informes: `.local/architecture-contact-view-evidence/`.

El paquete `.local/architecture-contact-view-final` tiene 1133 archivos verificados, manifiesto SHA-256 `d8b187408ef92c04846d87bed4a9814dcae908df9a17277d5a31512d491def07`. Delta: controlador, presentador y vista; sin cambios de SQL, esquema, datos ni recursos visuales. Respaldo `architecture-contact-view-backup-20261103`; privados, raíz montada y reserva de dev1 preservados. Sin producción, preproducción, commit o push.

Continúa pendiente **reclamación**, último consumidor de `_account-page.php`; después pueden retirarse el helper y su traductor de documento si se verifica que no quedan consumidores. También siguen pendientes el renderizador público (`PortalPageRenderer`), demostraciones que transforman HTML, consultas mezcladas en controladores/servicios, la unificación de idiomas y las pruebas de extremo a extremo. No se declara terminada la arquitectura, ni que cambiar a React/.NET u otro motor ya sea automático.

## Reclamaciones — separación inicial de consultas

`BusinessClaimSelector` reúne las lecturas de lugares, categorías, búsqueda y solicitudes. El servicio conserva su API anterior y delega estas consultas; las validaciones, escrituras y transacciones permanecen en el servicio. No se cambiaron tablas, columnas ni parámetros públicos heredados.

Comprobación local: 14 pruebas incluyen búsqueda, creación y revisión de reclamación en una base SQLite desechable y controles de responsabilidades. En la base MariaDB sintética de dev1 se compararon 309 resultados de lectura entre el servicio instalado y el selector nuevo, sin escrituras; los resultados coincidieron. Paquete construido: 72 respuestas públicas y 69 páginas SEO comprobadas. Dev1 actualizado con respaldo; 12 peticiones HTTP de reclamación y búsqueda en los tres idiomas, más 50 casos de administración y acceso, pasaron con MariaDB y sesiones sintéticas. La escritura completa de reclamación solo se probó en SQLite desechable, no por HTTP sobre MariaDB. La vista de reclamación, otras consultas y las transformaciones HTML restantes siguen pendientes de sus propias etapas y regresiones.

## Reclamaciones — vista explícita y retirada del traductor de documentos

La presentación de reclamaciones recibe datos preparados por el controlador; ya no captura ni recompone el HTML. El control de teléfono comparte una presentación explícita con el adaptador utilizado en administración. El último consumidor productivo de `_account-page.php` fue sustituido, y se retiraron el helper y los métodos de traducción y de inserción de recursos sobre documentos completos de `PortalPresentation`.

Evidencia: 42 comparaciones antes/después en los tres idiomas, con sesiones sintéticas, filtros, JSON, validaciones y envío en SQLite desechable, comprobando también los cambios de estado en la base. En dev1 se compararon 12 documentos HTTP normalizados y coincidieron; pasaron 18 peticiones de la página y JSON en MariaDB y otros 50 casos de administración y autenticación. El envío completo por HTTP con MariaDB todavía no se ha probado. El paquete final pasó las comprobaciones de 72 respuestas públicas y 69 páginas SEO. Dev1 se actualizó con respaldo y sin alterar configuración ni datos privados.

Continúan pendientes otras familias, en particular `PortalPageRenderer` y las demostraciones, además de la separación de SQL y reglas en otros módulos, traducciones y pruebas de extremo a extremo.

## Demostraciones — contactos inactivos en tarjetas

Las tarjetas de demostración indican explícitamente al componente de contactos que sus enlaces son inactivos; el enlace que permite abrir la página de ejemplo sigue funcionando. Ya no se captura ni transforma el HTML de esas tarjetas para desactivar sus contactos. Las páginas completas de demostración conservan temporalmente su adaptador heredado: esta etapa no elimina todas las transformaciones de `business/demo.php`.

Pasaron 14 comparaciones normalizadas de tarjetas y páginas completas, incluidas modalidades, panel integrado y enlaces de contacto sin navegación. En dev1/MariaDB pasaron 14 comprobaciones HTTP con contactos inactivos antes y después; los hashes del HTML serializado difieren en tarjetas porque cambia el orden de atributos, por lo que no se afirma igualdad byte a byte. Las pruebas del directorio conservaron límites, iconos y datos de los planes en los tres idiomas. Dev1 actualizado con respaldo; sin cambios en datos ni configuración.

## Demostraciones completas sin recomponer HTML

Las páginas completas ahora declaran directamente qué navegación mostrar y qué contactos dejar inactivos, usando las mismas vistas que el portal. Se eliminó el procesamiento posterior de documentos en `business/demo.php`; también se repararon etiquetas de cierre sobrantes que podía dejar el procesamiento anterior. El encabezado y pie normales se compararon sin diferencias en español, inglés y portugués.

Pasaron 14 comparaciones de DOM normalizado de las modalidades y sus estados integrados; las 14 rutas respondieron en dev1/MariaDB con contactos inactivos. Se comprobaron 72 respuestas públicas y 69 páginas SEO. Dev1 se actualizó con respaldo. Este cambio no elimina las transformaciones que siguen en `PortalPageRenderer` ni las de construcción.

## Reclamaciones — escrituras y transacciones

Las lecturas y escrituras de reclamaciones viven en `BusinessClaimSelector` y `BusinessClaimWriter`; el servicio conserva validaciones, coordinación y transacciones. Se corrigió el ID devuelto por `submit`: en MariaDB el servicio anterior devolvía 0 tras actualizar el negocio; ahora recupera el ID inmediatamente después de insertar la solicitud. La pantalla actual no dependía de ese valor.

Se compararon las dos versiones con tablas temporales InnoDB en una conexión MariaDB: creación, rechazo de duplicados, revisión, rechazo y liberación del negocio, y rollback provocado después de insertar. Solo la versión nueva devolvió el ID correcto. No se modificaron filas reales. También pasaron 42 comparaciones del controlador con fixture desechable, 31 controles de arquitectura y 18 peticiones de reclamaciones más 50 casos de acceso/administración en dev1. Dev1 actualizado con respaldo; el envío por HTTP sobre MariaDB sigue sin comprobarse.

## Reportes y registro comercial — consultas explícitas

El controlador de reportes mantiene autorización y prepara datos; `OperationReportSelector` contiene los cinco conteos y una vista independiente presenta las tablas. La respuesta del administrador, con conteos y recursos, y el rechazo al visitante coincidieron exactamente antes y después en dev1/MariaDB. Se verificaron además 50 casos de acceso/administración y se conservó respaldo. Durante la comparación se corrigieron los recursos de estilo de la vista extraída; la versión final es la que coincidió.

El controlador de registro comercial delega sus cuatro consultas restantes a `BusinessRegistrationSelector`, sin cambiar los nombres persistidos ni las condiciones de acceso. La comprobación en tablas temporales MariaDB cubrió borradores, solicitud pendiente, coincidencia de negocio y membresía. Pasaron 115 comparaciones con bases SQLite desechables, incluidos los cuatro planes y escrituras privadas sintéticas; en dev1/MariaDB coincidieron las 30 respuestas de lectura y rechazo antes/después, además de las regresiones de sesión, perfil y panel. No se ejecutó un alta completa por HTTP en MariaDB ni se enviaron mensajes reales.

## Reclamaciones — textos e información por idioma

La misma vista usa un catálogo explícito ES/EN/PT-BR, incluidos los mensajes de la búsqueda dinámica; los nombres procedentes de la base ya no se sustituyen por coincidencias con el catálogo de interfaz. Las consultas devuelven la traducción del lugar, categoría y negocio cuando existe y usan el español cuando no existe o está vacía. El filtro de nombre busca sobre el nombre mostrado. Los contratos de parámetros y el español predeterminado se conservan. Los mensajes de validación del servicio todavía pueden aparecer en español en EN/PT-BR y requieren una etapa específica con códigos de error estables; esta entrega no declara completa la traducción de todo el portal.

En MariaDB con tablas temporales se comprobaron los tres idiomas, fallback y búsquedas localizadas. Pasaron 42 comparaciones del controlador con datos aislados y comprobaciones de escapado y actualización dinámica, 35 pruebas de fronteras y reclamaciones, 72 respuestas públicas y 69 páginas SEO. Dev1/MariaDB pasó 18 verificaciones HTTP de interfaz y búsquedas en los tres idiomas y 50 de acceso/administración. Se conserva respaldo de código; no se modificaron datos del entorno.

## Servicios públicos — tarifas y límite municipal

Las consultas de experiencias publicadas y de existencia de un lugar publicado salieron de los controladores HTTP a selectores; se mantuvieron las reglas de acceso del límite municipal. Dev1/MariaDB respondió correctamente las tres peticiones verificadas. El JSON de tarifas cambia entre solicitudes por sus tiempos `captured_at`: se compararon las filas de la consulta original y el selector y coincidieron, y respuestas consecutivas son iguales al excluir esos tiempos. No se afirma igualdad byte a byte de tarifas.

## Sitemap — directorio de lugares en los tres idiomas

El sitemap público observado no incluía `/lugares/`, `/en/lugares/` ni `/pt-br/lugares/`, aunque el directorio español se observó indexable con canónica propia y alternates hacia los otros dos idiomas. `PublicSitemap` ahora incluye los tres directorios, sin inventar traducciones de lugares ni alterar las URL de entidades. En dev1/MariaDB la comparación del sitemap añadió exactamente las tres URL y conservó las demás. Pasaron las pruebas de sitemap, 38 controles SEO/arquitectura, 72 respuestas públicas y 69 páginas SEO. Cambio instalado únicamente en dev1 con respaldo; la producción observada todavía no contiene esta corrección.

## Rutas históricas de acceso — 3 de noviembre de 2026

El informe de Search Console incluía cuatro antiguas rutas de acceso y registro con prefijo de idioma que respondían 404 en producción. El paquete agrega redirecciones 301 a las rutas actuales, conservando parámetros e imponiendo el idioma del prefijo; el router local conserva el mismo comportamiento. En Apache de dev1 se verificaron ocho casos con y sin parámetros, estado final 200, idioma y noindex; además se comprobaron redirecciones existentes de index.html y de Arboletes. La batería SEO del paquete con fixture desechable aprobó 69 páginas y seis pruebas SEO/dominio adicionales pasaron. Se instaló solo `.htaccess` en dev1 con respaldo `.local/seo-account-redirects-dev1-backup.htaccess`; el manifiesto de la entrega anterior de dev1 no describe este cambio puntual. Sin modificaciones de datos ni publicación. La prueba reproducible es `backend/tests/legacy-account-redirects.http.py`.

El informe también enumera rutas antiguas de lugares, créditos retirados, aliados y URL sin equivalente confirmado. Una redirección a un lugar inexistente no lo hace disponible: revisar la publicación real y el destino editorial antes de hacer otras correcciones. Las cifras de Search Console son históricas y este cambio no demuestra que las otras URL estén corregidas ni que Google ya haya actualizado sus informes.

## SEO: antiguos aliados y origen de enlaces de cobertura — 3 de noviembre de 2026

El historial `3d5c5e6c` confirma que las páginas bajo `/aliados/` se trasladaron a `/negocios-locales/`. Se agregan redirecciones permanentes al equivalente concreto, incluidas las antiguas suscripciones y los tres idiomas; se conservan parámetros. Dev1/Apache pasó 42 variantes de ruta, idioma y parámetros, y repitió los ocho casos de acceso. La construcción pasó 69 respuestas SEO y 72 de presentación. Se instaló solo `.htaccess` en dev1 con respaldo `.local/seo-legacy-partners-dev1-backup.htaccess`; no es el manifiesto completo de dev1. Sin cambios de producción, datos ni preproducción.

`CoverageService` producía internamente `/destinos/`; el generador del paquete ya lo convertía a `/lugares/`. El origen ahora emite la ruta canónica directamente, sin depender de esa sustitución de construcción. Pasaron las pruebas de cobertura general y del ámbito de municipios, incluidas ambas ramas de enlaces publicados. El servicio de dev1 ya era idéntico al generado y no se sustituyó.

En producción se comprobaron las rutas canónicas españolas de los 18 lugares identificados en los ejemplos 404: Arboletes, Cocorná, Guatapé y Santa Fe de Antioquia respondieron 200; las otras 14 respondieron 404. Eso no determina su estado editorial ni autoriza publicarlas. Las rutas de créditos fueron retiradas expresamente del paquete; no redirigirlas a contenido ajeno ni eliminar obligaciones de atribución. Las variantes antiguas de páginas informativas, planes e interés comprobadas llegaron a sus equivalentes actuales; no se afirma una validación individual de los 97 ejemplos. Los informes de Google no se modificaron ni se solicitó validación. Queda pendiente el circuito completo de integración, batería de despliegue, preproducción y aprobación del paquete exacto.

### Auditoría adicional de duplicados y lugares, 3 de noviembre

Las 97 entradas de duplicadas corresponden a 40 rutas distintas si se eliminan host, protocolo y parámetros. Se consultaron las 40 rutas con HTTPS y host canónico en producción: 27 terminaron en 200 tras redirecciones 301 y con una única canónica coincidente con el destino final; 12 terminaron en 404 de lugares no disponibles y una en la página de créditos retirada. Esas 13 rutas sin contenido representan 14 de las 97 entradas; las 83 restantes comparten ruta con las 27 verificadas, pero no se probaron individualmente todos sus protocolos, hosts o valores de parámetros. Ejemplos reales de HTTP/www se comprobaron por separado. Se probaron los formularios de interés en ES/EN/PT-BR con parámetro `municipio`: lo conservan en la redirección y su canónica no incluye el parámetro. Resultado de las 40 comprobaciones: `.local/audit-duplicate-paths-result.json`.

Los 14 lugares que hoy devuelven 404 tenían HTML histórico con `index,follow` en el commit `7e6477e0`; `8af03e5b` retiró esos archivos heredados. No se ha demostrado desde ese historial cuándo se publicaron o retiraron del hosting. La documentación `docs/DESTINOS_PILOTO.md` identifica aquella entrega como local, no desplegada; los 14 expedientes territoriales actuales indican `planned`. Por tanto, no se debe afirmar que una retirada editorial productiva fue deliberada ni republicar automáticamente el HTML histórico: revisar los expedientes con el contenido, traducciones, mapas y licencias vigentes antes de cualquier publicación.

## Presentación pública — privacidad sin recomposición en cada solicitud

El controlador prepara los ajustes y `PrivacyPresentation` entrega líneas explícitas a `views/privacy-information.php`, que escapa los valores. Ya no se busca ni sustituye HTML de la página de privacidad durante la solicitud. Por ahora la construcción divide la página estática en dos fragmentos privados alrededor de un componente obligatorio; es una solución de transición y no elimina las transformaciones de construcción ni el procesamiento de inicio y tarifas. La construcción falla si falta o se duplica el punto de inserción.

La comparación contra el controlador anterior produjo 15 respuestas idénticas en ES/EN/PT-BR: configuración ausente, valores configurados o parciales, ajustes sobrescritos y ajustes inválidos, incluidos caracteres que requieren escape. En dev1 las tres respuestas de privacidad antes y después coincidieron byte a byte; inicio en tres idiomas, suscripciones, proceso y Jericó respondieron 200. Pasaron 69 comprobaciones SEO y 72 respuestas de presentación del paquete con fixture aislada, además de seis controles SEO/dominio. Instalada solo la vista, clase, seis fragmentos y controlador en dev1; respaldo `.local/architecture-privacy-dev1-backup`. No se modificaron datos ni configuración privada y el manifiesto anterior de dev1 no refleja esta instalación parcial. No se ha integrado a preproducción ni producción.

## Presentación pública — tarifas y portada sin recomposición en cada solicitud

Suscripciones y proceso presentan el catálogo mediante `SubscriptionPricingPresentation` y dos vistas de tabla y operador; los cálculos siguen en `SubscriptionPricing` y consultan las mismas tarifas. El bloque de atractivos de inicio utiliza `HomeAttractionsPresentation` y una vista explícita, conservando su texto de respaldo. `PortalPageRenderer` ya no lee ni recompone el documento completo mediante expresiones regulares o sustituciones. Los constructores todavía separan fragmentos de HTML estático privado en puntos explícitos: es una etapa transitoria, no una eliminación de las transformaciones de construcción ni la arquitectura final de plantillas. Las pruebas de fronteras bloquean nuevas sustituciones de HTML en ese controlador y exigen construir los componentes privados.

Se compararon byte a byte 36 respuestas comerciales y 21 de portada en ES/EN/PT-BR con datos sintéticos y fallos, más 15 respuestas de privacidad. Dev1/Apache/MariaDB produjo las mismas seis respuestas comerciales y tres portadas que antes; la prueba HTTP de tarifas comprobó 16 importes, descuentos y complemento de experiencias. Las tres respuestas de privacidad también permanecieron iguales tras la etapa comercial. El paquete con fixture aislada pasó 69 verificaciones SEO y 72 respuestas públicas. Dev1 recibió clases, vistas, fragmentos y controlador por etapas con respaldos `.local/architecture-commercial-dev1-backup` y `.local/architecture-home-dev1-backup`. Sin cambios de datos o configuración; el manifiesto anterior de dev1 no refleja estas instalaciones parciales. No se integró ni publicó en preproducción o producción.

## Separación de consultas — autorización de estadísticas privadas

La consulta de membresía activa y cuenta activa salió de `api/entity-audience.php` a `BusinessAudienceAccessSelector`. El controlador conserva validación de sesión, tipo, identificador, acceso al portal y decisión HTTP antes de solicitar el informe; la consulta y sus condiciones no cambiaron.

Pasaron nueve casos comparativos entre consulta anterior y selector en SQLite desechable y MariaDB dev1 con tablas temporales por conexión: titular, otro negocio, membresía inactiva, cuenta deshabilitada, inexistentes y entrada maliciosa. Ocho casos del controlador con fixture SQLite y sesión sintética simulada coincidieron, incluidos titular propio y ajeno. En dev1/Apache/MariaDB se compararon seis respuestas HTTP antes/después con sesiones sintéticas de invitado, administrador y caducada: códigos y hashes iguales. No había una membresía activa en la fixture de dev1, por lo que no se declara probado por HTTP allí el caso de titular; sí se cubrió en el controlador aislado y en la consulta MariaDB. Sesiones y archivos temporales retirados, sin cambios en cuentas ni negocios. Pasaron 69 controles SEO, 72 de presentación y controles de frontera. Selector y controlador instalados en dev1 con respaldo `.local/architecture-audience-dev1-backup`; sin preproducción ni producción, y el manifiesto anterior de dev1 no incluye esta instalación parcial.

## Separación de consultas — costos administrativos

`OperatingCostSelector` reúne los listados de lugares, últimos 200 gastos y metadatos de soportes. El controlador conserva el guard administrativo, selección de acciones y servicios de escritura; su HTML permanece dentro del controlador para una etapa posterior. Se mantiene SQL, orden, límite y agrupación previos.

Las consultas anteriores y el selector coincidieron en SQLite desechable y MariaDB con tablas temporales, usando 205 gastos sintéticos y soportes. La pantalla en dev1/MariaDB se comparó antes/después con sesiones sintéticas de administrador, visitante y caducada: HTTP y hashes del documento con CSRF normalizado iguales. Pasaron las pruebas de reglas financieras, controles de frontera, 69 SEO y 72 de presentación. Sesiones y archivos temporales retirados; no se modificaron gastos ni soportes de dev1. Selector y controlador instalados con respaldo `.local/architecture-cost-dev1-backup`; sin preproducción ni producción, y el manifiesto anterior de dev1 no incluye esta instalación parcial.

## Separación de consultas y escrituras — participación

`ParticipationSelector`, `ParticipationWriter` y `ParticipationService` separan las lecturas, el guardado y la gestión de colisiones de solicitud. El controlador conserva sus validaciones y el orden de duplicados/límite. No cambian datos, consentimientos, mensajes ni condiciones de captación.

Pasaron 11 comparaciones del controlador con SQLite sintética: GET en tres idiomas, métodos, CSRF, clave, bot, validación, alta, reintento y postulación de impulsor; las dos filas sintéticas coinciden. SQLite desechable y MariaDB con tabla temporal comprobaron escritura, reintento y propagación de una restricción única diferente. En dev1/MariaDB coincidieron antes/después cinco respuestas HTTP de lectura o rechazo en los tres idiomas, con sesión sintética retirada. No se probó un envío HTTP completo sobre MariaDB. Pasaron los controles de frontera y 69 respuestas SEO y 72 públicas del paquete. Las clases y el controlador se instalaron en dev1 con respaldo `.local/architecture-participation-dev1-backup`; sin producción, preproducción ni cambios en filas de dev1, y el manifiesto anterior no refleja esta instalación parcial.

## Idiomas — errores públicos de reclamación

Los rechazos conocidos de reclamación se identifican con `BusinessClaimException` y se traducen desde `business-claim.json`; el mensaje español permanece idéntico para consumidores existentes. Los errores específicos del componente de teléfono conservan el texto en español y se muestran como indicación de teléfono inválido en inglés y portugués. Los errores PDO muestran un mensaje seguro localizado. No se traducen nombres, datos, ni se alteran criterios o transacciones.

Pasaron las pruebas de códigos, todos los mensajes del catálogo y envío válido en SQLite. Se compararon 24 respuestas del controlador y las escrituras aisladas en los tres idiomas: solo cambiaron los textos traducidos previstos. En dev1/Apache/MariaDB se compararon seis respuestas GET o rechazo POST con sesiones sintéticas; coinciden al normalizar CSRF y el texto de error traducido. La transacción se comprobó antes y después en tablas temporales MariaDB, incluido rechazo duplicado y dos rollback. Se retiraron sesiones y archivos temporales; no se envió una solicitud completa por HTTP/MariaDB ni se modificaron filas de dev1. Pasaron 69 controles SEO, 72 respuestas públicas y las pruebas de filtro y reclamaciones. Instalado solo en dev1 con respaldo `.local/architecture-claim-errors-dev1-backup`; sin preproducción ni producción y con manifiesto de dev1 anterior sin actualizar. Queda por revisar la localización de otros módulos.

### Reclamación: navegación y filtros conservan idioma

La búsqueda incluye `lang` como campo explícito y lo conserva también en la búsqueda JSON. Los enlaces de información comercial y privacidad van a la página del idioma; acceso y registro usan las rutas compartidas con `lang` cuando corresponde. No cambian las URLs públicas ni se crean plantillas por idioma. Se verificaron 12 respuestas del controlador sobre fixture SQLite, con visitante y cuenta, además de búsqueda JSON y vistas; las reglas y el catálogo de errores son idénticos a la etapa anterior. En dev1/Apache/MariaDB se comprobaron las tres páginas, filtros, enlaces de acceso/registro y búsqueda JSON, así como seis destinos de enlaces; los errores siguen localizados. Se retiraron sesiones y archivos temporales; no se guardaron solicitudes reales. Pasaron 69 controles SEO y 72 respuestas públicas. Tres archivos instalados en dev1 con respaldo `.local/architecture-claim-navigation-dev1-backup`; sin preproducción ni producción. El control de idioma del enlace a privacidad con cuenta se verificó en fixture aislada, no con una membresía activa real en dev1.

## Idiomas — continuidad en contacto

La página de contacto usa enlaces del idioma activo para portada, información comercial, impulsor y privacidad, y traduce el título y la indicación posterior al registro. Tras un envío válido conserva `lang` en la redirección para inglés y portugués. El texto introducido por la persona y la referencia se escapan como datos y no pasan por el traductor de interfaz. Se mantienen el guardado, las reglas y las URL públicas.

Pasaron 12 casos del controlador con fixture SQLite desechable en los tres idiomas: lectura, confirmación, validación con texto escapado y envío sintético. En dev1/Apache/MariaDB se verificaron las tres páginas y sus 12 enlaces, y 18 casos HTTP de lectura/rechazo, además de 50 de sesión y acceso. No se realizó un envío completo HTTP sobre MariaDB; la redirección se revisó en el código y no se comprobó su cabecera HTTP en esta etapa. Se retiraron las sesiones sintéticas; no se guardaron solicitudes reales. Pasaron 69 controles SEO y 72 respuestas de presentación. Tres archivos instalados en dev1 con respaldo `.local/architecture-contact-language-next-dev1-backup`; sin preproducción ni producción, y el manifiesto anterior de dev1 no incluye esta instalación parcial. Persisten otros textos y errores de contacto por revisar en una etapa posterior, así como la localización de otros módulos.

## Idiomas — errores del formulario de contacto

Los cinco rechazos del servicio y los rechazos de validación y límite se identifican con `PublicRequestException`; el catálogo `contact-errors.json` proporciona los mensajes ES/EN/PT-BR. La presentación traduce también el fallo de almacenamiento sin mostrar detalles de PDO. El texto español y la compatibilidad con `RuntimeException` se conservan; no cambiaron validaciones, SQL, condiciones de captación ni formato del registro. Los errores inesperados mantienen el comportamiento anterior.

Con datos aislados se compararon 54 respuestas del controlador anterior y nuevo: ocho causas de error y envío válido en tres idiomas, con los mismos estados y filas resultantes. En MariaDB de dev1 se probaron cinco validaciones y un envío antes y después, usando exclusivamente una tabla temporal por conexión. En Apache/MariaDB se comprobó el rechazo del campo trampa en los tres idiomas y 50 regresiones de acceso/administración, con sesiones sintéticas retiradas; no se comprobó por HTTP el envío completo ni los demás errores de validación sobre MariaDB. También pasaron 12 casos de navegación/contacto, 69 respuestas SEO y 72 de presentación. Se instalaron seis archivos en dev1 con respaldo `.local/architecture-contact-errors-next-dev1-backup`; sin datos reales, preproducción ni producción. El manifiesto anterior de dev1 no incluye esta instalación parcial.

## Idiomas — respuestas de participación

La validación de participación devuelve identificadores estables y `ParticipationMessages` obtiene los textos ES/EN/PT-BR del catálogo. El controlador presenta mensajes de disponibilidad, rechazo, validación, duplicado, confirmación y fallo sin traducir ni modificar los datos enviados. La protección CSRF compartida acepta opcionalmente un texto específico para el API; los demás consumidores conservan el comportamiento predeterminado. Las reglas, orden, parámetros, escritura y transacciones no cambiaron.

Se compararon 102 respuestas del controlador anterior y el nuevo en fixtures SQLite desechables: rechazos de sesión, trampa, campos, consentimiento y entendimiento, además de alta y postulación de impulsor; estados, campos de error y filas coincidieron, con textos traducidos previstos. Pasaron las pruebas del formulario, frontera y modo de regresión local, además de 69 respuestas SEO y 72 de presentación. En dev1/Apache/MariaDB se comprobaron GET y método inválido en los tres idiomas y rechazos CSRF y trampa en español e inglés; en portugués el servicio está deshabilitado por la configuración de privacidad del ambiente, por lo que no se probaron esos POST allí. También pasaron 50 casos de acceso/administración y 18 de contacto. No se realizó un envío completo por HTTP/MariaDB ni se modificaron solicitudes reales; sesiones temporales retiradas. Cuatro archivos instalados en dev1 con respaldo `.local/architecture-participation-language-next-dev1-backup`, sin preproducción ni producción. El manifiesto anterior de dev1 no incluye esta instalación parcial.

## Consultas — solicitudes públicas en administración

`PublicRequestSelector::recent()` concentra la lectura que antes ejecutaba directamente el controlador administrativo de solicitudes públicas. Se preservan el mismo SQL, las 100 solicitudes más recientes, el orden y la autorización previa; la revisión sigue en `PublicRequestService` y la presentación permanece en el controlador para otra etapa. No hay cambios de escritura, tablas, datos ni reglas.

El selector reprodujo la consulta previa con tabla vacía y 105 solicitudes sintéticas, tanto en SQLite desechable como en una tabla temporal de MariaDB de dev1: filas, orden y límite iguales. En Apache/MariaDB se compararon antes y después administrador, visitante y sesión expirada: estados y hashes del HTML normalizado iguales; las sesiones sintéticas se retiraron y no se modificaron solicitudes. Pasaron pruebas de fronteras, servicio de solicitudes, 19 controladores privados, 72 respuestas públicas y 69 comprobaciones SEO. Controlador y selector instalados en dev1 con respaldo `.local/architecture-public-requests-selector-dev1-backup`; sin preproducción ni producción. El manifiesto anterior de dev1 no incluye esta instalación parcial.

## Rutas de aplicación privada en hosting — 3 de noviembre

El empaquetador SFTP adapta las referencias `dirname(__DIR__, N)` con nivel numérico positivo y `__DIR__` con uno o más segmentos `../` hacia la aplicación privada. Las pruebas reproducían dos fallos con raíces pública y privada separadas: operaciones de negocios (`dirname(__DIR__,3)`) y componentes de teléfono (`../../app/`). Pasan 21 pruebas del empaquetador y se preparó un paquete completo con ambas rutas adaptadas; no se desplegó ni se verificó producción. El paquete de demostración temporal se retiró después de la comprobación.

## Detalle de solicitudes comerciales — consultas y vistas

`AdminRequestDetailSelector` agrupa lecturas de solicitudes, cuentas, imágenes, soportes, membresías y negocios. La página y el resumen histórico son vistas privadas con datos preparados; se retiró el antiguo parcial público de la construcción. Autorización y acciones conservadas. Diez comparaciones con datos aislados abarcan solicitud, cuenta, búsqueda, vínculo, errores y permisos, con HTML idéntico. Las consultas coincidieron en MariaDB de dev1 en lectura; el ambiente no contenía una solicitud de participación, por lo que por HTTP se verificaron la ausencia y los rechazos, no un detalle completo. Controlador, selector y vistas instalados en dev1 con respaldo; el parcial antiguo quedó sin uso en dev1. No se envió comunicación ni se modificaron registros reales.

## Solicitudes públicas y cancelaciones — presentación y persistencia

`PublicRequestWriter` reúne los INSERT/UPDATE que antes estaban en el servicio; este conserva las validaciones. La bandeja pública y la de cancelaciones tienen vistas privadas, y `CancellationRequestSelector` concentra el listado de cancelaciones. Comparaciones aisladas cubrieron 54 respuestas de contacto, siete casos de administración y ocho de cancelaciones, incluidos envíos/revisiones sintéticas y estados. MariaDB temporal comprobó creación/revisión de solicitudes y 205 cancelaciones con el mismo límite de 200. En dev1 los hashes de las pantallas de administración y sus rechazos de acceso coincidieron antes y después; sin solicitudes o cancelaciones reales modificadas. Respaldos independientes conservados.

## Bandeja de participación y editor de negocios

`LeadInboxSelector` y `LeadInboxWriter` separan filtros/paginación y la eliminación validada; la bandeja recibe una vista privada. Dieciséis casos con 60 solicitudes sintéticas conservaron HTML, paginación, filtros, estados y eliminación en una base desechable; MariaDB con tabla temporal comprobó ocho filtros/páginas y eliminación. En dev1 coincidieron los hashes antes/después para administrador, invitado y sesión vencida; el modo administrativo heredado muestra acceso con HTTP 200 a invitado, sin mostrar datos. `LeadStatusStore` separa las consultas de cambio de estado y mantiene la transacción en una sola conexión: pruebas en SQLite y MariaDB temporal verificaron cambio, repetición, ausente y rollback.

El editor de negocios usa `BusinessEditorialSelector` para sus lecturas y una vista privada que recibe el contexto del teléfono sin acceder directamente a petición ni base. Se conservaron acciones, permisos, previsualización y publicación. Pasaron 104 comparaciones aisladas del editor y dos de datos no traducidos, más consultas con tablas temporales MariaDB. La muestra existente de dev1 respondió 503 antes y después por su configuración editorial, y visitante/sesión vencida mantuvieron 303; no se certifica por HTTP/MariaDB un editor completo ni publicación o correo. Instalado en dev1 con respaldo, sin publicación ni comunicaciones.

## Preparación de páginas públicas y volumen funcional de lugares

`PortalPageData` prepara privacidad, tarifas y atractivos; `PortalPageRenderer` conserva la resolución HTTP y composición. Los fragmentos privados creados durante la construcción siguen siendo una solución transitoria; no se afirma que se hayan eliminado todas las transformaciones de construcción. Coincidieron 15 respuestas de privacidad, 36 comerciales y 21 de portada sobre fixtures; 12 respuestas HTTP de dev1 coincidieron byte a byte en los tres idiomas. Pasaron 19 controladores privados, 72 respuestas públicas y 69 comprobaciones SEO.

Para el crecimiento editorial, un test con 100 lugares sintéticos comprobó paginación y búsqueda en tres idiomas en SQLite desechable y en tablas temporales MariaDB de dev1. También se verificaron las 60 rutas de los 20 lugares actuales en Apache/MariaDB, 93 reglas de publicación y la migración de contenido en fixture con aplicación repetida y control de conflictos. No es una prueba de carga, no verifica la calidad ni disponibilidad de los 90 lugares en preparación y no certifica capacidad de usuarios concurrentes.

Todo lo anterior permanece en la rama de trabajo y en dev1, sin integrar ni publicar. Los paquetes anteriores de dev1 tienen manifiestos que no describen las instalaciones incrementales. La migración 060 y su aplicación coordinada con el código, el mecanismo privado para el hosting sin consola, la integración, las pruebas completas y la aprobación de la entrega exacta siguen pendientes antes de producción.
