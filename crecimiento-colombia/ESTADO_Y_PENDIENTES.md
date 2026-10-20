## 19/10/2026 · Decisiones del propietario: alcance comercial y siguiente revisión

- Pagos: el propietario cargará los medios reales en administración de producción. Sigue pendiente la configuración; no se declara operativa la recepción de pagos por una prueba de cotización.
- Premium: verificar exclusivamente el contenido, presentación, capacidad adicional y URL personalizada prometidos frente a Página Comercial y su diferencia de precio. Reservas no forman parte de la oferta aprobada; no introducirlas como requisito ni beneficio. La validación de extremo a extremo de Premium sigue abierta.
- Precios: nota de tarifas de lanzamiento aprobada y añadida al generador de suscripciones ES/EN/PT. Conservar el precio del período pagado e informar cambios antes de renovación. Evaluar primero un lugar piloto: negocios contactados, altas pagas por modalidad, costos de adquisición/atención, uso de contenido/canales y renovaciones/bajas; no atribuir ventas al portal sin evidencia ni cambiar tarifas automáticamente por un resultado aislado. No se alteran importes.
- Agenda: primera búsqueda privada guardada en `revisiones/agenda-publica-2026-10-19.json`. Incluye candidatos de fuentes primarias, pistas pendientes y eventos pasados descartados. La búsqueda agrupada no completa cada agenda municipal. No se publica ni importa ningún evento ni lugar en borrador.
- Información práctica de Jericó y títulos descriptivos: diferidos explícitamente a otra versión. Para títulos, diseñar después una regla consistente para publicados, borradores y nuevos; no cambiar solo Jericó ahora.
- Instagram: preparar llamada al viajero en la biografía y enlace del sitio en el campo Enlaces. No depende de publicar el primer post. No se modifica la cuenta ni se considera verificada su biografía actual.

Revisión móvil de muestra en preprod, ancho CSS medido de 390 px: portada, Privacidad, suscripciones y formulario de cuenta sin desbordamiento horizontal de documento; menú abre/cierra. Las tarjetas conservan nombres abreviados y lugar asociado. Pendientes observados: comparación de suscripciones con desplazamiento horizontal (tarea existente); registro mostrado en inglés al abrir desde el portal en español (nuevo caso de coherencia de idioma, requiere reproducir con preferencia limpia antes de corregir). No se envió formulario ni se validaron todos los estados del modal o todas las páginas del portal. Evidencias locales `movil-portada.png` y `movil-registro.png` dentro de `.local/preprod/auditoria2/`.

## 19/10/2026 · Segunda auditoría: puntos 1–3 implementados en preprod

Enlaces comerciales diferenciados: registrar/reclamar abre `/negocios/registro/?tipo=business`; solicitar información conserva su formulario. Corregidos encabezado/pie de guías y fichas dinámicas, más páginas generadas ES/EN/PT. Privacidad retira la sección analítica opcional antigua y los avisos de configuración pendiente; conserva una explicación agregada sin cookies analíticas y muestra responsable/contacto/conservación desde los valores públicos configurados, sin inventar plazos. No modifica recolección ni datos históricos.

Portada: seis atracciones de los cinco lugares aprobados, siempre además publicados en base de datos; selección editorial, sin puntuaciones ficticias, imágenes vigentes y enlace a la sección de la guía. Tres columnas/dos filas en escritorio y adaptación a pantallas estrechas. Frase aprobada «Colombia, un país con muchos lugares para volver». Créditos breves de ilustraciones propias («Explorando Lugares»), conservando procedencia en datos y atribución/licencia/enlaces cuando son recursos externos.

Validación: regresión general aprobada, prueba de transformación e idempotencia, consultas HTTP de los tres idiomas, seis imágenes/enlaces por idioma y ausencia de enlaces de registro hacia información en las rutas revisadas. Revisión visual de tarjetas; sin despliegue a producción. Evidencia local: `.local/preprod/auditoria2/validacion.json` y `portada-corregida.png`. Pendientes de revisión del propietario y publicación. Pagos/Premium, nueva agenda, texto de «Por qué participar», título/información práctica e Instagram no se implementan en esta entrega.

