# Paquete ZIP para importar negocios públicos

Este paquete sirve exclusivamente para incorporar en lote negocios identificados mediante información pública. No es el paquete de alta de una cuenta comercial ni el paquete para editar el contenido de un negocio que ya eligió una suscripción.

La importación masiva de negocios acepta un archivo ZIP con `paquete-negocios.json` en la raíz y, opcionalmente, imágenes de referencia dentro de `images/`. La plantilla descargable es `content/plantilla-paquete-negocios.zip`; su manifiesto conserva el formato `business-import-v1` del JSON anterior.

## Contenido del manifiesto

- `destination_slug`: slug de un destino que ya existe en el portal. Un paquete corresponde a un solo destino.
- `businesses`: entre 1 y 500 registros con categoría, estado de información, nombre, textos en español y al menos una fuente comprobable.
- `image_references`: campo opcional por negocio. Cada entrada usa `file` para señalar un archivo del ZIP y, opcionalmente, `name` para darle un nombre reconocible en administración.

Ejemplo de imagen opcional:

```json
"image_references": [
  {"file": "images/cafe-fachada.jpg", "name": "Fachada enviada como referencia"}
]
```

El ZIP puede incluir JPG, PNG o WebP; máximo 64 MB en total, 5 MB por imagen y 100 imágenes por lote. Las imágenes se guardan en el almacenamiento privado para revisión. No se convierten automáticamente en logo, fotografía pública ni medio de una suscripción. No incluyas imágenes que no tengas autorización para usar.

## Qué hace la importación

La administración valida todo el paquete antes de guardarlo. Si un registro falla, no se importa ninguno. Los negocios reciben una suscripción `free` en estado `pending_publication`, quedan en `draft`, con `claim_status=NO_RECLAMADO`, sin cuenta asociada y sin publicación. El nombre propio se copia a ES, EN y PT-BR; no se inventan traducciones de marcas o nombres. La respuesta de importación ofrece abrir una vista previa privada del destino que incluye esos borradores y mantiene las tarjetas Gratis ocultas al público. El estado de información solo admite `PUBLICO` o `VERIFICADO`; para usar este último se debe haber comprobado la fuente y conservar evidencia.

El paquete no crea cuentas, no inicia reclamaciones, no asigna suscripciones pagas ni precios, no publica fichas y no modifica negocios existentes. Sus referencias visuales tampoco se validan contra un plan: son material privado de investigación, no contenido público prometido. Después de revisar el listado en la vista previa privada, la administración puede abrir cada negocio y autorizar su publicación Gratis desde Suscripciones. El registro debe seguir siendo público, no reclamado y respaldado por una fuente. Los planes pagos requieren la cuenta autorizada, el pago y la información adicional que corresponda.

## Instrucción para generar un paquete con una IA

Comparte este documento y la plantilla ZIP. Pide un `paquete-negocios.json` con el formato `business-import-v1`, un destino existente y datos respaldados por fuentes públicas. Las imágenes deben ser referencias autorizadas y sus rutas deben coincidir exactamente con `image_references[].file`. No inventes horarios, contactos, precios, dirección ni servicios; si un dato no se puede confirmar, déjalo vacío o exclúyelo. No marques como `VERIFICADO` un dato que solo provenga de una suposición. Entrega el ZIP para revisión; importar no implica publicar.

## Paquete administrativo para editar un negocio existente

El propietario del portal prepara un ZIP de actualización para un negocio ya existente. El comercio no sube ni publica directamente ese ZIP. Su función es transportar una propuesta de contenido lista para revisión; no crear negocios ni aceptar materiales sin supervisión. Este flujo es distinto de la importación masiva de negocios públicos descrita arriba.

El paquete administrativo debe:

- identificar un negocio existente mediante su identificador público estable, no crear otro registro ni permitir que el ZIP cambie su identidad;
- leer el plan y estado de suscripción actuales desde la base de datos; un plan escrito en el ZIP solo podrá servir como comprobación de coincidencia, nunca para asignar o conceder una suscripción;
- incluir los textos revisados y traducidos a todos los idiomas del portal, respetando los límites y campos permitidos por el plan;
- separar la evidencia y referencias visuales privadas de las imágenes finales adaptadas al estilo del portal; las referencias nunca se publican;
- conservar procedencia, autoría/licencia y permiso de uso de cada imagen final. Una imagen de origen sin derechos o permiso claros no se transforma ni se publica; se reemplaza por una imagen autorizada o creada para el portal;
- validar cantidad, rol y dimensiones de imágenes finales contra el plan vigente (por ejemplo, portada y galería); no aceptar material por encima de sus capacidades;
- guardar el resultado como una propuesta/revisión pendiente, sin sobrescribir textos o imágenes públicos; solo una aprobación administrativa explícita podrá reemplazar la versión publicada.

La IA puede ayudar a producir borradores de texto, traducciones e ilustraciones, pero la persona administradora revisa que las traducciones sean correctas, que las imágenes representen fielmente el negocio y que exista autorización para publicarlas. Si no se puede demostrar el derecho de uso de una fotografía, no se incluye como imagen final. Si un plan no admite imágenes, el paquete no puede traer medios finales para publicación. Los límites se obtienen de la suscripción y configuración vigentes, no de reglas independientes dentro de la plantilla.
