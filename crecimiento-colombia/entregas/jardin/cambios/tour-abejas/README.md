# Cambio pendiente: Tour de abejas

Solicitud del propietario,20/10/2026: sustituir `paseo-patrimonial` por `tour-de-abejas` en Jardín. Solo cambia esa experiencia. El manifiesto y ZIP actualmente aprobados se conservan; `aprobacion.json` sigue siendo el registro histórico de esa versión, no una aprobación de esta modificación.

## Textos preparados

| Idioma | Título | Descripción |
|---|---|---|
| ES | Tour de abejas | Conoce las colmenas con un apicultor y descubre cómo se produce la miel. |
| EN | Beekeeping tour | Visit beehives with a beekeeper and discover how honey is produced. |
| PT-BR | Passeio de apicultura | Conheça as colmeias com um apicultor e descubra como o mel é produzido. |

Sin nombres, enlaces comerciales o publicidad de oferentes en la tarjeta. La evidencia queda en `criterios-editoriales-pendiente.json`, de uso interno.

## Evidencia revisada

- [Jardín Ecotours](https://www.jardinecotours.com/tour-de-abejas/): página propia accesible; actividad en fincas del municipio, acompañamiento y vestuario protector. Es suficiente para acreditar oferta documental, sin prometer operación para una fecha ni copiar tarifas.
- [Jardín de Aventura](https://jardindeaventura.com/tour/tour-de-las-abejas/): oferta propia accesible de apiario y meliponario con acompañamiento y protección. No se trasladan recuentos de especies, precio o condiciones de un paquete al texto genérico.
- [Miel del Jardín](https://www.mieldeljardin.com/): la apertura del20/10 devolvió502; conserva su condición de antecedente investigado, no prueba necesaria de disponibilidad actual.

No se contactó a nadie, no se contrató publicidad y no se infiere alianza con el portal. No se descargaron fotografías.

## Componente bloqueado y siguiente paso

Falta **la referencia visual elegida por el propietario para la tarjeta «Tour de abejas»**. `brief-visual.json` especifica apiario, apicultor, visitantes protegidos y composición3:2, sin inventar la finca exacta. La imagen urbana anterior no representa esa actividad y no se reutiliza.

Según la instrucción vigente del propietario, no generar una imagen nueva ni buscar/descargar candidatas para este componente antes de recibir esa referencia. Una vez elegida, verificar uso permitido, aplicar el skill de imágenes, conservar maestro/derivados/procedencia/hash y revisar el resultado. Esto no hereda la aprobación del tour retirado.

`paquete-destino-pendiente.json` pasa el esquema nativo y conserva las demás tarjetas, pero referencia `images/tour-de-abejas.webp`, que **todavía no existe**. No es un ZIP importable; no se fabricó un archivo vacío ni se usó otra ilustración para superar el lector.

El coordinador integrará secuencialmente cuando exista la imagen correspondiente. No se tocó preproducción ni producción, no hay QA de página servida de este cambio y no se declara terminado.
