# Reglas del candidato de presentación

Base: `f26842de`. Trabajo aislado en `refactor/presentacion-rendimiento-20261021`. No es una versión aprobada ni desplegada.

## Índice canónico por responsabilidad

| Responsabilidad | Fuente vigente | Duplicados / tratamiento |
|---|---|---|
| Identidad independiente y alcance territorial | PROYECTO_INDEPENDIENTE.md | Sus restricciones iniciales de lanzamiento no describen el estado actual; no invalidan decisiones comerciales posteriores. |
| Producto y prioridades | docs/CONTEXTO_PRODUCTO_Y_HOJA_DE_RUTA.md | Distinguir futuro de código disponible; no prometer reservas ni automatización inexistente. |
| Diseño, tokens, accesibilidad | docs/GUIA_ESTILO_PORTAL.md; design/portal/tokens.json | AGENTS resume obligación de lectura. No crear otra paleta. |
| Medios, fidelidad, procedencia y aprobación | docs/PRODUCCION_IMAGENES_IA.md; docs/REGLAS_FIDELIDAD_VISUAL_DESTINOS.md; .agents/skills/imagenes-destinos/SKILL.md | Bloqueo anterior a preprod en AGENTS sigue vigente. No sustituir imágenes en esta tarea. |
| Calidad, ramas, promoción, respaldo y datos | docs/REGLAS_OPERATIVAS_DESPLIEGUE.md | AGENTS, preprod/README, docs/PREPRODUCCION y PARIDAD son índices/procedimientos específicos; no autorizan omitir controles. |
| Gobierno de agentes | docs/GOBERNANZA_AGENTES.md | La autorización actual cubre preparación local; no producción, contactos ni datos vivos. |
| Experiencias y contenido territorial | docs/POLITICA_EXPERIENCIAS_DE_OPERADORES.md; crecimiento-colombia/GUIA_GENERACION_DESTINOS.md | Sin alterar publicación ni identidad estable de los registros. |

## Conflictos detectados y límites

1. PROYECTO_INDEPENDIENTE conserva frases de etapa inicial: «No están definidos precios», «No hay acceso confirmado al cPanel», «No hay ... catálogo real». Son incompatibles con implementación posterior. No se borran ni se usan para retirar funcionalidades; la matriz los conserva como antecedentes, pendientes de corregir con trazabilidad.
2. Secuencia de decisiones: la auditoría sugería layout administrativo separado; después se pidió shell transversal universal; finalmente el propietario ordenó administración independiente sin header/footer públicos. Prevalece esta última decisión explícita(21/10/2026). Solo el portal conserva shell público único; administración comparte marca/tokens y conserva autorización, sesión, CSRF, cierre de sesión y retorno al portal.
3. La construcción histórica muta fuentes. El nuevo punto de entrada ejecuta esos generadores en una instantánea efímera y una salida vacía. Eso elimina contaminación, pero no equivale a haber eliminado todas las transformaciones históricas internas: se migran y documentan por separado.
4. No hay informe Lighthouse original ni lista de imágenes afectadas. Los valores aportados son hipótesis, no ahorros verificados. No se inventarán resultados ni se promoverán derivados por puntuación automática.

## Controles de no recurrencia del candidato

- Construcción a directorio nuevo/vacío, sin editar el checkout; manifiesto SHA-256 y comparación de dos entregas.
- Una fuente pública de shell, navegación y traducciones. La administración es una excepción explícita con layout privado propio y marca/tokens compartidos, sin navegación pública incrustada.
- No correctores editoriales en navegador; mover contenido/medios a datos y renderizadores antes de retirar cada corrector.
- Recursos declarados, sin cargadores en cascada, versionados sobre bytes finales.
- Medios actuales conservados. Candidatas privadas requieren comparativa y aprobación exacta antes de preprod.
- Verificación de permisos, idiomas, móvil/teclado, recursos, SEO, y ausencia de regresión de métricas. Ninguna prueba local puede reiniciar datos productivos.

`python3 scripts/inventariar-presentacion.py --public PAQUETE/public --output INVENTARIO.json` conserva cada regla y su línea/hash fuente, además de rutas, familias y recursos. El inventario no accede a configuración ni base de datos.
