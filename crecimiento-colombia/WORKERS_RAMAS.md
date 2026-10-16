# Aislamiento de workers por destino

Cada worker trabaja en una rama Git y checkout independientes. La coordinación del plan y los cambios de medición quedan en `plan/maestro-cobertura-auditado-20261016`.

| Worker | Destino | Rama | Checkout local |
|---|---|---|---|
| Lugar 1 | Cartagena | lugar/CO/bolivar/cartagena | ../worker-lugar-1 |
| Lugar 2 | Bogotá | lugar/CO/bogota-dc/bogota | ../worker-lugar-2 |
| Lugar 3 | Medellín | lugar/CO/antioquia/medellin | ../worker-lugar-3 |
| Lugar 4 | Santa Marta | lugar/CO/magdalena/santa-marta | ../worker-lugar-4 |

Lugar 4 lo ejecuta el coordinador en checkout propio; los otros tres son subagentes. La capacidad del entorno es cuatro agentes incluyendo coordinador, no cuatro subagentes adicionales.

Antes de entregar: ejecutar `python3 crecimiento-colombia/scripts/validar-ramas-workers.py` desde el checkout del plan. Verifica rama/checkout únicos y que cada diff desde la base de despacho `75d8d16` contenga únicamente su carpeta de entrega y manifiesto/proceso propio. No cambia Git. La comprobación es local, no se exige checkout de los otros workers en GitHub CI.

Los commits de un destino no se fusionan en la rama de otro. Cada PR de destino debe apuntar a la rama del plan mientras esta no se incorpore a main; publicación, merge y aprobación de contenido son decisiones separadas. Ningún worker se reasigna al siguiente destino hasta completar y recibir aprobación explícita de la entrega anterior.