## 19/10/2026 · Segunda auditoría: consolidación posterior a publicación

Registro de pendientes, no autorización de implementación o publicación. Contrastado con la cola de este documento y los informes `entregables/revision-promocion-2026-10-19/ANTES_DE_PROMOCIONAR.md` y `experiencia-publica/informe.md` del proyecto. Las observaciones históricas no se presentan como una nueva inspección de producción.

**Entrega anterior completada:** paquete acotado de 38 archivos publicado desde `8c8bb66`; migraciones 043/044 aplicadas; integración selectiva de auditoría en main `96a6eba`. Los estados «solo preproducción / pendiente de publicar» de las entradas históricas inferiores quedan superados para los cambios de cuentas, catálogo nacional, integridad/prorrateo de suscripciones y recuperación de despliegue incluidos en ese paquete. No implican cierre integral de P02/P03/P04/P07/P09. P08 se mantiene como antecedente: el propietario indicó que ya fue validado; no reabrirlo automáticamente por el informe antiguo.

**Correcciones y verificaciones que siguen abiertas:**

- P02: inventario de enlaces y etiquetas, distinguiendo solicitar información de registrar/reclamar negocio; incluye el texto comercial de «Por qué participar» de la cola posterior, punto 4.
- P03: privacidad coherente con configuración real y sus dos secciones de medición; detalle ampliado en su única fila existente.
- P04: evidencia del recorrido comercial completo de la versión vigente; las 21 rutas HTTP del despliegue no equivalen a probar todos sus procesos. Las pruebas destructivas, pagos o envíos reales no se ejecutan por este registro.
- P07: configuración real de pagos y comprobación operativa antes de anunciar contratación; no considerar un pago probado por una cotización de consulta.
- P09: comunicación y selección de lugares publicados en portada; conservar beneficios monetizados. No añadir contactos a Gratis.
- Cola posterior, puntos 2 y 3: diferenciación práctica del viajero y contenido confirmado de agenda de Jericó.
- Revisión visual móvil ya encolada: incluir comparación de suscripciones; no crear otra tarea para el mismo hallazgo.

**Tres incorporaciones nuevas, sin duplicar los puntos anteriores:**

1. **Información práctica de Jericó.** Ampliar la sección existente con decisiones útiles para el viajero: llegada/movilidad, tiempos orientativos, horarios y condiciones de acceso cuando sean confirmables. Identificar fuentes y fecha; no inventar precios, frecuencias o disponibilidad ni prometer contenido comercial de pago gratuitamente. Cierre: contenido útil y verificable dentro de la guía, revisado en móvil y en los idiomas publicados.
2. **Título descriptivo de Jericó.** Evaluar una alternativa a «Jericó, Antioquia | Explorando Lugares», reportado por el propietario. Es una mejora editorial/SEO propuesta, no un error técnico confirmado. Elegir el texto según el contenido real; revisar coherencia de título, encabezado y metadatos sin cambiar la URL ni duplicar páginas. Revisar/aprobar antes de publicar.
3. **Instagram: llamada al viajero en la biografía.** Preparar una invitación clara a explorar lugares y visitar el portal, equilibrada con la captación de comercios. Pendiente reportado en la revisión de la mañana; no se volvió a comprobar esta tarde ni se inspeccionó la cuenta en esta tarea. Verificar la biografía vigente antes de proponer sustitución. No modificar ni publicar en Instagram sin autorización explícita.

Correspondencia de la lista recibida: 7 → cola posterior 4; 8 → P03; 9 → P09; 10 → nueva incorporación 1; 11 → cola posterior 3; 12 → nueva incorporación 2; 13 → nueva incorporación 3. Cada tema mantiene una sola tarea.

## 19/10/2026 · Próximos destinos, diferidos por el propietario

Después de terminar la aprobación de los borradores actuales y validar/corregir los publicados con las nuevas reglas, conservar este orden: **Guatapé → Cali → Barranquilla → Rincón del Mar**. No iniciar investigación, imágenes, ramas ni workers ahora; sin arranque automático. No modificar puntuaciones nacionales por este registro. Cola estructurada: `revisiones/cola-futura-destinos.json`.

## 19/10/2026 · Preparación acotada de publicación de primera auditoría

