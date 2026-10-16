# Empezar la ampliación de cobertura

## Estado del plan

Plan operativo v1 terminado como documentación, estructura y herramientas de validación. Rama propia: `feature/ampliacion-cobertura-colombia`, creada desde `origin/main`. Contiene únicamente `crecimiento-colombia/`; no arrastra implementación ni commits de la rama de negocios.

La clasificación departamental y los destinos fuera de Antioquia siguen siendo propuestas editoriales, no prioridades aprobadas ni ranking de demanda. Esto no bloquea comenzar la preparación privada de los destinos P1 de Antioquia ya definidos por el propietario. El inventario de 78 candidatos no es el censo completo de Colombia.

## Qué está disponible

- Tres grupos de departamentos y tres grupos independientes de lugares, sin cupos; puntuación de preparación separada de prioridad turística.
- 32 departamentos y Bogotá D.C., 78 candidatos con carpeta, proceso y manifiesto. Cauca Viejo tiene padre Jericó; Sonsón es P2.
- Workers definidos para fuentes, fotografías reales, conversión, redacción, traducción EN/PT-BR, negocios gratuitos, integración y QA.
- Contratos JSON, evidencia por gate, hashes, cobertura ponderada y pruebas del validador.
- Flujo de rama y PR por lugar, revisión humana, autorización y comprobación separadas de publicación.
- Roadmap de onboarding, cuentas de viajeros/comercios, privacidad, retención, recomendaciones, métricas y monetización opcional sin membresía. Instagram aparte.

## Primer trabajo ejecutable

1. Empezar con la auditoría editorial de Jericó, conservando su prioridad existente y sin asumir que sus datos actuales cumplen los gates.
2. Crear rama `lugar/CO/antioquia/jerico` desde la base acordada que incluya este plan. Hasta incorporar el plan a `main`, usar esta rama como base y dejar explícita la dependencia en el PR de lugar.
3. Completar `colombia/departamentos/antioquia/lugares/jerico/PROCESO.md`: identidad, fuentes, fotos reales/permisos, textos, traducciones y directorio gratuito trazable. No conectar workers a producción.
4. Registrar evidencias en `lugar.json` y entregas; ejecutar desde la raíz `python3 crecimiento-colombia/scripts/validar.py`, `python3 crecimiento-colombia/scripts/test_validar.py` y `python3 crecimiento-colombia/scripts/cobertura.py`.
5. Entregar PR exclusivo de Jericó con vista previa y pruebas. Publicar solo la versión aprobada y autorizada. La preparación editorial puede avanzar antes de implementar cuentas o integraciones oficiales.
6. Continuar con los demás P1 de Antioquia, por lotes de capacidad manejable. Orden dentro de P1 según preparación, bloqueos y decisión editorial; no degradar la prioridad de quienes esperan.

## Grupo P1 histórico de Antioquia

- Jericó
- Jardín
- Guatapé
- Santa Fe de Antioquia
- Medellín
- El Peñol
- Támesis
- San Jerónimo
- Sopetrán
- San Rafael
- Cocorná
- Venecia
- El Retiro
- San Carlos
- El Carmen de Viboral
- Rionegro
- Necoclí
- Arboletes
- Puerto Triunfo
- San Pedro de los Milagros
- Santa Rosa de Osos

## Pendientes para ejecutar en el portal

Las instrucciones técnicas y capacidades revisadas se observaron en la rama de negocios; su presencia allí no significa que todas existan en la base `main` de esta rama. Antes de integrar un lugar, revisar de nuevo el backend de la base de publicación elegida, el paquete v2, versiones/aprobaciones, tipos territoriales y adaptador. No incluir código de negocios dentro del PR del plan para resolver esa dependencia.

Falta obtener/verificar material por lugar, adaptar los contratos al importador, implementar las automatizaciones futuras y comprobar permisos de APIs si se usan. Responsable y canal de privacidad reales son necesarios antes de captar datos; no impiden investigar y redactar en privado. Ninguna carpeta ni checklist equivale a destino publicado.
