# Lote de 16 lugares — estado de dev1

Rama fuente: `contenido/preparacion-16-departamentos-20261104`.

Los 16 lugares están publicados exclusivamente en dev1. La fuente es Git: investigación, imágenes aprobadas, textos de los tres idiomas, negocios, excepciones e importadores están versionados. Los paquetes de transporte son derivados de commits, no la única copia del contenido.

## Validación

- 48 rutas públicas comprobadas (ES/EN/PT-BR), todas con HTTP 200 e imágenes de contenido accesibles.
- 59 imágenes aprobadas en 118 WebP; incluye las imágenes conservadas de atractivos todavía en borrador.
- 40 atractivos publicados de 42 documentados. Coco Viejo y Cerro de Urania quedan en borrador por falta de coordenadas del sitio.
- 29 experiencias publicadas. Las excursiones móviles no usan el punto de un hotel como salida inventada.
- 448 negocios cargados: 404 publicados como fichas públicas gratuitas y no reclamadas; 44 en borrador por discrepancia territorial detectada por la aplicación.
- Los 47 negocios sin coordenadas tienen la excepción nominal autorizada por el propietario y están incluidos en los publicados. No se amplió esa excepción a los 44 puntos discrepantes.
- El contenido anterior ajeno al lote se conservó. El total de lugares publicados en dev1 pasó de 88 a 104.
- Producción, main y preprod no se modificaron.

| Lugar | Atractivos visibles | Experiencias | Negocios publicados / cargados |
|---|---:|---:|---:|
| Arauca | 2 | 1 | 13 / 16 |
| Bahía Solano | 3 | 2 | 22 / 23 |
| Capurganá | 3 | 2 | 28 / 28 |
| Florencia | 1 | 1 | 24 / 24 |
| Inírida | 3 | 3 | 40 / 40 |
| Ipiales | 1 | 1 | 43 / 43 |
| La Macarena | 3 | 3 | 17 / 17 |
| Leticia | 2 | 2 | 15 / 30 |
| Mitú | 2 | 2 | 30 / 30 |
| Mocoa | 2 | 1 | 21 / 21 |
| Nuquí | 3 | 3 | 21 / 30 |
| Popayán | 3 | 2 | 30 / 30 |
| Providencia y Santa Catalina | 3 | 2 | 8 / 24 |
| Puerto Carreño | 2 | 1 | 17 / 17 |
| Salento | 3 | 1 | 41 / 41 |
| San José del Guaviare | 4 | 2 | 34 / 34 |

## Reproducción y evidencias

Consultar `DEV1_RELEASE.md`. `scripts/build-release-input.py` construye `release-input.json` desde las fuentes editoriales y valida los hashes aprobados. `scripts/import-dev1.php` utiliza la administración vigente del ambiente. `scripts/import-businesses-dev1.php` usa los servicios comerciales existentes, sin convertir los negocios en aliados ni verificados.

`dev1-place-receipt.json`, `dev1-business-receipt.json` y `dev1-http-report.json` registran el resultado real. `dev1-policy-receipt.json` conserva la aplicación limitada de las 47 excepciones y `dev1-business-exception-ids.json` las relaciona con las identidades de ese ambiente. `dev1-media-alias.conf` es un derivado local para los 16 IDs, no reemplaza la configuración ni los medios anteriores.

## Pendientes de revisión

- Revisar visualmente dev1 antes de cualquier promoción a preprod.
- Resolver los 44 puntos territoriales discrepantes; el recibo lista negocio y causa. No afirmar que el negocio está en otro municipio solo por este rechazo: la precisión de la cartografía también debe contrastarse.
- Completar Coco Viejo y Cerro de Urania sin inventar coordenadas.
- Completar enlaces públicos nombrados de Maps donde no existen; no sustituirlos por búsquedas genéricas. Los enlaces pendientes no se añadieron a los atractivos.
- Las fuentes de negocios no certifican operación actual ni representación legal.
- Ojo de Dios conserva aviso de cierre temporal, sin invitación a entrar.

Los visores HTML de revisión son entregables regenerables; los archivos fuente aprobados y sus manifiestos se mantienen en `places/`.
