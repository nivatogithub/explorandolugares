## 19/10/2026 · Contingencia de archivos temporal

El cierre de cada despliegue validado elimina las copias de archivos, verificaciones y temporales remotos propios; conserva solo un recibo pequeño. Exige informe funcional del commit exacto y coincidencia de los bytes publicados. Otra entrega se bloquea hasta finalizar la contingencia anterior. Las versiones históricas permanecen en Git, no como copias sucesivas de archivos. Pruebas: limpieza tras validación, conservación ante informe incompleto/cambios ajenos y bloqueo de acumulación. Respaldos de base de datos fuera de esta limpieza. Solo herramientas preparadas, sin desplegar código productivo.

## Candidato revisado · 19/10/2026

Comprobación general local aprobada después de los cambios. MariaDB: migración reanudable/estados iniciales, ciclo de cuentas y concurrencia de suscripciones aprobados. Plan incremental calculado contra el último manifiesto confirmado: 43 archivos, 998.562 bytes; cero archivos de lugares/imágenes modificados o retirados. Se usará de todos modos `--preserve-places`. El cambio incompleto de CTA de lugares se retiró del candidato y permanece pendiente en P02.

Producción: respaldo real y restauración aislada realizados; la migración 043 solo se consultó, no se aplicó. Código productivo sin publicar desde esta rama. Antes de publicar: aprobación del commit, comprobar manifiesto remoto, aplicar únicamente 043 con la herramienta preparada, publicar el delta, validar y recién entonces alinear main. La preparación y las pruebas no equivalen a autorización de publicación.

## 19/10/2026 · Preparación de despliegue con tráfico activo

- Recuperación incremental preparada: copia anterior únicamente de los archivos modificados, temporales privados y verificados, reemplazo por renombrado, manifiesto al final y registro local duradero. Recuperación ensayada con cortes en cada punto y detección de cambios ajenos; renombrado de reemplazo comprobado en el hosting usando exclusivamente archivos privados desechables. No se publicó código de la aplicación.
- Respaldo real de producción creado y descargado: 69 tablas, 155.423 bytes comprimidos, checksum y gzip verificados. Restauración local aislada aprobada con 69 tablas y ocho tablas estadísticas; base de prueba eliminada. Copias privadas en hosting y Mac; no se incluyen en Git. No hay una programación automática de respaldos activa todavía.
- Consulta de migración sin aplicación: dos cuentas activas existentes; tablas 043 aún ausentes en producción. Se conservan ambas. Migración 043 idempotente/reanudable probada en MariaDB con estados activo, pendiente y deshabilitado; cuentas y contadores intactos, estados iniciales conservados, límite de espera de metadatos de cinco segundos.
- `--preserve-places` protege páginas, renderer, datos de contenido e imágenes y conserva sus hashes anteriores en el manifiesto parcial. Se retiró de este candidato la edición incompleta de CTA del renderer de lugares; P02 sigue pendiente.
- Plan y herramientas reproducibles: `docs/DESPLIEGUE_SFTP.md`, scripts de respaldo, restauración exclusivamente local, consulta/aplicación de migración 043 y recuperación incremental. El esquema se añade antes del código dependiente; recuperar código no restaura la base.
- Prohibición explícita: nunca reiniciar ni sustituir estadísticas activas. No se promete pérdida cero en un desastre sin binlogs/servicio de recuperación continua verificado. Pendiente elegir/activar programación y copia externa cifrada; las copias actuales sí están realizadas y comprobadas.

## 19/10/2026 · Retirada del enlace anterior

Por decisión del usuario se elimina la compatibilidad de `#audiencia`: ya no abre visitantes ni redirige al nuevo fragmento. El enlace vigente es `/cobertura/colombia/#visitantes`. Los fragmentos desconocidos muestran la pestaña Cobertura. Solo preproducción.

## 19/10/2026 · Enlace público de visitantes

La pestaña usa `/cobertura/colombia/#visitantes` (también con filtro de departamento). Los enlaces antiguos `#audiencia` se normalizan a `#visitantes` conservando los parámetros. Enlaces administrativos e identificadores de pestaña actualizados; navegación y compatibilidad comprobadas en navegador local. Solo preproducción, sin despliegue a producción.

