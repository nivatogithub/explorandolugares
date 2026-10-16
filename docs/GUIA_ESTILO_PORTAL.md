# Guía de estilo operativa de Explorando Lugares

Versión 1 · revisión del 16 de octubre de 2026. Exclusiva de Explorando Lugares.

## Propósito y alcance

Evitar decisiones de color aisladas, variantes accidentales entre páginas y el regreso de estilos antiguos. Esta guía registra los componentes cálidos observados en el portal y las preferencias expresadas por el propietario. Es una guía de implementación, no una nueva identidad de marca ni una aprobación de los borradores históricos.

Las instrucciones posteriores del propietario prevalecen. `design/pasaporte-local/`, `public/`, ZIP históricos y la primera paleta verde no son fuentes de estilo para páginas nuevas. Mantener la identidad separada de Paradigma Solutions.

La aplicación inicial se hace en **Estamos creciendo**, sus tres pestañas y el aviso de privacidad. La hoja global del portal conserva reglas históricas contradictorias; no se declara que todo el repositorio haya migrado a este sistema ni se cambia el resto del sitio silenciosamente.

## Fuentes de verdad

1. Preferencias actuales del propietario y esta guía.
2. Valores estructurados en `design/portal/tokens.json`.
3. Variables `--el-*` generadas en `backend/public/assets/cobertura/portal-tokens.css`.
4. Componentes de `backend/public/assets/cobertura/cobertura.css`.

Editar el JSON y regenerar. No crear otra paleta al final de una hoja ni volver a copiar una versión histórica de un componente. Las variables tienen prefijo `--el-` para no cambiar accidentalmente el header, el footer o las páginas existentes.

## Paleta por función

|Función|Color|Uso|
|---|---|---|
|Página|`#fffaf2`|Lienzo cálido del portal|
|Sección destacada|`#fff0dc`|Contenedor de mapa, audiencia, participación y pauta|
|Tarjeta|`#fff8ed`|Todas las tarjetas de métricas y contenido dentro de esas secciones|
|Campo|`#fffdf8`|Selectores, búsqueda y editor de invitación|
|Hover/aviso destacado|`#ffe4ba`|Interacción y énfasis puntual|
|Borde|`#f2c77e`|Separación de secciones y tarjetas|
|Borde de campo|`#e9b75f`|Controles de entrada|
|Texto|`#172b42`|Títulos, cifras y texto principal; no fondos de botones ni grandes paneles|
|Texto secundario|`#5e625f`|Ayudas y explicaciones|
|Acento|`#d84e27`|Detalle editorial y departamento seleccionado|
|Acción|`#c94720`|Botones principales y selección activa|
|Acción hover/enlace|`#a83b22`|Hover y acciones sobre superficies claras|
|Texto sobre acción|`#fffdf8`|Etiquetas de botones terracota|

Los colores cálidos ya aparecen en las tarjetas de destinos, el formulario comercial y sus bloques destacados. `#c94720` pertenece a la paleta existente de acción oscura; se usa como relleno para que texto pequeño claro alcance contraste, en lugar de copiar un naranja más claro con poco contraste.

**Jerarquía:** fondo de página → sección amarilla → tarjeta crema → control casi blanco. No poner amarillo idéntico en todos los niveles ni variar cada tarjeta de una misma familia. Usar separación, borde y espacio, no sombras pesadas.

No usar `#fafbfe`, `#e6edf9` u otros azules pálidos como fondo de tarjetas, formularios o paneles de información. No usar azul oscuro/negro como relleno de pestañas, filtros, compartir o bloque de pauta. El azul oscuro sí puede ser tinta.

### Excepción: estados del mapa

Los datos conservan colores propios: publicado `#315e9d`, preparación `#dfa04e`, pendiente `#bdc7cb`, desconocido con trama. El departamento seleccionado usa el acento terracota. La selección y el estado de publicación son conceptos distintos.

Estos colores se limitan a geometría, leyenda y puntos de estado. Las etiquetas conservan texto legible y nombre del estado; no convertir una tarjeta entera en azul para señalar “Publicado”. No presentar el cambio de color de selección como cambio de cobertura.

## Componentes

### Header, menú y footer

Reutilizar la estructura y CSS compartidos del portal. Conservar Inicio, Destinos, Planea tu visita, Conoce el portal, Estamos creciendo, negocios e idiomas. No crear una versión reducida en una página nueva. En móvil debe existir un control de apertura que funcione.

“Estamos creciendo” debe estar en el HTML cuando se genere un menú; el script compartido evita duplicados. Mantener WhatsApp, Instagram, correo y enlaces institucionales del footer. No recolorear sus iconos de marca como si fueran tarjetas de contenido.

### Pestañas, filtros y acciones

- Activo/principal: terracota de acción + texto claro.
- Inactivo/secundario: crema/campo + texto oscuro o enlace terracota + borde dorado.
- Hover: tono cálido; foco visible independiente del hover.
- Usar texto/`aria-selected`/`aria-pressed`, no solo color, para señalar selección.
- No utilizar relleno navy para distinguir la selección.
- Botones y controles: al menos 44 px de alto; bordes redondeados coherentes.

