# Piloto Cartagena: imágenes y descanso visual

Estado: integrado exclusivamente en vista previa local privada. Ninguna imagen nueva aprobada; destino no terminado ni publicado.

## Proceso reutilizable
1. Investigar textos en portales oficiales nacionales, locales y del propio atractivo u operador. Guardar URL, fecha, hechos comprobados y dudas. No convertir publicidad o una ilustración en evidencia geográfica.
2. Elegir los ocho atractivos más importantes comparando también candidatos excluidos. Separar atractivo de experiencia. Validar ES/EN/PT.
3. Preparar un brief por componente: destino, uso exacto, elementos obligatorios, elementos prohibidos, evidencia y modo: lugar específico, portada o actividad representativa.
4. Para portada/lugar concreto usar referencia real elegida por el propietario, comprobar identidad y condiciones de uso. Una escena imaginaria que recuerda Cartagena no acredita Cartagena. Para actividad genérica describir equipo, acción y entorno sin inventar un recinto u operador.
5. Generar con el skill existente y estilo EL-SEMI-REALISTA-1: ilustración editorial semirrealista; personajes completos, expresivos, ligeramente caricaturescos; vegetación y edificios orgánicos; colores naturales. Evitar cabezas de bolita, geometría elemental y baño amarillo.
6. Revisar identidad, anatomía, equipo, paisaje y estilo. Conservar prompt, referencia y hashes. La aprobación de referencia no aprueba el resultado generado.
7. Crear derivados WebP de dos tamaños, medir bytes y cargar solo derivados en alojamiento. Mantener originales fuera del servidor web. Usar srcset y carga diferida salvo portada.
8. Integrar en preproducción y revisar con el propietario imagen por imagen y luego textos ES/EN/PT con diseño. Publicar únicamente con aprobación final explícita.

Documentos detallados: ../../PROCEDIMIENTO_WORKER_IMAGENES.md y ../../INSTRUCCION_WORKER_INVESTIGACION_VISUAL.md. Prompts exactos y hashes: PILOTO_SEMIRREALISTA_PROMPTS.json y PILOTO_SEMIRREALISTA_MANIFIESTO.json.

## Colores de la prueba
Página #FAFAF7, paneles #E8EFEB, tarjetas #FFFFFF, bordes #D7E0DA, botones #C94720. Las imágenes conservan sus colores, sin filtros amarillos. Los tokens viven en design/portal/themes/descanso-visual.json; aplicación limitada a Cartagena privada.

## Entrega comprobada
- Una nueva portada derivada de La Popa (fotografía histórica de 2008, Norma Gòmez, CC BY 2.0). No representa el skyline actual.
- Siete composiciones de experiencias: caminata, museo, bus, buceo, snorkel, catamarán y manglares/pesca. No documentan salas, personas ni operadores reales.
- 16 derivados: 1.142.144 bytes en total; mayor archivo 199.698 bytes. No implica descargar los dos tamaños por imagen.
- Siete imágenes de experiencias y nueva portada detectadas en navegador; fondo calculado rgb(250,250,247); comprobación sintáctica JS correcta.
- Estilo pendiente: varias experiencias resultaron de realismo alto y requieren revisión artística humana.
- Ocho atractivos conservan las ilustraciones SVG anteriores: NO son imágenes finales semirrealistas ni están aprobados por esta entrega.
- Sin cambios de base de datos ni publicación en producción.
