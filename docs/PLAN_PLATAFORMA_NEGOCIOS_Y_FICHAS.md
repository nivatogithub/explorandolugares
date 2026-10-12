# Plan de plataforma para negocios locales y fichas públicas

## Propósito

Construir la segunda etapa de Explorando Lugares: los turistas continúan explorando el portal público sin crear una cuenta, mientras que un negocio local puede completar un proceso de incorporación, ser validado, escoger un plan, registrar un pago manual, preparar su ficha y solicitar publicación.

El portal público conserva tres niveles: sitio principal, destino y ficha del negocio. La aplicación no ofrecerá reservas, pagos con tarjeta, publicación automática, reseñas ni cuentas de turistas dentro de este alcance.

## Principios de arquitectura

- La experiencia pública será HTML, CSS y JavaScript; seguirá siendo portable entre hostings.
- PHP implementará inicialmente el panel, autenticación y API JSON porque ya funciona en el hosting actual. Las páginas públicas dependerán del contrato JSON, no de PHP; una migración futura podrá reimplementar esas rutas en Python, Node u otra plataforma.
- Ningún navegador se conecta directamente a MySQL. La API valida permisos y entrega únicamente datos públicos aprobados.
- Toda ficha tendrá un identificador interno, un `public_id` opaco e inmutable y un `slug` legible para URL.
- Los documentos, comprobantes y borradores no se publican ni se almacenan bajo `public_html`.
- Una persona administradora revisa onboarding, pagos, contenidos y publicación. Ningún paso sensible será automático en esta etapa.

## Estados del flujo

### Onboarding

`borrador` → `enviado` → `en_revision` → `requiere_informacion` → `aprobado` o `rechazado`.

La persona solicitante puede editar el borrador y responder solicitudes de información. Solo un onboarding aprobado permite escoger o activar un plan.

### Pago y plan

`sin_plan` → `plan_seleccionado` → `pago_reportado` → `pago_en_revision` → `activo` o `pago_rechazado`.

En la primera versión el negocio selecciona un método de pago manual, registra referencia y puede adjuntar comprobante. El equipo administrativo confirma o rechaza el pago. No se integra tarjeta, PSE ni validación bancaria automática.

### Ficha

`sin_ficha` → `borrador` → `en_revision` → `requiere_ajustes` → `pendiente_aprobacion_negocio` → `aprobada_para_publicar` → `publicada`.

Cuando se publica, la ficha queda disponible en la API pública. Si se retira o vence el plan, pasa a `suspendida` sin eliminar el historial.

## Modelo de datos por etapas

### Núcleo de identidad y acceso

- `businesses`: negocio, `public_id`, slug, municipio, categoría, estado y titularidad.
- `business_users`: personas autorizadas, correo, contraseña con hash, rol y estado de acceso.
- `business_user_businesses`: relación entre personas y negocios para permitir más de un representante en el futuro.
- `sessions` o sesiones seguras del servidor; nunca contraseñas en texto plano.

### Onboarding y validación

- `onboarding_cases`: estado, versión del formulario, observaciones y fechas.
- `onboarding_documents`: tipo de documento, archivo privado, fecha de carga, estado de revisión y nota administrativa.
- `review_events`: bitácora inmutable de solicitudes, aprobaciones y cambios de estado.

El checklist de documentos se define antes de abrir el onboarding. Debe validarse con asesoría legal y tributaria; el sistema no debe afirmar que una revisión equivale a certificación oficial.

### Planes y pagos

- `plans`: código, nombre, precio, moneda, visibilidad, límites y capacidades.
- `plan_features`: capacidades por plan, por ejemplo logo, cantidad de fotos, enlaces, descripción ampliada o ficha propia.
- `subscriptions`: plan elegido, estado, vigencia, precio aplicado y relación con negocio.
- `payment_instructions`: QR, llave, transferencia o cuenta; configuración administrable, sin datos sensibles en código.
- `payment_reports`: método elegido, referencia, monto declarado, comprobante privado, estado y revisión manual.

Antes de configurar valores reales se documentará una tabla comercial aprobada. Los nombres provisionales serán `gratuito`, `basico` y `ampliado`; no se publicará un plan ni un valor sin decisión explícita.

### Contenido y publicación

