# Auditoría editorial y neutralidad · 19/10/2026

Aplicadas las guías vigentes del coordinador (`../preprod/crecimiento-colombia/GUIA_GENERACION_DESTINOS.md` y `docs/POLITICA_EXPERIENCIAS_DE_OPERADORES.md`). **Entrega revisada: siete atractivos y una experiencia; nueve imágenes.** No hay mínimo de lugares o actividades.

## Correcciones

- Se retira Jesús Nazareno: su inclusión anterior como octavo tenía menor certeza y buscaba completar la cuota. No se reemplaza ese cupo. Se conservan investigación y maestro, fuera del ZIP vigente.
- Se retira Paseo por calles y patios: era caminar autónomamente, no una oferta organizada; además solapaba el recorrido patrimonial.
- Se retira Conocer la filigrana: se comprobó el oficio, pero una parada en talleres dentro de un tour no acredita una clase autónoma. No se deducen cursos de la existencia de joyeros. La antigua excursión de Comfama de 2022 tampoco prueba una oferta contratada hoy para esta ficha específica.
- Se retira Sabores de tamarindo: comprar/probar productos no acredita por sí solo servicio turístico organizado. No se conservará como experiencia para aprovechar una imagen existente.
- Se transforma Recorrido de memoria regional en **Tour urbano patrimonial**, con guía y oferta pública propia contrastada. Se conserva su slug técnico y su imagen pertinente; los textos ES/EN/PT son genéricos, sin oferente, marca, producto comercial, precio, horario, inclusiones o variantes de paquete.

## Evidencia de oferta interna

El sitio propio de **Providencia Travel** identifica el oferente y publica una página concreta de tour a Santa Fe de Antioquia con recorrido guiado por el centro. Se consultaron el 19/10/2026:

- https://providenciatravel.com/
- https://providenciatravel.com/producto/actividades/tour-santa-fe-de-antioquia/

La evidencia se conserva en `criterios-editoriales.json`, fuera del manifiesto público. Acredita una oferta documental, **no** disponibilidad para una fecha, vigencia registral, alianza, recomendación o contratación de publicidad. La página mezcla condiciones de embarcación incongruentes con un tour terrestre: no se copian esas condiciones ni ninguna inclusión concreta a la tarjeta. La ficha solo informa el tipo de actividad respaldado: recorrido urbano con guía. Sin contacto ni reserva realizada.

También se consultó el catálogo de tours de santafedeantioquia.com.co: describe recorridos patrimoniales y gastronómicos, pero no se usó para convertir la ficha gastronómica anterior en una oferta exacta con oferente inequívoco. No se prolongó la búsqueda para llenar cupos ni se crearon experiencias nuevas por analogía.

## Neutralidad pública

Nombre, descripción y alt de la experiencia no contienen oferente ni promoción. `external_url` y `external_label` quedan vacíos en **todos** los componentes del manifiesto. Los mapas normales del portal se resuelven por el sistema de destino; no se introduce una URL comercial como sustituto. Las fuentes públicas generales del destino siguen siendo institucionales; las pruebas comerciales solo están en el sidecar privado. No se activa ningún anuncio, suscripción, asociación o módulo de operador. El directorio gratuito de 30 registros conserva su alcance y sus límites de vigencia.

## Validación e integración

ZIP v2 regenerado con solo las nueve imágenes referenciadas; maestros y activos retirados se conservan para trazabilidad. Se ejecutaron el lector `DestinationPackage::readZip`, validación real de campos `DestinationAdminService` sin guardar ni usar base de datos, `BusinessPackage::validateManifest`, verificador de archivos/traducciones y el validador editorial compartido. Los controles no imponen ocho atractivos ni una cuota de experiencias. Hash actual en `VALIDACION_ZIP.json`.

**No se tocó preprod/DB/producción.** El QA y recibo local anteriores corresponden al paquete previo y no validan esta revisión. El coordinador debe reimportar y retirar/archivar los cuatro slugs excluidos para que un importador conservador no deje fichas antiguas publicadas. La aprobación humana de Santa Fe sigue pendiente.
