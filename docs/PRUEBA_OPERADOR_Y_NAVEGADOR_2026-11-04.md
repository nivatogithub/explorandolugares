# Operador y navegador — preproducción local

Fecha: 4 de noviembre de 2026 (Colombia). Entorno: `127.0.0.1:8088` y su MariaDB. No hubo pagos ni comunicaciones externas.

## Operador

El negocio ficticio `c0cd1478-6a85-40a1-a6e9-f3ce97d154af`, ya autorizado y publicado en el ensayo Premium, se preparó con categoría adicional de experiencias. Desde el navegador se seleccionó «Parapente biplaza en Jericó», se creó la orden `EL-6FA4B8C9C40E69E6256D6898` y se envió un comprobante ficticio. La revisión del comprobante y la activación se ejecutaron mediante los servicios de la aplicación en el contenedor; no se verificaron con clics de administrador. El operador apareció en Jericó en vista móvil y de escritorio. Después de activar se observaron los cinco productos, imágenes y canales de la ficha Premium.

Se corrigió la cotización de un complemento añadido al mismo plan: conserva la tarifa histórica de la vigencia y calcula el complemento proporcional. `preprod/probar-cotizacion-operador.php` verifica ese caso con el negocio ficticio y una transacción revertida de MariaDB; también verifica la renovación sin cambios. Se permite activar un complemento aprobado sobre una versión editorial ya publicada si no cambia el plan; una versión editorial pendiente o un cambio de plan siguen requiriendo sus validaciones.

## Navegador y regresión

Se comprobaron inicio de sesión desde el diálogo, nombre junto a idiomas, secciones de cuenta y cierre de sesión, combinando escritorio y móvil. Se comprobó selección de Antioquia en el mapa de cobertura, conservación al cambiar de idioma, contador en inglés, copia de invitación y búsqueda de lugares sin resultados. No se ha demostrado el estado de error del mapa ni hay una automatización visual repetible de todos los criterios de `UI-01`, `UI-02` y `SV-operador`; el informe HTTP los mantiene pendientes.

La regresión pública del paquete actualizado informó 48 aprobadas y 3 pendientes, sin fallos ni bloqueos adicionales. Su resultado sigue siendo «NO LISTO» por los casos visuales. El directorio y los cuatro niveles de suscripción pasaron su comprobación HTTP. El hash del código público servido en esa ejecución es `81bc325f2df3f68c8074d768f82710c0cf9f36ac1bdcdbbdcdc32e981588f7ad`.

Se retiró la generación de enlaces de mapa a partir de coordenadas: solo se muestran enlaces guardados con formato de ficha nombrada. El formato no certifica que un enlace apunte al establecimiento correcto: sigue siendo necesaria la revisión humana del destino. La consulta local halló negocios publicados sin ese formato; debe evaluarse su impacto y verificarse cada ficha antes de producción.

Las imágenes mostradas en portada para Gran Malecón y Primera Ensenada aparecen con créditos de borrador o procedencia pendiente. Gran Malecón registra un hash y `publication_authorized: true` por una orden previa, junto con `draft: true` y revisión pendiente; hay que reconciliar esa evidencia. Primera Ensenada tiene `editorial_approved: false` y `local_preview_only: true`. Es necesario verificar la aprobación y procedencia de las versiones exactas. Tampoco se ha auditado que todos los mecanismos de despliegue excluyan todas las entidades ficticias. El exportador local excluye expresamente los fixtures conocidos, pero esto no acredita los demás caminos. No se autoriza ni certifica un despliegue.