- `listings`: ficha, `public_id`, slug, estado, plan aplicado, textos y destino.
- `listing_media`: logo, imágenes, texto alternativo, orden, autorización y estado de revisión.
- `listing_services`: servicios o atributos permitidos por categoría.
- `listing_versions`: borradores y versión publicada para comparar cambios y permitir reversión.
- `slug_history`: redirecciones cuando cambie la URL legible.

## Rutas y API

### Públicas

- `GET /api/publicaciones.php?destino=jerico`: tarjetas publicadas de un destino.
- `GET /api/publicaciones.php?slug=casa-andina-jerico`: ficha completa publicada.
- `GET /api/publicaciones.php?public_id=...`: uso interno de interfaz, sin exponer contenido no publicado.

La ruta visible permanece limpia: `/destinos/jerico/lugares/casa-andina-jerico`. Una plantilla HTML lee el slug y consulta la API. La respuesta entrega tarjeta, ficha, fotos, contactos autorizados y fecha de actualización en una sola consulta lógica.

### Privadas para negocio

- Registro, inicio/cierre de sesión y recuperación de acceso.
- Lectura y actualización del onboarding propio.
- Selección de plan y reporte de pago.
- Edición de borrador, carga de recursos y solicitud de revisión.

### Privadas para administración

- Cola de onboarding, documentos y solicitudes de información.
- Configuración de planes, capacidades e instrucciones de pago.
- Revisión de pagos, borradores, versiones y publicación.
- Auditoría de acciones y eliminación protegida de pruebas o registros indebidos.

Cada ruta define autorización, límite de solicitudes, protección CSRF para formularios y validación del lado del servidor.

## Pantallas a construir

1. **Área de negocios locales**: explicación del proceso, planes cuando estén definidos y acceso o registro.
2. **Registro e inicio de sesión**: solo representantes de negocios.
3. **Onboarding guiado**: datos, representación, categoría, documentos y envío a revisión.
4. **Estado de solicitud**: avances, solicitudes de corrección y comunicación del equipo.
5. **Comparador de planes**: tabla de capacidades, precio aprobado y ejemplos de tarjeta/ficha; vistas de demostración, no promesas de publicación.
6. **Pago manual**: método, instrucciones, referencia, comprobante y estado de verificación.
7. **Panel del negocio**: resumen, plan, ficha, recursos, historial y llamadas a la acción según estado.
8. **Editor de ficha**: contenido condicionado por plan, validación y vista previa temporal.
9. **Panel administrativo**: bandejas de revisión y publicación, separado del directorio público.
10. **Plantillas públicas**: tarjeta común y dos o tres variantes de ficha según capacidades del plan y categoría, sin crear un diseño por negocio.

## Orden de implementación

### Hito 0 — Preparación

- Congelar la primera etapa pública funcional.
- Crear migraciones separadas y reversibles; nunca modificar producción a mano.
- Documentar campos obligatorios, documentos aceptados, planes, precios, plazos y responsable operativo.
- Definir política de retención y permisos para documentos sensibles.

**Aceptación:** esquema revisado, variables privadas fuera del repositorio y copia de respaldo de base de datos antes de cada migración.

### Hito 1 — Datos, autenticación y permisos

- Crear tablas de negocios, usuarios, onboarding, documentos, revisiones y bitácora.
- Crear acceso seguro para representantes y roles administrativos.
- Implementar controles de propiedad: un negocio solo puede leer o editar sus propios registros.

**Aceptación:** un usuario no puede acceder a otro negocio; un turista no puede acceder a áreas privadas; auditoría registra cambios de estado.

### Hito 2 — Onboarding y revisión administrativa

- Construir formulario progresivo y carga privada de documentos.
- Construir bandeja de revisión, solicitud de información y decisión final.
- Enviar avisos por correo solo cuando se defina el servicio de correo y sus credenciales.

**Aceptación:** un onboarding completo recorre todos los estados y no habilita plan ni ficha antes de aprobación.

### Hito 3 — Planes, pagos manuales y activación

- Configurar planes y capacidades desde administración.
- Construir comparador, vistas de ejemplo y selección de plan.
- Registrar pago manual y comprobante; permitir confirmación o rechazo administrativo.

**Aceptación:** capacidades de editor y publicación cambian según plan activo; ningún pago se confirma automáticamente.