Solicitud actual: preparar, no desplegar. Herramienta reproducible `scripts/preparar-publicacion-auditoria.py`: fuentes del commit Git, lista exacta de archivos propios, contraste opcional del manifiesto y bytes remotos, ZIP de solo el delta y reporte local de exclusiones. No incluye lugares, imágenes, agenda ni estilos/menús de otros workers; no elimina archivos ni traslada datos locales. Las diferencias ajenas se conservan fuera del paquete aunque compartan rama.

La herramienta de migración admite ahora `--include-subscription-changes` para consultar/aplicar únicamente 043 y 044. Sin `--apply`, consulta estados sin SQL de escritura. Verificación mediante helper temporal autenticado y retirado al terminar. Ambas migraciones limitan la espera de metadatos a cinco segundos. El respaldo reciente/restauración aislada se exige inmediatamente antes de aplicar, no se da por realizado al preparar el paquete. Validación funcional productiva y merge a main posteriores a publicación autorizada. La preparación no cierra automáticamente P02/P03/P04/P07 ni las tareas editoriales/comerciales de la auditoría posterior.

## 19/10/2026 · Upgrades y bajadas de suscripción (preprod)

Regla aprobada por el propietario: saldo no consumido de la vigente contra el valor de la superior por el mismo tiempo restante, pago de la diferencia antes de activar, misma frecuencia y vencimiento; las bajadas solo al vencimiento. Se usa el precio completo contratado con su descuento trimestral/semestral/anual y duración real en segundos UTC, redondeando cada importe a centavos. Segundo upgrade: saldo sobre el derecho adquirido, no sobre el recibo de diferencia anterior. La cotización pública dura 30 minutos, queda vinculada a sesión/negocio/selección y se revalida bajo bloqueo del negocio al enviar el soporte. Pago insuficiente y reemplazo de pagos ya confirmados bloqueados. No se cambia el nivel activo al cotizar ni enviar soporte.

Nueva migración **044-subscription-changes**, independiente de **043-account-lifecycle**: una tabla de trazabilidad de suscripción origen, valores completos, saldo aplicado, diferencia, fecha cotizada y vencimiento. No modifica suscripciones anteriores, cuentas ni estadísticas. La 043 sigue siendo exclusivamente historial de acceso y enlaces de reactivación de cuentas. No usar la herramienta de aplicación exclusiva de 043 para suponer aplicada 044.

Las bajadas se guardan como solicitud para el vencimiento y requieren el procedimiento de publicación existente; no hay un cambio automático programado. Si vence la suscripción origen antes de activar un upgrade pagado, se bloquea la publicación y se exige conciliar/recotizar, conservando historial; no se regala un período nuevo. No reemplazar una solicitud con pago ya aprobado pendiente de publicación. Cambiar la frecuencia se reserva para la próxima renovación. Los complementos se prorratean junto a la base; retirar/cambiar una experiencia vigente se difiere al vencimiento.

Pruebas: SQLite de cálculo, descuentos, año bisiesto, doble upgrade, publicación, bajadas y pagos insuficientes; HTTP público de cotización y privacidad de sesión; MariaDB aislada de migración idempotente y rollback de saldo/solicitud ante fallo. Solo preproducción, sin despliegue productivo ni cambios a estadísticas.

## 19/10/2026 · Conservación de respaldos implementada

Política 48 horarios / 30 diarios / 12 mensuales UTC, deduplicada por copia física, integrada en el exportador para hosting y Mac. Hash, ruta de origen, tamaño y copia reciente con integridad gzip/CRC como condición de borrado; archivos desconocidos/symlinks protegidos. Aplicada al inventario actual: una copia conservada en hosting y una en Mac, cero eliminaciones y cero archivos ignorados. No accede a tablas ni modifica estadísticas. Pruebas con más de mil copias sintéticas, idempotencia, copias viejas/corruptas y archivos ajenos aprobadas. Falta programar generación periódica y configurar copia externa cifrada.

## P0 · Worker de hotfix reservado

Rama `hotfix/reporte-produccion-20261019`, basada en main 8ff9dde, checkout independiente `trabajo/hotfix-produccion`. Chat «P0 · Hotfix de producción» creado para recibir el reporte urgente del propietario; sin cambios funcionales anticipados ni autorización de publicación. Su atención tiene prioridad máxima. Mantenerlo separado de los cambios acumulados de preprod.

