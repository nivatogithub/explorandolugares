# Roadmap técnico y de adquisición

Son propuestas por implementar y comprobar, salvo capacidades locales identificadas en `REVISION_REPOSITORIO.md`. El MVP editorial no espera todas estas funciones. No habilitar registro público ni aceptar documentos mientras falten responsable, canal de privacidad, controles de acceso y pruebas del entorno real.

## 0. Base y publicación segura

PHP/MySQL actual, SQLite solo para pruebas locales. Implementar versiones de contenido separadas de publicación, diff, aprobación vinculada a hash, auditoría, reversión, permisos de propuesta/revisión/publicación y adaptador del catálogo nacional al paquete v2. Compatibilidad de `corregimiento`, destino autónomo y padres debe demostrarse antes de importarlos. Migraciones aditivas; no cambiar IDs ni cargar masivamente producción desde este inventario. Actualizar opciones/indicativos colombianos: no extender el supuesto histórico de fijo 604 a todo el país.

Salida: rechazo de edición sobre versión obsoleta, borrador invisible, edición pública no aplicada hasta aprobar, pruebas en MySQL/HTTPS autorizado. Los workers solo escriben propuestas privadas. Integración de CI propuesta: ejecutar validador y comprobar que PR de lugar afecta un solo ID; no configurar despliegue automático.

## 1. Cuentas, acceso y logout

Comercios: reutilizar y auditar `BusinessAccountPortalService` y rutas de registro/login/logout/password existentes; no dar por activas sus banderas ni proveedores. Viajeros: crear entidad/cuenta y panel mínimos separados de permisos de comercios; una persona puede tener ambos roles sin acceso administrativo global.

Correo confirmado o proveedor OAuth configurado acredita control del canal, no identidad civil ni representación. Implementar verificación de correo, recuperación con tokens de un solo uso y caducidad, hash de contraseña seguro, CSRF, cookies Secure/HttpOnly/SameSite, rotación de sesión al entrar, límites de intentos, protección de enumeración y auditoría sin secretos. OAuth futuro: OIDC con state/nonce/PKCE y validación issuer/audience; no mostrar botones habilitados sin credenciales y pruebas. Logout mediante POST con CSRF invalida sesión en servidor y cookie; opción de cerrar todas las sesiones y revocar tokens. Probar cuenta A incapaz de editar negocio B, logout tras botón atrás, token vencido/reutilizado, sesiones robadas revocadas y contraseña cambiada.

Salida: recorridos completos en entorno aislado, privacidad y soporte reales, pruebas MySQL/HTTPS antes de abrir. Crear cuenta no reclama una ficha ni publica nada.

## 2. Onboarding de comercios con revisión por excepción

Tres ejes independientes:

- Identidad: persona/control del canal, y evidencia proporcional al riesgo. No equiparar correo u OCR con identidad civil verificada.
- Representación: propietario, representante legal o administrador delegado; documento oficial vigente y/o autorización verificable según caso. Comprobar alcance y caducidad. OTP a teléfono de la ficha por sí solo no demuestra propiedad.
- Registro legal: razón social/NIT/establecimiento/cámara y RNT cuando la categoría lo exige. RUT no sustituye representación, matrícula ni permiso de operar; un registro no demuestra operación actual.

Flujo propuesto: correo confirmado → elegir ficha o proponer alta → detectar duplicados → declarar relación → evaluación de riesgo → obtener evidencia mínima → contrastar fuentes permitidas → propuesta de vinculación → revisión humana del hash → habilitar edición en borrador. Ficha pública sigue gratuita antes y después. El código actual exige documentos en todos los casos; reducirlos requiere implementación explícita, no un cambio de texto del plan.

Adaptadores separados `dian`, `rues`, `rnt` con respuestas `match`, `mismatch`, `unavailable`, `not_applicable`, evidencia/fecha y nivel de confianza. Sin endpoints inventados; activar cada adaptador solo tras verificar contrato de acceso, finalidad, licencia, autenticación, límites, SLA y prueba autorizada. Ver investigación oficial. Nunca enviar claves DIAN del usuario, sortear CAPTCHA ni usar endpoints privados de navegador como API contractual.

Ruta estándar de bajo riesgo: coincidencia exacta de identificación empresarial permitida, canal confirmado, representación corroborada y sin conflictos → preparar recomendación automática. Publicación y aprobación inicial siguen humanas por gobernanza vigente. Revisión por excepción organiza la cola; no autoriza aprobar agentes por su cuenta. Una futura aprobación automática requiere decisión y cambio de política explícitos.

Excepciones: nombres parecidos, datos desactualizados, ausencia de fuente, cambio de representante, reclamos rivales, establecimiento distinto con mismo NIT, categoría RNT incierta, señal de fraude o documentos inconsistentes. API caída/403/429 → circuit breaker y backoff limitado, caché fechada que no se trate como prueba vigente, carga mínima por el usuario o consulta manual autorizada; no rechazar por caída ni marcar verificado. Controversia congela el cambio de control, no elimina automáticamente la ficha gratuita. Informar estado y permitir apelación; objetivo interno de revisión 2 días hábiles, sujeto a capacidad.

