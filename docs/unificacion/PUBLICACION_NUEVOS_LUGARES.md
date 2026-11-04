# Preparación de publicación de lugares sobre la arquitectura actual

Estado observado el 3 de noviembre de 2026. Este documento organiza la preparación; no autoriza desplegar ni declara que el contenido pendiente esté listo.

## Base común

1. Terminar, revisar y versionar los cambios del refactor. Integrar la base vigente de producción en la rama y resolver por funcionalidad; después integrar el resultado en preproducción siguiendo el circuito del proyecto. No copiar cambios parciales sobre producción.
2. Preparar la migración del contenido histórico 060 coordinada con el renderer que consulta MariaDB. Ejecutar preflight y pruebas repetidas en una copia aislada, conservar los identificadores, relaciones, imágenes y datos posteriores. El hosting documentado no dispone de shell: aún se necesita un mecanismo privado revisado para ejecutar el plan exacto; no exponer el CLI como endpoint público ni escribir en producción durante la preparación.
3. Construir y probar el paquete completo: rutas canónicas en tres idiomas, SEO, directorio, formularios, permisos, acceso privado, imágenes y conservación de datos. El adaptador de hosting debe cubrir las rutas a la aplicación privada, incluidas las anidadas.

## Contenido de nuevos lugares

- Inventariar cada lugar por identificador, fuente vigente y estado editorial. Una rama Git no demuestra que un lugar esté listo ni es una página separada; tampoco la ausencia de una rama significa ausencia de contenido.
- Mantener cada lugar en su línea de trabajo propia, conforme a las reglas del proyecto. Integrar sobre la base común verificada y resolver cada caso por contenido, no con reemplazos globales. Una rama de integración puede reunir los cambios ya revisados para un lote sin perder trazabilidad.
- Adaptar contenido aprobado e imágenes al contrato de importación vigente. `PlacePackage` exige portada y atractivos, metadatos y textos ES/EN/PT-BR y mantiene los atractivos en borrador; no importar versiones de traducción marcadas como anteriores ni inventar datos ausentes. El ZIP se valida antes de guardar.
- Comprobar identidad, geolocalización, enlaces reales de mapas, licencia y procedencia, calidad visual, contenido y traducciones, vistas móvil/escritorio, filtros, canonical/hreflang/sitemap. La aprobación visual de cada imagen nueva es independiente del refactor.
- Ensayar primero uno o pocos lugares de punta a punta en un ambiente y datos aislados; después agrupar los aprobados en lotes. Evitar tanto 90 despliegues independientes como asumir que 90 expedientes diferentes ya están completos.

## Evidencia y límites observados

En el repositorio local se observaron 18 ramas `lugar/` locales y 15 referencias remotas guardadas, correspondientes a 20 nombres distintos. No se actualizó el remoto, por lo que esta cifra no representa necesariamente todos los trabajos en curso. Hay además expedientes editoriales fuera de ramas individuales, incluidos los lotes de patrimonio y Antioquia; sus propios estados conservan revisiones, traducciones o aprobaciones pendientes. No se hizo un inventario completo de los 90 lugares solicitados.

En dev1 se verificaron las 60 rutas actuales de 20 lugares y se probaron 100 lugares sintéticos en el directorio, con paginación y búsqueda ES/EN/PT-BR tanto en SQLite como con tablas temporales de MariaDB. No se ejecutó una prueba de carga ni de usuarios concurrentes, y el código de esta rama no está integrado en preproducción ni desplegado.

Antes de producción: preparar el commit y paquete exactos, comprobar respaldo y migraciones en el entorno de integración, obtener la aprobación explícita aplicable y hacer la validación productiva y paridad. No usar bases preproductivas para sustituir cuentas, negocios o estadísticas reales.

## Regla de transición de contenidos aprobados a lugares reales

La aprobación de textos o imágenes no significa que exista un lugar en la plataforma ni que haya un paquete listo. Para cada expediente, registrar por separado: contenido aprobado, imágenes aprobadas y sus huellas, paquete generado y validado, lugar creado como borrador, prueba en dev1 e integración en preproducción. No inferir etapas completadas a partir de una anterior.

Después de integrar la arquitectura verificada, cada responsable debe partir de esa base y preparar el paquete compatible vigente con los datos aprobados, sus traducciones y procedencia. Mantener la rama o expediente individual y sus identificadores; no modificar plantillas compartidas por lugar ni sustituir datos existentes mediante importaciones antiguas. Si faltan traducciones, coordenadas, licencias, aprobación visual o un enlace de mapa verificado, registrar el pendiente y no inventar valores.

Crear primero el lugar como borrador en el ambiente reservado; comprobar su contenido, imágenes, enlaces, idiomas, directorio y presentación. Solo después de superar esas pruebas y de integrar la base común vigente podrá incorporarse el lote correspondiente a preproducción por el circuito del proyecto. La integración a preproducción no equivale a autorización de producción. Conservar en cada paso la versión del paquete y su huella para saber exactamente qué se revisó.

Dev1 es compartido y requiere reserva mediante el controlador de ambientes. Coordinar cada prueba con su titular; no reemplazar el código ni los datos de otro trabajo y no usar preproducción como un tercer ambiente de desarrollo.
