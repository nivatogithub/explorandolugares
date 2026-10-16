# Revisión previa

Copia local: remoto `https://github.com/nivatogithub/explorandolugares`, rama `negocios-dinamicos-phm`, base `7ef4996`. Árbol limpio al comenzar. Exclusivamente Explorando Lugares; no se usaron recursos de otros negocios. `sources/` permanece intacto.

Leídos: `AGENTS.md`, `PROYECTO_INDEPENDIENTE.md`, `docs/CONTEXTO_PRODUCTO_Y_HOJA_DE_RUTA.md`, `docs/GOBERNANZA_AGENTES.md`, `docs/REGLAS_OPERATIVAS_DESPLIEGUE.md`, `design/pasaporte-local/BORRADOR.md`, `docs/GUIAS_VISUALES_DESTINOS.md`, `docs/REGISTRO_NEGOCIOS.md`, `docs/MODELO_CUENTAS_NEGOCIOS_CANALES.md` y `docs/CIERRE_PENDIENTES_2026-10-15.md`. Inspeccionados el borrador HTML de Jericó, `content/jerico-fotografias.json`, el generador, importador y plantilla de paquete v2, y rutas de cuentas empresariales.

| Evidencia local | Capacidad y límite |
|---|---|
| `ensayos/v1/jerico.html`, borrador Pasaporte | Referencias de contenido/diseño; no identidad aprobada ni estado productivo de hoy |
| `content/jerico-fotografias.json` | Procedencia existente para revisar foto por foto; no renovar permisos automáticamente |
| `.agents/skills/imagenes-destinos/SKILL.md` | Único skill local encontrado: ilustración editorial IA, revisión y optimización; no un conversor fotográfico dedicado |
| `scripts/generar-destinos.py` | Validación/generación de guías y fotografías WebP, no conversión general de archivos |
| `ensayos/v1/plantillas/paquete-destino-v2.json`, `DestinationImporter.php` | Contrato de importación revisable; acepta atractivos `place` y `experience`, estas últimas borrador por defecto |
| `backend/public/business/{register,login,logout,password,dashboard}.php` | Implementación de cuentas empresariales presente y condicionada por configuración; no se probó autenticación real en esta tarea |
| `register.php` | Solicita documentos de identidad, negocio y representación; reducirlos exige cambios de backend y evaluación de privacidad |
| Informe de cierre 15/10 | Documenta Jericó público y Sonsón borrador en aquel momento; no se verificó producción hoy |

No se encontró implementación de cuentas de viajeros en las rutas revisadas. OAuth se anuncia condicionado, no se considera operativo. La visibilidad efectiva de experiencias de Jericó requiere consulta de su versión/datos y vista previa; no se deduce del HTML histórico ni se cambia aquí.

El nuevo esquema es un contrato de planificación: NO se importa directamente al panel. Un adaptador futuro mapeará IDs internos al paquete v2 y comprobará tipos, padres, imágenes y versión. No cambiar `destination_type` ni inventar códigos DANE para lograr compatibilidad.

No se comprobó acceso remoto de escritura, GitHub CLI (no disponible), cPanel, API de registros ni despliegue. Los resultados del plan son locales hasta tener evidencia explícita de push/PR/publicación.

## Separación del plan

El plan se trasladó a `feature/ampliacion-cobertura-colombia` desde `origin/main`, copiando únicamente el árbol versionado `crecimiento-colombia/`. Las observaciones anteriores describen la copia de la rama de negocios revisada originalmente; no garantizan que las capacidades estén en esta base. Antes de integrar contenido al portal, comprobar de nuevo la rama técnica elegida. Los commits de negocios no forman parte de esta entrega.
