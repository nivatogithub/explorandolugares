# Entrega integral de SEO — 1 de noviembre de 2026

Estado: candidato integrado en preprod, regresión inicial aprobada y revisión
móvil completada. Se incorporan dos correcciones semánticas detectadas en la
medición pública antes de repetir la regresión y aprobar el commit definitivo.
Main y producción permanecen en `d0e188a4` hasta completar la promoción.

## Alcance y límites

Rama `fix/seo-integral-20261101`, desde el main publicado `d0e188a4`.
Conservar identidad, URL cortas, renderer PHP único, idiomas, contenido e imágenes
aprobados, suscripciones y datos vivos. No cambiar qué lugares o negocios están
publicados. La investigación privada y documentos de comercios nunca son metadatos.
La promoción productiva requiere la revisión y aprobación del commit exacto.

## Plan verificable

1. Consolidar URL canónicas y enlaces públicos: eliminar enlaces a index.html
   y corregir accesos de cuenta localizados sin duplicar el flujo de autenticación.
2. Mejorar títulos, descripciones y encabezados de guías en ES/EN/PT-BR,
   utilizando únicamente información que ya existe en su contenido revisado.
   No cortar palabras ni acumular palabras clave. No ampliar fichas gratuitas.
3. Añadir marcado semántico seguro y coherente con lo visible: identidad del
   portal, páginas, lugares, migas y versiones de idioma. Excluir previews,
   borradores y campos privados. El marcado no garantiza resultados enriquecidos.
4. Completar la presentación SEO de páginas comerciales que ya sean públicas:
   idiomas realmente disponibles, medios y campos permitidos por el plan.
5. Comprobar sitemap, robots, redirecciones, medios, navegación y lectura móvil.
   Medir y documentar límites de rendimiento; no llamar Core Web Vitals a
   una simple comprobación de HTML o a mediciones de laboratorio.
6. Integrar controles de regresión y el manual
   [SEO y publicación de lugares](SEO_PUBLICACION_LUGARES.md) en la construcción,
   las instrucciones del repositorio y el procedimiento territorial.
7. Construir desde fuentes, probar el paquete completo en Dev1 y entregar
   resultados, diferencias y pendientes antes de solicitar promoción.

## Defectos observados que deben quedar cubiertos

- Enlaces internos a variantes index.html y cuatro rutas de acceso/registro
  localizadas inexistentes.
- Títulos territoriales que no describen la finalidad de la guía.
- Descripciones demasiado genéricas o cortadas a mitad de palabra.
- Relación de idiomas y metadatos sociales incompletos en páginas comerciales.
- Ausencia de JSON-LD en las guías examinadas.
- Falta de una comprobación integral de SEO sobre las respuestas PHP finales.

## Fuera de una reparación automática

- Reincorporar lugares que actualmente responden 404 requiere comprobar
  intención editorial, datos, medios y aprobación. No crear guías vacías,
  restaurar HTML antiguo ni redirigir contenido inexistente al inicio.
- No enviar solicitudes de indexación, validar correcciones en Search Console
  ni contactar a terceros sin incluir expresamente esa acción en la autorización.
- No comprar enlaces, crear reseñas, prometer posiciones o declarar éxito por
  el total de exclusiones históricas. Medir las URL canónicas actuales.

## Criterio de entrega

Documentar por separado: código implementado, pruebas efectivamente ejecutadas,
revisión visual, rendimiento medido, decisiones pendientes y aprobación humana.
Un control sin ejecutar no equivale a aprobado. Los resultados de Search Console
se conservan como evidencia privada fuera del repositorio, no como promesa de tráfico.

## Resultado de implementación y revisión

Se conserva un solo renderer PHP de lugares, sin nuevas páginas estáticas ni
correctores editoriales en navegador. Se corrigieron enlaces internos canónicos,
los accesos de cuentas EN/PT-BR, títulos con intención y ubicación, encabezados
contextuales, metadatos sociales y alternates de negocios. Los idiomas inexistentes
de un negocio redirigen a una versión real: no se indexa una copia española bajo
otra URL de idioma. La compatibilidad de lectura del repositorio se conserva.

Hay JSON-LD inicial de WebSite/Organization en inicio, TouristDestination/WebPage
y migas en lugares, y WebPage/Organization en negocios públicos. No se inventan
direcciones, reseñas, horarios ni requisitos de resultados enriquecidos; no se
emite ese marcado para previews privados. No se promete su aparición en Google.

27 descripciones revisadas, coherentes entre catálogo vigente, semilla aplicable
y snapshots territoriales activos. Migración 059 privada, transaccional y con
comparación exacta de bytes: conserva cambios posteriores incluso bajo la
collation de MySQL que ignora mayúsculas. No cambia publicaciones ni IDs.

### Pruebas efectivamente ejecutadas

- `scripts/verificar-antes-de-desplegar.py`: pasada final completa aprobada.
  Sintaxis de 335 PHP y 134 Python, diseño, oferta, cobertura, pruebas PHP,
  113 pruebas Python, registro de cuatro planes, borradores, pagos, cupones,
  ZIP/reimportación/editorial/publicación, QR, privacidad, conteos y panel.
- 69 respuestas PHP SEO: 60 guías, tres inicios, tres páginas informativas
  de comercio y tres páginas comerciales sintéticas. Metadatos únicos,
  canónicas, reciprocidad real, JSON válido y ausencia de enlaces index.html.
- 72 respuestas PHP de presentación: un solo shell/menú, recursos presentes
  y enlaces de idioma absolutos. No se promovieron imágenes nuevas.
