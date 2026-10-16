# Negocios, personas de contacto, canales y cuentas

Estado: implementación local preparada el 15 de octubre de 2026. No desplegada por esta tarea. Migración aditiva 028, sin rellenar personas a partir de directorios públicos ni renombrar/eliminar tablas productivas.

## Conceptos

- **Negocio:** el establecimiento existente del directorio. El CRM consulta ese mismo registro e ID.
- **Persona de contacto:** una persona identificada y su relación con uno o más negocios. No se infiere de un teléfono empresarial, nombre comercial o registro público.
- **Canal:** un correo, teléfono, WhatsApp, red social o sitio web. Puede ser general del negocio o un medio para comunicarse con una persona. Los canales de una persona pueden ser personales o específicos para su relación con un negocio.
- **Cuenta:** acceso autenticado. La relación de una persona con un negocio en el CRM no crea credenciales ni otorga permisos. Las membresías y la revisión de representación conservan sus controles.

Un negocio gratuito puede no tener ninguna persona identificada y sí tener canales empresariales. No se exige completar personas para importar directorios.

## Esquema vigente y compatibilidad

`businesses` conserva los negocios. `business_contacts` es el nombre histórico del almacenamiento de canales de ficha; no es una entidad de personas. `business_research_contacts` conserva observaciones de canales con sus fuentes, fechas y restricciones de uso. Un canal observado en varias fuentes puede tener varias evidencias. Estas tablas no se renombran físicamente para preservar los datos y los flujos desplegados.

La migración 028 incorpora:

| Tabla | Función |
|---|---|
| `people` | Identidad de la persona, con ID permanente y nombre conocido |
| `business_person_contacts` | Relación persona–negocio, activa o inactiva, contacto principal y origen |
| `business_person_contact_roles` | Uno o varios cargos por relación: propietario, representante legal, administrador, empleado, representante autorizado u otro |
| `person_channels` | Canales privados de la persona, generales personales o limitados al negocio indicado |
| `account_person_links` | Asociación de la cuenta con la persona; independiente de los permisos de negocios |

Una persona puede vincularse a varios negocios usando su registro existente. Personas con el mismo nombre no se fusionan automáticamente. Los correos generales del negocio tampoco se usan para deducir identidades personales.

Los canales de personas nunca se incluyen en el renderizador público. Cada canal nuevo queda sin consentimiento de marketing ni autorización de campañas. Una autorización de onboarding no se convierte en permiso de marketing.

## Importación masiva por nombre

El contrato recomendado es `business-channels-import-v1`:

```json
{
  "schema_version": "business-channels-import-v1",
  "destination_slug": "jerico",
  "businesses": [
    {
      "name": "Nombre Del Negocio Existente",
      "channels": [
        {
          "channel": "email",
          "value": "info@example.invalid",
          "source_url": "https://example.invalid/fuente",
          "consulted_at": "2026-10-15",
          "evidence_status": "PENDIENTE_VERIFICACION",
          "legal_basis_status": "Pendiente de revisión"
        }
      ]
    }
  ]
}
```

El ejemplo es ficticio. El archivo no necesita IDs exportados. El importador usa nombre y destino para resolver internamente el ID existente. Ignora mayúsculas, tildes, espacios y puntuación. No adivina errores de escritura, nombres incompletos o coincidencias ambiguas; en esos casos rechaza el lote antes de escribir.

Los archivos anteriores `business-research-contacts-v1`, con la colección `contacts`, siguen siendo aceptados. Ambos formatos almacenan las mismas evidencias; alternarlos no duplica observaciones ni borra exclusiones o permisos gestionados posteriormente.

No crea fichas, personas, cuentas ni membresías; no publica datos ni envía mensajes.

## Pantalla de administración

**Canales y contactos → Consultar negocios** permite buscar por negocio, persona, correo o teléfono. El listado diferencia canales generales y personas. Los filtros de disponibilidad se refieren a canales generales del negocio; una persona puede existir aunque todavía no tenga un canal.

**Carga masiva de canales** recibe el archivo complementario. En el detalle de un negocio, **Personas de contacto** permite añadir una persona identificada, reutilizar otra ya registrada, asignar varios cargos, añadir correos/teléfonos y marcar un contacto como inactivo. El primer contacto activo se hace principal; al desactivarlo se elige otro activo si existe. Marcar a alguien como contacto inactivo no revoca por sí mismo sus permisos de cuenta: estos se administran en Cuentas.

La gestión básica de personas admite correo y teléfono; la investigación de canales empresariales mantiene los ocho tipos existentes. El selector inicial de personas muestra hasta 500 registros; una búsqueda paginada para registros personales mayores queda como mejora de interfaz, sin cambiar el modelo relacional.

## Registro de responsables

Con 028 instalada, el registro empresarial crea la persona a partir del nombre declarado en el formulario y la asocia a su cuenta. Hasta aprobar su solicitud no se crea la relación activa con el negocio ni se conceden permisos.

Al aprobar la solicitud mediante la revisión documental existente, se asocian la persona, su cargo y los medios que proporcionó para ese negocio. Esa escritura forma parte de la misma transacción que la aprobación: un fallo revierte la aprobación y las nuevas membresías.

Para responsables aprobados antes de la actualización existe una acción administrativa explícita: **Recuperar responsables aprobados antes de esta actualización**. Solo utiliza solicitudes aprobadas con una membresía activa, completa asociaciones faltantes y conserva contactos que ya se gestionaron o desactivaron. Leer el CRM no modifica datos.

Si 028 no está instalada, los flujos anteriores continúan funcionando y la gestión de personas se muestra pendiente de habilitar. No se hace una conversión automática de cuentas históricas o canales del directorio en personas.

## Validación y despliegue

Pruebas locales sobre SQLite y HTTP: importación antigua/nueva, coincidencia única por nombre y destino, idempotencia, preservación de evidencias y exclusiones, varias personas/cargos/negocios, canales por ámbito, cuenta independiente, ausencia de permisos implícitos, reconciliación histórica y reversión transaccional de aprobaciones.

La prueba de Jericó conserva 199 negocios, 285 registros de canal/fuente para 99 negocios y cero personas inventadas. El contenido público permanece igual.

La migración MySQL/MariaDB está preparada pero todavía no se aplicó ni se validó contra el servidor de producción en esta tarea. El despliegue debe incluir la migración 028 y sus dependencias, los servicios, el parcial de administración y los recursos de interfaz. No desplegar únicamente la pantalla.
