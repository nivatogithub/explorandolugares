# Plan UX y navegación para cerrar v1

## Propósito

Cerrar la primera versión pública con dos recorridos inequívocos:

1. El viajero descubre un destino, entiende cómo preparar su visita y encuentra información local útil.
2. El negocio llega sin desvíos a una solicitud de contacto, mientras conserva acceso a la información necesaria para decidir si participa.

El alcance sigue siendo informativo. No incorpora cuenta de viajero, reservas, pagos en línea, favoritos, itinerarios guardados ni publicación automática de negocios.

## Decisiones de producto

### Navegación del viajero

El logo seguirá llevando a Inicio. La navegación principal será:

| Elemento | Destino | Objetivo |
| --- | --- | --- |
| Destinos | Directorio de destinos | Elegir un municipio o ciudad. |
| Planea tu visita | Nueva página informativa | Entender cómo combinar destino, alojamiento, gastronomía, experiencias, movilidad y eventos. |
| Conoce el portal | Evolución de la página actual de etapas | Entender el propósito, el piloto en Jericó y la expansión gradual. |
| Registrar mi negocio | Formulario de interés | Entrada directa para propietarios y representantes. |
| ES / EN | Equivalente de idioma | Mantener idioma durante la navegación. |

`Registrar mi negocio` será un botón separado. `Conoce el portal` sustituirá el nombre `Nuestro avance` en la navegación. La ruta histórica `/etapas/` se conservará con redirección o contenido equivalente para no romper enlaces ni el sitemap.

Eventos seguirá dentro de cada destino. No se añadirá como opción global hasta contar con contenido vigente y consistente.

### Recorrido de negocios

El botón principal de la cabecera, el CTA de Inicio y los enlaces comerciales del footer llegarán directamente a `Registrar mi negocio`.

La página `Para negocios` conservará el contexto comercial, pero no será un paso obligatorio. Su navegación secundaria quedará reducida a:

| Prioridad | Sección | Propósito |
| --- | --- | --- |
| Principal | Registrar mi negocio | Acción de conversión. |
| Secundaria | Por qué participar | Valor frente a presencia aislada, mapas y plataformas con comisión. |
| Secundaria | Cómo funciona | Solicitud, revisión, aprobación y publicación manual. |
| Contextual | Planes futuros | Referencia, sin precios ni contratación activa. |
| Contextual | Impulsa tu destino | Postulación especial para referentes locales; no forma parte del recorrido común. |

La terminología se unificará así:

- Acción de entrada: `Registrar mi negocio`.
- Página informativa: `Para negocios`.
- Envío: `Enviar solicitud`.
- Resultado: `Recibimos tu solicitud`.

No se usará `↗` en navegación interna; se reservará para enlaces externos. Las rutas internas usarán `→` o no usarán flecha.

## Cambios por lote

### Lote A — Arquitectura de navegación

1. Construir `Planea tu visita` en español e inglés.
2. Replantear `etapas` como `Conoce el portal`, con una ruta compatible para la URL existente.
3. Actualizar cabecera y footer en español e inglés con la nueva estructura.
4. Llevar todos los CTAs comerciales principales al formulario.
5. Reducir el menú secundario de negocios y mover `Impulsa tu destino` a un enlace contextual.
6. Mantener el estado activo de menús primarios y secundarios en toda ruta pública.

**Validación manual:** Inicio, Destinos, Planea tu visita, Conoce el portal, formulario, páginas de negocios, Jericó, móvil y ambos idiomas.

### Lote B — Conversión y formulario

1. Reducir el contenido previo al formulario a una explicación breve y una sola ruta de ayuda secundaria.
2. Distribuir visualmente los campos en tres grupos: `Tu negocio`, `Cómo contactarte` y `Confirmación`.
3. Simplificar la redacción de confirmación y privacidad sin retirar consentimiento ni trazabilidad.
4. Mostrar de inmediato qué datos son obligatorios y por qué el botón aún no se puede enviar.
5. Mantener correo **o** teléfono como contacto válido, junto con la validación existente de datos.
6. Conservar la vista previa local y el comportamiento publicado como mensajes distintos según entorno.

**Validación manual:** campos vacíos, correo válido e inválido, teléfono, fijo, celular, WhatsApp, privacidad, éxito, error y segundo envío intencional.

### Lote C — Legibilidad y composición visual

1. Reducir el `letter-spacing` negativo de la tipografía cursiva a aproximadamente `-0.5px` y aumentar su interlineado.
2. Moderar el espaciado negativo de titulares sans grandes.
3. Compactar la cabecera móvil: logo, menú, CTA comercial e idioma tendrán prioridad; la nota descriptiva no ocupará altura innecesaria en páginas internas.
4. Reducir el peso visual del aviso de piloto para que contextualice sin competir con el título de cada página.
5. Alinear las plantillas de cabecera de las páginas comerciales: altura de título, ubicación de imagen, inicio de contenido y CTA.
6. Revisar el selector flotante en móvil, escritorio, teclado, filtros de destinos, directorio, planes y panel administrativo.

**Validación manual:** 320 px, 390 px, 768 px, 1024 px y escritorio amplio; español e inglés.

### Lote D — Accesibilidad, enlace y rendimiento

1. Convertir las tarjetas de destino en una acción principal por tarjeta, evitando tres paradas de teclado hacia el mismo destino.
2. Dar nombres accesibles específicos a enlaces repetidos como `Conocer el destino`.
3. Confirmar que los iconos decorativos no se anuncien como texto.
4. Verificar foco visible, Escape, flechas y selección con teclado en el selector flotante.
5. Mantener imágenes con carga diferida, dimensiones declaradas y versiones WebP.
6. Evitar nuevas dependencias y mantener los recursos críticos ligeros para conexiones lentas e intermitentes.

## Pruebas automáticas

Se ampliarán las pruebas existentes para verificar:

- Cada menú contiene cada opción una sola vez y el elemento activo se conserva.
- `Registrar mi negocio` llega al formulario desde header, Inicio y footer.
- Todas las rutas internas, recursos, scripts, formularios y equivalentes ES/EN existen.
- Los selectores flotantes conservan el valor del `<select>` original y emiten cambios para filtros y formularios.
- No hay enlaces internos que usen el indicador `↗`.
- El sitemap solo contiene páginas públicas aprobadas y sus equivalentes en inglés.

La validación funcional incluirá recorridos manuales completos de viajero y negocio, con especial atención a móvil y a navegación por teclado.

## Orden de trabajo y aprobación

1. Aplicar el Lote A en copia local y realizar pruebas automáticas.
2. Revisar visualmente y aprobar el Lote A.
3. Aplicar y aprobar el Lote B.
4. Aplicar y aprobar los Lotes C y D.
5. Ejecutar todas las pruebas, revisar enlaces, rendimiento básico, sitemap y versión en inglés.
6. Hacer commit de los cambios aprobados, push de la rama y despliegue incremental.
7. Revisar producción con caché renovada.
8. Cuando se apruebe el resultado, fusionar la rama en `main`, etiquetar el commit como `v1.0.0` y eliminar la rama de trabajo.

Mientras v1 no esté aprobada, los cambios se mantienen en `trabajo/entorno-local`; no se etiqueta ni se fusiona en `main`.

## Fuera de este cierre

- Registro e inicio de sesión de viajeros.
- Reservas, pagos, pasarelas y precios activos.
- Itinerarios, favoritos y cuenta personal.
- Publicación automática de perfiles de negocio.
- Gestión nacional de municipios desde base de datos.
- Agenda global de eventos.
- Chat o agente de atención para viajeros.
