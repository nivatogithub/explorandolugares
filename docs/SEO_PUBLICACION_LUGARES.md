# Manual obligatorio de SEO para publicar lugares

Vigente desde el 1 de noviembre de 2026. Aplicable a lugares nuevos, cambios de
contenido, importaciones y entregas de cualquier worker o herramienta de IA.
Leer junto con AGENTS.md, REGLAS_PROYECTO.md y la guía territorial.

## Objetivo y arquitectura

Cada lugar publicado debe tener una guía útil y accesible en español, inglés
y portugués de Brasil. El motor PHP compartido renderiza los datos aprobados:
no generar páginas estáticas por municipio, copias por palabra clave ni contenido
oculto solo para buscadores. La URL y los ID estables se conservan.

Rutas: `/lugares/<slug>/`, `/en/lugares/<slug>/` y
`/pt-br/lugares/<slug>/`. Inicio, listado de lugares, guía y negocio son
entidades diferentes. Una ficha gratuita no obtiene página propia por aplicar SEO.

## Antes de redactar

- Definir qué pregunta real resuelve la guía: qué conocer, experiencias
  documentadas y economía local disponible. No prometer reservas ni servicios
  que el portal o sus participantes no ofrecen.
- Usar fuentes verificables y conservar su fecha y alcance en el expediente
  editorial. No deducir hechos de una ilustración ni inventar horarios, precios,
  distancias, acceso libre, seguridad, reseñas, popularidad o disponibilidad.
- Contrastar nombres y ubicación. Diferenciar municipio, localidad, atractivo,
  experiencia operada y negocio. Indicar excursiones fuera del lugar.
- Conservar las reglas editoriales y de imágenes: SEO no sustituye la aprobación
  de hechos, traducciones, fidelidad visual, licencias y medios exactos.

## Textos y metadatos en los tres idiomas

- Un título descriptivo y específico, que identifique el lugar y la utilidad
  de la página. Ejemplo de intención: “Qué hacer en Jericó: lugares y experiencias”.
  Adaptar el idioma y lo realmente publicado; no repetir mecánicamente términos.
- Una descripción única, comprensible y completa. Debe identificar el lugar
  y resumir su contenido real. No dejar palabras cortadas ni listas de keywords.
  La longitud es una guía editorial, no un requisito numérico de Google.
- Un H1 claro y una jerarquía coherente de secciones. Mantener el diseño y
  el tono de marca sin que oculten el propósito de la página.
- Textos útiles para elegir y organizar una visita. No aumentar extensión por
  alcanzar una cuota, introducir bloques duplicados ni hacer párrafos invisibles.
- Verificar legibilidad y traducción del contenido, no solo del menú.
- Las fichas Gratis conservan solo lo permitido. Los contactos, descripciones,
  imágenes, productos y páginas propias siguen las reglas de su suscripción.

## Contrato técnico

- La URL canónica responde 200 directamente, sin login ni redirección.
- Una sola canónica absoluta HTTPS del dominio propio, sin parámetros privados.
  Debe coincidir con sitemap, enlaces internos y URL social.
- Idiomas con hreflang absoluto y recíproco, incluido el propio idioma.
  x-default apunta a la versión de referencia. No anunciar traducciones inexistentes.
- Enlaces internos a la ruta final: sin destinos antiguos, www alternativo ni
  index.html. Mantener parámetros funcionales y fragmentos que sí correspondan.
- Redirecciones permanentes de variantes antiguas al equivalente real, sin
  bucles ni desvío de páginas inexistentes al inicio. Conservarlas durante la
  migración; que Google las excluya no exige eliminarlas.
- Sitemap dinámico con contenido público elegible. Sin borradores, panel,
  pagos, login, demos, previews, filtros ni URLs que redirigen o devuelven error.
  No inventar lastmod ni actualizarlo cada vez que se genera el sitemap.
- Robots permite rastrear páginas y recursos públicos. No utilizar robots.txt
  para resolver duplicados. noindex protege formularios/vistas privadas pertinentes.
- Datos estructurados válidos y correspondientes a la información visible.
  No exponer investigación, documentos, correos privados, borradores ni campos
  comerciales que el visitante no pueda ver. No inventar estrellas ni reseñas.
- Open Graph y Twitter coherentes con título, descripción, URL e imagen revisada.
  No confundir etiquetas sociales con señales que aseguren posiciones en Google.

## Imágenes, accesibilidad y rendimiento

- Medios aprobados, rutas reproducibles, tamaños y hashes correctos.
- Alternativos fieles, descriptivos y traducidos; sin acumulación de keywords.
  Logo decorativo puede usar alt vacío si la marca ya tiene nombre accesible.
- Declarar dimensiones, priorizar la portada visible y diferir imágenes inferiores.
  No cargar todas las portadas del catálogo en tamaño original.
- Conservar HTML útil sin necesitar JavaScript para descubrir el contenido.
  Filtros y cargar más son interacción, no la única fuente de texto para Google.
