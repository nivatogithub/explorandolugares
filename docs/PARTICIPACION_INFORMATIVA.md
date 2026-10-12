# Participación informativa v1

Versión confirmada por el formulario: `participacion-informativa-v1`.

Página `/aliados/` ES/EN reorganizada: propósito y valor junto a la introducción; pasos de participación y dudas frecuentes junto al formulario. Los enlaces internos llevan a propósito, funcionamiento, alcance y preguntas; no se inventa un equipo ni una página de quiénes somos sin datos de sus responsables.

## Contenido que debe entender el interesado

Portal informativo de destinos y oferta local. El formulario solicita contacto, sin crear cuenta, contratar un servicio, autorizar cobros, garantizar inclusión, visitas o ventas, gestionar reservas ni publicar automáticamente. Los planes y tarifas están pendientes de definición. Una eventual propuesta comercial se explicará y acordará por separado. Las funciones futuras no se ofrecen como disponibles.

## Declaración obligatoria, separada de privacidad

> He leído cómo participar y el alcance de esta primera etapa. Entiendo que solicito contacto sobre un portal informativo; no contrato un servicio ni obtengo reservas o una publicación automática.

Enlaces incluidos a `#como-funciona` y `#condiciones-participacion`. No se marca por defecto. La casilla registra una declaración del interesado; no verifica que haya leído cada sección. No requiere abrir cada desplegable ni pretende aceptar condiciones comerciales futuras. Navegador, JavaScript y servidor exigen la confirmación además del consentimiento de privacidad.

Se guarda `leads.participation_version` con la versión fija definida en servidor y la fecha existente del registro. La versión puede verse en administración. Para cambios sustanciales al texto o alcance, crear otra versión; conservar esta documentación como referencia del texto anterior.

## Actualización de bases existentes

Instalaciones nuevas usan los esquemas actualizados. Para bases MySQL existentes, hacer respaldo y ejecutar una sola vez `backend/database/migrations/002-participation-version.mysql.sql` antes de actualizar el backend; aplicar también 001 si falta el canal telefónico. Los registros previos quedan sin confirmación registrada, sin inferir una aceptación retrospectiva. No se ha ejecutado ninguna migración en hosting.