## 19/10/2026 · Cierre voluntario y suspensión separados (preproducción)

- Migración 043 añade historial privado de estados y enlaces de reactivación con hash; no altera ni elimina las cuentas existentes. Las cuentas previamente deshabilitadas sin historial conservan el bloqueo administrativo y no admiten reactivación automática.
- Cierre voluntario: registra evento `closed`. Volver a registrarse con el mismo correo o solicitar recuperación envía un enlace de reactivación cuando corresponda. Confirmación explícita y contraseña nueva; 24 horas, uso único, límite de reenvíos. Se recupera el mismo ID, conserva asociaciones y no renueva suscripciones.
- Suspensión: motivo obligatorio, administrador y fecha; invalida sesiones y enlaces pendientes. Solo el administrador puede restablecerla. No se puede suspender ni cerrar la última cuenta administradora activa. Se exige confirmación del titular por correo para reabrir un cierre voluntario.
- La prueba privada de sesión incluye la última transición de acceso: restablecer una suspensión no revive sesiones anteriores. No hay alertas automáticas de riesgo implementadas en esta entrega.
- Pruebas automatizadas de servicio, HTTP público con buzón local y transporte Brevo simulado; prueba MariaDB en base temporal aislada con `preprod/probar-ciclo-cuentas.php`. No se enviaron correos reales ni se tocó producción. Esperar aprobación antes de avanzar al siguiente arreglo.

## 19/10/2026 · Sesiones y cierre de cuenta (solo preproducción)

- Recuperar la contraseña cambia la prueba privada de credencial: las sesiones anteriores de viajero, negocio y administrador dejan de autorizar la siguiente solicitud. Las sesiones antiguas sin prueba también deben volver a iniciar sesión.
- Mi cuenta → Personal → Cerrar mi cuenta: contraseña actual, confirmación y protección CSRF. Desactivación lógica, consumo de enlaces de verificación/recuperación y cierre de sesión. Se conservan cuenta, asociaciones, historial y pagos; no se elimina ni despublica automáticamente el negocio. La información retenida sigue siendo privada y accesible para gestión histórica autorizada, no figura como cuenta activa.
- El último administrador activo debe asignar otro antes de cerrar su cuenta. Reabrir una cuenta administrativamente no restablece sus sesiones anteriores.
- Pruebas: `php backend/tests/account-session-security.php`. No se enviaron correos ni se modificaron cuentas reales. Pendiente revisión visual local y validación integral de flujos públicos; sin autorización de despliegue.

# Revisión previa a promoción · 19/10/2026

Referencia recibida: `entregables/revision-promocion-2026-10-19/ANTES_DE_PROMOCIONAR.md` del espejo del proyecto (fuera de este checkout). Objetivo: promoción a viajeros y registro de comercios. Base contrastada: `8ff9ddecaa2c9b0e8b423ef1e814d9a69c4672aa`. Esta incorporación es planificación; no autoriza otro despliegue, envíos, cambios de tarifas ni acceso adicional a secretos.

