# Estado de la entrega

16/10/2026. Implementado como documentación y herramientas locales de planificación; no es implementación del portal ni automatización conectada.

- Creadas 33 unidades (32 departamentos + Bogotá D.C.) y 78 lugares candidatos, cada uno con manifiesto y proceso. Cinco P1: Jericó, Jardín, Sonsón, Salento y Cartagena. Cauca Viejo P2 con padre Jericó.
- Contratos JSON, puntuación, invariantes y cobertura calculable incluidos. Cobertura inicial del nuevo plan: 0%; refleja ausencia de auditoría de gates, no ausencia de contenido histórico.
- Siete pruebas aprobadas: semilla/MVP, gate sin evidencia, publicación prematura, ciclo territorial, puntuación alterada, escape de ruta y cambio del hash de aprobación. Validación estructural aprobada para los 78 lugares. No valida autenticidad, licencias ni contenido remoto por sí sola.
- Revisado remoto GitHub de la rama de trabajo; coincide con base `7ef4996` al consultar. No se revisaron servicios privados ni producción.
- Cambios concurrentes detectados en tres archivos de `backend/`: no incluidos ni modificados por esta tarea.

## Siguiente trabajo, por orden

1. Propietario: aprobar alcance/editor/revisor y modelo gratuito sin membresías; resolver responsable y canal de privacidad reales antes de habilitar captación.
2. Editor: auditar Jericó sobre versión actual, fuentes y fotos reales; registrar cada gate y preparar su rama/PR propio. No reutilizar como hechos vigentes los estados históricos.
3. Técnico: demostrar control de versiones/aprobaciones, soporte territorial padre/hijo y adaptador al paquete v2; probar en entorno aislado. El procedimiento fotográfico debe materializarse con herramienta y versión comprobadas cuando se procesen fotos.
4. Editor/técnico: completar los otros cuatro P1 en ramas propias; publicación siempre separada y autorizada. Este plan no crea los PR de lugares aún no desarrollados.
5. Investigación: reconciliar inventario con catálogo oficial DANE versionado; candidatos editoriales no equivalen a censo administrativo completo.
6. Responsable jurídico/técnico: confirmar condiciones de DIAN/RUES/RNT, derechos de datos, proveedores, retención y documentos; no depender de APIs no habilitadas.
7. Producto: cuentas de viajeros, incentivos funcionales, onboarding proporcional, medición y recomendaciones conforme a roadmap; monetización opcional e Instagram después.

No hay imágenes nuevas, negocios descubiertos/contactados, APIs habilitadas ni despliegue en esta entrega. Los workers y el procedimiento están definidos; su ejecución editorial queda para cada lugar. El portal y sus formularios no cambian por añadir estos archivos.
