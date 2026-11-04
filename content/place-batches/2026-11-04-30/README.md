# Lote de 30 lugares: origen canónico

Esta carpeta versiona el contenido del lote patrimonio/Antioquia y sus imágenes WebP aprobadas. La rama de consolidación es `contenido/lote-30-reproducible-20261104`. No está aprobado para preproducción ni producción.

## Fuentes y reconstrucción

- `places/<slug>/manifest.draft.json`: contrato de importación, textos ES/EN/PT-BR, coordenadas y referencias de medios. La importación comienza en borrador; el estado público se aplica mediante los servicios de publicación, nunca cambiando esa regla del importador.
- `places/<slug>/images/`: originales WebP aprobados necesarios para generar cada paquete, sin enlaces a archivos externos al repositorio.
- `media-evidence.json`, `source-content.json` y `geolocation-evidence.json`: procedencia y contexto. Las rutas históricas de revisión son trazabilidad, no dependencias de construcción.
- `verified-geography.json`: coordenadas documentadas; distingue referencia de área/sitio de acceso exacto.
- `businesses/<slug>.json`: las 96 fichas iniciales y sus fuentes. `verified-geolocation.json` contiene las ubicaciones corroboradas. PUBLICO no significa negocio verificado, reclamado ni aliado.
- `businesses/expanded-candidates.json`: investigación separada, NO se importa ni se publica en los paquetes. Contiene atribución y licencia de sus fuentes.
- `evidence/`: recibos locales de dev1, no una base de datos ni una autorización de despliegue. Sus IDs son de prueba y no deben reutilizarse en otro ambiente.

Desde la raíz del repositorio:

```sh
python3 content/place-batches/2026-11-04-30/build.py --output .local/place-batch-30
```

El generador produce 30 ZIP compatibles con el contrato existente, un archivo de negocios, intención de publicación y huellas SHA-256. No consulta red, no abre dev1 y no escribe fuera del directorio de salida indicado. Dos ejecuciones con las mismas fuentes producen exactamente los mismos archivos.

## Promoción y pendientes

El ZIP de lugar NO incluye negocios: `businesses.json` y `publication-intent.json` son acompañantes obligatorios. El adaptador de promoción todavía debe validarse con los servicios actuales, preservando IDs/public_id y sin sobreescribir fichas reclamadas ni planes comerciales. No afirmar que subir esta carpeta equivale a desplegar o a completar esa validación.

Antes de promover: cerrar las ubicaciones pendientes, revisar Rionegro y el mapa de cobertura, comprobar paridad del contenido y los medios contra dev1, validar importación/reintento en el ambiente autorizado y obtener aprobación del propietario. Las 900 fichas siguen siendo una meta; candidatos no equivalen a negocios publicados.

No editar las antiguas copias de `trabajo/place-packages-30` como segunda fuente. Esta carpeta es la fuente editorial desde su consolidación. Los recibos se actualizan solo al ejecutar y comprobar una nueva operación.
