# Contactos privados asociados a negocios

Preparado el 15 de octubre de 2026. Comprobado localmente; requiere desplegar esta versión e instalar la migración 026 antes de utilizarlo en el hosting.

El paquete `business-import-v1` crea fichas gratuitas con nombre, categoría y fuentes. No contiene contactos ni importa el CRM. Los canales comerciales encontrados durante la investigación necesitan una carga complementaria; excluirlos del directorio público no implica descartar su almacenamiento privado.

## Flujo para negocios ya importados

1. Desplegar `app/BusinessResearchContactImporter.php`, `public/admin/contactos-negocios.php` y el enlace correspondiente en `public/admin/_navigation.php`. El panel de negocios incorpora accesos a esta carga y a la publicación masiva.
2. Instalar `026-business-research-contacts.mysql.sql`, registrada en `DatabaseMaintenance`. Es aditiva: crea una tabla y no altera la publicación ni las suscripciones existentes. Verificar los pendientes del hosting antes de aplicar migraciones; no incluir otros cambios de tareas simultáneas sin revisión.
3. Abrir **Contactos privados**, cargar el JSON `business-research-contacts-v1` y pulsar **Vincular contactos privados**.
4. Revisar los contactos asociados por negocio, con fuente, fecha, periodo de la fuente y estado de evidencia. Los teléfonos incompletos se conservan como observaciones que requieren revisión.

La carga nunca crea negocios. Busca un único registro dentro del destino por nombre normalizado, sin tildes, mayúsculas ni puntuación. Si falta una ficha o hay coincidencia ambigua, rechaza todo el paquete. No utiliza coincidencias aproximadas.

Una observación se identifica por negocio y un SHA-256 de canal, valor, procedencia, fecha y evidencia. Repetir el archivo no duplica observaciones ni sobrescribe decisiones posteriores de exclusión. Distintas fuentes de un mismo contacto se conservan por separado.

## Privacidad y uso

`business_research_contacts` es una tabla privada, distinta de los canales que puede publicar una ficha. No participa en `BusinessRepository` ni `DestinationRepository`. Las consultas y cargas solo se exponen en el panel administrativo, con sesión, CSRF y respuestas sin caché. No hay endpoint público de estos datos.

La carga conserva el estado de autorización y de revisión de base de uso. No admite acreditar consentimiento mediante el paquete, ni habilita campañas, mensajes, cuentas, suscripciones o publicación. Tampoco altera la privacidad de canales ya guardados en `business_contacts`.

## Publicación masiva existente

La ruta es **Negocios → Revisar y publicar**, `admin/negocios.php?tab=revisar`. Permite seleccionar la página visible o todos los resultados filtrados y pulsar **Aprobar y publicar seleccionados**. La importación y la publicación son pasos distintos; solo la acción explícita del administrador publica los registros aptos. Los casos omitidos se informan individualmente.

## Comprobaciones

- `backend/tests/business-research-contacts.php`: vinculación por destino, coincidencias ambiguas, repetición sin duplicados, preservación de exclusiones, rechazo de fechas y fuentes inválidas, y ausencia de efectos sobre campañas y publicación.
- `entregables/jerico-contactos-complementarios-2026-10-15/verificar_paquete.php`, fuera del repositorio: carga real de 285 observaciones para 99 de los 199 negocios de Jericó, conserva todos los valores y su procedencia, reimporta sin insertar y deja intactas las fichas.
- Prueba HTTP local con información ficticia: acceso administrativo, CSRF, carga de archivo, búsqueda, escape de HTML, ausencia de caché y repetición de carga.

El JSON con contactos reales permanece fuera del repositorio y de la raíz web. No incorporarlo a plantillas públicas, ZIP del sitio ni control de versiones.
