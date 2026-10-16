# Exportación y actualización masiva por ID

Preparado el 15 de octubre de 2026, en `negocios-dinamicos-phm`. Esta función es distinta de la importación de negocios nuevos y no inserta registros en `businesses`.

## Recorrido del administrador

**Exportar / actualizar** en el panel abre `admin/actualizar-negocios.php`:

1. Elegir destino y, opcionalmente, categoría o fragmento del nombre. Descargar el JSON privado de hasta 500 negocios y 2 MB.
2. Conservar los IDs y la versión de cada registro. Complementar los campos deseados; se pueden quitar negocios que no se quieran actualizar.
3. Cargar el JSON editado y revisar una tabla con el valor actual y el propuesto. Preparar la vista previa no escribe en la base de datos.
4. Marcar la confirmación y aplicar el lote revisado. El servidor vuelve a comprobar la versión y los permisos antes de guardar todo en una transacción.

Si el negocio cambió después de exportarlo, la carga se detiene con un conflicto. Si falla cualquier registro, se revierte todo el lote. Si una nueva carga falla, se descarta la revisión anterior para evitar aplicar accidentalmente otro archivo. La revisión también expira tras veinte minutos y queda vinculada a la sesión y a un token de confirmación.

## Contrato JSON

El formato es `business-bulk-update-v1`, con `destination_slug` y una lista `businesses`. Cada negocio lleva:

- `id`: ID interno, conservado cuando viene en el archivo.
- `public_id`: UUID persistente; obligatorio y utilizado para localizar el registro existente dentro del destino. Un ID inexistente o ajeno al destino rechaza el lote. No se busca por nombre para actualizar.
- `version`: huella del estado exportado, necesaria para detectar modificaciones posteriores.
- `fields`: categoría, dirección, referencia, coordenadas, horarios, rango de precios, capacidad y enlaces de menú/reservas.
- `translations`: nombres y textos en `es`, `en` y `pt-BR`.
- `contacts`: canales de la ficha, con sus IDs y permisos actuales.
- `sources`: URL, fecha y nota de cada fuente, con sus IDs. Se conservan las horas de consulta existentes.
- `research_contacts`: contactos privados de investigación, sus IDs, procedencia, fecha, evidencia y estado de uso. Se incluye cuando está instalada la tabla 026.
- `_context`: URL del negocio, estados, fecha observada y suscripción, solo para referencia. No permite cambiarlos.

Todos los apartados de datos son opcionales en una actualización parcial; solo se necesitan el ID público y la versión además de los datos que se desea complementar. Si se incluye el ID interno, debe corresponder al ID público.

## Reglas que evitan pérdidas y duplicados

- Los campos omitidos, vacíos o nulos conservan el dato actual. Esta versión no permite borrar valores mediante una celda vacía ni eliminar fichas, contactos o fuentes por omitirlos del archivo.
- Los contactos, fuentes y observaciones existentes se actualizan por su propio ID. Un ID ajeno al negocio se rechaza. Para añadir un elemento, se incluye sin ID.
- Añadir un elemento ya existente con el mismo contenido no crea otro. Se rechaza un cambio que genere una nueva duplicidad. Las observaciones idénticas que ya existieran se conservan sin impedir editar otros campos.
- La categoría debe existir y estar activa. Un cambio de nombre no puede producir otro negocio con el mismo nombre normalizado en ese destino.
- Los IDs del negocio, su URL, destino, suscripción y estados de publicación, verificación y representación permanecen intactos.
- Los contactos nuevos quedan privados. No se puede usar este proceso para cambiar permisos de publicación, habilitar campañas, acreditar consentimiento ni levantar una exclusión.
- Un negocio ya publicado conserva ese estado. La vista previa lo advierte expresamente: al confirmar, los cambios de nombre, categoría y datos visibles según la suscripción se reflejarán en el portal. No se publica ningún borrador.

La dirección y el mapa de una ficha gratuita quedan ocultos aunque el administrador complete esos datos internamente. La tarjeta conserva nombre y categoría sin publicar el enriquecimiento privado. Los textos ampliados continúan sujetos a las funciones visibles de cada suscripción.

## Registro de cambios y estructura

La migración aditiva **027-business-bulk-update-audit** crea un historial privado por lote y negocio, con el actor administrativo, los valores anteriores, los posteriores y los campos modificados. La actualización y su historial se guardan en la misma transacción. Este historial no añade una operación de restauración automática; una restauración futura requiere revisión administrativa.

La función no requiere recrear negocios, reiniciar la base de datos ni volver a importar el inventario de Jericó. La migración 026 permite incorporar el CRM privado; la 027 permite aplicar actualizaciones con historial. El panel explica cuáles faltan.

## Validación y límites

- `backend/tests/business-bulk-edit.php`: exportación, actualización parcial, conservación de IDs, URL y publicación, contactos privados, procedencia, historial, archivos desactualizados, IDs incorrectos, categorías inválidas, duplicidad de nombres y reversión del lote ante un fallo tardío.
- Prueba HTTP local en `entregables/actualizacion-masiva-json-2026-10-15/probar_panel.py`, fuera del repositorio: autenticación, CSRF, descarga JSON privada, carga real de archivo, vista previa sin escritura, confirmación, prevención de repetición y vinculación al token de revisión.
- `scripts/tests/test_destinos_dinamicos_php.py`: verifica que completar dirección, resumen y contacto de un negocio Gratis no exponga esos valores en el directorio, conservando su estado de publicación.

Pruebas locales con SQLite; no se exportaron ni modificaron registros de producción para preparar esta función. El JSON real debe descargarse desde el panel una vez desplegado: los UUID se asignaron al importar y no se deben reconstruir a partir de los nombres del inventario anterior.

No subir las exportaciones con datos privados a la raíz web ni al control de versiones. El panel requiere autenticación administrativa y protege sus respuestas con ausencia de caché e indexación.
