# Lote de 38 municipios — fuente canónica

Rama: `contenido/lote-38-reproducible-20261104`. Este lote conserva 38 municipios, 75 atractivos y 112 fichas comerciales documentadas. No está aprobado para preprod ni producción.

## Reconstrucción

Desde cualquier copia del repositorio, con Python 3:

```
python3 content/place-batches/2026-11-04-38/build.py --output /tmp/lote38
```

El constructor usa exclusivamente los manifiestos y WebP versionados. Produce ZIP individuales, negocios, inventario con SHA-256 e intención editorial. No depende de la base de dev1 ni de rutas personales. Los ZIP son resultados, no fuentes.

- `places/*/manifest.json`: contrato v2, textos ES/EN/PT-BR, motivos de visita, fotos y coordenadas disponibles. Importación en borrador.
- `places/*/images`: 113 WebP aprobados (38 portadas y 75 atractivos). No incluye las imágenes de los cinco descartados.
- `places/*/editorial-evidence.json`: procedencia y trazabilidad previa; rutas históricas no son dependencias del constructor. Algunos estados antiguos de aprobación son históricos; no confundirlos con la decisión final del usuario.
- `geolocation.json`: 74 puntos identificados y una excepción de precisión. Coordenada de sitio no equivale a entrada. Solo se utiliza una ficha Maps nombrada comprobada como enlace público; enlaces numéricos del expediente son investigación interna.
- `retired.json`: cinco identidades excluidas por instrucción del propietario. Conservar esta evidencia; el constructor bloquea su reintroducción. No contiene archivos gráficos de los retirados.
- `businesses`: 112 fichas documentadas. No significa operación confirmada, negocio reclamado, aliado ni geolocalización corroborada. No inventar canales que faltan.
- `business-ledger.json`: 119 investigados, incluidos siete pendientes que no entran a los paquetes.
- `evidence`: recibos editoriales limitados a este lote, sin credenciales, cuentas o métricas. Los IDs numéricos son de dev1; en otros ambientes reconciliar por identidad, nunca reemplazar la base.

## Estado aplicado y pendiente

Dev1 conserva 38 municipios. El 4 de noviembre se archivaron `los-patios/qc-33-4`, `el-zulia/qc-34-2` y `la-paz/qc-37-4`; nueve páginas anónimas ES/EN/PT-BR comprobaron su ausencia. No se borraron relaciones ni identidades. `retire-dev1.php` es una operación acotada, repetible, protegida para el entorno local. No es un script de producción.

Las nuevas coordenadas, motivos de visita y los nuevos paquetes comerciales están preparados; NO se afirma que estén aplicados a dev1. `evidence/retirements-verification.json` refleja lo realmente persistido. No promover los cuatro puntos dudosos como ubicaciones exactas: San Antonio (Girardota), Atuncela (Dagua), Majuy (Cota), Tasajera (Puebloviejo).

El contrato v2 y los 113 WebP fueron validados con `PlacePackage` del checkout del portal `contenido/lote-30-reproducible-20261104`, commit `76c59d01`. La base de esta rama no contiene todavía ese importador; no se ha copiado código de otro worker. Puede repetirse pasando a `validate.php` la ruta de `backend` que contenga el contrato vigente. La reconstrucción no necesita ese runtime.

La promoción incremental de los 38 a otro ambiente aún requiere integración con el importador/servicios vigentes y ensayo idempotente preservando IDs. Generar ZIP válidos NO demuestra promoción completa. No reimportar creando duplicados y no copiar una base de datos entera.

## Copias antiguas

`trabajo/expansion-cobertura` y `.local/environments/dev1/editorial-38` fueron áreas de preparación/operación. Son históricas; no se usan para reconstruir. Las evidencias de retiradas se conservan para que no se vuelvan a publicar, no como contenido activo. Ninguna limpieza de histórico debe borrar pruebas o medios sin respaldo remoto verificado.

## Ajuste posterior del propietario — 4 de noviembre

También se retiraron QC-16-2 (Atuncela) y QC-30-4 (Tasajera). Los cinco están archivados en dev1; 15 rutas verificadas. San Antonio QC-06-3 quedó actualizado en dev1 a 6.353044,-75.4259681 con el enlace Maps compartido por el propietario. Majuy es la única excepción pendiente: la cima de la ruta aportada muestra antenas y no confirma la roca de la imagen. Estos datos posteriores prevalecen sobre los recibos históricos anteriores.

## Majuy aprobado — 4 de noviembre

QC-17-7 usa ahora la foto georreferenciada de Wikiloc y su nueva ilustración aprobada por el propietario. Se sustituyó el WebP del paquete y se actualizaron los tres idiomas y las coordenadas 4.8048233,-74.1195667. Hay 75 puntos identificados; este es un punto fotográfico, no un acceso. No tiene ficha nombrada de Google Maps verificada; el enlace público queda vacío. Este ajuste está preparado en Git, aún no aplicado a dev1.

## Actualización dev1 — 4 de noviembre, cobertura ampliada

Los 38 centros de localidad se contrastaron con Wikidata o GeoNames y se incorporaron en los manifiestos y dev1. Son referencias del casco urbano, no accesos. Las coordenadas de los 75 atractivos, los mapas disponibles y los motivos de visita en ES/EN/PT-BR se aplicaron en dev1. La corrección aprobada de Majuy está aplicada. Las 114 páginas respondieron HTTP 200 y muestran motivos de visita; ver recibos en `evidence/`.

Dev1 contiene 1.340 fichas gratuitas incorporadas por este lote, con fuentes públicas de RNT, directorios y cartografía. `PUBLICO` no significa operación confirmada, negocio reclamado ni validación individual en campo. En especial los candidatos cartográficos requieren contrastar vigencia y posibles duplicados. Nueve municipios tienen menos de diez fichas y falta ampliar categorías. Los paquetes de negocios individuales previos no contienen todavía esta ampliación: las fuentes reconstruibles y recibos están en `businesses-dev1-*.json` y `evidence/`. Este estado no autoriza preprod ni producción ni certifica la publicación final. No contar el número global como cobertura completa por municipio.
