# Bogotá · propuesta semirrealista v1

Alcance: preproducción privada, sin autorización de publicación. Rama propia de Bogotá.

- Portada vigente v2: Plaza de Bolívar a nivel peatonal, con Catedral y Capitolio. La portada anterior con Colpatria se conserva como versión rechazada por semejanza compositiva con Medellín.
- Ocho atractivos: Plaza de Bolívar, La Candelaria, Museo del Oro, Monserrate, Museo Botero, Usaquén, Jardín Botánico y Parque Simón Bolívar.
- Quince experiencias con ilustraciones separadas de las tarjetas de lugares.
- Jardín Botánico: se conserva byte por byte la ilustración previamente aprobada; no se extiende esa aprobación a otras imágenes.
- Tres experiencias con bloqueos documentales continúan ocultas: senderismo, aves y noche.
- Textos breves ES/EN/PT, historia en un párrafo de ancho completo, secciones abiertas y carga por filas.

`prompts.json` y `manifiesto.json` conservan descripción, procedencia, rol, hashes y medidas. `maestros/` conserva PNG fuera del alojamiento; solo WebP optimizados van a assets. `generaciones.json` registra los archivos originales entregados por el generador.

Las ilustraciones son composiciones editoriales representativas: no fotografías, reconstrucciones métricas de los edificios ni prueba del estado actual de una sala o servicio. Los interiores de museos y actividades son representativos; no copiar obras ni inferir disponibilidad del operador. La selección Top8 procede de la auditoría existente; esta tarea cambia presentación, no afirma un ranking estadístico nuevo.

Revisión: no se observan imágenes rotas; 8 tarjetas y 15 experiencias en el borrador local. Directorio de Bogotá sigue sin comercios cargados en esta base; no se inventaron entradas para llenar tarjetas. Las imágenes nuevas siguen pendientes de aprobación humana.

## Integración compartida

`QA_INTEGRACION_TRES_DESTINOS.json` registra cargas guardadas en MariaDB local y revisión visible de Bogotá, Santa Marta y Medellín. Se comprobaron ES/EN/PT, imágenes y controles de carga progresiva. Los cambios compartidos de preprod se hicieron de forma puntual, conservando el trabajo previo de otros workers.

El script `scripts/integrar-semirrealista-preprod.py` repite la preparación de archivos desde los tres worktrees existentes; el script PHP separado exige entorno local, destino permitido, hash del contenido y estado borrador antes de guardar. La plantilla privada usa un mapa de medios para evitar descargar también las fotos antiguas. La sesión administrativa sigue siendo obligatoria y los enlaces de idiomas conservan `preview=1`.