## Cola posterior al despliegue · solicitud del propietario · 19/10/2026

Estas mejoras se retoman **después del despliegue actual**. No amplían el alcance de la entrega ni autorizan publicar contenido, eventos o cambios comerciales ahora.

1. **Captación de negocios / coherencia de catálogos.** El propietario reporta que el formulario al que llega un negocio desde Bogotá aún exige un municipio de Antioquia y no permite Bogotá; nueva comprobación reportada a las 10:05 a. m. «Reclamar negocio» ofrece los cinco lugares publicados. La corrección nacional del formulario ya está implementada y probada en preproducción, pendiente de desplegar: catálogo de 1.122 unidades, independiente de guías publicadas. Después del despliegue, verificar de nuevo el acceso desde Bogotá y la coherencia entre flujos. No limitar la captación a cinco lugares ni confundir el catálogo geográfico nacional con los lugares publicados elegibles para asociar un negocio; revisar esa restricción como decisión separada. No declarar resuelto en producción antes de comprobarlo.
2. **Diferenciación del portal.** Registrar como hipótesis estratégica del propietario que guía, directorio e itinerarios ya existen en competidores. Priorizar decisiones prácticas del viajero y actualización del contenido, conservando los beneficios comerciales pagos. Contrastar competidores y necesidades antes de convertirlo en implementación; no afirmar una investigación competitiva nueva realizada en esta tarea.
3. **Captación de viajeros / agenda de Jericó.** Revisar el texto preparado sobre «Las notas de Tita», reportado como anunciado por Museo MAJA para el sábado 24 de octubre de 2026. Fuente indicada: https://museomaja.com/ . El propietario reporta que la agenda de Jericó estaba vacía. El texto preparado no está adjunto en este mensaje: localizarlo en la auditoría o pedirlo si no aparece. Confirmar anuncio, fecha, hora, lugar, condiciones y vigencia antes de publicar; si el evento ya pasó al retomar, no presentarlo como próximo. No se verificó ni publicó el evento al guardar este pendiente. El alcance incluye incorporar contenido confirmado a la agenda de Jericó, no solo ese evento; evitar duplicar esta tarea como una nueva agenda. Si no hay anuncios vigentes confirmables, mantener el estado vacío honesto.
4. **Conversión / «Por qué participar».** Revisar el mensaje que todavía describe una propuesta futura pese a que hay suscripciones disponibles. El propietario indica que existe un reemplazo listo para revisar: localizar el texto y contrastarlo con las tarifas, funciones y contratación reales. Conservar los beneficios pagos aprobados. Enlazar con P02 (consulta frente a registro) y revisión comercial para evitar tareas duplicadas. Pendiente de revisar y aprobar; no se cambia la página en esta entrega.

