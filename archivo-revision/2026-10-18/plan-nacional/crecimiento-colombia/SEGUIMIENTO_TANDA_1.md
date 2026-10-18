# Primera tanda — entregas por rama

Cada destino está aislado. Estas son entregas privadas en preparación, no destinos terminados/aprobados/publicados. No se reasignó ningún worker.

| Worker | Destino | Rama | Último commit comprobado | Entrega |
|---|---|---|---|---|
| Lugar 1 | Cartagena | lugar/CO/bolivar/cartagena | b12722a | `../worker-lugar-1/crecimiento-colombia/entregas/cartagena/` |
| Lugar 2 | Bogotá | lugar/CO/bogota-dc/bogota | f86d611 | `../worker-lugar-2/crecimiento-colombia/entregas/bogota/` |
| Lugar 3 | Medellín | lugar/CO/antioquia/medellin | 58bacaf | `../worker-lugar-3/crecimiento-colombia/entregas/medellin/` |
| Lugar 4 | Santa Marta | lugar/CO/magdalena/santa-marta | 9c344e3 | `../worker-lugar-4/crecimiento-colombia/entregas/santa-marta/` |

Cartagena: ZIP v2 validado y QA Chrome 390/1440 reportado por worker; faltan revisión humana e integración/previsualización PHP y PR. Bogotá: textos/10 fichas y 1 original/2 derivados; faltan imágenes por atractivo/historia; QA navegador no autorizado. Medellín: textos/10 fichas y 3 originales/derivados; faltan imágenes específicas, integración y revisión. Santa Marta: textos/10 fichas, 3 originales/6 derivados inspeccionados; integración v2, QA navegador y revisión pendientes. Consultar STATUS.md de cada rama para detalle.

Subida a GitHub comprobada para las cuatro ramas y la rama del plan. PR todavía no creado: el navegador de GitHub está sin sesión iniciada y no hay herramienta de creación autenticada disponible. No tratar ramas subidas como PR, merge ni despliegue. La cobertura de preparación/publicabilidad exige evidencias aceptadas: los borradores por sí solos no aprueban gates.

El control de ramas `scripts/validar-ramas-workers.py` pasó para los cuatro checkouts. No hay cambios de un destino dentro de la rama de otro.

La comprobación del aislamiento se repitió después de los commits finales y pasó en los cuatro destinos. Los informes STATUS.md de los workers describen su punto de entrega anterior a la subida realizada por el coordinador.