El botón de pauta es principal. Copiar invitación y otras acciones son secundarios. Aceptar y rechazar medición tienen la misma visibilidad y jerarquía; no resaltar una opción de consentimiento más que la otra.

### Tarjetas, formularios y avisos

Tarjetas de Cobertura y Audiencia comparten el mismo token de superficie. Pauta utiliza el contenedor cálido, sin panel navy ni texto blanco heredado. Inputs y textarea comparten el token de campo y el borde de control. Avisos usan amarillo/ámbar suave, texto oscuro y un mensaje explícito.

No diseñar la tarjeta “sin datos” con otro azul: la ausencia de medición se explica con palabras. No cambiar cálculos, cifras ni estados de publicación para resolver un problema visual.

### Tipografía y ritmo

- Texto y controles: pila UI del sistema usada por el portal.
- Titular editorial de portada de sección e invitación: Georgia/Times, como la composición editorial vigente de esta página.
- Títulos funcionales y métricas: fuente UI; evitar introducir una tercera familia.
- Texto habitual 14–18 px; ayudas 12–13 px; etiquetas cortas 11 px. No usar texto diminuto para información esencial.
- Escala de espacio: 8, 12, 16, 24, 32 y 48 px. Tarjetas 20–24 px; secciones 24–36 px.
- Radios: campo 8 px, tarjeta 12 px, panel 18 px, botones pill.

El portal histórico mezcla tipografías en distintas plantillas. Esta convención define componentes nuevos y corregidos; no autoriza a cambiar de golpe todas las páginas o a tratar una exploración histórica como identidad final aprobada.

### Responsive y accesibilidad

Header/footer conservan su comportamiento compartido. En esta página el explorador pasa a una columna a 960 px; a 700 px las cifras superiores y de audiencia quedan en dos columnas, con el alcance nacional en una fila completa. Evitar columnas demasiado estrechas y scroll horizontal. El mapa sigue siendo Colombia completa con una silueta departamental ampliada, nunca un segundo mapa municipal.

Contraste calculado con los tokens: acción/texto claro **4,69:1**, texto/tarjeta **13,63:1**, texto secundario/panel **5,53:1**. Son comprobaciones de pares concretos, no una certificación de accesibilidad de todo el portal. Conservar navegación por teclado, foco, etiquetas, áreas de interacción y reducción de movimiento.

## Reglas de implementación

- Usar clases específicas (`coverage-intro`), evitando colisionar con clases globales como `intro`.
- Limitar estilos de página a `#contenido`. No redefinir globalmente `header`, `footer`, `.brand`, `body` o variables sin prefijo.
- Una definición por componente y estado. Agrupar responsive por breakpoint; no añadir una colección de parches históricos al final.
- Usar `!important` solo en compatibilidad explícita con el CSS compartido que ya lo utiliza; documentar el motivo. No usarlo para inventar otra paleta.
- No editar a mano los archivos generados de tokens. Usar el JSON.
- Versionar recursos por su hash de contenido. El HTML debe cargar primero el CSS compartido, después tokens y por último componentes.
- Mantener créditos, datos, rutas, consentimiento y comportamientos existentes al cambiar presentación.

## Flujo obligatorio antes de entregar un cambio visual

1. Leer esta guía y el contexto independiente; identificar el componente de referencia.
2. Revisar **las tres pestañas**, estados activos/inactivos, contenido vacío, formularios, avisos y preferencias de privacidad.
3. Ejecutar `python3 scripts/sincronizar-estilos-cobertura.py` y las comprobaciones de diseño.
4. Verificar colores **calculados en el navegador**, no solo buscar un hexadecimal en CSS: herencia, especificidad y `!important` pueden cambiar el resultado.
5. Probar una vista amplia, una estrecha, teclado y apertura de selectores; comprobar que no hay desbordamiento horizontal.
6. Revisar con datos reales/vacíos sin fabricar cifras ni enviar invitaciones de prueba a terceros.
7. Guardar capturas y un resumen de hallazgos y cambios. No declarar revisión de una pantalla que no se ha comprobado.
8. Publicar únicamente dentro de una autorización de despliegue. Después comprobar rutas, CSS versionado y comportamiento en producción.

Comprobaciones locales:

```text
python3 scripts/generar-tokens-portal.py --check
python3 scripts/sincronizar-estilos-cobertura.py --check
python3 backend/tests/test_portal_design.py
python3 backend/tests/test_coverage_package.py
php backend/tests/coverage-service.php
```

## Auditoría que originó esta guía

Estado revisado antes de la consolidación, 16-10-2026, a partir de código y estilos calculados en producción:

