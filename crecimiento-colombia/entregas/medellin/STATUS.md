# Lugar 3 · Medellín · entrega privada de investigación

Fecha de consulta: 16/10/2026. ID CO-05-medellin. Rama exclusiva `lugar/CO/antioquia/medellin`.

## Preparado y comprobado

- Lectura de AGENTS, contexto, gobernanza, WORKERS y decisión de cuatro workers; aplicado skill existente `.agents/skills/imagenes-destinos/SKILL.md` y procedimiento `crecimiento-colombia/procedimientos/convertir-fotos.md`. No se generó ilustración ni sustituyó fotografía por IA.
- Diez fuentes primarias leídas, con fecha, editor y hechos observados en `fuentes.json`. Redacción propia ES, EN y PT-BR, historia, cuatro atractivos, cuatro propuestas de visita respaldadas por operadores reales e información práctica en `contenido.json` y `experiencias.json`. Sin horarios/precios/eventos futuros inventados.
- Diez fichas gratuitas propuestas: tres alojamientos, tres restaurantes y cuatro instituciones culturales. Las instituciones son servicios culturales, no comercios afiliados. Los tres restaurantes de Elcielo comparten grupo/sede; no son tres alianzas independientes. Todas las fichas conservan fuente y fecha, sin contacto ni reclamación ni vigencia confirmada.
- Tres fotografías reales de Commons: panorámica desde Picacho (Luis90f, 2016, CC BY-SA 4.0), Orquideorama (Jorge Láscar, 2010, CC BY-SA 3.0) y El Poblado (CONOCER, 2009, dominio público). Se revisaron descripción, autor y licencia en páginas originales. Fuentes de texto no conceden licencias de sus imágenes. No se usaron fotos de Alcaldía. Las fotos históricas no prueban condiciones actuales.
- Originales descargados con permiso de red, hashes y derivados WebP reproducibles con Pillow 12.3.0: orientación EXIF, reducción sin ampliar ni recortar, calidad 82, método 6; GPS/EXIF omitidos en derivados. Crédito, licencia y declaración de cambios visibles en `preview.html` y `imagenes.json`.
- Inspección visual de los tres derivados realizada: corresponden a panorámica urbana, estructura del Orquideorama y edificios de El Poblado; sin distorsión por recorte. WebP bajo los presupuestos de 300 KB portada y 120 KB tarjetas.
- Previsualización HTML local privada en tres idiomas y paquete JSON editorial basados en contrato v2, conservando borrador. No se tocó producción, panel, cola, inventario general ni backend.

## Pendientes reales

- Fotografía específica autorizada para Museo de Antioquia, Parque Explora y MAMM e imagen de historia vinculada al relato. No asignar una panorámica como foto de un museo. Se conserva candidato histórico Plaza Botero fuera del paquete hasta revisar también los derechos de las obras representadas.
- El JSON `.borrador.json` no se declara paquete v2 importable final: los atractivos sin imagen no satisfacen aún la entrega visual completa. ZIP final e integración en la plantilla pública/panel pendientes; no se conectó al panel.
- Revisión de traducciones, profundidad editorial, representación de vida nocturna/agenda/comercios/servicios, accesibilidad y vigencia de los negocios. La muestra de diez registros no constituye censo de economía local.
- QA visual de la plantilla final en móvil/escritorio pendiente; el HTML local no prueba integración en el sitio.
- Aprobación humana de textos, imágenes y hash exacto: pendiente. Publicación: no autorizada ni realizada.
- PR remoto pendiente: `gh` no está instalado; no se buscó acceso secreto ni se hizo push. Commit local contiene exclusivamente esta entrega.

**Estado: investigación y borrador preparados, entrega completa del destino aún pendiente.** Lugar 3 mantiene Medellín y no recibe siguiente destino. Cobertura publicada/aprobada añadida: cero. Las duraciones de revisión/correcciones son desconocidas; no se registran ceros ficticios.

Reproducir: ejecutar `preparar.py` con el Python del runtime que incluye Pillow, después `validar.py`. `SHA256SUMS` cubre todo el directorio excepto sí mismo y se regenera después de cambios; no representa aprobación.