### Hito 4 — Editor, recursos y aprobación de ficha

- Crear borradores, versiones, carga de logo/fotos y validaciones de recursos.
- Generar vista previa privada con enlace temporal.
- Permitir solicitud de revisión, comentarios, aprobación del negocio y publicación administrativa.

**Aceptación:** cambios en borrador no aparecen en público; publicación y reversión conservan historial.

### Hito 5 — API pública y renderización de fichas

- Implementar API de solo lectura para contenido `publicado`.
- Conectar directorios municipales, tarjetas y plantilla de ficha por slug.
- Implementar historial de slug y redirecciones.

**Aceptación:** una ficha publicada aparece en su municipio y URL; una ficha suspendida, borrador o no aprobada no aparece en ninguna respuesta pública.

### Hito 6 — Operación, seguridad y lanzamiento controlado

- Configurar respaldos, retención de archivos, registro de errores y monitoreo básico.
- Crear guía de operación para revisar onboarding, pagos, contenido, publicación y retiro.
- Abrir primero a pocos negocios reales y corregir el flujo antes de ampliar municipios.

**Aceptación:** simulación completa con un negocio piloto y reversión documentada de cada acción crítica.

## Estrategia de pruebas

### Unitarias

- Validadores de campos, slugs, categorías, planes, precios, capacidades y transiciones de estado.
- Permisos por rol y por pertenencia al negocio.
- Reglas de visibilidad: solo `publicada` aparece en API pública.
- Sanitización de HTML, tipos y tamaño de imágenes, comprobantes y documentos.
- Cálculo de límites por plan.

### Integración

- Migraciones sobre una base aislada desde cero y sobre una copia de esquema existente.
- API con MySQL y SQLite de prueba cuando sea viable.
- Sesión, CSRF, límites de intentos, subida de archivos y rutas privadas.
- Directorio → API → ficha; asegurar que fotos, categorías y contactos correspondan al mismo `public_id`.
- Eliminación administrativa con confirmación, permisos y bitácora.

### Flujos funcionales de extremo a extremo

1. Representante crea negocio, completa onboarding y carga documentos.
2. Administrador solicita corrección; representante corrige y reenvía.
3. Administrador aprueba onboarding; negocio selecciona plan y reporta pago.
4. Administrador confirma pago; el editor habilita las capacidades correctas.
5. Negocio crea ficha y recursos; administrador solicita ajuste y luego aprueba.
6. Negocio revisa vista previa; administrador publica.
7. Turista encuentra tarjeta por municipio, abre URL limpia y ve solo datos publicados.
8. Administrador suspende ficha; la API y directorio dejan de mostrarla.

### Regresión antes de cada despliegue

- Ejecutar pruebas actuales del formulario, administración, directorio, destinos, generación y despliegue.
- Añadir pruebas nuevas al mismo comando automatizado, sin depender de datos productivos.
- Validar enlaces, respuesta HTTP, JSON, idioma ES/EN, diseño responsive y accesibilidad básica.
- Probar manualmente en escritorio y móvil las pantallas modificadas.

## Protocolo de validación manual contigo

Cada hito se entrega en un entorno de prueba con una lista corta de casos. Tú validas texto, decisiones de negocio, apariencia, documentos requeridos, planes y comportamiento real. No se despliega a producción hasta que el hito tenga:

1. Pruebas automáticas aprobadas.
2. Prueba manual de los escenarios del hito.
3. Revisión de datos de prueba y limpieza de registros de ensayo.
4. Respaldo previo y plan de reversión.
5. Aprobación explícita para publicar el hito.

## Decisiones pendientes antes del Hito 1

- Nombre legal u operativo que figurará como responsable de datos.
- Checklist de documentos y política de retención.
- Criterios para aprobar un negocio y una ficha.
- Nombre, precio, vigencia y capacidades de cada plan.
- Instrucciones reales de pago manual y responsable de verificación.
- Servicio de correo para avisos transaccionales.
- Límites de fotografías, formatos, tamaños, derechos y proceso de autorización.
- Alcance exacto de las dos o tres plantillas de ficha.

No se inventarán precios, requisitos legales, documentos ni beneficios comerciales. Se configuran cuando estén definidos y aprobados.
