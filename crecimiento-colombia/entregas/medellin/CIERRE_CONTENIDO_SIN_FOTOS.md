# Medellín · cierre de revisión textual sin fotos

Fecha: 17/10/2026. Subtarea de textos y clasificación preparada para revisión; el destino completo sigue pendiente. No hubo búsquedas, descargas ni generación de imágenes.

## Qué quedó revisado

ES/EN/PT: identidad, movilidad, 28 borradores de lugares, experiencias y 10 fichas de directorio. Se corrigieron repeticiones, etiquetas de enlaces y lenguaje interno. Recinto y actividad tienen roles distintos y relaciones por ID. No se prometen tours, inscripciones, cupos o RNT vigentes.

Metro/Metrocable queda como movilidad y experiencia autónoma. Los ocho lugares vigentes mantienen IDs, orden y fotos. La propuesta histórica de auditoría incluía Metro como puesto 3: no debe aplicarse automáticamente porque sería 7 lugares + 1 experiencia. La selección física requiere nuevo corte y decisión humana; no se rellenó por variedad.

## Conteos reales

| Componente | Cantidad |
|---|---|
| Lugares físicos en borrador | 28 |
| Lugares principales vigentes, sin cambio | 8 |
| Experiencias visibles en borrador | 9 |
| Propuestas autónomas | 7 |
| Ofertas de operador documentadas, vigencia pendiente | 2 |
| Antecedentes externos ocultos | 2 |
| Eventos actuales verificados | 0 |
| Fichas gratuitas propuestas | 10 |
| Negocios comerciales | 6 |
| Instituciones culturales | 4 |
| Experiencias con referencia histórica de recinto | 5 |
| Experiencias sin referencia | 4 |
| Referencias repetidas del Top8 | 3 |
| Referencias contextuales restantes | 2 |
| Fotos de experiencia aprobadas | 0 |

Las cinco referencias históricas de recintos no demuestran servicios ni cinco actividades finalizadas: tres repiten imagen del Top8 y dos son contexto sin aprobación de actividad. Las cuatro experiencias sin referencia son Avenida Jardín, visita EPM, City Tour y recorrido Metro/Metrocable. Ninguna foto se reasignó o modificó.

## Faltantes y límites

- URLs del propietario: pendientes; búsqueda automática detenida
- Imágenes de actividades: 4 sin referencia + 3 repetidas del Top8 + 2 contextuales que requieren revisión; 0 aprobadas
- Negocios: Presencia web revisada el 17/10/2026; no cupos ni RNT verificados.
- Operadores: EPM programa histórico; City Tour oferta web, RNT 213192 declarado no registro confirmado.
- Ubicaciones exactas: Accesos y coordenadas definitivos pendientes para 28 recintos/distritos; describir ubicaciones no equivale a coordenada validada.
- Revisión humana: Textos/idiomas, roles, corte Top8, usos de imágenes y posterior salida IA por componente.
- Prueba visual: IAB no disponible en este worker; coordinación verificará vista local. QA estático realizado.

## Integración posterior

1. Leer contenido.json, experiencias.json, roles-contenido.json y eventos.json como borradores. No importar automáticamente.
2. Mantener los 8 ID actuales de principales-v3.json y sus fotos hasta decisión humana sobre selección; no aplicar auditoría histórica sin corregir rol de Metro.
3. Integrar movilidad/experiencia Metro en sección propia, sin crear novena tarjeta ni registro físico para contar cobertura.
4. Usar place_ids para enlazar experiencia y recinto. Una visita no suma otro destino/lugar; eventos requieren fecha concreta.
5. No reutilizar foto de lugar como imagen propia de experiencia sin decisión específica. Mantener referencias históricas; propietario proporcionará URLs.
6. Actualizar paquete futuro desde texto corregido solo al resolver revisión e integración; ZIP v3 conserva texto anterior y fotos rechazadas, no es cierre final.
7. No modificar aprobaciones; todo texto cambiado requiere revisión de su nuevo hash. No generar IA, importar BD/preprod ni publicar.

## Evidencia de conservación

El JSON conserva hashes de selección, ledgers, auditoría, manifiestos y todos los originales/derivados. Los archivos de contenido modificados llevan hashes antes/después y requieren revisión; una aprobación previa no se traslada al texto nuevo. Paquete y ZIP anteriores se conservan, sin importación a BD/preproducción/producción. Los lotes fotográficos no ejecutados quedan inactivos con salida inmediata; no son trabajo en segundo plano.
