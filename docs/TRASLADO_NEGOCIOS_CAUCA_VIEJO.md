# Traslado territorial Jericó → Cauca Viejo

La vista privada `/admin/trasladar-negocios.php`, enlazada desde Destinos, prepara un lote de catorce nombres del inventario importado. La lista y su evidencia están en `backend/app/cauca-viejo-business-transfer.json`.

Cada candidato debe tener una coincidencia nominal única en Jericó o Cauca Viejo y conservar en sus fuentes la referencia RNT del inventario revisado. Se normalizan mayúsculas, tildes y espacios; no se usa similitud aproximada ni se asignan ID inventados. Los registros ausentes, ambiguos, con RNT distinto o con una dirección web en conflicto se omiten para revisión.

El operador puede revisar las fuentes y desmarcar candidatos. Algunas evidencias son históricas o identifican la ubicación mediante la denominación registrada: no acreditan operación actual, renovación vigente del RNT ni autorización de marketing. Las notas de cada candidato explican el alcance de su evidencia.

Posada Dulcinea queda fuera porque la fuente del Hay Festival la sitúa en la cabecera de Jericó. Finca Paraíso Del Cauca, Casa Mamá Inés, Hacienda Malinche y Las Maru quedan pendientes de corroboración inequívoca; una referencia al río Cauca o una coincidencia nominal por sí sola no añade candidatos al lote.

## Aplicación

La acción requiere sesión administrativa, CSRF y confirmación del lote. Si Cauca Viejo no existe, utiliza el alta preparada de destino en borrador. Si existe, conserva su ID y contenido.

El traslado actualiza únicamente `businesses.destination_id` y `updated_at`, en una transacción, conservando ID interno, UUID público, slug, canales públicos y privados, categorías, coordenadas, confirmación de ubicación, cuentas, suscripciones, estados y fuentes. Cada cambio registra antes/después, evidencia y administrador en la auditoría 027. Un fallo cancela todos los traslados del lote; el borrador de Cauca Viejo creado previamente se conserva.

La vista previa tiene una huella de su contenido. Si cambian los registros, se exige revisar de nuevo. Repetir una aplicación no crea fichas ni vuelve a trasladar negocios que ya están asociados al destino.

**Visibilidad:** los negocios trasladados dejan el directorio de Jericó. Si Cauca Viejo sigue en borrador, sus fichas no estarán disponibles públicamente bajo ese destino; no se publica automáticamente ni el destino ni los negocios.

No exige una migración nueva. Requiere la auditoría 027 ya utilizada para actualizaciones masivas. La actualización 030 de categorías múltiples es independiente.

El código desplegado no equivale a haber ejecutado el traslado en producción. El resultado real se muestra después de aplicar el lote desde el panel.