Documentos: fuera del webroot y de git, cifrado, permisos mínimos, enlaces temporales, análisis de malware, sin entrada automática a modelos externos; conservar resultado y hash de evidencia cuando baste. Política propuesta: eliminar copias a los 30 días del cierre de revisión/apelación si no existe obligación justificada; responsable debe aprobar base, excepciones y retención antes de activarla. No publicar número de documento, firma ni expediente.

Salida: casos exacto, ambiguo, caída, duplicado, reclamación rival y revocación pasan pruebas; registro legal no genera insignia genérica. Mostrar dimensión y fecha concreta de comprobación.

## 3. Viajeros: valor y datos mínimos

Navegar, descubrir y contactar no requiere cuenta. Incentivos que solo se anuncian cuando funcionen: guardar lugares, listas de viaje sincronizadas, recuperar favoritos en otro dispositivo y alertas voluntarias de cambios/eventos confirmados. No descuentos ficticios, premios sin acuerdo ni contenido esencial bloqueado.

Perfil mínimo: ID interno, correo confirmado, idioma y consentimiento/política versionados con fecha/finalidad. Alias, intereses turísticos amplios y zona de origen aproximada opcionales. No pedir cédula, fecha de nacimiento, dirección, ubicación continua, salud o ingresos. Marketing independiente y desmarcado; personalización opcional revocable; exportar/corregir/eliminar cuenta con verificación proporcional. Borrar preferencias no elimina el derecho de navegar. Retención propuesta de cuentas inactivas: 24 meses, aviso previo si está autorizado y eliminación salvo obligación justificada; revisión legal/operativa pendiente.

Recomendaciones iniciales sin perfil: lugares relacionados por región/categoría y disponibilidad de contenido verificado, con explicación. Después: intereses elegidos y guardados consentidos; sin inferir rasgos sensibles. Botón para reiniciar preferencias, diversidad territorial, excluir fichas cerradas/datos vencidos y separar contenido patrocinado. Medir utilidad con clic a información/guardado/contacto, no asumir que un clic es visita física o venta.

## 4. Adquisición, uso y aprendizaje

SEO por lugar/categoría, páginas rápidas, canonicals/hreflang y sitemap; no indexar páginas vacías para fingir cobertura. Enlaces internos entre padre/hijo y regiones. Distribución orgánica con organizaciones locales solo mediante contactos aprobados en tareas separadas. Campañas, email y WhatsApp requieren autorización y base válida; directorio público no es una lista de marketing.

Eventos propuestos: `place_view`, `search_used`, `category_view`, `business_contact_click`, `save_place`, `registration_started`, `registration_completed`, `claim_submitted`, `claim_resolved`, `return_session`. Propiedades: place_id, categoría, idioma, canal/UTM saneado y dispositivo agregado. No correo, teléfono, documento, búsqueda libre sensible ni ubicación precisa en analítica. Evitar rastreadores no necesarios hasta tener base/consentimiento cuando corresponda. Analítica agregada y retención propuesta de eventos 90 días; revalidar política.

Definiciones: adquisición = visitantes/sesiones nuevas por canal con método declarado; activación = guardado o contacto o uso útil de búsqueda / visitantes elegibles; registro = completados / iniciados; retorno D7/D30 = cohorte que vuelve en ventana definida / cohorte observable (solo identificadores consentidos); reclamación = resueltas favorablemente / enviadas; cobertura editorial y publicada según inventario. Distinguir visitantes anónimos no deduplicables de cuentas. Excluir bots y tráfico interno con regla documentada.

Primeras dos semanas: establecer línea base; comparar semanalmente adquisición, activación, retorno, errores, tiempo de revisión y correcciones por lugar. No inventar tráfico ni metas de conversión. Objetivos de servicio propuestos: 0 filtraciones de borradores/documentos, 100% fotos con permisos, 100% fichas con fuente, atención editorial de errores críticos antes de siguiente lote. Si hay errores repetidos, detener expansión y corregir plantilla. Owner producto por asignar.

## 5. Monetización posterior sin membresías

Explorar patrocinios identificados por región, pauta contextual transparente, servicios opcionales de producción/fotografía, campañas acordadas y afiliación/comisión por transacción futura explícita. Ninguna elimina ficha básica gratuita, cobra por reclamar/corregir ni vende insignia de verificación. No alterar ranking editorial sin etiqueta, no vender datos personales. Validar disposición a pagar, costos, impuestos, términos y separación publicidad/recomendación antes de implementar pagos. Reservas, pagos y comisiones no están activados por este plan.

## 6. Instagram como proyecto aparte

Después del MVP: investigación de audiencia, pilares, formatos, permisos de reutilización, calendario, enlaces y atribución de adquisición; presupuesto y cuentas por confirmar. Esta fase no crea publicaciones, campañas ni contactos.
