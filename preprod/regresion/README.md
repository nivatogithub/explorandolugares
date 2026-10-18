# Regresión de aceptación en preproducción

Criterios solicitados por el propietario el 17 de octubre de 2026. Esta suite no despliega ni autoriza producción. Una función pendiente nunca cuenta como aprobada.

Para comparar lo servido en preproducción y producción, ejecutar `python3 preprod/regresion/paridad_produccion.py`. Alcance, evidencias y requisitos todavía pendientes del recorrido con cuentas técnicas en producción: [PRODUCCION.md](PRODUCCION.md). Las pruebas locales no se contabilizan como pruebas de escritura en producción.

## Ejecutar

Con preproducción encendida y el código candidato actualizado:

```sh
./preprod/actualizar.sh
./preprod/regresion.sh
```

El segundo comando genera `.local/preprod/regresion/resultado.html` y `resultado.json`. Salida 0: todos los criterios implementados y comprobados; salida 1: fallos, pendientes o pruebas bloqueadas impiden el pase; salida 2: entorno local no comprobado, sin escrituras. El informe incluye commit, indicador de cambios sin commit y huella de los archivos públicos del paquete ejecutado. El resultado solo vale para ese candidato; cambiar código exige repetir pruebas.

## Conjuntos y criterios

- **CU — Cuenta:** alta personal con nombre/correo/clave, validación de campos, no duplicados, correo de activación, bloqueo previo a activación, token válido/vencido/alterado/usado.
- **AU/AZ — Acceso y permisos:** credenciales válidas e inválidas, nombre/sesión durante navegación, Mi cuenta Personal/Mis negocios, cierre, CSRF, no escalamiento a administrador, borradores privados.
- **RC — Recuperación:** formulario, recepción en buzón local, enlace, confirmación de clave, nueva clave funciona y anterior deja de funcionar, enlaces aleatorios/vencidos/usados rechazados.
- **NG — Negocio:** búsqueda, alta nueva con misma cuenta, reclamación gratuita sin dueño, vinculación tras validación, solicitud de revisión cuando otra persona lo administra sin quitarle la propiedad.
- **SU/PG — Suscripción/pago:** elegir gratuito o suscripción al alta, mensual/trimestral/semestral/anual, cotización persistida, medios reales configurados y tratamiento diferente del gratuito. Contrato corregido: Gratuito y tres pagas — Destacado, Página Comercial y Premium. El complemento de operador es independiente. Básico permanece solo en históricos. Política aprobada: trimestre 2,5 mensualidades, semestre 5,2 y año 10; migración037 y prueba SU-03. Los medios reales de pago siguen pendientes, nunca se inventan.
- **PB — Público:** navegación anónima y autenticada, idiomas, ficha publicada, cobertura/API y conservación de las tres pestañas y compartir.
- **CT — Contacto:** consulta comercial y promotor sin cuenta, tipo persistido y reenvío idempotente; PQRS tiene almacenamiento independiente, cuatro tipos, referencia y revisión administrativa; no envía respuestas por correo.
- **UI — Interfaz:** modal, posición de nombre/idiomas, pestañas, mapa, móvil y compartir requieren prueba en navegador del candidato. HTTP no ejecuta JavaScript. La suite no afirma cobertura visual automática completa; estos criterios siguen bloqueando mientras estén pendientes.

Los casos PENDIENTE son contratos de aceptación aún sin implementación o automatización completa. No basta con cambiar su etiqueta: deben implementarse, ejecutarse y aportar evidencia. Los conjuntos CU/AU/RC siguen dependencias explícitas: alta pendiente → buzón local de activación → rechazo previo al enlace → activación HTTP real → acceso → recuperación → nueva clave. Si una dependencia falla, sus procesos derivados se informan BLOQUEADA sin ejecutarlos; no se acumulan fallos secundarios por falta de cuenta, sesión o cambio de clave. Las pruebas independientes continúan. Ningún estado bloqueado cuenta como aprobado.

## Datos y aislamiento