| Punto | Estado contrastado y criterio de cierre |
|---|---|
| P01 · Alta prioridad | Corregido localmente; pendiente de aprobación/publicación. Se reprodujo el defecto en producción sin enviar desde el agente. El formulario usa selección departamento → municipio en las 1.122 unidades del catálogo nacional existente, independiente de guías publicadas. Valida códigos/departamento en servidor, conserva compatibilidad de clientes anteriores y corrige indicativos fijos. Pruebas de Bogotá, Cartagena, Santa Marta, Medellín, Jericó, Mocoa y Zipaquirá; evidencia visual local Mocoa/Putumayo. No confundir interés con alta real de negocio. |
| P02 · Alta prioridad | Pendiente de inventario de CTA. Separar «Solicitar información» de «Registrar o reclamar negocio» en guías, pie, proceso y suscripciones. |
| P03 · Alta prioridad | Parcial. La versión publicada ya retiró la solicitud y emisión de cookies analíticas, procesa país localmente sin guardar IP en estadísticas y conserva agregados sin caducidad. Falta reconciliar responsable/conservación y textos de recepción del formulario con su configuración real; revisión del propietario. No concluir incumplimiento legal de esta revisión. |
| P04 · Alta prioridad | Pendiente de nueva evidencia integral del recorrido comercial en la versión actual. Las pruebas anteriores de correo y las suites nominales no sustituyen cuenta → verificación → alta/reclamación → documentos → revisión. Primero datos sintéticos aislados; envíos reales solo con autorización concreta. |
| P05 · Alta prioridad técnica | Implementado y probado en preproducción: revocación de dos sesiones por recuperación, cierre voluntario, suspensión administrativa y reactivación por correo con enlaces únicos. Pruebas HTTP con correo simulado y MariaDB; pendiente aprobación/publicación. |
| P06 · Antes de anunciar contratación | Parcial en desarrollo local. Se añadió transacción propia o savepoint cuando existe transacción del llamador, y bloqueo de fila de negocio en MySQL. Pruebas SQLite con fallo de INSERT preservan la solicitud anterior y la transacción del llamador. Validado también en MariaDB/InnoDB local, con base temporal independiente: fallo de INSERT, savepoint del llamador y bloqueo entre dos conexiones con reintento; una sola solicitud pendiente al finalizar. Script reproducible: `preprod/probar-suscripcion-atomica.php`; evidencia privada `p06-mariadb.log`. Pendiente aprobación/publicación. No provocar este fallo en producción. |
| P07 · Antes de anunciar contratación | Pendiente de evidencia operativa/decisión del propietario: medios reales de pago, total/ciclo, soporte y publicación. Conservar tarifas/beneficios aprobados. Simulación o HTTP 200 no equivalen a pago validado. |
| P08 · Preparación de campaña | Pendiente. Las fuentes revisadas de inicio y suscripciones no incluyen `og:title` ni `twitter:card`. Añadir metadatos y verificar la vista previa social en las páginas de campaña. |
| P09 · Preparación de campaña | Pendiente editorial. Ajustar cada mensaje a la guía disponible y a sus beneficios reales; no prometer contacto en Gratis ni reservas. Inicio centrado en Jericó frente a cinco guías: revisar sin sustituir contenido aprobado silenciosamente. |
| P10 · Antes del siguiente despliegue | Preparado y probado: recuperación incremental con registro duradero, interrupciones en sustitución/manifiesto y protección ante cambios ajenos. Reemplazo SFTP comprobado con archivos privados desechables del hosting. Sin copia integral del sitio y sin restauración automática de datos. Pendiente usar la herramienta en la publicación aprobada. |
| P11 · Evidencia de publicación | Resuelto para `8ff9dde`: despliegue del 18/10, manifiesto productivo de 703 archivos sin diferencias; activación agregada, conservación de cuentas/negocios/configuración y comprobaciones públicas. Main/preprod locales/remotas iguales tras publicar. Evidencias operativas privadas: `.local/production/dashboard-activate.json`, `dashboard-validation.json`, `despliegue-dashboard.log`, `validation.json`. Repetir sobre cualquier candidato posterior; no inferir publicación de otras ramas. |

Limpieza autorizada del 19/10: eliminada únicamente la solicitud de prueba productiva ID 4 («pepe / pepito restaurante»), identificada por nombre/negocio/fecha exactos; cuentas, negocios, suscripciones y otras solicitudes conservados. Evidencia privada: `.local/production/pepito-deleted.json`.

Orden de ejecución propuesto: P01/P02 y P03; P05/P06 con pruebas aisladas; P04 integral; P07 si se anuncia pago; P08/P09 para campaña. P10 se cierra antes del siguiente despliegue y P11 se renueva después. Las suites locales aprobadas no acreditan ausencia de todos estos defectos.

HTTPS (19/10): comprobación pública actual de inicio y /en/ con certificado validado, HTTP/www → HTTPS sin www mediante 301, canónicas coherentes, robots accesible y 52 URLs HTTPS en sitemap. Search Console «HTTPS no evaluado» sigue sin causa interna demostrada; solicitar prueba publicada/rastreo, sin cambiar SSL/DNS por conjetura.

---

# Pendientes actualizados · 18/10/2026

