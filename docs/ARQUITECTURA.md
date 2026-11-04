# Arquitectura de Explorando Lugares

Convenciones acordadas el 2 de noviembre de 2026. Se aplican al contenido nuevo y al código nuevo; los incumplimientos existentes se retiran por etapas verificadas.

## Alcance actual: aplicación modular sencilla

Aclaración del propietario del 2 de noviembre de 2026: preparar el crecimiento sin implementar ahora un sistema distribuido. Mantener una aplicación PHP modular y MariaDB, desplegadas como hoy. La prioridad es que consultas, reglas, escrituras, presentación y archivos tengan límites claros y puedan extraerse en el futuro con cambios localizados.

Cada abstracción nueva debe resolver una dependencia o duplicación concreta del código actual y justificar sus consumidores. No añadir una interfaz por clase ni anticipar varias implementaciones hipotéticas. No introducir llamadas HTTP internas, microservicios, colas, cachés distribuidas, múltiples servidores o adaptadores sin uso por el solo propósito de una separación futura. Configurar dependencias y mantener los límites internos es suficiente en esta fase; las API se incorporarán cuando las necesite un cliente real o una extracción concreta.

## Responsabilidades y dependencias

| Componente | Responsabilidad y límite |
| --- | --- |
| Controlador HTTP | Traduce la petición, exige autorización y devuelve la respuesta. Delega los casos de uso; no añade SQL ni reglas editoriales particulares. |
| Servicio de aplicación | Coordina la operación, sus políticas y la transacción. No genera HTML. |
| Política de negocio | Define una decisión del dominio con entradas explícitas. No depende de HTTP, sesiones, HTML ni almacenamiento de archivos. |
| Selector | Consulta datos mediante parámetros y columnas explícitas. No escribe entidades ni produce HTML. Los criterios SQL de visibilidad deben conservar el contrato de la política correspondiente. |
| Repositorio | Recupera y compone datos del módulo mediante sus selectores. Las decisiones comerciales compartidas se delegan en políticas; no se duplica SQL. |
| Persistencia | Ejecuta escrituras bajo la transacción coordinada por el servicio. No decide permisos HTTP. |
| Almacenamiento de medios | Valida y guarda archivos. La operación conserva referencias, procedencia, licencias y limpieza de sus archivos nuevos ante un fallo. |
| Plantilla | Presenta datos preparados. No consulta la base, publica entidades ni repara contenido mediante reemplazos posteriores de HTML. |

La autorización de cuenta es transversal: se valida antes de leer o modificar contenido privado. No corresponde al selector inferir permisos desde una bandera HTTP ni a la plantilla ocultar datos ya entregados indebidamente.

## Regla implementada: acceso a estadísticas de negocio

El controlador de audiencia conserva la validación de sesión, tipo y parámetros, y decide si devuelve 403 antes de solicitar estadísticas. `BusinessAudienceAccessSelector` consulta la relación entre el ID público del negocio y la cuenta activa, incluyendo el estado de la membresía y de la cuenta. El selector no lee la sesión ni genera respuestas HTTP. Ningún consumidor debe tratar la existencia del negocio como autorización, ni omitir el guard del portal de cuentas.

`backend/tests/business-audience-selector-contract.php` compara la consulta original y el selector con tablas temporales, incluyendo negocio ajeno, membresía inactiva y cuenta deshabilitada. `scripts/tests/test_business_audience_boundary.py` protege la separación y el orden de los controles. La extracción no modifica la política de autorización ni el cálculo de estadísticas.

## Regla implementada: consultas de costos administrativos

`OperatingCostSelector` reúne las lecturas de lugares, los últimos 200 gastos y la agrupación de metadatos de soportes. El controlador valida primero la sesión administrativa y decide cuándo solicitar cada lectura. Crear gastos, guardar soportes y anularlos siguen bajo `OperatingFinanceService` y `CostEvidenceService`; el selector no escribe ni accede a archivos privados. La vista de costos continúa en el controlador y queda pendiente de una extracción independiente.

`backend/tests/operating-cost-selector-contract.php` compara las consultas anteriores con el selector en tablas temporales, incluidos el orden, el límite y soportes. `scripts/tests/test_operating_cost_boundary.py` protege la ausencia de consultas SQL en el controlador y el orden de autorización.

## Regla implementada: solicitudes de participación

El controlador de participación valida método, tamaño, disponibilidad, CSRF, clave de solicitud, protección automática, datos y límite de envíos. `ParticipationSelector` realiza las lecturas; `ParticipationWriter` guarda los datos ya validados; `ParticipationService` coordina la escritura y solo acepta una colisión de clave única si la misma solicitud existe. El control inicial de duplicados sigue antes del límite de envíos. No se amplían permisos ni se envían mensajes por esta extracción.

`backend/tests/participation-persistence-contract.php` comprueba guardado, reintentos y errores de integridad ajenos en tablas temporales. `scripts/tests/test_participation_boundary.py` protege el orden de validaciones y la separación del SQL.

## Regla implementada: errores localizados de reclamación

`BusinessClaimException` conserva el mensaje original en español y expone un código estable para la presentación. El catálogo `business-claim.json` tiene los mensajes en ES/EN/PT-BR; la vista escapa el texto antes de mostrarlo. Los errores de teléfono compartidos se agrupan como número inválido en los otros idiomas y conservan su mensaje específico original en español. Los errores de base de datos muestran un mensaje genérico, sin filtrar detalles. No se traducen datos del negocio ni se cambian validaciones o transacciones.

`backend/tests/business-claim-errors.php` verifica códigos, traducciones, fallback español y envío válido; `scripts/check-business-claim-errors.py` compara respuestas y escrituras aisladas frente al paquete previo. Esta regla cubre reclamaciones, no certifica que todos los errores del portal estén traducidos.

La búsqueda de reclamaciones envía el idioma explícitamente al filtrar y en las consultas dinámicas. Los enlaces a información comercial y privacidad usan la página pública equivalente del idioma; los de acceso y registro conservan `lang` como parámetro en las rutas compartidas de cuenta. No se crean páginas ni controladores duplicados por idioma. `scripts/check-claim-language-navigation.py` verifica los enlaces, filtros y búsqueda en los tres idiomas con una fixture aislada.

## Regla implementada: autorización de previews

- Administración y preview de lugares usan `AccountSessionValidator`, que compone `AccountSessionSecurity` y `AccountAuthorization`.
- Se comprueban credenciales vigentes, estado de cuenta, relación de identidades, rol y caducidad. Un error de validación invalida la sesión y no entrega contenido privado.
- La apertura de sesión y la respuesta HTTP permanecen en el límite HTTP. El validador no conoce peticiones, encabezados ni HTML.
- El acceso antiguo por bandera administrativa solo se permite cuando la configuración efectiva admite administración no unificada. Una bandera sola no basta en modo unificado.
- Los datos de borrador se consultan después de autorizar la preview. Las páginas públicas conservan el filtro de publicación y no adquieren una dependencia de sesión.
- Proteger este límite con pruebas de comportamiento de sesión real y un control de dependencia compartida; un HTTP 200 o una prueba de render no demuestran por sí solos autorización correcta.

Comprobaciones de esta etapa:

```sh
php backend/tests/account-session-validator.php
php backend/tests/account-authorization.php
php backend/tests/account-session-security.php
python3 -m unittest scripts.tests.test_admin_session_boundary
python3 scripts/check-preview-authorization.py --package PAQUETE_LOCAL --database FIXTURE_LOCAL --output INFORME_LOCAL
```

El último comando solo admite paquetes y copias SQLite sintéticas bajo `.local/`; crea sus propias copias y no modifica la fixture original. La comprobación Apache/MariaDB se ejecuta en dev1 o dev2 reservado, nunca en un servidor adicional.

## Regla implementada: políticas de publicación

`PlacePublicationPolicy` contiene las decisiones de alta inicial en borrador, avisos editoriales incompatibles con publicación, traducciones de atractivos publicados y contenido mínimo para publicar un lugar. Recibe datos ya normalizados y la lista explícita de idiomas; no abre conexiones, lee archivos ni conoce sesiones o peticiones.

