# Propuestas desde hechos contrastados · V6

20-10-2026. Sin importación ni aprobación humana desde este subproceso.

Se aplicó `docs/CIERRE_OPERATIVO_DESTINOS.md`: observar fotos públicas no equivale a subirlas al generador. Se consultaron las imágenes de turismo municipal/local para identificar rasgos; no se cargaron esas fotografías como entradas. Se registran URL, ficha, método, límites y hashes en `FICHAS_IDENTIDAD_V6.json`.

## Cambios concretos

- Colegio Gabo: patio sin jardín ni fuente, galerías en U de dos pisos, postes naranja/rojo, estructura azul, blanco con zócalo rojo. Una prueba y una corrección, luego cierre. La corrección ajustó placas/vano; no resolvió completamente la baranda torneada ni el pavimento más irregular. Por ello se entrega como aproximación para revisión, sin certificar arquitectura exacta.
- Casa Museo Quevedo: encuadre acotado de entrada con jambas de piedra beige entre dos ventanas azules, una planta, techo de teja y acera estrecha. No hay patio, fuente ni piezas de museo inventadas. Placas, proporciones y molduras son simplificadas; no pretende mostrar el inmueble completo.

## Integración selectiva

`PATCH_SOLO_COLEGIO_QUEVEDO_V6.json` contiene únicamente esos dos componentes. Usarlo para conservar el recorte de Catedral que ajustó el coordinador y la foto original del tren V5. Retirar el ocultamiento de esos dos slugs **solo al aplicar sus nuevos activos**, sin reactivar ninguna imagen vieja.

Paquete de referencia completo: `paquete-destino-hechos-v6.zip`, SHA-256 `6934821b6f1e3863ac8d9a473a483a18037da73706ef965d36a9d287a2284639`. Incluye 7 atractivos/3 experiencias, ES/EN/PT y 11 imágenes. ZIP nativo y criterios editoriales pasan. No importar ciegamente el paquete completo sobre los ajustes posteriores de Catedral del coordinador.

## Lista cerrada de pendientes

1. Revisión humana de colegio y museo, con límites explícitos del colegio. No aprobados por existir archivo o pasar esquema.
2. Estación: fachada y actividad ferroviaria real acreditadas por separado; **no** se verificó un encuadre conjunto del edificio con vías/andén/tren. Las cinco referencias revisadas no resolvieron esa relación. `cua.listBrowsers()` devuelve lista vacía; Maps/StreetView no se observó. El nodo cartográfico 5.02164,-74.00115 acredita posición, no orientación del andén. No se genera un tren montado frente a la fachada ni se declara cerrado ese componente.

No hay otras generaciones activas ni búsquedas abiertas. Producción intacta.
