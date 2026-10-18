# Medios de pago manuales y prueba local

Administración → Sistema → Medios de pago (`/admin/medios-pago.php`). Guarda titular, banco, número/tipo de cuenta, llave Bre-B, notas y QR. La imagen se sube por el formulario administrativo, se valida como PNG/JPG/WebP, máximo2MB/2048px, se decodifica y recodifica a PNG, queda privada0600 con nombre aleatorio. Dejar el archivo vacío conserva el QR anterior. La configuración vive en la tabla `payment_instructions` (migración039): titular, banco, número, tipo, llave, notas, habilitación, modo de prueba, fecha/autor y nombre privado del QR. El archivo de imagen vive en `private/payment-instructions/`; una copia de seguridad completa debe incluir la base y esa carpeta. No se guardan credenciales bancarias.

Cada ambiente se configura por separado. `local_test` impone test_mode al guardar; un registro marcado como test no se carga en producción. La llave de ejemplo `PRUEBA-EXPLORANDO-NO-PAGAR` y cuenta `NO-TRANSFERIR-0000` no son datos bancarios válidos. El QR versionado en `preprod/fixtures/qr-pago-simulado.png` contiene únicamente un texto de simulación, sin orden bancaria. Generado con qrcode8.2 y PyPNG: "EXPLORANDO LUGARES | SIMULACION DE PAGO | NO TRANSFERIR DINERO | SIN VALOR BANCARIO". No implica integración Bre-B ni conexión bancaria.

Sitio público autenticado: Mi cuenta → Mis negocios → Elegir suscripción y enviar soporte. Gratis no requiere transferencia ni comprobante; pagas muestran instrucciones cuando se seleccionan. El importe se toma de la tarifa del servidor. QR se sirve solo con sesión activa de cuenta o administración, con encabezados de imagen/no-cache. Un comprobante es evidencia pendiente, no confirmación de fondos, no publicación ni activación automática. En modo local la referencia lleva prefijo forzado [SIMULACIÓN].

Prueba reproducible (solo localhost8088, MariaDB local y cuentas persistentes ficticias):

```sh
php backend/tests/payment-instructions.php
node backend/tests/business-subscriptions-ui.cjs
python3 preprod/probar-pago-simulado.py
python3 preprod/probar-pago-simulado.py --pago
```

El script guarda configuración usando el formulario administrativo, carga el QR, inicia otra sesión normal sin rol admin, abre el paso público y opcionalmente envía un PNG ficticio como soporte. Comprueba aislamiento/403, tarifa del servidor frente a un importe enviado falso, estado pending y ausencia de inicio de vigencia. Mantiene el comprobante de prueba para verlo, nunca aprueba fondos ni cambia datos reales. Informe local: `.local/preprod/evidencia/pagos/resultado.json`. Cada ejecución con --pago crea una solicitud de prueba; aplica el límite normal de5/hora, no borrar contadores para repetir.

Límite actual separado: el negocio nuevo o reclamado todavía requiere el flujo de verificación/asociación existente. Este paso se valida con los negocios sintéticos ya asociados a `negocio@pruebas.invalid`; no certifica que el alta/reclamo completo esté resuelto ni autoriza aprobación automática de propiedad. Antes de producción configurar medios reales y completar validación del recorrido candidato.

Antes de promover esta versión, aplicar la migración039 con el proceso controlado de base de datos. Producción se configura desde su propio panel con datos y QR reales; no se copia la fila ficticia local. El JSON privado anterior deja de ser fuente de lectura/escritura. En este equipo se conserva sin borrar como respaldo; los datos ficticios se recargaron desde el formulario administrativo y se comprobó su persistencia en MariaDB.

## Identificación y revisión de la transacción (migración040)

Para una suscripción paga, el portal muestra una referencia `EL-…` generada por el servidor y vinculada a la sesión y al negocio seleccionado. La persona puede incluirla en el concepto bancario si su entidad lo permite; no se exige que el banco admita notas. Al enviar el soporte debe indicar el número de transacción/autorización del comprobante, fecha no futura, valor efectivamente transferido, medio usado y banco o aplicación de origen. No se piden claves ni números completos de cuentas de origen. Gratis oculta estos campos y no requiere pago.

`business_subscription_payment_evidence` guarda `checkout_reference` (única), `transaction_date`, `paid_amount`, `payment_method`, `source_bank` y el número de operación en `payment_reference`. La cotización sigue independiente en `business_subscriptions.quoted_amount`: el servidor no la sustituye por el valor declarado. En preproducción se mantiene la marca de simulación. Los comprobantes anteriores conservan campos nuevos nulos; el panel los identifica como anteriores.

