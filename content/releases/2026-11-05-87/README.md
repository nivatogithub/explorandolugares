# Candidato de preproducción del 5 de noviembre

Base del refactor: 6ef59083. El alcance autorizado se fija en scope.json por slug y commit de los cuatro lotes (38, 30, 16 y 3). public-content.json conserva el estado público operativo contrastado en dev1. Incluye únicamente las guías del alcance, sus puntos publicados, las fichas públicas de directorio sin reclamar y sus fuentes públicas. Los identificadores internos de origen sirven para resolver relaciones. El importador conserva las identidades del ambiente de destino.

El manifiesto vincula el hash del contenido, cada WebP y su archivo de origen en Git. En el paquete se usan rutas por hash para evitar colisiones entre IDs locales. El manifiesto de medios del portal incorpora esos archivos al constructor del refactor. El filtro usa el código y el catálogo de términos versionados; el SQL de content/place-labels completa las etiquetas faltantes sin reactivar etiquetas deshabilitadas editorialmente.

## Verificación efectuada en 8088

- Dos pasadas sobre una copia local de la base de preprod. La verificación de cada pasada encontró 87 guías, 274 fichas de atractivos y experiencias, 2.699 negocios públicos y 18 Pueblos Patrimonio.
- Una aplicación en preprod con los mismos resultados. El directorio completo quedó en 107 lugares publicados.
- Reconstrucción con preprod/actualizar.sh sobre el refactor. Prueba HTTP de las 261 rutas canónicas, sus vínculos con imágenes, la integridad de los 322 archivos y la presencia de las 261 rutas en el sitemap.
- Comprobaciones de La Fe, cascada/cascadas, charco/charcos y combinaciones de patrimonio y departamento.

## Repetir la prueba local

En el preprod reservado, usar el paquete reconstruido desde esta rama y la base local con sus datos persistentes. Colocar apply-preprod.php, verify-preprod.php, scope.json, manifest.json, public-content.json y el SQL heritage-colombia-20261104.sql en un mismo directorio privado de operación. El importador verifica el hash del contenido y los medios antes de escribir. El modo rehearse presupone una copia privada de la base llamada el_rehearse_nov87. El modo apply apunta exclusivamente a la base local de preprod. Verificar también desde el host con python3 content/releases/2026-11-05-87/verify-http.py.

## Paso a producción

Requiere la aprobación del candidato, el respaldo del hosting y la conciliación contra sus identidades vigentes. El contenido de base y el código son dos partes del despliegue. Subir únicamente los archivos del sitio no publica las 87 guías. El ejecutor de este directorio está restringido a preprod. Preparar y revisar el traslado incremental específico del hosting antes de ejecutar la publicación, comprobar cuentas/estadísticas/suscripciones protegidas y validar páginas, medios, filtros y sitemap en el dominio. Sincronizar main después de validar producción.