- **ENCOLADO:** cierre del comportamiento de creación y reclamación de negocios.
- **ENCOLADO:** revisión visual móvil completa.
- **POR REVISAR:** `feature/advance-admin`: seguimiento comercial con próxima acción/fecha, plantillas reutilizables con versiones y evaluación de sus tres cambios sin commit. No fusionar el esquema antiguo ni cambiar Brevo por Resend.
- **POR REVISAR:** investigación nacional y scripts conservados en `archivo-revision/2026-10-18/`. El manifiesto registra procedencia y etiquetas de recuperación; no son contenido aprobado ni scripts listos para ejecutar.

---

# Estado de la entrega

16/10/2026. Implementado como documentación y herramientas locales de planificación; no es implementación del portal ni automatización conectada.

- Creadas 33 unidades (32 departamentos + Bogotá D.C.) y 78 lugares candidatos, cada uno con manifiesto y proceso. Grupos P1/P2/P3 sin cupos; restaurados los 21 municipios P1 históricos de Antioquia. Sonsón conserva P2. Cauca Viejo P2 con padre Jericó.
- Contratos JSON, puntuación, invariantes y cobertura calculable incluidos. Cobertura inicial del nuevo plan: 0%; refleja ausencia de auditoría de gates, no ausencia de contenido histórico.
- Siete pruebas aprobadas: semilla/MVP, gate sin evidencia, publicación prematura, ciclo territorial, puntuación alterada, escape de ruta y cambio del hash de aprobación. Validación estructural aprobada para los 78 lugares. No valida autenticidad, licencias ni contenido remoto por sí sola.
- Revisado remoto GitHub de la rama de trabajo; coincide con base `7ef4996` al consultar. No se revisaron servicios privados ni producción.
- Cambios concurrentes detectados en tres archivos de `backend/`: no incluidos ni modificados por esta tarea.

## Siguiente trabajo, por orden

1. Asignar editor/revisor; directorio gratuito y ausencia de membresía son instrucciones vigentes del propietario. Resolver responsable y canal de privacidad reales antes de habilitar captación.
2. Editor: auditar Jericó sobre versión actual, fuentes y fotos reales; registrar cada gate y preparar su rama/PR propio. No reutilizar como hechos vigentes los estados históricos.
3. Técnico: demostrar control de versiones/aprobaciones, soporte territorial padre/hijo y adaptador al paquete v2; probar en entorno aislado. El procedimiento fotográfico debe materializarse con herramienta y versión comprobadas cuando se procesen fotos.
4. Editor/técnico: completar progresivamente todos los destinos del grupo P1 en ramas propias; publicación siempre separada y autorizada. Este plan no crea los PR de lugares aún no desarrollados.
5. Investigación: reconciliar inventario con catálogo oficial DANE versionado; candidatos editoriales no equivalen a censo administrativo completo.
6. Responsable jurídico/técnico: confirmar condiciones de DIAN/RUES/RNT, derechos de datos, proveedores, retención y documentos; no depender de APIs no habilitadas.
7. Producto: cuentas de viajeros, incentivos funcionales, onboarding proporcional, medición y recomendaciones conforme a roadmap; monetización opcional e Instagram después.

No hay imágenes nuevas, negocios descubiertos/contactados, APIs habilitadas ni despliegue en esta entrega. Los workers y el procedimiento están definidos; su ejecución editorial queda para cada lugar. El portal y sus formularios no cambian por añadir estos archivos.

Corrección del propietario: Sonsón no pertenecía a las prioridades turísticas anteriores. Su paquete preparado no justifica priorizarlo. No sustituir la priorización turística por disponibilidad de artefactos; contrastar la lista previa antes de cerrar la clasificación nacional. Las demás prioridades siguen siendo propuestas, no decisiones históricas del propietario.

Corrección de alcance: prioridad es pertenencia a uno de tres grupos, no un cupo de tres departamentos ni de cuatro/cinco destinos. La puntuación de preparación no puede sustituir la prioridad turística. Clasificación departamental y destinos fuera de Antioquia aún provisionales; validar antes de presentarlos como decisiones del propietario.

Plan separado en `feature/ampliacion-cobertura-colombia`, basado en `origin/main`; ver `ARRANQUE.md`. El diseño del proceso está terminado; las prioridades nacionales provisionales y la ejecución por lugar están identificadas como trabajo pendiente. No se afirma que la cobertura de destinos esté completada.
