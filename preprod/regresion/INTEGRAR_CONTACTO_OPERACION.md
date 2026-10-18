# Integración de pruebas PQRS y reclamación controvertida

El módulo no ejecuta pruebas al importarse. Reutiliza las guardas, reporte,
cliente HTTP, consultas y captura de errores del runner. No se ejecutó contra
la base compartida durante su desarrollo; comprobación estática Python aprobada.

## Cambios que debe integrar el responsable del runner

1. Sustituir el `pending('CT-03', ...)` antiguo por:

```python
from contacto_operacion import run as run_contact_operation
run_contact_operation(globals())
```

2. Sustituir el callable antiguo de NG-05 (o su cuerpo), conservando una sola
   declaración del caso y su dependencia NG-00:

```python
from contacto_operacion import disputed_claim
case('NG-05', 'Reclamación controvertida queda en revisión sin cambiar propietario',
     lambda: disputed_claim(globals()))
```

No ejecutar ambos NG-05: el segundo sería una solicitud duplicada. El nuevo caso
usa los dos usuarios y negocio ocupado ya creados por NG-00; el runner conserva
la propiedad de su limpieza, incluidas evidencias privadas subidas por HTTP.

## Qué se comprueba

- Cuatro tipos PQRS por HTTP anónimo: sugerencia, petición, queja y reclamo.
- Persistencia de tipo, contenido, consentimiento, estado y referencia entregada.
- Referencia no expone mensaje/correo a otro visitante ni se conserva en pantalla.
- CSRF y consentimiento obligatorio; no se envían correos.
- Administrador puede consultar y registrar atención; anónimo/viajero no pueden
  consultar ni modificar solicitudes, comprobando también BD intacta.
- Limpieza `finally` solo de cuatro correos exactos derivados del RUN; nunca LIKE.
- NG-05 verifica marcador disputed_existing, estado in_review, explicación visible,
  propietario/roles/estado del negocio intactos y rechazo de repetición.

Se usan cuatro envíos válidos y uno inválido por consentimiento: la cuota actual
es cinco solicitudes por hora. El POST de CSRF inválido se rechaza antes del
contador. Si existen intentos anteriores, se informa BLOQUEADA; no se borran
contadores ni se desactiva protección para que el pase dé verde.

La activación del esquema debe incluir migración036 antes de ejecutar esta parte.
Las pruebas requieren que dashboard muestre el `review_message` de la solicitud
controvertida; una respuesta200 aislada no se considera suficiente.
