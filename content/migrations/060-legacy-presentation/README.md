# Evidencia histórica, no contenido ejecutable

Estos cinco JSON son los archivos retirados de `backend/app/presentation/destinations/` al preparar la migración 060 desde la base `c6b1ee2b`. Conservan su procedencia original para comparar y auditar la transferencia.

No se consultan al servir páginas, no entran en el paquete ejecutable y no deben usarse para editar o reimportar contenido publicado. El único consumidor autorizado del plan de migración es la herramienta privada, con preflight y registro de aplicación. Después de migrar, la base de datos contiene los valores publicados.

Procedimiento y límites: `docs/unificacion/CONTENIDO_BD_060.md`.
