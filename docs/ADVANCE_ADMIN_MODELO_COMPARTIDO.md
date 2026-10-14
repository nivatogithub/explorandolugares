# Contrato de datos compartido de Advance Admin

Actualizado: 14 de octubre de 2026. Rama `feature/advance-admin`.

El módulo de destinos es propietario del esquema territorial/editorial. Advance Admin lo consume mediante `BusinessAdmin`; no crea un catálogo territorial o empresarial paralelo y no modifica sus migraciones 003–006.

| Entidad | Fuente única | Uso de Advance Admin |
| --- | --- | --- |
| Destinos | `destinations`, `destination_translations` | Selección por ID; nombres españoles con fallback a slug; solo lectura |
| Categorías | `service_categories`, `service_category_translations` | Selección por código; no crea categorías ni usa el catálogo del formulario de leads para negocios |
| Negocios | `businesses` | Lista los mismos IDs/public_id. Alta en borrador o archivado; unicidad compartida destino/slug |
| Contenido empresarial | `business_translations` | Edición de español de negocios no publicados; conserva otros idiomas |
| Canales empresariales | `business_contacts` | Consulta privada; respeta `is_public`; no copia canales al CRM ni a campañas |
| Fuentes empresariales | `business_sources` | Consulta y adición con URL/nota/fecha; no borra fuentes anteriores ni repite la misma URL y nota |
| Imágenes y procedencia | `media_assets`, traducciones y relaciones `*_media` | Se conservan sin alteración; ninguna segunda tabla de imágenes |
| Lugares/experiencias | `points_of_interest` y traducciones | Se conservan sin alteración; ninguna segunda entidad de experiencias |
| Persona/prospecto CRM | `advance_contacts` | Notas, relación comercial, autorización/supresión de campañas y vínculo opcional a solicitud y destino |
| Relación negocio/CRM | `advance_business_crm` | Solo `business_id`, `contact_id` y versión de edición; no guarda nombre, categoría, estado o dirección del negocio |
| Comunicación | `advance_templates`, `advance_campaigns`, `advance_deliveries`, `advance_audit` | Datos propios de campañas y auditoría |

`advance_contacts` representa a la persona/prospecto y sus permisos comerciales; `business_contacts` representa un canal del establecimiento. Son conceptos distintos. Vincularlos no expone el correo de una persona ni autoriza campañas. El alcance actual admite un contacto CRM principal por negocio, y el mismo contacto puede vincularse a varios negocios.

El campo antiguo `advance_contacts.municipality` permanece únicamente para conservar la procedencia de solicitudes y contactos previos que aún no se han asociado a un ID. Al seleccionar `destination_id`, el nombre se obtiene del catálogo y la copia antigua queda vacía. El formulario nuevo no acepta crear nombres de destinos. No se resuelven municipios por coincidencias ambiguas de texto.

## Reglas de compatibilidad

- Conservar `public_id`, estados `information_status`, `claim_status` y `operation_status`, orden y fecha de creación al editar. El módulo no los convierte a sus propios nombres ni confirma reclamaciones o actividad.
- El negocio publicado es consultable y puede vincularse al CRM, pero no se modifica su contenido, estado ni publicación desde Advance Admin. La edición pública queda a cargo del futuro flujo editorial aprobado.
- Revisar la misma versión leída: el formulario lleva una huella del contenido empresarial, traducciones, canales, fuentes y relación CRM. Bajo una transacción se bloquea el registro padre en MySQL y se comprueba la huella antes de guardar.
- Otros escritores del catálogo deben bloquear también el padre `businesses` al modificar sus hijos. La huella detecta cambios previos al guardado; no sustituye este contrato transaccional para escritores concurrentes que ignoren el bloqueo del padre.
- Conservar medios, fuentes territoriales, historias, razones y lugares de interés. No sincronizar ni reinterpretar experiencias como negocios.
- El catálogo consumido es el esquema versionado de migración 003. `poi_type = experience` de la evolución del otro Worker no requiere una segunda tabla ni cambios de Advance Admin: el módulo no escribe lugares de interés.

## Migración desde la primera versión de Advance Admin

La migración histórica 007 de la primera entrega creó `advance_establishments`. Esa tabla se retira en 008 y deja de usarse en todo el código operativo. También se corrigió una cláusula de índice inválida en la versión MySQL de 007; no cambia tablas del módulo de destinos.

**No ejecutar directamente el SQL 008**. Con un respaldo y el backend detenido en staging, aplicar:

```sh
php backend/bin/advance-schema.php --integrar-catalogo
```

La herramienta usa configuración privada; no muestra credenciales y no acepta SQL libre. Antes de cualquier DDL confirma las tablas compartidas y comprueba que la tabla heredada esté vacía. Si ya tiene establecimientos, detiene la operación sin cambiar el esquema ni borrar datos. En ese caso hace falta reconciliar cada registro con el ID canónico correspondiente (o crear un borrador legítimamente nuevo), conservando su contenido, procedencia y vínculo CRM, antes de retirar la tabla. No hay deduplicación automática por nombre.

Para una instalación nueva: aplicar el esquema de leads, la migración 003 y posteriores del Worker de destinos, importar el catálogo con su herramienta existente, aplicar 007 y ejecutar la herramienta 008. El estado final no tiene `advance_establishments`. Una segunda ejecución de la herramienta confirma el estado final sin repetir columnas ni tablas. Si detecta DDL parcial se detiene; MySQL requiere revisar respaldo/esquema antes de continuar.

No se ejecutó esta migración sobre bases reales. La protección ante registros heredados y la repetición segura se probaron con SQLite. Sigue pendiente comprobar DDL y claves foráneas en MySQL/MariaDB de staging.

## Convención de idioma del modelo

Tablas, columnas, relaciones y valores internos nuevos se modelan en inglés. El panel traduce los estados para mostrarlos en español. Advance Admin usa:

- Relaciones CRM: `prospect`, `under_review`, `partner`, `archived`.
- Campañas: `draft`, `approved`.
- Entregas: `pending`, `sending`, `skipped`, `uncertain`, `accepted`.
- Variables para plantillas nuevas: `{{name}}`, `{{municipality}}`. Los alias anteriores siguen admitidos para no cambiar mensajes ya revisados.

Después de 008, aplicar `009-advance-english-values.mysql.sql` una vez en staging con respaldo y backend detenido. Convierte valores previos y defaults sin modificar HTML, destinatarios, ID de proveedor, versión ni autor/fecha de aprobación. Los estados `sending` y `uncertain` siguen bloqueados para reenvío. La variante SQLite convierte datos; el servicio escribe estados explícitos, sin depender de defaults antiguos. La migración 007 ya usa defaults ingleses en instalaciones nuevas.

Los estados heredados del catálogo compartido (`PUBLICO`, `VERIFICADO`, `NO_RECLAMADO`, etc.) y los de `leads` pertenecen a otros módulos. Este cambio los conserva: traducirlos requiere una migración coordinada de todos sus lectores y escritores. No introduce una segunda nomenclatura almacenada ni tablas paralelas para esos estados.
