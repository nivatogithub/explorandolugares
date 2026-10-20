# Reglas operativas para pruebas y despliegues

Estas reglas reducen las autorizaciones repetidas y el trabajo innecesario. Se aplican al repositorio y al hosting de Explorando Lugares junto con `AGENTS.md`, `docs/DESPLIEGUE_SFTP.md` y `docs/GOBERNANZA_AGENTES.md`.

## Preproducción obligatoria

### Coherencia obligatoria desde el 20 de octubre de 2026

Main debe reconstruir la última versión validada de producción: coincidencia de rutas y SHA-256 de TODO el paquete con el manifiesto remoto confirmado. Configuración privada, datos vivos e imágenes cargadas por usuarios se conservan aparte; nunca importar la base local sobre producción.

1. Actualizar referencias Git y consultar el manifiesto remoto. Un solo responsable publica a la vez. Una entrega interrumpida o una referencia cambiada exige reconciliación antes de continuar.
2. Integrar main en preprod ANTES de aprobar. Revisar cada conflicto y conservar las funcionalidades publicadas. Prohibidos ours/theirs global, reset y force-push para resolver la integración.
3. Construir desde un commit limpio y revisar todo el delta generado. `desplegar-sftp.py` bloquea una selección que deja otros cambios desplegables del candidato sin publicar. Separar las fuentes o revisar/aprobar un alcance completo; no publicar una parte y fusionar todo después.
4. Regresión automatizada y revisión visual pertinente sobre el paquete compilado. Distinguir pruebas reales de simulaciones. Aprobación humana del commit y alcance exactos; cambios funcionales posteriores requieren nueva revisión.
5. Antes de migrar: respaldo privado verificable, restauración ensayada en base aislada y precondiciones de migración comprobadas. Usar respaldo reciente al publicar si ingresaron datos. Nunca reiniciar estadísticas ni sustituir datos por los de preprod.
6. Despliegue incremental recuperable, dependencias antes que consumidores y migraciones identificadas antes del código que las necesita. No se promete atomicidad global entre archivos ni indisponibilidad cero.
7. Validar producción: páginas, recursos, cuentas, base de datos y conservación de estadísticas. No introducir datos ficticios ni enviar mensajes reales sin autorización específica. Informe privado: `schema_version: 1`, `commit` igual al manifiesto, `manifest_sha256` mediante `release_guard.manifest_digest`, y comprobaciones booleanas verdaderas, sustentadas por evidencia, en `checks`: `public_pages`, `accounts`, `statistics`, `assets`, `database`.
8. `python3 scripts/sincronizar-main-produccion.py --validation-report INFORME.json` consulta producción, exige el candidato publicado y el informe exacto, reconstruye todo y vuelve a consultar el manifiesto. Solo si pasa, `--apply` actualiza main local con `--ff-only`. No publica ni hace push automático. Después enviar las referencias autorizadas y comprobar main/preprod local y remoto. Guardar el recibo fuera de Git; no introducir un commit de código adicional solo para registrar la entrega.
9. Limpiar la contingencia de código solo tras validar; conservar respaldos de base según política. Rollback de código nunca restaura una base antigua sobre datos nuevos. Reconciliar main con la versión recuperada antes de otro despliegue.

Ante una petición de saltarse pasos, recordar brevemente la regla y el riesgo concreto; para urgencias usar un hotfix mínimo por el mismo circuito. Estos controles locales no reemplazan protección de ramas en GitHub ni impiden comandos manuales externos. No presentarlos como garantía absoluta.

El flujo vigente está en `PREPRODUCCION.md`: integrar en `preprod`, levantar la copia local con Podman, revisar y recibir aprobación explícita del propietario para ese commit, desplegar a producción desde `preprod`, validar y solo entonces integrar el mismo commit en `main`. Una aprobación anterior de otro commit no autoriza publicar esta versión. No desplegar desde ramas de workers.

## Regla principal: una autorización por tarea

Cuando el propietario pide una tarea concreta —por ejemplo, «prepara y despliega esta versión»— esa instrucción autoriza de una vez los pasos rutinarios necesarios dentro de ese alcance. No se deben pedir permisos separados para cada comando, lectura, prueba o archivo del mismo trabajo.

Si la solicitud es solo «revisa», «prepara» o «dime si está listo», el alcance termina en la revisión o preparación: no implica publicar en producción. Si pide «despliega», la autorización cubre el despliegue de la versión identificada y sus verificaciones normales. No cubre borrar datos, cambiar credenciales, alterar DNS ni ejecutar una migración destructiva.

Si aparece una decisión material que no estaba contemplada, se pausa únicamente esa parte y se continúa con las demás tareas independientes. La pregunta debe explicar qué acción necesita aprobación y por qué, sin volver a pedir permiso para pasos ya autorizados.

## Acciones rutinarias permitidas dentro del alcance

No pedir autorización adicional para:

