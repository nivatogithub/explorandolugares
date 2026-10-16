# Contratos v1

Los tres contratos usan JSON Schema 2020-12 sin dependencias externas. El validador incluido implementa solo las palabras clave utilizadas y falla ante otras. Para consumirlos externamente, usar un validador estándar 2020-12 y además las invariantes de `scripts/validar.py`.

`inventario`: universo, puntuaciones, departamentos, lugares y relaciones. `lugar`: workflow, nueve gates, aprobación y publicación. `entrega`: fuentes, hashes de artefactos, manifiestos fotográficos y dimensiones independientes de negocios.

`content_hash(manifest)` canoniza JSON UTF-8 con claves ordenadas y separadores compactos, omitiendo `state`, `approval` y `publication`; un cambio a contenido/evidencia invalida aprobación. Estados y transiciones deben persistirse con auditoría en el backend futuro; el validador verifica snapshots, no identidad real del revisor ni autenticidad de evidencias. Evidencias HTTPS requieren lectura humana. Documentos personales nunca entran al contrato público; usar referencia privada opaca y controlada en el sistema futuro.

No importar estos manifiestos al panel: falta adaptador al paquete v2 y soporte demostrado de padres/tipos. Campos nulos expresan no comprobado; no equivalen a falso ni a aprobado.
