# Procedimiento de creación de lugar v1

Disparador: preparar un lugar de Explorando Lugares. Resultado: propuesta revisable en rama propia y PR, nunca publicación automática. Este procedimiento no instala un skill ni habilita workers en producción.

1. Leer instrucciones vigentes del repositorio, este plan, contratos, gobernanza y paquete v2. Confirmar remoto y árbol limpio; preservar trabajo ajeno. Asignar editor y revisor.
2. Resolver ID estable, tipo y departamento; buscar duplicados en inventario/backend. Crear candidato y carpeta desde `plantillas/PROCESO.md`. Para corregimiento/destino autónomo, verificar padre; no inventar municipio ni código DANE. Mantener redirect si cambia slug.
3. Desde base actualizada acordada crear `lugar/CO/<departamento>/<slug>`. Cada lugar usa rama/PR independiente; cambios compartidos de esquema van antes en un PR de infraestructura. No mezclar varios lugares en una entrega salvo corrección transversal aprobada.
4. Ejecutar roles fuentes → redacción/fotos/negocios → conversión/traducción → integración → QA. Los roles pueden ser personas, tareas manuales o agentes futuros con contratos limitados; no necesitan acceso al panel.
5. Guardar entrega, evidencias y hashes. Validar esquemas/invariantes y cobertura. El validador estructural no verifica la verdad de una URL o licencia: revisor humano lo comprueba.
6. Convertir a `ensayos/v1/plantillas/paquete-destino-v2.json` con adaptador revisado. Probar en base aislada siguiendo `scripts/ensayar-paquete-destino.py`; no conectar a producción. Primero resolver soporte de tipos/padres si falta.
7. PR incluye nombre/ID, base/hash final, cambios, evidencias, licencia, vista previa, pruebas, riesgos y checklist de gates. CI valida contratos y diff; no importa ni publica. Revisión humana y aprobación de hash antes de merge.
8. Publicar únicamente con autorización concreta de versión conforme a reglas operativas. Registrar URL/commit/fecha, HTTP anónimo, medios, idiomas, navegación y reversión prevista. Si falla, estado bloqueado y conservar evidencia; merge no suma cobertura pública.
9. Programar mantenimiento en backlog (sin crear automatización aquí). Proponer skill `crear-lugar` cuando dos pilotos demuestren pasos repetibles; su empaquetado/instalación y conectores son trabajo posterior.
