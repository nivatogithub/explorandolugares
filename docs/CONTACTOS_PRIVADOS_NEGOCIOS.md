# Contactos privados asociados a negocios

Preparado el 15 de octubre de 2026. Comprobado localmente; requiere desplegar esta versión e instalar la migración 026 antes de utilizarlo en el hosting.

El paquete `business-import-v1` crea fichas gratuitas con nombre, categoría y fuentes. No contiene contactos ni importa el CRM. Los canales comerciales encontrados durante la investigación necesitan una carga complementaria; excluirlos del directorio público no implica descartar su almacenamiento privado.

## Un solo registro de negocio

El CRM no tiene una lista independiente de establecimientos ni requiere volver a importar los negocios. Parte directamente de `businesses` y muestra una sola fila por su mismo `id`/`public_id`. Todos los negocios gratuitos aparecen automáticamente, incluidos los que todavía no tienen ningún canal. Los que contratan una suscripción se pueden consultar con el filtro de clientes pagos; conservan su identidad e historial.

`BusinessCrmRepository` usa los contactos como información relacionada, no como punto de partida del listado. Varias observaciones de fuente de un mismo contacto no multiplican la fila del negocio. El filtro predeterminado muestra Gratis como oportunidades comerciales; hay filtros por destino, búsqueda y presencia o ausencia de contacto.

En la prueba con el inventario de Jericó se muestran 199 negocios únicos: 99 con contactos investigados y 100 pendientes de conseguir contacto. La base sigue teniendo 199 negocios después de cargar el complemento, no 298 ni 398. La pantalla muestra el ID original y enlaza al mismo formulario de edición.

## Flujo para negocios ya importados

1. Desplegar `app/BusinessResearchContactImporter.php`, `public/admin/contactos-negocios.php` y el enlace correspondiente en `public/admin/_navigation.php`. El panel de negocios incorpora accesos a esta carga y a la publicación masiva.
2. Instalar `026-business-research-contacts.mysql.sql`, registrada en `DatabaseMaintenance`. Es aditiva: crea una tabla y no altera la publicación ni las suscripciones existentes. Verificar los pendientes del hosting antes de aplicar migraciones; no incluir otros cambios de tareas simultáneas sin revisión.
3. Abrir **Contactos de negocios** (misma ruta `contactos-negocios.php`), cargar el JSON `business-research-contacts-v1` y pulsar **Vincular contactos privados**. Es un complemento de contactos, no una segunda importación de establecimientos.
4. El listado conserva todos los negocios; abrir **Ver contactos y fuentes** en el registro deseado. Los teléfonos incompletos se conservan como observaciones que requieren revisión. La ausencia de contacto no elimina ni oculta un prospecto.

La carga nunca crea negocios. Busca un único registro dentro del destino por nombre normalizado, sin tildes, mayúsculas ni puntuación. Si falta una ficha o hay coincidencia ambigua, rechaza todo el paquete. No utiliza coincidencias aproximadas.

Una observación se identifica por negocio y un SHA-256 de canal, valor, procedencia, fecha y evidencia. Repetir el archivo no duplica observaciones ni sobrescribe decisiones posteriores de exclusión. Distintas fuentes de un mismo contacto se conservan por separado.

## Privacidad y uso

`business_research_contacts` es una tabla privada, distinta de los canales que puede publicar una ficha. No participa en `BusinessRepository` ni `DestinationRepository`. Las consultas y cargas solo se exponen en el panel administrativo, con sesión, CSRF y respuestas sin caché. No hay endpoint público de estos datos.

La carga conserva el estado de autorización y de revisión de base de uso. No admite acreditar consentimiento mediante el paquete, ni habilita campañas, mensajes, cuentas, suscripciones o publicación. Tampoco altera la privacidad de canales ya guardados en `business_contacts`.

## Publicación masiva existente

La ruta es **Negocios → Revisar y publicar**, `admin/negocios.php?tab=revisar`. Permite seleccionar la página visible o todos los resultados filtrados y pulsar **Aprobar y publicar seleccionados**. La importación y la publicación son pasos distintos; solo la acción explícita del administrador publica los registros aptos. Los casos omitidos se informan individualmente.

## Comprobaciones

- `backend/tests/business-research-contacts.php`: vinculación por destino, coincidencias ambiguas, repetición sin duplicados, preservación de exclusiones, rechazo de fechas y fuentes inválidas, y ausencia de efectos sobre campañas y publicación.
- `backend/tests/business-crm.php`: una fila por negocio existente, presencia de registros sin contacto, filtro Gratis/pago, búsqueda, paginación, ausencia de escrituras y funcionamiento del listado antes de instalar la tabla de contactos.
- `entregables/jerico-contactos-complementarios-2026-10-15/verificar_paquete.php`, fuera del repositorio: carga real de 285 observaciones para 99 de los 199 negocios de Jericó, conserva todos los valores y su procedencia, reimporta sin insertar y deja intactas las fichas.
- Prueba HTTP local con información ficticia: acceso administrativo, CSRF, carga de archivo, búsqueda, escape de HTML, ausencia de caché y repetición de carga.

El JSON con contactos reales permanece fuera del repositorio y de la raíz web. No incorporarlo a plantillas públicas, ZIP del sitio ni control de versiones.

## Uso de la pantalla

La sección del menú se llama **Contactos de negocios**. **Consultar contactos** muestra los negocios existentes, con y sin canales, y permite buscar información ya guardada. El botón **Ver contactos** abre los canales y sus fuentes para el negocio seleccionado. La búsqueda no investiga nuevas fuentes ni envía mensajes.

En **Cargar archivo de contactos**, selecciona el JSON complementario y pulsa **Guardar contactos del archivo**. Esta carga vincula la investigación con fichas existentes; no vuelve a importar negocios ni publica sus canales. El selector muestra su etiqueta y el archivo elegido en español, independientemente del idioma del navegador.

## Terminología vigente: canales y personas

La pantalla se denomina **Canales y contactos**. La investigación existente representa **canales del negocio**, y la carga masiva conserva la asociación por nombre y destino. El formato `business-channels-import-v1` usa `channels`; el formato anterior sigue siendo compatible.

La gestión de **personas de contacto**, sus cargos y sus canales privados se incorpora de manera aditiva con la migración 028. Los negocios públicos de Jericó no se convierten en personas. Ver `MODELO_NEGOCIOS_PERSONAS_CANALES_CUENTAS.md` para el esquema, el onboarding, la compatibilidad y las limitaciones de la versión preparada.