En Administración → Suscripciones → Comprobantes por revisar se muestra el soporte, referencia, operación, fecha, origen e importe declarado; se advierte si difiere de la cotización. Antes de confirmar hay que contrastar el abono real en el banco: estos campos y una imagen no verifican por sí mismos una transferencia ni consultan APIs bancarias. La confirmación conserva el proceso existente y no publica automáticamente.

## Complemento de operador de experiencia

Decisión comercial aprobada: Gratis + tres suscripciones pagas; el operador no es una cuarta suscripción. Requiere Destacado, Página Comercial o Premium. Una contratación cubre un negocio en **una** experiencia publicada del mismo lugar (se compara destination_id, sin heredar otros lugares). Precios adicionales COP: mensual29.900, trimestral74.750, semestral155.480, anual299.000. Destacado + complemento mensual =79.800. Los demás períodos conservan los factores aprobados2,5 /5,2 /10.

Migración041: `experience_operator_prices` guarda tarifas; `business_experience_subscriptions` relaciona experiencia y suscripción con precio contratado. El total cotizado del pago incluye ambos componentes; la aplicación no confía en importes enviados por el navegador. Las solicitudes de alta conservan la elección y el desglose en `business_details_json.requested_subscription`; una solicitud no genera cobro ni una asociación automáticamente. Tras autorizar el negocio se confirma la selección al enviar el soporte desde Mis negocios.

El panel identifica la experiencia y el importe adicional en la revisión del pago. Confirmar debe incluir comprobar que el negocio presta la actividad; confirmar no publica. La publicidad en la experiencia exige negocio/lugar/experiencia publicados, suscripción paga aprobada y vigente. Los borradores, Gratis, vencidos y negocios trasladados no reciben esa visibilidad. Los enlaces externos editoriales anteriores se conservan en datos, pero ya no se convierten automáticamente en anuncios de operador sin contratación. Las páginas de negocio conservan sus beneficios propios y el directorio conserva su clasificación.

No hay débito recurrente automático: si una experiencia se oculta, se deja de anunciar al operador y se impide una nueva contratación/renovación para esa experiencia. Esta entrega no realiza devoluciones, abonos ni prorrateos de períodos ya pagados. No promete reservas, exclusividad o comisión. Revisar el complemento de nuevo al publicar si la experiencia cambió de estado entre la solicitud y la aprobación.

El complemento de operador solo se ofrece a negocios con la categoría activa `experiencias` (Experiencias y actividades), principal o adicional. Un hotel sin esta categoría no lo ve. El servidor vuelve a comprobarla al solicitar, confirmar el pago y publicar; retirarla también oculta la aparición pública en la experiencia. La selección de un negocio existente usa sus categorías guardadas, no las enviadas por el navegador.

## Simulador público y continuación de contratación

La tabla de `/negocios-locales/planes/` consulta `/api/subscription-prices.php`: usa los importes guardados en `subscription_plan_prices` y `experience_operator_prices`, sin aceptar importes del navegador. Los precios aparecen al final de la tabla, con un radio centrado debajo de cada suscripción. Una única frecuencia actualiza todos los precios y el total de la opción seleccionada. Gratis totaliza cero y elimina el complemento; las pagas permiten seleccionar una experiencia publicada. El complemento se calcula con la misma frecuencia. La experiencia elegida se conserva en sesión y se vuelve a validar contra el lugar y las categorías del negocio antes de continuar al pago. Si no se pueden consultar precios, los enlaces para continuar quedan deshabilitados.

`/suscripciones/continuar/` conserva durante dos horas una intención en sesión (suscripción, período, interés en complemento), sin crear cobros ni beneficios. Solicita autenticación y permite elegir entre negocios con membresía autorizada, o ir al registro/reclamación conservando la selección. Una cuenta personal también puede hacerlo. El pago de negocios nuevos/reclamados continúa disponible después de la asociación existente; el simulador no sustituye esa revisión. El complemento exige categoría de experiencias y la selección posterior de una experiencia publicada del mismo lugar.

Regresión local: `python3 preprod/probar-simulador-suscripciones.py`. Comprueba doce tarifas base, cuatro tarifas del complemento, anónimo → login → negocio → formulario de pago, selección conservada en el registro, cuenta viajera → registro de negocio, rechazo de negocios ajenos, planes/períodos inválidos y complemento sin categoría. Utiliza cuentas ficticias, sin crear negocios ni enviar comprobantes o correos.