- Leer código, instrucciones y documentación del repositorio; revisar rama, cambios locales, diferencias, historial y manifiestos.
- Ejecutar pruebas, análisis estático, construcción y validaciones locales; levantar un servidor temporal local y comprobar el sitio por HTTP en `localhost`.
- Crear archivos temporales de prueba fuera del hosting y eliminarlos al terminar cuando sean claramente temporales y propios de la tarea.
- Preparar el paquete y comparar localmente rutas, tamaños y hashes una sola vez por archivo.
- Consultar por SFTP el directorio de publicación, sus nombres, tamaños, fechas y manifiestos sin modificarlos.
- Consultar páginas públicas por HTTP/HTTPS después de un despliegue autorizado y comprobar enlaces, recursos y respuestas del servidor.
- Dentro de un despliegue pedido explícitamente, transferir incrementalmente solo archivos nuevos o modificados según `docs/DESPLIEGUE_SFTP.md`, conservar `private/config.php` y no borrar archivos remotos automáticamente.
- Aplicar migraciones aditivas y no destructivas que estén identificadas en la versión solicitada, después de verificar su precondición y estado; no ejecutar SQL arbitrario.
- Dejar un informe breve con lo probado, el resultado y cualquier limitación real.

Las consultas de producción deben ser de solo lectura salvo que la instrucción explícita de despliegue autorice la escritura concreta. No se deben mostrar claves, frases de acceso, contraseñas, hashes de contraseñas ni datos personales en salidas o informes.

## Cuándo sí detenerse y preguntar

Pedir una aprobación explícita antes de:

- Eliminar archivos activos, imágenes, configuración o registros de producción; hacer limpiezas masivas o purgar la papelera.
- Ejecutar migraciones destructivas, borrar o reiniciar tablas, cambiar datos en masa o realizar una operación cuyo resultado no pueda revertirse desde el código.
- Sobrescribir `private/config.php`, credenciales, claves, permisos de acceso o datos privados.
- Cambiar DNS, dominio, SSL, reglas de enrutamiento globales o cualquier ajuste que pueda dejar inaccesible el sitio.
- Cambiar qué destinos o negocios quedan públicos, abrir el registro a todo el público, publicar contenido no aprobado o enviar comunicaciones a terceros.
- Hacer una publicación cuando el usuario no haya pedido desplegar esa versión concreta.
- Ante un conflicto inesperado de archivos remotos, un manifiesto ausente o inconsistente, destino SFTP dudoso, credenciales de otro proyecto o resultado que indique que se tocaría otro sitio.

Una autorización general para desplegar no autoriza acciones destructivas. Si se detecta una acción de esta lista, informar el impacto concreto y proponer la alternativa menos riesgosa.

## Flujo estándar de una publicación autorizada

1. **Confirmar el alcance sin interrogar por cada paso.** Identificar rama/commit o cambios que el usuario señaló y comprobar que el destino sea exclusivamente Explorando Lugares.
2. **Validar localmente.** Revisar el estado del repositorio, preservar cambios ajenos a la tarea, correr las pruebas pertinentes y construir el paquete.
3. **Preparar el cambio incremental.** Comparar con el manifiesto local del último despliegue y mostrar internamente el resumen de altas, cambios y archivos que ya no aparecen. No hacer lecturas repetidas del hosting para recalcular hashes.
4. **Publicar solo el paquete autorizado.** Usar el script documentado y el directorio `public_html/explorandolugares.com`. Mantener intactos la configuración privada y los archivos remotos no administrados. No crear una copia integral, un release paralelo o un staging remoto.
5. **Validar por HTTP.** Comprobar las rutas y recursos afectados, páginas clave y una muestra de navegación. Una transferencia exitosa no equivale por sí sola a una aplicación funcional.
6. **Cerrar con evidencia.** Informar qué commit/versión quedó publicado, cuántos archivos cambiaron, pruebas locales y HTTP realizadas, errores o pendientes. Si algo falla, parar y explicar; no borrar ni restaurar datos automáticamente.

El usuario decide por separado cuándo hacer merge, crear etiquetas o ramas y desplegar, salvo que su solicitud actual incluya explícitamente esas acciones. Una tarea que pida «desplegar y etiquetar» autoriza ambas; no requiere confirmaciones intermedias.

## Rollback y almacenamiento

- El código se recupera desde Git y sus etiquetas estables, desplegando incrementalmente la versión elegida.
- No crear copias completas del sitio, carpetas de staging ni respaldos de hosting por defecto.
- No borrar automáticamente archivos obsoletos: reportarlos con sus rutas y esperar una instrucción de limpieza, ya que podrían estar referenciados por datos existentes.
- La base de datos no se revierte con Git. Antes de cambios destructivos o de alto riesgo, explicar el impacto y acordar una protección/restauración específica. No generar respaldos repetidos de la base por despliegues rutinarios.

## Permisos del entorno

La autorización del propietario define el alcance del trabajo, pero no sustituye los controles técnicos del sistema. Si una herramienta bloquea una operación y exige aprobación del sistema operativo o de la aplicación, solicitarla una sola vez para el grupo de acciones necesario, explicando el motivo. Nunca buscar una vía alterna para eludir ese control.

Si no se necesita una aprobación técnica, continuar el trabajo autorizado y no presentar comprobaciones locales, HTTP de solo lectura o listados SFTP de solo lectura como si requirieran una decisión adicional.
