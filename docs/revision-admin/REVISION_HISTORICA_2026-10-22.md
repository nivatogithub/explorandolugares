# Revisión histórica de la integración — 22 de octubre de 2026

## Resumen y alcance

Comprobar que la unificación del flujo, presentación y administración no perdió funciones ni restauró contenido anterior. Se consultaron las referencias remotas actuales con `git fetch origin --prune` y se inventariaron 32 ramas locales. El informe distingue inclusión en el historial, comparación funcional y diferencias de ramas no integradas: ninguna de estas comprobaciones aislada certifica equivalencia completa.

## Hallazgo funcional corregido — prioridad alta

`DestinationPresentation::run()` retiraba todos los elementos `.directory-description` dentro del directorio. Esa limpieza heredada de la rama de presentación alcanzaba también las tarjetas aprobadas `data-directory-layout="editorial-v2"`: el resumen de un plan pago existía en la plantilla y se perdía al renderizar la página del lugar.

Se limita la limpieza al formato heredado, preservando los resúmenes de las tarjetas aprobadas. La prueba `backend/tests/directory-normalization-preservation.php` falla antes del arreglo y pasa después para Destacado, Página Comercial y Premium en Jericó, Cartagena y Guatapé. También comprueba conservación de contactos y acceso a página. No modifica datos ni derechos de suscripción.

## Integraciones confirmadas

| Origen | Resultado |
|---|---|
| Flujo `ae24427e` | Ancestro de la rama actual. Versiones, asociación privada, pagos, teléfonos y publicación permanecen. |
| Presentación `24811a40` | Ancestro. Plantillas, composición, constructor y recursos explícitos permanecen; hallazgo de resúmenes corregido. |
| Operación administrativa `6a348800` | Ancestro. Catálogo por origen, filtros, plantillas/correos, vistas privadas y seguimiento presentes. |
| Integración `00a4adb6` | Ancestro, sin commits pendientes propios. |
| Producción remota `66953908` | Ancestro. Cero diferencias en archivos raster y ninguna migración eliminada frente a ese commit. No se consultó el servidor productivo. |

Los servicios centrales siguientes son idénticos por blob respecto al origen de su integración:

- `backend/app/BusinessEditorialService.php` frente a `ae24427e`: idéntico.
- `backend/app/BusinessSelfOnboarding.php` frente a `ae24427e`: idéntico.
- `backend/app/BusinessSubscriptionAdminService.php` frente a `ae24427e`: idéntico.
- `backend/app/AdminBusinessOperations.php` frente a `6a348800`: idéntico.
- `backend/app/AdminEmailWorkspace.php` frente a `6a348800`: idéntico.
- `backend/app/AdminBusinessFilters.php` frente a `6a348800`: idéntico.

## Retiradas deliberadas, con funcionalidad conservada

- Revisión de cancelaciones salió de `costos.php` y está en `cancelaciones.php`, con la misma operación del servicio, CSRF y sesión privada.
- La asociación manual desde Cuentas ya había sido retirada en la rama administrativa original; no se restaura porque el flujo actual la realiza desde el registro/reclamación del responsable.
- Preparación editorial, registro/reclamaciones y publicación mantienen responsabilidades diferentes. Se retiraron accesos redundantes del menú, no sus servicios ni registros.
- El diagnóstico de correo se dejó a Brevo; Configuración enlaza a esa pantalla.
- El traslado puntual Jericó → Cauca Viejo se retiró del flujo general. La herramienta histórica se conserva.
- Copias antiguas del menú y fotografías anteriores se sustituyeron en la unificación/entrega de medios productivos. No corresponde restaurarlas desde una rama antigua.

## Ramas conservadas fuera de la integración

- `feature/advance-admin`: 18 commits exclusivos locales. Mantiene una arquitectura administrativa paralela y migraciones propias. El propietario ya la había dejado para evaluación; no se incorpora ni se elimina. Varias capacidades tienen equivalentes actuales, pero no se certifica identidad de toda esa rama.
- `propuesta/navegacion-contacto`: dos commits de propuesta aislada, no aprobada como rediseño general. No restaurar automáticamente.
- `correccion/cobertura-turistica`: 25 commits no ancestros; parte de sus diferencias de mapa tampoco figura en `origin/main`. La candidata conserva el CSS/JS principal del mapa de `origin/main`; la diferencia preexiste a esta unificación. Hace falta conciliar esa entrega con su responsable antes de considerar una integración; no es evidencia de pérdida causada por el arreglo administrativo.
- Ramas territoriales: contienen commits y documentos de revisión/aprobación/snapshots que no están todos en la rama integrada. Los archivos permanecen en sus ramas. La comparación con producción confirma raster intacto, pero no equivale a aceptar cada variante de contenido o los materiales pendientes. No borrarlas ni fusionarlas globalmente.

El inventario completo de refs, archivos propios y blobs se conserva como evidencia local en `.local/revision-ramas/`.

## Pruebas y límites

- Fallo reproducido antes y corregido después en normalización de tarjetas.
- Pruebas de editorial, teléfonos, operaciones administrativas, protección de las cuatro tarjetas y retorno al listado: correctas.
- Paquete local reconstruido en `.local/revision-historica`, servido por 8097 conservando la base privada existente.
- La regresión HTTP integral y 48 comprobaciones de pantallas corresponden a la revisión inmediatamente anterior; esta corrección añade pruebas específicas del contenido perdido.

No se detectó otra pérdida confirmada en las rutas y servicios revisados. Esto no certifica todas las variantes históricas ni paridad con el hosting. Quedan la aceptación visual y la demo comercial completa. No se modifican main, preprod, producción, estadísticas ni base productiva.