`PlaceAdminService` conserva la normalización de entradas y coordina esta política con los controles existentes de geolocalización y las transacciones. La extracción mantiene condiciones, orden y mensajes; no cambia el contenido, el estado de publicación ni los permisos. El almacenamiento de imágenes se delega en `PlaceImageStorage`; las escrituras SQL se delegan en `PlaceWriter`.

Cada regla nueva o modificada se añade en la política correspondiente con casos de aceptación/rechazo. No se duplica en una plantilla, controlador o importador. Las decisiones de geolocalización conservan las excepciones aprobadas de `GeolocationPolicy`; esta etapa no las modifica.

```sh
php backend/tests/place-publication-policy.php
php backend/tests/place-admin.php
php backend/tests/business-place-transfer.php
python3 -m unittest scripts.tests.test_place_query_boundary
```

El test administrativo puede ejecutarse contra un paquete anterior con `EL_TEST_APP_ROOT` apuntando a su carpeta `app` bajo `.local/`. Usa su propia SQLite en memoria y permite comparar las mismas operaciones antes y después.

## Regla implementada: almacenamiento de imágenes

`PlaceImageStorage` valida subidas HTTP reales, formato, peso y dimensiones, y guarda las imágenes sin modificar sus bytes. No consulta la base de datos ni lee variables globales HTTP. Cada operación de guardado utiliza una instancia y un registro privado de los archivos que acaba de crear.

`PlaceAdminService` coordina la transacción, valida procedencia/licencias y delega las escrituras SQL en `PlaceWriter`. Ante un fallo revierte la transacción y solicita eliminar únicamente los archivos nuevos de esa operación. Nunca se incorporan archivos existentes al registro de limpieza, ni se eliminan las imágenes de operaciones exitosas. La limpieza es repetible; si algún archivo no puede eliminarse, se registra el fallo sin sustituir el error original.

Las rutas históricas `assets/destinos/` se conservan hasta su migración independiente. No se cambian IDs, referencias, traducciones ni formatos de imagen mediante esta separación.

```sh
php backend/tests/place-image-storage.php
php backend/tests/place-admin.php
python3 -m unittest scripts.tests.test_place_query_boundary
```

`dev/check-place-image-storage.php` compara subidas multipart reales contra dos versiones del servicio en el Apache de un entorno reservado. Usa SQLite y archivos desechables propios; requiere configuración local, acceso de loopback y un token temporal. Su pasarela HTTP se instala solo durante la comprobación y se retira al terminar; nunca se incluye en paquetes publicados. La comparación cubre formatos válidos, límites, metadatos y fallos de persistencia después de guardar uno o varios archivos.

## Regla implementada: persistencia de lugares

`PlaceWriter` ejecuta las escrituras de lugares, traducciones, historia, motivos, atractivos, fuentes, investigación y relaciones de medios. Usa la misma conexión del servicio; no abre, confirma ni revierte transacciones propias. No valida permisos HTTP ni reglas de publicación, ni maneja archivos. Las consultas de lectura se mantienen en `PlaceAdminSelector`; el writer puede usarlo para conservar identidades al sincronizar registros.

`PlaceAdminService` conserva la validación de entradas, pertenencia de atractivos, existencia de medios y protección del borrado con negocios relacionados. Coordina el orden de las escrituras y una única transacción de guardado, junto con la compensación de archivos nuevos ante un fallo. Controladores e importadores no deben eludir estas reglas invocando el writer como caso de uso. Archivado y borrado conservan su comportamiento previo.

El servicio no ejecuta SQL. Las secciones admitidas para sincronizar párrafos se resuelven mediante un catálogo cerrado dentro del writer; los nombres de tablas nunca provienen de la petición. Los nombres persistidos existentes conservan su contrato hasta una migración independiente.

```sh
php backend/tests/place-admin.php
php backend/tests/business-place-transfer.php
python3 -m unittest scripts.tests.test_place_query_boundary
```

La regresión administrativa provoca fallos en once fases de edición y cuatro fases de creación; comprueba que se revierte toda la operación y se conservan filas e identidades. La misma prueba se ejecuta contra el paquete anterior y el candidato. El control de fronteras prohíbe SQL en el servicio y transacciones propias, HTTP o archivos en el writer.

`dev/check-place-persistence.php` comprueba el dialecto MySQL con copias temporales de la conexión en el dev reservado. Cubre creación, edición, traducciones, relaciones de medios, IDs, archivado, borrado y rollback temprano de edición/tardío de creación. Las tablas originales no reciben escrituras. Las copias `LIKE` no reproducen claves foráneas; además, el MariaDB local pierde la fila en un caso mínimo de borrar/reinsertar la misma clave temporal y revertir. Ese caso se sondea y registra por separado: no se presenta como una regresión del refactor ni como prueba satisfactoria de rollback tardío de edición en MariaDB. Los quince fallos administrativos y las subidas HTTP ejercitan rollback tardío con SQLite en ambas versiones; la comprobación complementaria con tablas ordinarias y claves foráneas quedó cerrada en la etapa 6; el historial de las pruebas temporales conserva su limitación.


## Regla implementada: vista de administración de lugares

`public/admin/places.php` conserva autorización, CSRF, recepción de acciones, llamadas al servicio, respuestas HTTP y preparación de datos. La plantilla privada `app/views/admin/places.php` recibe los datos necesarios en un ámbito de función, sin recibir el servicio ni la conexión. Presenta los formularios, campos, avisos y listados; no consulta el servicio ni lee directamente las variables globales de petición/sesión. Los recursos usan el helper compartido `admin_asset` y sus hashes reales.

Es una organización mediante funciones, arrays e inclusión de archivos PHP. No introduce un framework, router, contenedor de dependencias o llamadas remotas. Los controles de negocio y transacción continúan en los componentes existentes. Desde la etapa 7, `_navigation.php` y `_admin-layout.php` reciben contexto ya preparado y tampoco leen HTTP/sesión ni consultan cuentas. Desde las etapas 8–9, ambos modos del editor compartido declaran directamente recursos, clases, navegación o header/footer según corresponda; el parcheador administrativo `_page.php` fue eliminado y el editor dejó de usar `_account-page.php`.

La regresión `scripts/check-place-admin-view.py` compara 24 peticiones del controlador anterior y candidato: HTML, textos, atributos, campos, filtros, errores, CSRF y acceso privado. Valida además los hashes de recursos; admite que sus URL pasen a ser absolutas y usen el hash del helper común. Cada ejecución usa sus propias copias y datos sintéticos. `dev/check-place-admin-http.php` comprueba seis pantallas reales con Apache/MariaDB y una sesión sintética propia, que retira al terminar.

## Regla implementada: contexto del layout administrativo

`AdminLayoutContext` prepara el elemento activo y los datos de cuenta/cierre de sesión a partir de parámetros explícitos. No conoce PDO, variables globales HTTP ni sesiones y no concede permisos. `AdminAccountSelector` conserva la consulta mínima de `display_name` y su fallback anterior; nunca recupera credenciales o datos de autorización para presentarlos.

La función `admin_layout_context()` del límite HTTP compone esos datos después de los controles existentes. Cada controlador la invoca antes de generar la página. El controlador de lugares la incluye en sus datos de vista; `admin_page()` prepara su contexto antes de renderizar el menú del editor compartido. Los 27 controladores que ya incluían directamente navegación solo añaden esa llamada: no cambian sus acciones, consultas ni permisos. No se añade una consulta del layout a los endpoints que no lo presentan.

`_navigation.php` recibe el contexto y `_admin-layout.php` renderiza los datos de cuenta explícitos. No leen petición/sesión ni ejecutan SQL. El identificador interno del menú de lugares es `places`; los enlaces, campos de formularios y rutas de salida mantienen su contrato. La cuenta personalizada usa `/negocios/salir/` y el modo administrativo antiguo conserva `/admin/index.php` y `action=logout`, con su CSRF. La compatibilidad antigua sigue condicionada a la configuración efectiva; el contexto de presentación no modifica esa decisión ni reemplaza `AccountSessionValidator`.

