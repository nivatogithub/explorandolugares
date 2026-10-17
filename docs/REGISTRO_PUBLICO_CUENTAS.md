# Registro público de cuentas y negocios

Cambios preparados en preproducción; no habilitan ni despliegan producción automáticamente.

- `/negocios/registro/` pregunta si la persona es viajero/turista o representa un negocio.
- Un viajero crea una cuenta personal con correo, contraseña y consentimiento versionado. No crea negocios ni recibe permisos administrativos.
- El recorrido empresarial busca negocios por lugar y categoría. Permite reclamar uno disponible o abrir «No lo encuentro · Crear negocio». Conserva los requisitos actuales de documentos privados de identidad y representación.
- La solicitud de negocio nuevo recoge descripción, historia, servicios, categorías adicionales del catálogo, dirección/referencia y ubicación con el mismo mapa del administrador, horario, capacidad, precios, enlaces, canales y hasta cuatro fotos privadas de referencia. No concede acceso al panel ni acepta estados de publicación o suscripciones elegidos mediante campos manipulados.
- Los datos se guardan en `business_account_applications.business_details_json`; las fotos se guardan fuera del directorio público y su metadata en `business_application_images`. La migración aditiva 034 también crea `account_consents`.
- El revisor consulta los datos y fotos en Solicitudes de cuenta. Al aprobar crea el borrador y su asociación a la persona, conservando coordenadas, texto, categorías, canales y fotos en las tablas ya existentes. Los canales se insertan con `is_public=0`. Ni aprobar ni registrar significa publicar o contratar una suscripción paga.
- El negocio existente conserva su ficha; reclamarlo no crea otro registro ni sobrescribe sus datos públicos.
- Las imágenes reutilizan la validación del administrador: MIME real y dimensiones, JPG/PNG/WebP, máximo 8 MB por imagen y 32 MB por envío; almacenamiento privado, nombres aleatorios y revisión antes de cualquier uso público. Las fotos de solicitudes rechazadas quedan privadas hasta definir su conservación o eliminación.

## Navegación y sesión

Dirección (Inteligencia e inversión, Costos y cancelaciones) comparte la zona fija inferior con Sistema. Operación/Contenido puede desplazarse; la barra de desplazamiento es visible. Los enlaces ya existían: el problema era su posición fuera del área visible.

La cuenta y Cerrar sesión se muestran a la derecha de la primera franja blanca. El panel y las páginas PHP de cuentas usan un fragmento compartido; las páginas estáticas de preproducción consultan su sesión mediante el mecanismo local existente. El administrador tiene además el enlace Administración. No se convierte un correo o una selección de tipo de cuenta en permiso administrativo.

Pruebas: batería HTTP de administración ampliada con registro viajero, consentimiento, rechazo de permisos manipulados, negocio nuevo con ubicación/canales/foto y aprobación que conserva los datos privados; pruebas locales de acceso administrador y recuperación de contraseña. Revisión visual del selector de tipo, formulario/mapa, header público y navegación administrativa.