- 25/25 comprobaciones de reparación, concurrencia y recuperación; pruebas
  MariaDB de registro, promociones, reloj y descripciones aplicadas dos veces.
- Dev1 HTTP: 60 URL de su sitemap, todas 200, canónica correcta, sin enlaces
  internos index.html y con X-Robots-Tag noindex del ambiente.
- Revisión visual de Jericó en escritorio y navegación ES/EN/PT-BR correctas.

### Datos y límites de la revisión

Dev1 conserva su base anterior: ocho lugares publicados y 222 negocios antes
y después. No es una réplica de producción. La cobertura de los 20 lugares
actuales se probó mediante el catálogo versionado y una fixture desechable;
no se importó ninguna base productiva. Migración 059 confirmada en Dev1.
La rama pendiente del descuento se conserva sin incorporar su cambio no aprobado.
Dev2 sigue reservado a administración y permanece intacto.

Se respaldaron los archivos anteriores y la base de Dev1 antes de sustituir
su paquete. Los HTML antiguos que conservaba ese ambiente se retiraron de su
raíz servida; son recuperables desde el respaldo local. No se borraron cuentas,
negocios, métricas ni configuración privada. No restaurar ciegamente una base.

El sondeo concurrente local de HTML de Dev1 tuvo un máximo de 0,203 segundos
por respuesta, sin descargar/renderizar todos los recursos. Esto NO es LCP,
INP, CLS, PageSpeed ni Core Web Vitals de producción.

### Cierre previo a producción

El candidato inicial `ac58991a` se integró en preprod desde main vigente. Allí
pasaron la regresión completa, los 28 controles de compatibilidad y el sondeo
público/administrativo. Cobertura permanece fuera de esta entrega. La revisión
visual a 390 píxeles CSS efectivos confirmó ausencia de desbordamiento horizontal,
imágenes visibles y apertura/cierre del menú de Jericó. No se confunde el tamaño
configurado del navegador con el ancho CSS medido.

PageSpeed Insights examinó la producción anterior, no el candidato, en móvil:
rendimiento 84, accesibilidad 95, buenas prácticas 100 y SEO 92. Medición de
laboratorio con Lighthouse 13.6.0, Moto G Power y red 4G lenta: FCP 1,2 s,
LCP 4,2 s, bloqueo total 0 ms y CLS 0. Sin datos de usuarios reales disponibles.
[Informe verificable](https://pagespeed.web.dev/analysis/https-explorandolugares-com-lugares-jerico/282fbp3q65?form_factor=mobile).

Dos defectos semánticos reales quedan corregidos en la fuente compartida:
las envolturas de fotos no navegables son `span`, no enlaces sin destino; y
los títulos de experiencias son `h3` bajo su sección `h2`, conservando diseño,
identidad, enlaces reales e imágenes. La normalización no duplica clases al
repetirse. Las pruebas PHP y el control del HTML final cubren estas condiciones.

LCP de 4,2 s no es óptimo ni cumple el umbral bueno de 2,5 s. El informe señala
optimización de imágenes, recursos bloqueantes y CSS no utilizado; estos son
límites medidos, no errores declarados resueltos. No se recodificaron imágenes
aprobadas ni se introdujeron cambios visuales o una limpieza CSS global dentro
de esta promoción. No se promete una puntuación posterior sin volver a medir.

Se comprobó el manifiesto remoto contra el local y el estado previo de la
migración 059: pendiente, con 27 textos antiguos exactos. Se obtuvo respaldo
privado de las 89 tablas y se ensayó su restauración en una base aislada, incluidas
las ocho tablas de estadísticas. No se sustituyó ninguna base activa.

### Dictamen de revisión de código

Intención: mejorar descubrimiento y representación del contenido público sin
ampliar suscripciones, alterar publicaciones ni exponer datos privados.

- Críticos abiertos en el alcance revisado: ninguno identificado.
- Hallazgos corregidos: aislamiento de fixtures, prueba que aún esperaba la
  canónica antigua, preservación del fallback de negocios mediante redirección
  y comparación binaria de textos en la reparación MySQL.
- Patrones conservados: consultas parametrizadas, marcado con lista permitida
  y escape JSON, aprobación/publicación previas al SEO comercial y actualización
  idempotente sin sustituir la base viva.
- Dictamen: candidato apto para revisión de desarrollo; NO equivale a aprobación
  productiva ni a certificación de ausencia absoluta de errores o de rendimiento.

## Evaluación del efecto en búsquedas

Línea base observada en Search Console, 12–30 de octubre: alrededor de 1.070
impresiones, 11 clics, CTR 1 % y posición media 37,3. Es una muestra pequeña.
Comparar consultas, páginas, país y dispositivo con posiciones similares; no
atribuir causalidad a una fluctuación del CTR global ni a un cambio de mix.
Priorizar oportunidades que ya tienen impresiones cerca de primera página.
Un objetivo experimental de 1 % a 2 % no es un resultado garantizado.

Las exclusiones históricas de redirecciones, canónicas alternativas y páginas
retiradas no deben desaparecer por la fuerza. La inspección actual distinguió
una versión EN de Riohacha técnicamente disponible pero aún no indexada por
Google y tres versiones de una página comercial aún desconocidas para Google.
No se solicitaron indexaciones ni validaciones en Search Console.

Siguiente promoción: completar las verificaciones pendientes, revisión humana,
integración en preprod con main vigente, commit y paquete exactos, aprobación,
respaldo/migración 059, despliegue incremental y validación antes de sincronizar main.