- Revisar móvil, teclado, navegación, imágenes, desbordamientos y estado sin JS.
- Medir rendimiento en condiciones repetibles. Diferenciar pruebas de laboratorio
  de Core Web Vitals de usuarios reales; no inventar una medición que no existe.

## Comprobaciones y evidencia de cierre

El responsable de la entrega, no el propietario, reúne y revisa:

1. Fuentes, revisión editorial, traducciones y aprobación de imágenes.
2. Resultado de validadores de contenido, SEO, construcción y regresión.
3. Respuestas finales ES/EN/PT-BR del paquete PHP con canónica, idiomas,
   directivas, recursos y marcado. No basta revisar una plantilla sin renderizar.
4. Recorrido visual de escritorio/móvil y enlaces al dominio correcto.
5. Comparación del paquete con el publicado: no pérdida de páginas elegibles,
   imágenes, cuentas ni medición. Bajas editoriales requieren decisión explícita.
6. Revisión humana del candidato exacto y circuito de preprod/producción vigente.
7. Tras publicar, validación de HTTP y sitemap productivos. En Search Console,
   comprobar las canónicas que interesan, no únicamente el acumulado histórico.

No marcar “listo” si una comprobación falla o está pendiente. Documentar el bloqueo,
la causa y el responsable de resolverlo. No afirmar que una prueba visual o un
HTTP200 demuestra indexación, resultados enriquecidos o posicionamiento.

## Seguimiento sin repetir reparaciones básicas

### Controles ejecutables obligatorios

Desde la raíz del checkout vigente:

```sh
python3 scripts/verificar-antes-de-desplegar.py
```

La batería construye desde fuentes y comprueba las respuestas PHP de todos los
lugares públicos en los tres idiomas, inicio y acceso informativo de comercios,
además de páginas comerciales sintéticas. Verifica canónica, metadatos sociales,
H1, títulos únicos, idiomas recíprocos, enlaces sin alias y JSON-LD válido.
Incluye publicación/suscripción, datos privados, texto Unicode y la reparación
de descripciones aplicada dos veces en SQLite y MariaDB desechables.
Si falla cualquier control, no promover el paquete. No deshabilitar pruebas
para obtener un resultado verde.

Para inspeccionar un candidato construido en una carpeta nueva bajo `.local/`:

```sh
python3 scripts/preparar-hosting.py --output .local/candidato-seo
python3 scripts/preparar-fixture-presentacion.py --package .local/candidato-seo
python3 scripts/probar-seo-paquete.py --package .local/candidato-seo --output .local/resultado-seo.json
python3 scripts/probar-presentacion-paquete.py --package .local/candidato-seo --output .local/resultado-presentacion.json
```

La fixture contiene datos y cuentas sintéticos: nunca desplegar su carpeta
`private/`, configuración ni base de pruebas. No reutilizar una salida existente
ni apuntar estas comprobaciones a una base de producción. Para revisión visual,
usar únicamente Dev1 o Dev2 reservado mediante el procedimiento del proyecto.

### Reparación de descripciones históricas

La migración `059-public-seo-descriptions` es solo de metadatos: compara slug,
idioma y texto anterior antes de actualizar. No cambia publicaciones, IDs,
contenidos visibles, cuentas ni métricas. Una corrección editorial posterior
se conserva. Las nuevas publicaciones toman el texto revisado del catálogo;
no ejecutar una importación inicial encima de una base existente para actualizarla.
Promover esta migración por el mismo circuito, respaldo y aprobación exacta que
el código; no aplicarla al hosting por anticipado.
La herramienta `scripts/migrar-cuentas.py --public-seo` selecciona exclusivamente
059 y, sin `--apply`, solo inspecciona. Es una operación remota privada: usarla
únicamente dentro de la revisión/despliegue autorizado, con su configuración
correcta. `--apply` sigue exigiendo el commit aprobado de preprod.

Guardar una línea base por URL canónica y grupo de consultas, idioma y período:
estado de indexación, impresiones, clics y posición media. Distinguir tráfico
orgánico del conteo interno de visitas y considerar el retraso de cada informe.
La inspección individual de Google puede estar más actualizada que los ejemplos
del informe agregado. Una búsqueda site: no sustituye un inventario de indexación.

Si aparece una regresión técnica, corregir su causa y añadir una prueba que la
detecte. Si la página es técnicamente válida pero no mejora su visibilidad,
evaluar utilidad, competencia y descubrimiento sin inventar un fallo ni prometer
que agregar etiquetas garantiza primeros puestos. No existe una configuración
que asegure crecimiento indefinido sin mantenimiento.

## Referencias oficiales

- [Guía SEO de Google](https://developers.google.com/search/docs/fundamentals/seo-starter-guide)
- [Títulos](https://developers.google.com/search/docs/appearance/title-link)
- [Descripciones y fragmentos](https://developers.google.com/search/docs/appearance/snippet)
- [Canónicas](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls)
- [Idiomas](https://developers.google.com/search/docs/specialty/international/localized-versions)
- [Datos estructurados](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data)
- [Indexación](https://support.google.com/webmasters/answer/7440203?hl=es)
- [Inspección individual](https://support.google.com/webmasters/answer/9012289?hl=es)