Controles de regresión:

```sh
php backend/tests/admin-layout-context.php
php backend/tests/admin-presentation.php
python3 -m unittest scripts.tests.test_admin_session_boundary
python3 scripts/check-admin-layout.py --before PAQUETE_ANTERIOR --after PAQUETE_CANDIDATO --database FIXTURE_LOCAL --output INFORME_LOCAL
```

La comparación usa datos aislados y cubre 114 combinaciones de menú, cuenta/administración antigua, cuenta ausente y editor compartido, incluidos escape de nombres, CSRF y cierre de sesión. Se comprueban también los controladores de lugares y las demás pantallas privadas. `dev/check-admin-layout-http.php` verifica pantallas y sesiones por HTTP real con MariaDB en el dev reservado; retira las sesiones sintéticas que crea. En dev1 la configuración efectiva permite el modo antiguo: la prueba verifica su conservación, mientras los casos unificados de autorización prueban el rechazo cuando corresponde. No se cambia configuración para satisfacer una prueba.

## Comprobación cerrada: rollback con MariaDB ordinario

`dev/check-place-image-storage.php --mariadb` utiliza `LocalMariaDbFixture` para copiar solo el esquema necesario y sus dependencias, sin copiar filas del portal, dentro del MariaDB del dev reservado. Crea una base y usuario desechables con nombres aleatorios y marcador de propiedad; las credenciales solo están en el control local de la prueba. Las claves foráneas se comprueban contra esa misma base y permanecen activas durante las operaciones del servicio. La base, usuario, control y pasarela se retiran al terminar; un recibo de propiedad permite recuperar la limpieza si se interrumpe la conexión.

Siete escenarios por versión (14 subidas HTTP reales) pasaron con 21 claves foráneas: PNG y dos imágenes válidas, alt incompleto, fallo al insertar el medio, fallo de relación, segunda imagen inválida y fallo final de clave foránea después de guardar dos imágenes. El fallo tardío de edición conserva todas las filas previas, identidades, relaciones y bytes de imágenes existentes, y elimina únicamente los archivos nuevos. Los resultados coinciden entre el servicio previo a extraer el writer y el actual. MariaDB puede consumir números autoincrementales al revertir: se conserva la identidad de las entidades existentes, sin exigir secuencias consecutivas.

La primera ejecución completa se interrumpió al caer MariaDB; se recuperó y eliminó exclusivamente su fixture identificada. La repetición se acotó al esquema necesario y a estos siete casos, y pasó. Esta comprobación reemplaza el pendiente de validación con tablas ordinarias; no es una prueba de capacidad, no cambia la base del portal y no modifica configuración del entorno.

## Regla implementada: primer contrato de lectura

`PlaceReader` define las ocho operaciones de lectura que consume `PlaceRepository`. `PlaceSelector` implementa ese contrato con el SQL actual. El constructor del repositorio recibe `PlaceReader`, por lo que admite otra implementación sin heredar del selector ni cambiar el ensamblado del modelo. Se conservan los parámetros, orden de resultados, idiomas, identidades y filtros de publicación; el contrato no autoriza previews.

Esta entrega no modifica SQL ni define una API HTTP. Las proyecciones conservan claves históricas internas por compatibilidad y deberán mapearse explícitamente al introducir contratos externos. `fromDatabase(PDO)` sigue como composición compatible para los consumidores existentes; directorio y operadores todavía tienen dependencias concretas. El servicio administrativo conserva sus dependencias PDO hasta su propia etapa de lectura/escritura/transacción.

```sh
php backend/tests/place-reader-contract.php FIXTURE_LOCAL
php backend/tests/place-selectors.php FIXTURE_LOCAL
python3 -m unittest scripts.tests.test_place_query_boundary
```

La primera prueba sustituye el selector por una implementación independiente con las mismas respuestas. Verifica los seis modelos de idioma/preview, directorios, orden de llamadas, normalización, duplicados, ausencias y propagación de errores. `dev/check-place-reader.php` ejecuta los mismos escenarios sobre MariaDB sintético mediante lecturas y una transacción revertida, usando una biblioteca candidata bajo `/srv/state/`. No acredita un segundo motor ni separación entre servidores; comprueba el límite introducido.

## Sustitución de componentes y contratos

La separación por archivos es un primer paso; no acredita intercambiabilidad. Hoy `PlaceAdminService` todavía recibe `PDO` y construye selectores y writer concretos; el repositorio público recibe `PlaceReader` para lugares, pero directorio y operadores aún son concretos. Esa dependencia queda pendiente de inversión mediante contratos y composición explícita. No declarar el módulo independiente del motor ni listo para React o .NET hasta comprobar los límites correspondientes.

- Los casos de uso dependerán de interfaces pequeñas de lectura, escritura, transacción y medios cuando exista un límite sustituible. No añadir una interfaz por clase ni un repositorio genérico para todas las tablas. La configuración/composición construirá los adaptadores concretos y comprobará que lectura, escritura y transacción participan en la misma unidad de trabajo.
- Las interfaces de aplicación no expondrán `PDO`, sentencias SQL, nombres de tablas, `PDOStatement`, sesiones o detalles del framework. Los resultados y errores tendrán contratos explícitos; las reglas de negocio conservarán entradas independientes de HTTP y del motor.
- Cambiar MariaDB por PostgreSQL exigirá un adaptador de persistencia, migración versionada de esquema/datos y pruebas equivalentes de IDs, restricciones, ordenación, fechas, tipos y transacciones. PDO ni una interfaz hacen portable el SQL por sí solos. El resto del módulo deberá conservar sus contratos y comportamiento.
- El portal PHP, una futura interfaz React y las aplicaciones móviles compartirán casos de uso. Los controladores HTTP adaptarán peticiones y respuestas; una API documentada expondrá datos públicos explícitos, errores, paginación e idiomas sin filtrar proyecciones administrativas o nombres históricos de almacenamiento. Conservar autorización, sesiones/tokens y protección de formularios/subidas al añadir clientes. La estrategia de React deberá conservar HTML indexable, SEO y rutas públicas.
- Sustituir PHP por .NET requiere reimplementar el backend. El contrato estable entre tecnologías es la API y su comportamiento comprobado, no la interfaz del lenguaje. Mantener ese contrato permite conservar clientes y, cuando corresponda, el esquema de datos; las reglas se portan y se verifican con los mismos casos de aceptación.
- Las pruebas de contrato comprobarán las mismas operaciones y errores en cada adaptador implementado. Un doble de prueba no acredita compatibilidad con otro motor real. Introducir contratos primero en un módulo piloto y conservar compatibilidad de los consumidores antes de ampliar el cambio.

## Índices y cercanía geográfica: estado y etapa futura

Revisión del 2 de noviembre de 2026, sobre migraciones, selectores y metadatos del MariaDB sintético de dev1; no se consultó producción ni se modificaron tablas. En las seis tablas revisadas hay 17 índices BTREE, incluidos identidades/slugs, traducciones por entidad/idioma, publicación/orden, directorio de negocios por lugar/estado/categoría y categorías relacionadas. Tenerlos declarados no acredita que cada consulta los use de la mejor forma; una auditoría de rendimiento debe comprobar los planes reales y carga representativa.

Lugares y atractivos conservan coordenadas `DECIMAL(9,6)` y negocios `DECIMAL(10,7)`. No hay columnas geométricas ni índices espaciales en esas entidades. `BusinessMunicipality` contiene funciones de pertenencia a polígonos y distancia entre puntos; el formulario de ubicación también calcula distancias y muestra la precisión informada por el dispositivo. Esos mecanismos no implementan un buscador público optimizado de negocios/atractivos dentro de un radio.

