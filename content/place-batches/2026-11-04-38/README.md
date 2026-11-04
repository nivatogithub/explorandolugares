# Lote de 38 municipios — fuente canónica

Rama: `contenido/lote-38-reproducible-20261104`. Este lote conserva 38 municipios, 77 atractivos y 112 fichas comerciales documentadas. No está aprobado para preprod ni producción.

## Reconstrucción

Desde cualquier copia del repositorio, con Python 3:

```
python3 content/place-batches/2026-11-04-38/build.py --output /tmp/lote38
```

El constructor usa exclusivamente los manifiestos y WebP versionados. Produce ZIP individuales, negocios, inventario con SHA-256 e intención editorial. No depende de la base de dev1 ni de rutas personales. Los ZIP son resultados, no fuentes.

- `places/*/manifest.json`: contrato v2, textos ES/EN/PT-BR, motivos de visita, fotos y coordenadas disponibles. Importación en borrador.
- `places/*/images`: 115 WebP aprobados (38 portadas y 77 atractivos). No incluye las imágenes de los tres descartados.
- `places/*/editorial-evidence.json`: procedencia y trazabilidad previa; rutas históricas no son dependencias del constructor. Algunos estados antiguos de aprobación son históricos; no confundirlos con la decisión final del usuario.
- `geolocation.json`: 73 puntos identificados y cuatro excepciones de precisión. Coordenada de sitio no equivale a entrada. Solo se utiliza una ficha Maps nombrada comprobada como enlace público; enlaces numéricos del expediente son investigación interna.
- `retired.json`: tres identidades excluidas por instrucción del propietario. Conservar esta evidencia; el constructor bloquea su reintroducción. No contiene archivos gráficos de los retirados.
- `businesses`: 112 fichas documentadas. No significa operación confirmada, negocio reclamado, aliado ni geolocalización corroborada. No inventar canales que faltan.
- `business-ledger.json`: 119 investigados, incluidos siete pendientes que no entran a los paquetes.
- `evidence`: recibos editoriales limitados a este lote, sin credenciales, cuentas o métricas. Los IDs numéricos son de dev1; en otros ambientes reconciliar por identidad, nunca reemplazar la base.

## Estado aplicado y pendiente

Dev1 conserva 38 municipios. El 4 de noviembre se archivaron `los-patios/qc-33-4`, `el-zulia/qc-34-2` y `la-paz/qc-37-4`; nueve páginas anónimas ES/EN/PT-BR comprobaron su ausencia. No se borraron relaciones ni identidades. `retire-dev1.php` es una operación acotada, repetible, protegida para el entorno local. No es un script de producción.

Las nuevas coordenadas, motivos de visita y los nuevos paquetes comerciales están preparados; NO se afirma que estén aplicados a dev1. `evidence/retirements-verification.json` refleja lo realmente persistido. No promover los cuatro puntos dudosos como ubicaciones exactas: San Antonio (Girardota), Atuncela (Dagua), Majuy (Cota), Tasajera (Puebloviejo).

El contrato v2 y los 115 WebP fueron validados con `PlacePackage` del checkout del portal `contenido/lote-30-reproducible-20261104`, commit `76c59d01`. La base de esta rama no contiene todavía ese importador; no se ha copiado código de otro worker. Puede repetirse pasando a `validate.php` la ruta de `backend` que contenga el contrato vigente. La reconstrucción no necesita ese runtime.

La promoción incremental de los 38 a otro ambiente aún requiere integración con el importador/servicios vigentes y ensayo idempotente preservando IDs. Generar ZIP válidos NO demuestra promoción completa. No reimportar creando duplicados y no copiar una base de datos entera.

## Copias antiguas

`trabajo/expansion-cobertura` y `.local/environments/dev1/editorial-38` fueron áreas de preparación/operación. Son históricas; no se usan para reconstruir. Las evidencias de retiradas se conservan para que no se vuelvan a publicar, no como contenido activo. Ninguna limpieza de histórico debe borrar pruebas o medios sin respaldo remoto verificado.