|Hallazgo|Evidencia|Corrección local conjunta|
|---|---|---|
|Tarjetas casi blancas percibidas como frías|Audiencia calculada `#fffefa`; tarjetas superiores ya en `#fff8ed`|Una sola superficie crema `#fff8ed` para ambas|
|Fondos azulados aún definidos en CSS|`#fafbfe` en métricas, cuadros vacíos y editor, con algunas declaraciones anuladas por reglas posteriores|Eliminar declaraciones antiguas y usar tokens; no confundir código muerto con color realmente visible|
|Pestañas y filtros muy oscuros|Fondo calculado `#172b42`|Terracota de acción|
|Panel de pauta oscuro|Fondo calculado `#172b42`|Contenedor amarillo y texto oscuro|
|Compartir con relleno oscuro|Enlace de WhatsApp calculado `#172b42`|Acción terracota|
|Editor dependiente del CSS global|Fondo blanco calculado aunque la hoja de página declaraba azul pálido|Campo cálido explícito con compatibilidad de cascada|
|Selectores repetidos|Varias familias verde/aqua/dorada en la misma hoja|Un componente selector con estados definidos|
|Parches sucesivos de contenedores|Reglas repetidas cambiaban el mismo fondo|Consolidación en una hoja legible y tokens únicos|
|Dependencia de números de versión manuales|`?v=17` y variantes anteriores|Versionado automático por contenido|

Referencias contrastadas: estilos del directorio de Jericó (selección terracota), formulario comercial (tarjetas `#fff8ed`, bordes `#f2c77e`), sección Haz parte (sección cálida y tarjetas internas claras) y CSS compartido de producción. El header/footer compartidos conservan rasgos históricos; una limpieza global futura debe revisarse por separado.

## Despliegue y ramas

La guía, tokens, componentes y pruebas deben viajar juntos al integrar esta rama. No hacer un despliegue completo desde una rama que no los contenga: el generador de obsoletos ya retiró anteriormente la función de cobertura. El despliegue incremental conserva archivos ajenos; actualizar el manifiesto no equivale a integrar las fuentes. Esta guía no autoriza por sí sola un merge ni una publicación.

## Evidencia de esta revisión conjunta

Versión local consolidada, con datos de prueba. No confundir estas capturas con estadísticas reales de producción.

- [Cobertura](../design/portal/evidencias/cobertura.png)
- [Audiencia](../design/portal/evidencias/audiencia.png)
- [Invitaciones](../design/portal/evidencias/participacion.png)
- [Pauta](../design/portal/evidencias/pauta.png)
- [Tres vistas móviles de 390 px](../design/portal/evidencias/movil.png)
- [Registro de colores y comprobaciones](../design/portal/verificacion-diseno.json)

La vista local `/__design-review/` muestra las tres pestañas a 390 px y un botón para volver a medir colores y desbordamiento. Solo existe en el servidor de prueba con configuración local; no se incluye en el paquete público. Se comprobaron además una vista de 1600 px, navegación de pestañas por teclado, apertura del periodo, editor de invitación y equivalencia visual de aceptar/rechazar privacidad. No se enviaron mensajes.

## Lectura móvil compacta · 16-10-2026

La primera pantalla debe permitir entender el propósito y llegar a los indicadores sin una introducción extensa. Mantener cifras, alcance, periodo, limitaciones de medición y acciones visibles. Las explicaciones de cobertura, leyenda/procedencia, metodología de ampliación, países y evolución usan `details` nativos: cerrados inicialmente hasta 700 px y abiertos en pantalla amplia. Sin JavaScript siguen abiertos; la elección manual se conserva mientras no cambie el breakpoint. No esconder contenido solo con CSS ni reducir controles por debajo de 44 px.

El mapa sigue visible (275 px en móvil), con selector departamental independiente y las mismas geometrías. No se cambian estadísticas, consentimiento ni filtros. La introducción y la invitación usan textos más breves también en escritorio.

Medición local a 390 px, mismo departamento y fixture, altura de `main` (incluye el aviso local):

|Pestaña|Antes|Después|Reducción aproximada|
|---|---:|---:|---:|
|Cobertura|3039 px|2167 px|29%|
|Audiencia|2164 px|1612 px|26%|
|Haz parte|3206 px|1997 px|38%|

No hubo desbordamiento horizontal en las tres vistas. En 1600 px los seis desplegables parten abiertos y no hay desbordamiento. Evidencia: [vista compacta](../design/portal/evidencias/movil-compacto.png). Cifras de prueba, no producción. La revisión del resto del portal queda como trabajo separado: priorizar inicio, destino/directorio y formulario comercial; medir recorrido hasta la acción principal antes de aplicar esta pauta. No convertir todo texto editorial en acordeones.


## Métrica básica sin cookies · candidato posterior a v1.2.0

La tarjeta “Páginas vistas · sin cookies” ocupa una fila completa sobre la rejilla de métricas consentidas. Conserva superficie crema, tipografía funcional y ayuda explícita: son vistas, no personas únicas. Los campos con permiso lo indican en etiqueta o ayuda. No añadir otra paleta para distinguir los métodos. “Cómo medimos” reúne alcance, fechas y fuentes, sin volver a extender la página con avisos técnicos.

Revisión local: 1600 px y tres pestañas a 390 px, sin desbordamiento y con los mismos tokens cálidos. [Vista amplia](../design/portal/evidencias/audiencia-sin-cookies.png), [móvil](../design/portal/evidencias/movil-sin-cookies.png), [registro de comprobaciones](../design/portal/verificacion-conteo-basico.json). Datos de prueba; todavía no es evidencia de funcionamiento en producción.
