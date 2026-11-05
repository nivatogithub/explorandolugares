# Publicación de los 16 lugares en dev1

La fuente de verdad es la rama `contenido/preparacion-16-departamentos-20261104`. Ningún contenido depende exclusivamente de un ZIP local ni de datos editados a mano en dev1.

## Fuentes versionadas

- `places/*/research.json`: identidades, investigación, coordenadas y 448 negocios documentales; no son negocios reclamados ni aliados.
- `places/*/images/`: WebP aprobados y manifiestos con hashes.
- `places/*/references/`: procedencia y referencias conservadas.
- `editorial/localized-copy.json`: texto editorial EN/PT-BR y contexto territorial ES/EN/PT-BR. No se inventaron fechas fundacionales para completar historia.
- `business-location-exceptions.json`: decisión del propietario para 47 negocios sin coordenadas. No se extiende a atractivos.
- `release-input.json`: entrada reproducible para los servicios de la aplicación.
- `scripts/build-release-input.py`: recompone la entrada desde estas fuentes y verifica aprobaciones y hashes.
- `scripts/import-dev1.php`: importa mediante el formulario/servicio vigente; crea primero borradores y publica después. Conserva el código del ambiente y comprueba la integridad del contenido ajeno al lote.

## Ejecución

Reservar dev1 con el controlador compartido. Reconstruir la entrada, comprobar el diff y guardar las fuentes en Git. Exportar desde el commit exacto con `git archive`, copiar al contenedor reservado y ejecutar el importador con el hash completo del commit. Los recibos se recuperan al repositorio. No reconstruir ni sustituir el portal completo para esta carga de contenido.

Alcance: 16 destinos, 42 atractivos documentados (40 con coordenadas), 29 experiencias, 59 imágenes y 448 negocios. Coco Viejo y Cerro de Urania conservan sus imágenes y textos pero no se publican como atractivos mientras falte su punto; sus destinos sí se publican. Las experiencias móviles no reciben el punto de un hotel como supuesto lugar de encuentro.

Los enlaces de mapas ausentes se dejan vacíos; no se inventan enlaces cortos ni búsquedas genéricas. Ojo de Dios conserva el aviso de cierre temporal. Las fuentes y aprobaciones de este lote no autorizan producción ni preprod. El despliegue se valida en las tres rutas idiomáticas y se registra con su commit de contenido.

## Resultado comprobado

Publicado en dev1: 16 lugares, 40 atractivos, 29 experiencias y 404 negocios gratuitos. Conservados como borrador: 2 atractivos sin coordenadas y 44 negocios cuyos puntos fueron rechazados por el control territorial. Consultar los recibos para identidades exactas. La excepción de 47 negocios sin coordenadas no elimina ese control.

Preparar negocios con `scripts/import-businesses-dev1.php prepare`. Resolver las 47 excepciones nominales a los IDs locales mediante `scripts/apply-dev1-business-exceptions.py`; se conserva el resto de la política. Publicar con el mismo importador en modo `publish`. Para servir medios nuevos en dev1, generar la configuración limitada con `scripts/build-dev1-media-alias.py` a partir del recibo y habilitarla en Apache de ese ambiente. No copiar configuraciones con IDs de dev1 a otro entorno: regenerarlas desde sus propios recibos.
