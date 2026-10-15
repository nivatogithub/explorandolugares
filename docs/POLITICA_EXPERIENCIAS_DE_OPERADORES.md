# Experiencias ofrecidas por operadores

## Diferencia frente a los atractivos del destino

Un atractivo público —por ejemplo, una iglesia, un parque, un mirador o una cascada— puede formar parte de la guía del destino cuando su existencia y descripción están verificadas. No requiere un convenio comercial.

Una experiencia —por ejemplo, un vuelo, una visita guiada o un recorrido en finca— es una oferta de un operador. No se presenta como atractivo abierto ni como recomendación disponible hasta validar quién la presta y acordar su promoción.

## Publicación

- Las experiencias nuevas se importan en estado `draft` por defecto; los atractivos informativos pueden entrar publicados con el destino aprobado.
- El borrador puede revisarse en una vista previa privada. No aparece en la página pública ni incluye un enlace comercial sin confirmar.
- Una persona administradora solo cambia la experiencia a `published` cuando el prestador está identificado, existe un acuerdo comercial aprobado y la información de contacto y operación está confirmada.
- La publicación usa tarjetas gráficas coherentes con las de los atractivos, con una imagen aprobada, descripción clara y contacto del operador. La tarjeta no implica reserva ni pago en línea.
- Las imágenes ilustrativas sirven para valorar el diseño; no prueban que exista un operador, una ruta o una actividad en una ubicación específica.

## Modelo comercial por definir

La prioridad del portal es entregar información útil al viajero y visibilidad clara al prestador. Para la primera etapa se prefiere evaluar una tarifa fija de publicación o visibilidad, con periodicidad y alcance transparentes. Una comisión sobre ventas solo se usará si el operador la acuerda expresamente y el proceso puede medirse con claridad. No se debe inferir ni activar ninguna comisión por defecto.

## Implementación actual y evolución

La tabla de atractivos reutiliza temporalmente el tipo `experience` y el estado de publicación existente para mantener la implementación sencilla. El estado `draft` es la barrera pública actual. Cuando se construya el flujo de prestadores, cada experiencia deberá vincularse a un `business` validado y guardar el estado del convenio y su modalidad comercial como datos estructurados, con historial de aprobación. La publicación seguirá siendo una decisión administrativa explícita.