Solo `http://127.0.0.1:8088`, contenedor `explorando-preprod`, configuración `local_test`, directorio privado local y runtime MySQL/MariaDB comprobado. No admite dominio o URL de producción. Credenciales aleatorias por ejecución; no se imprimen ni se guardan en el informe. Se crean personas y negocios inequívocamente sintéticos; uno se publica temporalmente **solo en preprod** para probar la ficha pública. No se alteran negocios importados ni las tres cuentas persistentes. Las consultas/escrituras auxiliares de BD preparan y comprueban fixtures; las acciones de usuario se prueban por HTTP.

La recuperación usa exclusivamente el buzón local. No se invoca proveedor de campañas ni se realiza transferencia/pago real. Al finalizar se eliminan cuenta, relaciones, solicitudes, negocios, correos y evidencias de esa ejecución; un fallo de limpieza también bloquea. Una interrupción forzada puede dejar fixtures con el prefijo `regresion-`; revisar el informe antes de limpiar, sin borrar datos ajenos. No se desactivan límites de abuso: si varias ejecuciones los alcanzan, se informa BLOQUEADA y se espera su ventana. La suite reconoce HTTP429 y mensajes de límite antiabuso en formularios HTTP400; no limpia ni modifica contadores globales.

La limpieza incluye los archivos físicos de imágenes adjuntas a solicitudes y negocios técnicos: borrar la fila no elimina la imagen del disco. El bloque final comprueba que ya no existan las cuentas, negocios, solicitudes, correos e imágenes de esa ejecución. No se declara aprobada la limpieza solo por haber intentado los borrados. Las cuentas y negocios persistentes de demostración local se conservan para revisión; no se trasladan a producción.

## Regla de paso

Todos los criterios solicitados deben aprobarse, ninguna prueba pendiente, limpieza correcta y validación de navegador del mismo candidato. Además sigue vigente la aprobación humana en preprod antes del despliegue; estas pruebas no sustituyen pruebas del proveedor real de correo, configuración real de pagos ni la validación posterior en producción. El comando solo genera informes y devuelve un código; todavía no está conectado como bloqueo obligatorio del script de despliegue o de CI.

## Panel de administración y lugares

`administracion_lugares.py` se ejecuta siempre con la suite completa. También puede repetirse sin altas, correos ni solicitudes comerciales:

```sh
./preprod/regresion.sh --solo-panel-lugares
```

El informe parcial se guarda en `.local/preprod/regresion/panel-lugares/`, separado del completo, y nunca aprueba por sí solo el pase general.

- **AD:** inicio del administrador sintético, las 14 entradas actuales del menú, integridad de enlaces, herramientas complementarias y salida. Se descubren enlaces adicionales del menú servido para no ignorar nuevas opciones. Se incluyen Reportes y Solicitudes de usuarios, y se revisan enlaces adicionales descubiertos en el menú.
- **AP:** las mismas 14 opciones deben rechazar tanto al anónimo como a un viajero autenticado sin rol administrador. La cuenta persistente de prueba solo inicia/cierra sesión; no se cambia su clave ni su rol.
- **LU:** todos los lugares con estado publicado en la base local deben figurar en el listado. En cada uno se comprueba respuesta, historia con contenido/enlace interno y directorio con filtros. Jericó debe conservar al menos las tres experiencias habilitadas y su posición después de los atractivos. Si otros lugares no tienen experiencias se señala para revisión editorial, sin inventarlas. El estado de publicación en base puede diferir de las restricciones del catálogo público: la prueba expone esa discrepancia para revisarla, no publica ningún lugar.

Acceder a una pantalla no prueba cada operación de guardado. AD-18 ejecuta la integración HTTP administrativa en una base aislada, con CRUD, validación de pagos y documentos privados. El conjunto separado unitarios_operacion.py comprueba diez servicios de dominio sin enviar campañas. La revisión visual de escritorio tiene capturas locales; no equivale a automatización visual completa ni prueba móvil.

## Presentación por suscripción