**También pendiente posterior:** automatización de respaldos completos horarios (48), diarios (30) y mensuales (12), verificación, aviso de fallos, restauración aislada periódica y copia externa cifrada. Es una propuesta aún no programada. Comprobar tareas del hosting y elegir destino externo antes de activarla; conservar las estadísticas productivas intactas.

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
| P03 · Alta prioridad | Parcial. La versión publicada ya retiró la solicitud y emisión de cookies analíticas, procesa país localmente sin guardar IP en estadísticas y conserva agregados sin caducidad. Falta reconciliar responsable/conservación y textos de recepción del formulario con su configuración real; revisión del propietario. Incluir la contradicción entre las dos secciones públicas de medición: conteo agregado sin cookies frente a medición opcional descrita con cookies. Unificar la explicación conforme al funcionamiento efectivo, en los idiomas disponibles; no reactivar rastreo ni alterar estadísticas para adaptar el software al texto. No concluir incumplimiento legal de esta revisión. |
| P04 · Alta prioridad | Pendiente de nueva evidencia integral del recorrido comercial en la versión actual. Las pruebas anteriores de correo y las suites nominales no sustituyen cuenta → verificación → alta/reclamación → documentos → revisión. Primero datos sintéticos aislados; envíos reales solo con autorización concreta. |
| P05 · Alta prioridad técnica | Implementado y probado en preproducción: revocación de dos sesiones por recuperación, cierre voluntario, suspensión administrativa y reactivación por correo con enlaces únicos. Pruebas HTTP con correo simulado y MariaDB; pendiente aprobación/publicación. |
| P06 · Antes de anunciar contratación | Parcial en desarrollo local. Se añadió transacción propia o savepoint cuando existe transacción del llamador, y bloqueo de fila de negocio en MySQL. Pruebas SQLite con fallo de INSERT preservan la solicitud anterior y la transacción del llamador. Validado también en MariaDB/InnoDB local, con base temporal independiente: fallo de INSERT, savepoint del llamador y bloqueo entre dos conexiones con reintento; una sola solicitud pendiente al finalizar. Script reproducible: `preprod/probar-suscripcion-atomica.php`; evidencia privada `p06-mariadb.log`. Pendiente aprobación/publicación. No provocar este fallo en producción. |
| P07 · Antes de anunciar contratación | Pendiente de evidencia operativa/decisión del propietario: medios reales de pago, total/ciclo, soporte y publicación. Conservar tarifas/beneficios aprobados. Simulación o HTTP 200 no equivalen a pago validado. |
| P08 · Preparación de campaña | Pendiente. Las fuentes revisadas de inicio y suscripciones no incluyen `og:title` ni `twitter:card`. Añadir metadatos y verificar la vista previa social en las páginas de campaña. |
| P09 · Preparación de campaña | Pendiente editorial. Ajustar cada mensaje a la guía disponible y a sus beneficios reales; no prometer contacto en Gratis ni reservas. Inicio centrado en Jericó frente a varias guías: mostrar una selección más amplia de lugares ya publicados en portada, sin publicar borradores ni sustituir contenido aprobado silenciosamente. |
| P10 · Antes del siguiente despliegue | Preparado y probado: recuperación incremental con registro duradero, interrupciones en sustitución/manifiesto y protección ante cambios ajenos. Reemplazo SFTP comprobado con archivos privados desechables del hosting. Sin copia integral del sitio y sin restauración automática de datos. Pendiente usar la herramienta en la publicación aprobada. |
| P11 · Evidencia de publicación | Resuelto para `8ff9dde`: despliegue del 18/10, manifiesto productivo de 703 archivos sin diferencias; activación agregada, conservación de cuentas/negocios/configuración y comprobaciones públicas. Main/preprod locales/remotas iguales tras publicar. Evidencias operativas privadas: `.local/production/dashboard-activate.json`, `dashboard-validation.json`, `despliegue-dashboard.log`, `validation.json`. Repetir sobre cualquier candidato posterior; no inferir publicación de otras ramas. |

Limpieza autorizada del 19/10: eliminada únicamente la solicitud de prueba productiva ID 4 («pepe / pepito restaurante»), identificada por nombre/negocio/fecha exactos; cuentas, negocios, suscripciones y otras solicitudes conservados. Evidencia privada: `.local/production/pepito-deleted.json`.

Orden de ejecución propuesto: P01/P02 y P03; P05/P06 con pruebas aisladas; P04 integral; P07 si se anuncia pago; P08/P09 para campaña. P10 se cierra antes del siguiente despliegue y P11 se renueva después. Las suites locales aprobadas no acreditan ausencia de todos estos defectos.

HTTPS (19/10): comprobación pública actual de inicio y /en/ con certificado validado, HTTP/www → HTTPS sin www mediante 301, canónicas coherentes, robots accesible y 52 URLs HTTPS en sitemap. Search Console «HTTPS no evaluado» sigue sin causa interna demostrada; solicitar prueba publicada/rastreo, sin cambiar SSL/DNS por conjetura.

---

# Pendientes actualizados · 18/10/2026

- **ENCOLADO:** cierre del comportamiento de creación y reclamación de negocios.
- **ENCOLADO:** revisión visual móvil completa. Incluir la comparación de suscripciones a 360 px: desplazamiento horizontal, pérdida de encabezados al bajar y conservación del contexto de plan/precio. Hallazgo del informe de experiencia pública del 19/10; no repetir como tarea separada.
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
