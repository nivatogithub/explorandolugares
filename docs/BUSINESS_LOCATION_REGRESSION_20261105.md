# Ubicación de negocios: regresión del 5 de noviembre de 2026

El commit `569f34dc` incorporó navegación desde las coordenadas almacenadas: Android mediante `geo:`, Apple mediante Maps y escritorio mediante Google Maps. El refactor `6ef59083` sustituyó ese comportamiento por un filtro de enlaces de fichas nombradas, incompatible con el enlace numérico que produce `BusinessAdminService::submittedLocation()` durante el registro.

Decisión vigente del propietario: el punto elegido al registrar el negocio debe permitir llegar al establecimiento desde su tarjeta y su página. No exigir volver a capturar la ubicación ni confundir la regla de enlaces de atractivos editoriales con el punto confirmado por el responsable de un negocio.

`BusinessLocationLink` conserva una implementación compartida. Primero valida ambas coordenadas, finitud y rangos. Android recibe un enlace `geo:` sin fijar una aplicación; Apple recibe un enlace Maps y escritorio un enlace Google Maps. Sin coordenadas utilizables, conserva únicamente las fichas HTTPS de Google Maps admitidas previamente. No transforma nombres genéricos o URL externas arbitrarias en ubicaciones verificadas.

La aparición del selector de aplicaciones depende del sistema, navegador, aplicaciones instaladas y preferencias. Una web no puede garantizar que siempre aparezca; iOS no ofrece necesariamente el selector de Android. La validación automatizada de URL no acredita apertura real en dispositivos físicos.

Regresión obligatoria: `php backend/tests/business-location-link.php`. Cubre el formato generado por el registro, tarjeta, página, Android, Apple, coordenadas inválidas/incompletas, enlaces de respaldo y vistas previas sin navegación. No comprueba el registro específico de Casa Hotel Ruta en la base productiva ni entrega de correo. Este cambio no escribe en la base y todavía requiere el circuito de preproducción/aprobación antes de publicarse.