Los casos SV comprueban los cuatro niveles vigentes en el PHP servido: gratuito sin contacto/página ni destacado; Destacado resaltado; Página Comercial con enlace y página normal; Premium resaltado y con secciones propias. Se ejecutan en la suite general y con `python3 preprod/probar-directorio-suscripciones.py`. Las pruebas de compra, periodicidades y complemento de operador siguen siendo criterios separados.

## Contrato de activación y Mi cuenta (F04/F05)

CU-01 exige cuenta `pending_activation`, correo todavía no verificado y ausencia de sesión. CU-02 comprueba mensaje de activación para el destinatario sintético de esta ejecución. CU-03 intenta iniciar sesión antes de verificar. CU-04 abre por HTTP enlaces alterado, vencido, válido y usado; verifica activación/fecha en MariaDB y que el enlace no cree una sesión por sí solo. Solo se inserta directamente el fixture del token vencido, con digestSHA256 y fecha pasada para esa cuenta sintética; ni el enlace válido ni la activación se fabrican por base de datos. Se requiere migración035 aplicada y ruta `/cuenta/verificar-correo/` disponible en el paquete. El correo usado es local y no prueba entregabilidad real.

AU-04 verifica las dos pestañas mediante sus rutas reales `?tab=personal` y `?tab=business`, nombre de la persona y contenido específico de cada sección. No impone `role=tab` a enlaces normales ni usa el antiguo selector como requisito. El inicio de sesión no debe terminar en `/cuenta/espacio/`. El intento de enviar un espacio administrador sigue probando rechazo de escalamiento, sin utilizarlo para navegar normalmente. Las acciones de negocio abren Mis negocios por su pestaña, conservando la misma cuenta personal.

Los bloqueos de dependencias también se aplican al acceso administrativo: si AD-00/AP-00 no prepara la sesión correspondiente, sus comprobaciones derivadas no se ejecutan como falsos fallos. Persisten los criterios de medios reales de pago, validación automática externa, complemento de operador y automatización visual completa. Las comprobaciones de precios y PQRS ya se ejecutan; actualizar una etiqueta nunca sustituye la ejecución.

## Flujos públicos sin aprobación administrativa

```sh
./preprod/regresion.sh --solo-publico
```

Informe independiente en `.local/preprod/regresion/flujos-publicos/resultado.html` y `resultado.json`. Esta ejecución no inicia sesión como administrador, no abre pantallas del panel para completar procesos y no aprueba solicitudes ni comprobantes. Solo comprueba el rechazo de accesos indebidos al panel como prueba de permisos. Conserva alta/activación, acceso, recuperación, búsqueda, solicitud de negocio nuevo, reclamaciones, selección de suscripciones, navegación y contacto comercial/promotor públicos. Las pruebas administrativas siguen disponibles en el conjunto completo; no se borran.

`negocios_publicos.py` agrega NG-06 (altas independientes con las tres suscripciones pagas y cotización persistida), PG-01 (medios ficticios ya configurados), PG-03/04/05 (comprobante público para Destacado/Página/Premium, importe calculado por servidor frente a importe manipulado, estado pendiente e historial), PG-06 (Gratis sin soporte ni cobro), AZ-04 (selección de negocio ajeno rechazada).

La preparación crea un negocio sintético que representa un negocio **ya asociado antes de la prueba**. Este fixture sirve para probar pagos independientemente; no se utiliza como prueba de que una solicitud nueva o reclamación pendiente se haya asociado. NG-04 sigue comprobando esa transición por separado, sin aprobarla ni insertar su membresía para hacerlo pasar. Si no hay asociación automática implementada, el resultado debe seguir FALLIDA y el informe general NO LISTO. La inspección de persistencia y limpieza usa MariaDB; las acciones de las personas usan formularios/rutas HTTP públicas. No se alteran las cuentas persistentes, se envían correos solo al buzón local y se retiran únicamente los comprobantes y datos sintéticos de la ejecución.

Los medios de prueba deben estar previamente configurados en preproducción. PG-01 no los configura ni modifica: ausencia se informa BLOQUEADA. Esta prueba no certifica pagos bancarios reales ni activación automática de suscripciones. El complemento de operador y la cobertura visual completa siguen separados; no se califican como aprobados sin ejecución.
