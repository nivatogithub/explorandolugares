# Visibilidad de audiencia y valor para los comercios

Requisito del propietario, 16/10/2026: mostrar visitas anónimas y viajeros registrados, desglosados por país, para demostrar alcance a negocios. Esta es la especificación de implementación; NO hay cifras reales, rastreadores ni recolección nuevos habilitados por este documento.

## Experiencia pública propuesta

Sección “Nuestra audiencia” en la página de cobertura, con periodos últimos 30/90 días y fechas exactas; comparación con periodo anterior de igual duración. Seleccionar departamento/destino filtra la demanda de contenido sobre ese lugar, no afirma que la persona viaje o se encuentre allí. Mostrar fuente, última actualización, cobertura de medición y metodología. Hasta tener datos reales: “Medición aún no habilitada”, nunca números de muestra o un 0 falso.

Bloques públicos: alcance del portal; audiencia por país de conexión; comunidad viajera; interés por departamento/destino/categoría; acciones hacia negocios. En un futuro panel privado cada comercio puede ver exclusivamente sus propias fichas y resultados. Rankings públicos de comercios y sus cifras individuales requieren reglas y autorización aparte; no incluirlos por defecto.

## Métricas y significado

| Métrica | Definición operativa | Qué demuestra / límite |
|---|---|---|
| Vistas de páginas | Eventos válidos de página pública; excluir bots conocidos, pruebas y administración | Lecturas, no personas; recargas pueden repetir |
| Sesiones sin iniciar sesión | Sesiones de navegación anónima observables bajo el método elegido | Actividad sin cuenta; no visitantes humanos únicos demostrados |
| Visitantes únicos estimados | Identificadores de navegador observables deduplicados en el periodo, solo si existe base y mecanismo adecuados | Una persona con dos dispositivos puede contar dos; sin medición suficiente mostrar no disponible |
| Sesiones con cuenta viajera | Sesiones autenticadas del tipo viajero | No mezclar con administradores o comercios |
| Viajeros registrados | Cuentas viajeras activas y correo confirmado; definir tratamiento de cuentas deshabilitadas/eliminadas y excluir pruebas | Comunidad habilitada, diferente de total de filas creadas en administración |
| Nuevos viajeros | Altas habilitadas y confirmadas durante el periodo, deduplicadas por cuenta | Crecimiento; no implica uso |
| Viajeros activos | Cuentas viajeras con actividad válida en los últimos 30 días | Uso reciente, distinto del acumulado registrado |
| Retorno | Visitantes/cuentas observables que vuelven según ventanas y denominador definidos | Fidelidad estimada; no sumar únicos diarios para obtener únicos mensuales |
| Interés por lugar/categoría | Vistas, búsquedas válidas, aperturas de fichas y guardados, cuando esas funciones existan | Demanda informativa; no presencia física en destino |
| Clics hacia negocios | WhatsApp, llamada, web, indicaciones al mapa y correo, por canal | Intención de contacto; no contacto completado, cliente, visita física, reserva ni venta |
| Tasa de acción | Sesiones con ≥1 clic de contacto / sesiones que vieron fichas elegibles | Proporción de interacción sin inflar por clics repetidos |
| Origen de adquisición | Buscadores, directo, redes, referencia y campaña con parámetros saneados | Qué canales traen tráfico medido; no recuperar búsquedas privadas ni datos personales de URLs |

Para mostrar “clics únicos”, deduplicar por sesión + negocio + canal + ventana y explicar método. Mantener total de eventos separado. No inflar cifras con tráfico automático o enlaces abiertos por el propio comercio.

## Países: dos conceptos separados

1. País de conexión de las visitas: aproximación de fuente de analítica/geolocalización permitida; VPN y redes móviles pueden distorsionarlo. No presentar como nacionalidad ni residencia. Mantener “Desconocido” en el total, sin redistribuirlo entre países conocidos.
2. País de residencia declarado por viajeros: campo opcional del perfil, consentido y actualizable. No deducirlo del idioma ni de una IP. Sin dato, “No informado”. No reutilizar el país de una sesión como dato permanente del perfil.

Para cada desglose, numerador/denominador deben referirse a la misma métrica y periodo. Un país de conexión de sesiones no puede compararse directamente con país de residencia de cuentas.

## Calidad, privacidad y publicación

- Elegir y verificar una fuente real de analítica o instrumentación propia antes de diseñar una cifra como activa. No sumar logs de hosting sin filtrar como si fueran viajeros; incluyen bots, recursos y pruebas.
- Un viajero registrado también puede navegar sin sesión: no sumar anónimos + cuentas acumuladas y llamarlo personas distintas. Para segmentar sesiones, usar una regla estable y documentada (por ejemplo, estado al comienzo de sesión) y no contar dos veces una sesión al iniciar sesión.
- Excluir pruebas, personal interno y administración con reglas registradas. No garantizar detección perfecta de bots. Registrar cambios de metodología para no comparar series incompatibles.
- Publicar exclusivamente agregados. Umbral propuesto de 5 observaciones por celda país/destino antes de desglosar; agrupar celdas pequeñas en “Otros” sin permitir reconstrucción por filtros/comparaciones. Aprobar método antes de habilitarlo. Ningún email, IP, ID de sesión o cuenta llega al endpoint público.
- Evitar retener IP para el informe público. Si una fuente la procesa para país/antifraude, documentar finalidad, acceso y retención mínimos; evaluar privacidad y proveedores antes de activarla. No usar fingerprinting para sortear falta de consentimiento.
- Declarar el tráfico no observable, ausencia de país, desfase de actualización y limitaciones. “Sin medición” no equivale a cero. El comienzo de medición no reconstruye un histórico que nunca se registró.
- Dashboard público con actualización diaria, fechas y datos cerrados del día anterior como propuesta. Contadores en vivo se posponen: facilitan abuso y aportan menos confianza que una serie verificable.

## Fases y gates

A. Auditar fuente de visitas disponible y cuentas reales; acordar definiciones, política y exclusiones. B. Instrumentar eventos mínimos y agregación privada; validar tráfico sintético y filtros sin publicarlo. C. Recoger línea base real y contrastar agregados. D. Publicar alcance/país/interés y acciones hacia negocios. E. Completar registro de viajeros, país opcional y funciones útiles de guardado/listas; habilitar métricas de comunidad solo después de comprobar esos recorridos. F. Panel privado por comercio con aislamiento entre cuentas y comparación de periodos.

Pruebas exigidas: bots/exclusiones, repetición de evento, sesiones mixtas, cambio de día/periodo, países desconocidos, bajos conteos, consulta por destino y cuentas no habilitadas. Clics y visitas nunca deben registrarse como ventas. No depender de recolectar documentos de identidad para medir audiencia viajera.

## Estado observado del repositorio

En la rama del plan no se encontró instrumentación de visitas ni registro público de viajeros. En la rama de negocios hay tabla `accounts` con tipos `traveler|business` y administración de cuentas viajeras pendientes de activación, pero esto NO demuestra un registro público de viajeros operativo ni un conteo real de usuarios activos. No se consultó la base productiva ni un proveedor de analítica en esta tarea. No hay país de residencia en la tabla de cuentas inspeccionada; revisar perfil y versiones vigentes antes de una migración.

## Implementación de interfaz

Añadida a la página de cobertura: tarjetas, periodo 30/90 días, contexto territorial y espacios por país/evolución. Datos aún no conectados, mostrados como no disponibles; no se habilitó recolección, analítica ni consulta de registros de viajeros.
