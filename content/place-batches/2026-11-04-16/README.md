# Revisión privada: 16 nuevos destinos

Abrir `review.html`. Elegir destino y pestaña Imágenes, Contenido y ubicación o Negocios. Aprobar o comentar cada versión y exportar el JSON de revisión. Las decisiones se guardan en el navegador y dependen del hash de la imagen; exportarlas evita perderlas al cambiar de equipo.

## Estado real

- 16 destinos, 58 imágenes de portada/atractivos listas para revisión; 116 versiones WebP verificadas.
- 42 atractivos, 40 con coordenada documentada y tipo de punto.
- 29 experiencias documentadas; los recorridos sin punto fijo no tienen salida inventada.
- 434 fichas candidatas, 364 con coordenadas documentadas. No son negocios verificados, aliados ni anunciantes.
- Ningún lugar publicado, ningún entorno modificado, ninguna aprobación humana simulada.

## Inventario

| Lugar | Imágenes | Atractivos con coordenada | Experiencias | Negocios con coordenada / fichas |
|---|---:|---:|---:|---:|
| Arauca | 3 | 2/2 | 1 | 11/13 |
| Bahía Solano | 4 | 3/3 | 2 | 19/23 |
| Capurganá | 4 | 3/3 | 2 | 27/28 |
| Florencia | 2 | 1/1 | 1 | 24/24 |
| Inírida | 5 | 3/4 | 3 | 5/37 |
| Ipiales | 2 | 1/1 | 1 | 43/43 |
| La Macarena | 4 | 3/3 | 3 | 14/14 |
| Leticia | 3 | 2/2 | 2 | 30/30 |
| Mitú | 4 | 2/3 | 2 | 11/30 |
| Mocoa | 3 | 2/2 | 1 | 21/21 |
| Nuquí | 4 | 3/3 | 3 | 30/30 |
| Popayán | 4 | 3/3 | 2 | 30/30 |
| Providencia y Santa Catalina | 4 | 3/3 | 2 | 22/24 |
| Puerto Carreño | 3 | 2/2 | 1 | 2/12 |
| Salento | 4 | 3/3 | 1 | 41/41 |
| San José del Guaviare | 5 | 4/4 | 2 | 34/34 |

## Pendientes antes de construir/publicar

- Coco Viejo y cerro Urania: imágenes preparadas, coordenada específica sin cerrar; otras referencias de aproximación permanecen diferenciadas. No usar centroides ni comunidades cercanas como si fueran el atractivo.
- Completar las coordenadas restantes de negocios y contrastar identidad/funcionamiento actual. Los resultados OSM son evidencias cartográficas, no certificaciones comerciales.
- Enlaces públicos de Google Maps nombrados, traducciones editoriales completas EN/PT-BR, SEO y conversión a PlacePackages según arquitectura vigente.
- Revisión humana de cada versión exacta, condiciones de uso de referencias y créditos. La modificación con IA no concede derechos sobre la foto original.
- Ojo de Dios: aviso de cierre temporal por operador y discrepancia entre fuentes. No recomendar como visita disponible sin comprobar reapertura.
- Coco Viejo: ICANH documentó vandalismo en 2025. La ilustración no prueba el estado actual de los petroglifos.
- Cerro Azul y muelle de Leticia conservan fotografías licenciadas, no ilustraciones, para evitar alteraciones de pictogramas y detalles.
- El objetivo interno de 30 negocios por destino no se ha alcanzado en todos; no se rellenó con negocios inventados.

## Archivos canónicos

- `places/<slug>/research.json`: ficha integrada, contenido, coordenadas, negocios y experiencias.
- `places/<slug>/references/`: fotografía utilizada, origen y licencia/estado de derechos.
- `places/<slug>/images/`: WebP1200/640 y manifiestos con hashes.
- `places/<slug>/verified-research.json`: investigación complementaria trazable.
- `validation.json`: recuento exacto y pruebas de archivos.
- `scripts/build-review.py`: reconstruye la revisión sin servidor.
- `scripts/validate-batch.py`: comprueba archivos, hashes y cobertura real de coordenadas.
- `scripts/check-review.cjs`: comprueba 48 vistas y persistencia de aprobaciones/comentarios en simulación aislada del DOM. No equivale a inspección visual de navegador.

La herramienta de navegador no permite abrir `file://`; no se creó un servidor alternativo ni se ocupó dev1 para eludir esa restricción. La revisión se entrega como archivo HTML local.

## Reproducción

```sh
python3 content/place-batches/2026-11-04-16/scripts/validate-batch.py
python3 content/place-batches/2026-11-04-16/scripts/build-review.py
node content/place-batches/2026-11-04-16/scripts/check-review.cjs
```