Cuando se implemente cercanía, el selector debe recibir punto y radio en metros y devolver resultados publicados con distancia y orden definidos. El servicio aplicará validaciones, límites y criterios del producto; la presentación no debe descargar todo el catálogo para calcular distancias. Se evaluará un filtro geográfico indexado que reduzca candidatos, seguido de distancia esférica y orden/paginación. MariaDB ofrece índices espaciales y `ST_DISTANCE_SPHERE`; no se necesita cambiar el motor para esas operaciones. La elección concreta del índice se comprobará con `EXPLAIN` y medidas, sin crearlo ahora para una consulta inexistente.

Definir antes de implementar: distancia geográfica frente a recorrido por calles, orden longitud/latitud y unidades, calidad/origen de cada punto, representación de coordenadas desconocidas sin inventar `(0,0)`, filtros de publicación y pruebas de límites de 10/200/500 metros. Conservar IDs, relaciones y coordenadas existentes mediante una migración si se incorpora geometría. La cantidad de decimales almacenados no expresa la precisión real de la ubicación: el radio de 10 metros requiere considerar la incertidumbre informada por el dispositivo y la calidad del punto del negocio.

Fuentes de capacidades del motor: [índices espaciales de MariaDB](https://mariadb.com/docs/server/reference/sql-structure/geometry/spatial-index) y [distancia esférica en metros](https://mariadb.com/docs/server/reference/sql-statements/geometry-constructors/geometry-relations/st_distance_sphere). Precisión de ubicación: [especificación de Geolocation](https://w3c.github.io/geolocation/). Evidencia local: `.local/architecture-query-index-audit/mariadb-indexes.json`. Esta sección es una referencia para desarrollo futuro, sin infraestructura, índices o API nuevos.

## Separación futura entre servidores: referencia de diseño

La arquitectura debe facilitar una futura separación de presentación, backend y base de datos conservando la tecnología actual. La siguiente tabla documenta condiciones para cuando se decida esa separación; no obliga a implementarlas en el refactor actual. No se necesita React para alojar la presentación en otro servidor: una presentación PHP también puede consumir servicios HTTP. Una interfaz PHP solo desacopla componentes dentro del proceso; no constituye una llamada remota ni un contrato de red.

| Límite | Estado actual del módulo de lugares | Comprobación necesaria antes de separarlo |
| --- | --- | --- |
| Presentación → backend | La página PHP obtiene datos mediante PDO/repositorio en el mismo proceso. El primer contrato interno de lectura está introducido. | Casos de uso compartidos y API versionada con modelos explícitos; consumidor remoto con timeout y errores controlados. Paridad de HTML inicial, rutas, SEO e idiomas. |
| Backend → base | SQL agrupado en selectores y writer; el servicio administrativo aún conoce PDO. | Configuración del host y credenciales fuera del código, pruebas con conexión remota, límites/tiempos de conexión y transacción coordinada en una sola conexión por operación. No distribuir la transacción entre llamadas HTTP independientes. |
| Backend → imágenes | Adaptador de archivos separado; usa disco local y rutas relativas históricas. | Contrato de medios, clave estable independiente de ruta local/origen público y adaptador compartido o almacenamiento externo. Comprobar subida, lectura desde otra instancia, créditos y compensación de archivos sin tocar imágenes aprobadas. |
| Sesiones y autorización | Sesiones y comprobaciones de cuenta en los límites HTTP actuales. | Sesión accesible desde todas las instancias necesarias, expiración/revocación coherentes, cookies/CSRF u otro esquema explícito para clientes remotos; un cambio de servidor no debe conceder permisos. |
| Configuración y operación | Paquetes reproducibles y dev reservado; no se ha ensayado despliegue distribuido del módulo. | Orígenes públicos, URL del backend, base, medios y sesión configurables; HTTPS, secretos por entorno, registros correlacionados, salud, respaldo/restauración y compatibilidad de versiones. |
| Capacidad | Las regresiones verifican funcionalidad y número estable de consultas en ciertos flujos. | Medir carga con un perfil representativo: latencia, errores, CPU, memoria, conexiones, consultas y disco. Registrar umbrales y cuello de botella antes de atribuir una cifra de usuarios a la arquitectura. |

Trabajo actual, por entregas pequeñas con comparación anterior/candidato y regresiones:

1. Revisar y completar los límites internos del módulo público, con contratos solo donde desacoplen un consumidor real; mantener las entradas existentes.
2. Separar las dependencias administrativas de lectura, escritura y transacción sin dividir una operación entre conexiones. Conservar la regresión MariaDB con tablas ordinarias aisladas y claves foráneas, cerrada en la etapa 6.
3. Simplificar controladores y preparar datos explícitos para las plantillas; conservar reglas compartidas, render inicial, autorización, idiomas y SEO.
4. Continuar retirando transformaciones de HTML y duplicaciones, sin añadir infraestructura para sustituirlas.

Cuando exista una necesidad concreta de clientes móviles, React o separación física, decidir el contrato HTTP y revisar medios, sesiones, configuración y fallos de red antes de extraer ese componente. La medición de capacidad puede hacerse con la aplicación actual: no requiere crear servicios remotos. Este plan no autoriza ambientes, puertos, gastos ni producción adicionales.

## Idioma, datos y lugares nuevos

### Etiquetas de lugares y ubicación — 3 de noviembre de 2026

La etiqueta geográfica usa `subregion` y, si está vacía, `department`. El contrato histórico mezcla regiones y subregiones; este ajuste no lo transforma en una clasificación nacional uniforme. La pertenencia a la Red Turística de Pueblos Patrimonio se guarda por identidad en `place_labels`, código técnico `heritage_town`, separada de la geografía y del tipo de lugar. No inferirla del nombre, la arquitectura, la cercanía ni del municipio padre: Cauca Viejo no hereda la etiqueta de Jericó.

`PlaceSelector` lee únicamente la bandera pública mediante `EXISTS` y la clave primaria `(place_id,code)`, sin consultas adicionales por tarjeta. `PlaceLabelPresentation` prepara ubicación y texto de la etiqueta en ES/EN/PT-BR; `views/place-labels.php` escapa ese modelo y se comparte entre listado y guía. No consultar la base ni agregar condiciones por slug en la vista.

Administración guarda las etiquetas mediante `PlaceAdminService` y `PlaceWriter`, dentro de la transacción editorial. Desmarcar conserva una fila deshabilitada y su procedencia; la migración no la reactiva. Una entrada antigua sin `labels` conserva su estado, mientras una lista explícita vacía lo retira. `PlaceLabels::normalize()` valida los códigos compartidos con importación inicial y paquetes. Los catálogos usan `labels: ["heritage_town"]` o `labels: []`; no generar una plantilla especial por lugar. El importador del formulario reconoce el campo opcional y conserva compatibilidad con paquetes anteriores.

La migración versionada 062 crea la tabla e incorpora las tres pertenencias verificadas actuales —Jardín, Jericó y Santa Fe de Antioquia— con fuente y fecha. Completa solo las regiones vacías de Barranquilla, Riohacha, Valledupar y San Bernardo del Viento con Caribe. Conserva valores posteriores no vacíos, filas deshabilitadas, identidades y relaciones. El mantenimiento reconoce/resume la aplicación incompleta de estas sentencias idempotentes. Aplicar antes de instalar el código que consulta la tabla; no importar de nuevo todo el catálogo para reparar metadatos.

La evidencia de pruebas, fuentes y entrega en dev1 figura en `unificacion/ARCHITECTURE_STAGES.md`. Producción requiere su circuito de integración, revisión y aprobación; que el fallo se haya reproducido allí no autoriza corregir directamente sus datos.

La implementación usa nombres ingleses y la entidad `Place`. El portal y la administración tienen español como idioma principal, con traducciones a inglés y portugués de Brasil. Los textos visibles de interfaz se mantienen separados del contenido editorial traducido.

Un lugar nuevo aporta registros, relaciones y medios al mecanismo compartido. No se crean controladores, plantillas ni condiciones por slug para incorporarlo. Los catálogos de aprobación y procedencia no autorizan mantener varias versiones contradictorias del contenido publicado.

Las URL anteriores se conservan mediante compatibilidad explícita que reutiliza la misma implementación. Los contratos JSON/ZIP y nombres persistidos se cambian mediante migraciones versionadas; no mediante reemplazos textuales sobre datos o planes aplicados. Conservar identidades, estadísticas y cuentas.

## Etapas pendientes y control de cambios

### Listado público: filtros y paginación — 3 de noviembre de 2026

El listado `/lugares/` usa `PlaceDirectoryService`, `PlaceDirectorySelector` y `PlaceDirectoryPresentation`, con una vista privada explícita y un catálogo de interfaz ES/EN/PT-BR. El controlador resuelve petición/conexión/redirección; no contiene SQL ni HTML. La vista recibe filas ya paginadas, filtros, opciones y enlaces; no conoce la petición, sesiones o consultas. No reconstruir sus tarjetas con regex ni recuperar el paginado ocultando un catálogo completo en el navegador.

La página tiene nueve lugares. El selector ejecuta conteo, selección con `LIMIT/OFFSET` y opciones publicadas del idioma. Búsqueda por nombre, departamento, región/subregión y reconocimiento patrimonial se combinan antes de paginar. Los valores se parametrizan; `%` y `_` escritos en la búsqueda son literales. La variante del slug permite buscar Jericó sin tilde. El selector elige una portada determinista por lugar para no duplicar filas cuando haya varias relaciones de portada. `PlaceSelector::listPublished()` conserva su contrato completo delegando al mismo selector; los consumidores antiguos no reciben accidentalmente una sola página.

Los formularios GET y enlaces funcionan sin JavaScript. `q`, `region` y `pagina` conservan sus parámetros históricos; `department` y `heritage` agregan los filtros nuevos. Cambiar filtros inicia la primera página; paginar, cambiar idioma y volver por historial conservan el estado en la URL. Una página fuera de rango redirige temporalmente al límite válido. Vacío, parámetros mal formados y filtros incompatibles no exponen borradores ni producen errores técnicos.

Cada página sin filtros tiene canónica propia; búsquedas/filtros llevan `noindex,follow`. Los enlaces de idioma y metadatos sociales conservan página/filtros. El JSON-LD describe solo las tarjetas visibles. Se comparten encabezado, pie y etiquetas de lugares sin postprocesamiento. La hoja histórica `places.css` mantiene las reglas del título y su alias de recursos; los controles nuevos tienen CSS propio y versionado. No retirar esos recursos por no reconocer una referencia dinámica.

Controles: `backend/tests/place-directory.php`, el mismo conjunto sobre MariaDB ordinario, `scripts/check-place-directory.py`, fronteras en `test_place_query_boundary.py` y `dev/check-place-directory-http.php`. El comprobador de etiquetas recorre todas las páginas y verifica el conjunto completo, además de cada etiqueta; no sustituir la comprobación de veinte lugares por una comprobación exclusiva de la primera página. Estas pruebas no certifican capacidad de carga ni una clasificación geográfica nacional uniforme.

1. Revisar las dependencias de directorio/operadores y composición; introducir contratos solo si simplifican sus consumidores actuales. Completar después los límites administrativos de lectura/escritura/transacciones, manteniendo una aplicación modular.
2. Extender la separación de controladores/vistas solo a los módulos que lo requieran; la vista de administración de lugares ya está separada y verificada.
3. Retirar transformaciones de HTML de portada, privacidad y suscripciones, una familia por entrega.
4. Centralizar el registro de idiomas, introducir organización y comprobaciones automáticas en un módulo piloto.
5. Extender selectores y límites de responsabilidad a los demás módulos administrativos.
6. Migrar nombres persistidos `destination_*` y contratos relacionados con respaldo, repetición y conservación de relaciones comprobados.

Las excepciones existentes incluyen SQL en otros controladores, transformaciones en `PortalPageRenderer` y la construcción. Esta guía no declara esos trabajos terminados. No se amplían las excepciones para incorporar nuevos lugares o funcionalidades.

## Regla implementada: layout explícito del editor de negocios

El modo administrativo de `public/business/editorial.php` prepara `admin_layout_context()` después de autorizar y procesar la petición. Declara `PortalPresentation::assets(false,'admin')` en el head, `portal-admin` en el body, `admin-page` en el main y la inclusión del menú en su posición final. No abre un buffer para capturar y transformar el documento. Los enlaces compartidos de navegación parten de `/admin/` y funcionan desde el editor situado en `/business/`, sin corregir posteriormente sus atributos.

El modo de propietario usa `account_layout_context()` para resolver y recordar el idioma, el contexto de la cuenta y CSRF en el límite HTTP. Declara `portal-account-page`, encabezado y pie compartidos en sus posiciones finales. La selección `css`/`js` de `PortalPresentation::assets()` filtra el catálogo de recursos antes de generar etiquetas; no extrae etiquetas desde HTML. El modo administrativo usa la selección `all` y conserva sus recursos. Los consumidores anteriores de `assets()` conservan exactamente su salida por defecto.

Los datos del negocio no pasan por `translateAccount()` ni por un parser del documento. La traducción de interfaz corresponde a textos/componentes identificados; un nombre o nota que coincida con «Nombre» o «Tu cuenta» debe conservarse como dato. Los textos y campos editoriales existentes se mantienen, con el encabezado/pie compartidos en ES/EN/PT-BR; esta extracción no completa las traducciones faltantes de otras pantallas. Las previews siguen delegando en sus renderizadores existentes.

No introducir capturas/reemplazos de HTML completo para añadir menús, clases o recursos. Preparar datos de presentación e incluir los componentes en su posición definitiva. Usar URLs explícitas que funcionen desde todos sus consumidores; verificar el destino resuelto, además de la existencia del archivo. Los buffers que solo devuelven un componente ya definido, sin analizarlo ni reemplazar su contenido, son una técnica distinta y no están prohibidos.

La regresión `scripts/check-editorial-layout.py` compara el controlador real anterior y candidato en 104 escenarios de lectura/rechazo: cuatro planes, siete estados, administrador/propietario, administración antigua, idiomas explícitos/recordados, vistas previas, escapado, versiones ajenas/ausentes, caducidad y CSRF. Incluye contacto, acceso con diálogo y las dos secciones del portal de cuenta para comprobar la extracción compartida del contexto. Dos casos adicionales reproducen la traducción accidental de nombres/notas en la referencia y exigen conservar los datos en el candidato. Comprueba estructura/textos/campos, versiones y bytes de recursos, y ausencia de escrituras. Ignora únicamente nodos de texto vacíos de espacios de formato. Usa copias SQLite sintéticas desechables por CLI, no un nuevo servidor del portal. `check-admin-layout.py` conserva 114 comparaciones; los links relativos/desde raíz se comparan por el mismo destino efectivo. Una barrera de arquitectura impide reintroducir los dos parcheadores en el editor.

Apache/MariaDB de dev1 verifica el layout y las sesiones reales antes y después en 23 casos. Su fixture no contiene suscripciones ni relación del negocio elegido con la cuenta de prueba: administración conserva el aviso HTTP 503 «Primero selecciona una suscripción» y cuenta conserva su rechazo de acceso en los tres idiomas. Coinciden el HTML administrativo y el DOM de cuenta una vez neutralizado el token sintético y los espacios de formato. Esto acredita composición/autorización en esos estados, no un circuito comercial completo sobre MariaDB.

Excepciones pendientes explícitas: otras páginas de cuenta todavía usan `_account-page.php`, que captura/recompone HTML y traduce nodos; otras familias usan `PortalPageRenderer` y transformaciones de construcción. Retirarlas por familias con regresión propia. Acceso, recuperación y cambio de contraseña se extrajeron en la etapa 10. El primer bloque de la etapa 11 separa el registro personal, la verificación de correo y la pantalla anterior al registro de un negocio. El segundo bloque separa el perfil personal y el tercero el panel de negocios; las demás pantallas de cuenta continúan por entregas pequeñas. El SQL que queda en el editor también requiere otra separación; estas etapas no declaran terminada la arquitectura del módulo.

## Regla implementada: acceso y contraseñas con vistas explícitas

Los controladores de `login.php`, `recover.php` y `password.php` mantienen su procesamiento anterior completo: autenticación, autorización de enlaces, envío de recuperación, límites, CSRF, reactivación, transacciones y redirecciones. Después preparan un modelo y llaman a `AccountAuthPresentation`. La comparación exige igualdad literal del código anterior a ese límite de presentación, además de comprobar su comportamiento.

`views/account/auth-page.php` declara documento, recursos, encabezado, fondo, diálogo y pie. Sus vistas de contenido reciben modelo y textos preparados; no leen petición/sesión, consultan datos, modifican cuentas ni capturan el documento para reescribirlo. `AccountAuthPresentation` selecciona una de tres vistas conocidas y resuelve las etiquetas por identificadores técnicos explícitos desde el catálogo existente. Los valores de formularios, errores y tokens se presentan escapados, sin traducir nodos de HTML. Se conservan las traducciones actuales; no se declara completa la cobertura multilingüe del portal. También se conserva la hoja de identidad final que añadía el constructor anterior, para mantener la misma cascada de estilos; su duplicación heredada no se elimina mediante este refactor.

El fondo comparte `views/home-main.php` con la portada pública: una estructura y un catálogo de textos en `presentation/home.json`. Las imágenes existentes y sus hashes se conservan. El constructor inserta el componente en un único marcador explícito `portal-home-main`; el acceso lo incluye directamente. No extraer una sección de otra página mediante regex ni crear una copia de portada por formulario/idioma. El renderizador público todavía incorpora datos dinámicos de atractivos mediante su mecanismo anterior: retirarlo pertenece a la etapa pública pendiente.

Validación de la etapa 10: 96 comparaciones de controladores en copias SQLite sintéticas y sesiones desechables por CLI; incluyen acceso correcto personal/administrativo, errores, estado cerrado, idioma recordado, recuperación disponible/no disponible/confirmada, token válido/vencido, contraseña nueva, reactivación con confirmación y rechazo CSRF. Se comprueban formulario, estructura, recursos, sesión y resultado persistido, incluidos revocación de enlaces y conservación de identidad. Seis comparaciones de portada/componente conservan textos, enlaces resueltos, imágenes y metadatos de las tres portadas. Las vistas/presentador tienen barreras automáticas contra HTTP/sesión/SQL y parcheado de HTML.

Apache/MariaDB de dev1 compara 27 casos de lectura/rechazo antes y después, más las 23 comprobaciones privadas anteriores. Conserva encabezados privados, diálogo, campos y DOM localizado; los POST de esa prueba fallan por CSRF antes de escribir. No se cambian contraseñas de las cuentas de dev1 ni se envían correos. Los cambios de contraseña/reactivación y accesos correctos se ensayan únicamente en las copias desechables por CLI; no atribuir esas escrituras al ensayo MariaDB.

Quedan nueve consumidores de página del helper heredado: registro/elección de registro, verificación de correo, portal personal/negocios, pago/checkout, contacto y reclamación. Retirarlos de uno en uno, comprobando también los diálogos que reutiliza `account-entry.js`. No sustituir todas las páginas a la vez ni ampliar el helper que reconstruye HTML.

Cada etapa registra alcance, comportamiento anterior, pruebas, paquete y limitaciones. Una regresión se resuelve antes de avanzar. La revisión incluye permisos, reglas, consultas, idiomas, nombres, transacciones, medios y SEO. Los controles automáticos se amplían al completar cada límite, sin desactivar comprobaciones para obtener resultados positivos. Producción conserva su circuito independiente de integración y aprobación exacta.


### Registro y residencia: límites del primer bloque de la etapa 11

- El controlador procesa la cuenta, sesión, CSRF, residencia y correo antes de entregar datos a `AccountAuthPresentation`. Las vistas de registro/verificación/acceso previo al negocio no consultan la base ni leen petición o sesión. Un texto ingresado por una persona no se busca en el catálogo de etiquetas.
- `AccountResidencePresentation` prepara los valores y presenta un único componente para registro y perfil. Recibe el catálogo ya cargado y los valores explícitos de petición/almacenamiento; no carga datos de cuenta ni decide su validez. El pequeño adaptador público de residencia conserva el acceso a la petición de sus consumidores heredados hasta que se extraigan.
- Los recursos de componentes se declaran con la versión de sus bytes reales antes de incluir la vista. Un archivo movido desde un controlador público a `app/views` no debe depender de que el constructor reescriba sus etiquetas ni conservar un `v=1` fijo. La comparación del perfil verifica también la versión de su recurso, además de sus bytes.
- `AccountAuthPresentation` elige entre diálogo y página completa por una lista cerrada de páginas; no analiza ni recompone el documento producido. Los recursos y el favicon propios de cada pantalla conservan su posición y presencia anteriores.

Control de regresión: `scripts/check-account-registration-views.py` compara los controladores, los resultados de cuenta/verificación en copias descartables y el componente de residencia. `scripts/tests/test_admin_session_boundary.py` impide volver a introducir petición, sesión, SQL o transformación posterior del HTML en estas vistas. Los controles de seguridad se conservan en sus límites actuales; no se trasladan a una plantilla.


### Perfil personal: vista y acciones separadas (etapa 11, segundo bloque)

`public/business/traveler.php` sigue procesando la residencia y el cierre después de la autorización del panel. Conserva el procesamiento previo al render; prepara después cuenta, avisos, permisos, CSRF y residencia y entrega solo esos datos a `AccountProfilePresentation`. La vista privada `app/views/account/profile.php` declara directamente el documento y usa encabezado/pie compartidos; no abre un parcheador de HTML ni consulta petición, sesión o base. Los recursos se declaran con sus hashes reales, conservando el orden y la cascada anteriores.

`AccountProfilePresentation::renderLogout()` presenta un único formulario firmado de salida. El perfil y el panel de negocios lo usan con CSRF preparado. El adaptador público `public/business/profile-logout.php` se retiró al extraer el panel; no debe reintroducirse. El adaptador público de residencia ya no tiene consumidores entre los controladores de cuenta; mantiene las comprobaciones históricas de compatibilidad hasta su retirada explícita. No añadir consumidores nuevos a esos adaptadores.

Solo las etiquetas identificadas de interfaz usan el catálogo de traducciones. El nombre y el correo de una persona se presentan escapados como datos: un nombre «Nombre» no se convierte en «Name/Nome» por coincidir con una etiqueta. `scripts/check-account-profile-views.py` compara el controlador completo anterior/candidato, exige que el procesamiento y el dispatch no cambien y verifica guardado/cierre, aislamiento de cuenta, conservación de identidades/suscripciones y dos casos de nombre almacenado. La validación de sesión que el bootstrap omite bajo CLI se invoca explícitamente; la evidencia HTTP/MariaDB se registra por separado.


### Panel de negocios: datos antes de la vista (etapa 11, tercer bloque)

`dashboard.php` conserva autorización, selección de pestaña, decisiones del propietario y redirecciones. Reutiliza las filas de `BusinessWorkspace` y el historial de `BusinessPaymentEvidenceService`; no se crearon servicios equivalentes. Después de procesar la petición prepara el contexto, los avisos, CSRF, etiquetas de plan/período y límite de comentario. Para cada fila de negocio obtiene el QR con `BusinessQrCode::publishedPage()` antes de renderizar. La consulta era idéntica dentro del HTML; se conserva su política de página pública y vigencia y no se declara optimizada su consulta por fila.

`BusinessWorkspacePresentation` recibe esos datos y resuelve exclusivamente recursos y decoración. La vista privada declara el documento, sus componentes, formulario de salida, diálogos y scripts en la posición final. No usa SQL, PDO, petición/sesión, constantes de servicios de negocio, postprocesamiento del documento ni importaciones de plantillas públicas. Los estados y las decisiones siguen perteneciendo a los servicios actuales y al controlador.

Se retiraron los tres fragmentos públicos sin consumidores restantes: iconos del panel, diálogo QR y adaptador de salida. Sus componentes son privados y reutilizan la única presentación de salida ya existente. Las ocho formas SVG se conservan byte a byte. Las pruebas comparan SVG con etiquetas vacías/cerradas por su misma estructura, sin ignorar anidación, atributos u orden. Nombres de cuenta/negocio y mensajes de pago se escapan como datos; nunca se traducen por coincidir con etiquetas de interfaz.

`scripts/check-business-workspace-views.py` exige identidad del procesamiento anterior al render, salvo retirar las dos importaciones de helpers de presentación. Compara las decisiones, resultados persistidos, identidades, contratos y pagos en copias aisladas y el HTML/recursos en todos los estados del panel. Las barreras de arquitectura impiden reintroducir esas dependencias en la nueva vista y el presentador. Las comprobaciones de perfil siguen verificando el dispatch previo a su render, y la autorización de sesión bajo CLI se invoca explícitamente.

### Registro comercial: formulario explícito y componentes compartidos (etapa 11, cuarto bloque)

`public/business/register.php` conserva el procesamiento previo a la presentación, incluida la recuperación de archivos después de un rechazo. Autorización, cuenta verificada, búsqueda, CSRF, borradores, revisión optimista, captura, soporte, asociación y redirecciones siguen en sus límites anteriores. El controlador prepara ahora también las etiquetas de períodos y el texto del complemento mediante `SubscriptionPricing`, las reglas existentes de `BusinessRegistrationPlan` y el contexto del teléfono; estas operaciones ya no se ejecutan desde una plantilla.

`BusinessRegistrationPresentation` recibe el modelo explícito y resuelve recursos y las etiquetas identificadas del catálogo existente. Las vistas privadas de documento, suscripción, contenido y complemento no leen petición/sesión, consultan la base ni procesan acciones. Declaran encabezado, pie, mapa y recursos en su posición final, sin capturar, analizar ni reconstruir el HTML. Solo las etiquetas conocidas se traducen; nombres de lugares, datos capturados y errores se escapan como datos. Esta extracción conserva las traducciones disponibles y no completa textos que ya estaban sin traducir.

`BusinessPhonePresentation` prepara el mismo control de teléfono/WhatsApp con entradas explícitas. El registro lo incluye directamente; edición y administración conservan un pequeño adaptador público hacia ese único componente privado. La normalización de dígitos del teléfono permanece: no es una transformación de HTML. También se conserva la representación anterior de entradas mal formadas cuando el controlador devuelve un error, sin convertir una validación rechazada en un error fatal por tipos nuevos de presentación. El mapa está en un único componente privado; sus consumidores anteriores usan un adaptador sin datos ni lógica. No ampliar los adaptadores con consultas o nuevas responsabilidades.

Se retiraron los tres fragmentos comerciales públicos sin consumidores de ejecución: `new-business-fields.php`, `subscription-choice.php` y `operator-addon-choice.php`. Las pruebas históricas ahora ejercitan las vistas privadas con datos preparados; no se mantienen implementaciones paralelas para satisfacerlas. Los recursos conservan bytes, orden y versiones anteriores, incluidos dos parámetros literales heredados y la hoja de identidad final que mantiene la cascada.

Controles: `check-business-registration-views.py` exige identidad del procesamiento y compara 115 casos del controlador real, más dos regresiones de nombres almacenados. Incluye los tres idiomas, cuatro planes, intención de compra, borrador propio/ajeno/inexistente, archivos vencidos, revisiones obsoletas, rechazo y conservación de campos, búsquedas JSON, CSRF, permisos y guardado de borradores. Los cuatro registros satisfactorios usan copias CLI desechables con SQLite, documentos e imágenes sintéticos: comprueban negocio privado, plan pendiente, borrador enviado, consumo/copia de soportes e identidad de cuenta conservada. No acreditan subida HTTP real, pago, publicación, entrega de correo ni un recorrido completo en MariaDB. Nueve comparaciones adicionales verifican el teléfono almacenado/enviado, prefijos, escape y compatibilidad de ambos consumidores.

Apache/MariaDB en dev1 compara 30 casos comerciales antes/después, además de los 143 casos previos: HTML, recursos, formularios, mapa, planes, búsqueda, estados y rechazos coinciden. Estos casos nativos no crean negocios, cuentas ni borradores. Las vistas nuevas tienen barreras contra petición/sesión/SQL y postprocesamiento del documento. Continúan pendientes SQL en el controlador comercial, la organización adicional de sus acciones y la revisión visual completa del wizard; no se declaran resueltos por mover su presentación.

Quedan cuatro consumidores de `_account-page.php`: checkout, pago, contacto y reclamación. La siguiente extracción es checkout/pago por entregas verificables, preservando el plan, comprobantes, cupones y autorización. El renderizador público y las transformaciones de construcción conservan su etapa independiente.

### Requisito de evolución: una orden para varios negocios — 3 de noviembre de 2026

El propietario pide seleccionar varios negocios de su cuenta y pagar en una sola operación altas, renovaciones y complementos de experiencia. Pueden pertenecer a categorías y lugares distintos, y conservar planes y períodos diferentes. No obligar a repetir el pago por cada negocio. El pago manual agrupado está implementado en el sexto bloque de la etapa 11, descrito al final de esta guía. La futura pasarela conserva su entrega propia.

La separación necesaria es cuenta → orden → conceptos por negocio/suscripción, y orden → intentos de pago/confirmación. Cada concepto debe identificar negocio autorizado, operación (alta, renovación o cambio admitido), plan, período, complemento aplicable, cotización y descuentos validados por las reglas existentes. El total de la orden se calcula en el servidor como suma exacta de conceptos en la misma moneda. Agrupar no crea automáticamente descuentos por volumen ni traslada un cupón a negocios para los que no aplica.

El pago manual y una futura pasarela deben confirmar la misma orden y su distribución por conceptos. Conservar una sola referencia/comprobante por operación y la trazabilidad del importe aplicado a cada suscripción; no duplicar el mismo comprobante como pagos independientes. Los intentos y confirmaciones deben ser idempotentes: un reintento o notificación duplicada no cobra, aplica saldo ni renueva dos veces. Revalidar permisos, vigencias y cotizaciones antes de iniciar el pago; ante cambios mostrar el nuevo resumen antes de confirmar. Una operación iniciada conserva su cotización e historial para conciliación.

La confirmación del total y su distribución se registran de forma consistente en una transacción. Cada negocio conserva por separado su revisión de titularidad, preparación editorial, publicación y vigencia: pagar juntos no publica todos ni iguala sus vencimientos. La renovación no debe perder días ya contratados. Las reglas de inicio/activación, cambios y tratamiento de incidencias se verifican por concepto; no se rediseñan por conveniencia de la agrupación. Las decisiones de reembolsos parciales o pagos insuficientes requieren su política explícita antes de implementarse.

Compatibilidad histórica: `BusinessPaymentEvidenceService::submit()` recibe un solo `businessId`, y `business_subscription_payment_evidence` referencia un solo `subscription_id`. El nuevo flujo usa una orden persistida y relaciones de conceptos/evidencia; no repite el envío individual por negocio. La fila histórica del comprobante se vincula a la primera suscripción, y la relación de orden permite consultar la misma evidencia desde todos los conceptos. El método individual bloquea negocios reservados por una orden abierta, también después de adquirir el bloqueo de escritura.

Aceptación de esa entrega: seleccionar dos o tres negocios de diferentes categorías/lugares; mezclar alta y renovación con complemento permitido; mostrar desglose y total; excluir negocios ajenos, conceptos duplicados y cotizaciones inválidas; admitir un solo comprobante para la orden; conservar fechas, saldos e identidades; repetir envío/confirmación sin duplicar efectos; probar rollback de aplicación incompleta y mantener la lectura de comprobantes históricos individuales. Registrar aparte las pruebas del medio manual y las de la futura pasarela.

### Selección del negocio: presentación independiente (etapa 11, quinto bloque)

`public/business/checkout.php` mantiene su URL y todo el procesamiento anterior: captura/vencimiento de la intención, sesión, listado de negocios propios, autorización del complemento, selección y redirección. Después prepara una lista de nombres, lugares y URL, etiquetas del plan/período y avisos; `BusinessSelectionPresentation` recibe ese modelo. La vista privada declara documento, recursos, encabezado y pie en sus posiciones finales. No consulta datos, accede a la petición/sesión, invoca reglas comerciales ni recompone HTML. Los nombres de clases nuevos describen la selección; no representan una pasarela ni una orden de pago.

El enlace de vuelta usa la ruta canónica `/negocios-locales/suscripciones/`, que antes reescribía el constructor sobre la plantilla pública. La nueva vista privada debe emitir esa ruta directamente. Se conservan los recursos, versiones, orden y textos anteriores. El catálogo antiguo no traduce etiquetas de esta pantalla; el refactor conserva esa cobertura y no declara completada su traducción. Los valores de negocio/lugar se escapan como datos.

Regresión: `scripts/check-business-selection-views.py` exige identidad literal del procesamiento y compara 126 escenarios de controladores reales en copias CLI desechables: cuatro planes/cuatro períodos, intención vigente/ausente/vencida, cuenta y membresía, sesión vencida/revocada, selección propia/ajena, categorías del operador, experiencia del mismo/otro lugar, tarifa ausente, nombres almacenados y recursos. Comprueba ausencia de escrituras en toda la base de prueba y los resultados de selección en sesión. No acredita órdenes agrupadas ni operaciones de pago. Dev1/MariaDB verifica 75 casos de lectura/rechazo más los 173 previos, con resultados, estructura y destinos iguales antes/después; no crea negocios, suscripciones ni pagos. El caso satisfactorio de elegir un negocio asociado se prueba en las copias CLI; la fixture nativa de este informe no acredita esa redirección satisfactoria con membresía.

El comparador HTTP de pruebas usa `setAttribute()` al normalizar atributos y resuelve enlaces que solo contienen query sobre el archivo actual. No asignar URL con `&` mediante `DOMAttr::value`: puede interpretar entidades y perder parámetros. Tres pruebas independientes ejercitan las funciones reales de comparación, comprueban el destino completo y exigen que un parámetro diferente produzca otra huella. Es código de validación; no introduce DOM ni transformaciones en la aplicación.

Al cerrar el quinto bloque quedaban tres consumidores de `_account-page.php`. El sexto retira pago; quedan contacto y reclamación. La selección individual compatible no restringe la nueva orden agrupada.

### Pago manual y orden agrupada (etapa 11, sexto bloque)

`business/payment.php` controla acceso, CSRF, acciones y redirecciones; `BusinessPaymentPresentation` recibe un modelo explícito y renderiza vistas privadas sin SQL, sesión ni recomposición de HTML. Los componentes de instrucciones y términos conservan adaptadores para consumidores anteriores. La orden admite de uno a cincuenta negocios distintos de la cuenta, planes y períodos diferentes, renovación y complemento compatible. Se reutilizan cotizaciones, promociones y prorrateos existentes; no hay otro catálogo de precios.

`BusinessPaymentOrderSelector` concentra lecturas y `BusinessPaymentOrderWriter` escrituras en la misma conexión. El writer exige una transacción abierta; `BusinessPaymentOrderService` controla validación, bloqueos, transacción e idempotencia, sin ejecutar SQL. Todavía recibe PDO y compone adaptadores concretos: no se declara independiente del motor. `PaymentEvidenceStorage` separa la subida HTTP real de su sustituto de pruebas; `BusinessPaymentUpload` valida MIME/tamaño/origen HTTP, guarda privadamente y elimina únicamente archivos nuevos si falla la operación.

La migración 061 añade tres tablas de órdenes, conceptos y relaciones de evidencia, con claves foráneas y sin alterar las tablas anteriores. Una clave de petición por cuenta y su huella impiden crear órdenes duplicadas o reutilizar la clave con otra selección. La reserva única por negocio impide órdenes abiertas superpuestas. El total se calcula en centavos COP en el servidor. La cotización emitida se conserva aunque cambie el catálogo; el usuario puede cancelar una selección sin comprobante en revisión y preparar otra. Cancelar la selección no revierte una transferencia.

Cada intento adjunta un archivo y una fila de comprobante, con referencia de intento única y referencia de orden estable. Un rechazo conserva el intento anterior; la corrección crea otro, sin sobrescribirlo. La aprobación administrativa confirma todos los conceptos o revierte todos. Reintentar preparación/envío/aprobación no duplica efectos. Un cupón parcial conserva su suscripción y saldo; cobertura total no inventa comprobantes. El importe insuficiente se rechaza; un excedente sigue la recepción histórica, visible para revisión, sin generar crédito automático.

Historial, descarga privada y revisión administrativa muestran la orden una sola vez con su desglose. Workspace y preparación editorial consultan también la evidencia del concepto para los negocios que no son el primero. El borrado administrativo conserva negocios con historia de órdenes. La confirmación no publica negocios ni modifica las fechas activas anteriores. No se implementó una nueva política automática de activación de renovaciones, reembolsos ni una pasarela.

Verificación: 43 casos del servicio en SQLite y los mismos 43 en MariaDB con tablas ordinarias y claves foráneas, migración repetida, rollback de archivos/filas y aprobación parcial impedida. Dos preparaciones simultáneas en MariaDB reutilizan la misma orden. Diecinueve comprobaciones HTTP cubren tres negocios, PDF multipart real, descarga byte a byte, permisos, CSRF, reintentos y revisión administrativa. Dev1 pasa 248 regresiones anteriores más siete lecturas/rechazos del nuevo pago y 60 rutas públicas. Los cambios de DOM anteriores se limitan al enlace de pago agrupado del workspace, incluidos tres casos que prueban allí la salida compartida. Recursos visuales sin cambios.

Dev1 no tiene medios de pago configurados: se puede revisar selección y resumen, pero no enviar un comprobante. Las pruebas completas usan medios ficticios en una base aislada. No se enviaron correos externos ni dinero, ni se modificaron producción/preproducción. La revisión visual integral y el circuito completo de registro/publicación no quedan certificados por esta entrega. Continúan las excepciones de arquitectura de otras familias y las dos pantallas de cuenta restantes.

### Contacto: vista explícita (etapa 11, séptimo bloque)

`contact.php` mantiene el procesamiento existente y entrega contexto, errores, referencia, campos y tipos a `ContactPresentation` y su vista. La traducción opera sobre textos individuales del catálogo, sin transformar el documento. Se conservan expresamente las comprobaciones de sesión/cuenta heredadas mientras se revisa su lugar definitivo. La vista y presentador no acceden a petición, sesión ni SQL; el control `test_contact_view_uses_explicit_data` evita reintroducir ese acoplamiento. Los resultados de 30 comparaciones aisladas y 18 casos HTTP/MariaDB antes/después están en `ARCHITECTURE_STAGES.md`.

### Contacto: conservar el idioma y separar los datos del texto de interfaz

El formulario de contacto conserva el idioma al dirigir a su confirmación y a los enlaces de ayuda relacionados. Sus datos introducidos y referencias se escapan sin buscar coincidencias en el catálogo de traducciones. Los mensajes de error restantes y otros módulos deben revisarse por etapas; el detalle y el alcance de las pruebas están en `unificacion/ARCHITECTURE_STAGES.md`.

Los rechazos conocidos del formulario de contacto usan razones estables y un catálogo independiente por idioma. El servicio conserva sus reglas; la presentación decide el texto. Los fallos de almacenamiento no exponen el detalle de la base. Las pruebas y su alcance se registran en `unificacion/ARCHITECTURE_STAGES.md`.

Los mensajes de participación también se identifican mediante claves estables y se traducen en la presentación de la respuesta. Las reglas de validación y los datos almacenados no dependen del idioma; el alcance de las pruebas figura en `unificacion/ARCHITECTURE_STAGES.md`.

La lectura administrativa de solicitudes públicas reside en `PublicRequestSelector`; la autorización y la gestión de la petición siguen en el controlador, y la revisión en `PublicRequestService`. La presentación y otras consultas administrativas pendientes se tratan en etapas independientes.
