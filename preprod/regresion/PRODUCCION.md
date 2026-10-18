# Regresión y paridad de producción

## Ejecutores disponibles

`python3 preprod/regresion/paridad_produccion.py` compara HTTP real en localhost:8088 y explorandolugares.com: 27 páginas, cinco lugares en tres idiomas, directorios por nombre completo/categorías/nivel conservando duplicados, títulos, imágenes, CSS, JavaScript por SHA-256, enlaces públicos y rechazo administrativo anónimo. Los UUID pueden diferir porque la importación conservó identificadores existentes en producción. Excluye únicamente los fixtures locales conocidos con prefijo f00d0000. No exige el acceso administrativo local sin contraseña en producción.

Informe: `.local/production/regresion-paridad/resultado.html` y JSON. El comando devuelve 1 mientras falte el recorrido completo; una coincidencia de páginas no se presenta como aprobación de cuentas, pagos o diseño visual. No despliega ni modifica producción.

`./preprod/regresion.sh --solo-publico` ejecuta altas, correo local, activación, contraseña, negocio nuevo, reclamación y comprobantes desde formularios públicos en preproducción. `./preprod/regresion.sh` añade administración y otras comprobaciones. Sus resultados son locales; nunca se presentan como operaciones realizadas en producción.

## Contrato pendiente del ejecutor con escrituras en producción

El informe enumera E2E-01 a E2E-12 y revisión visual como BLOQUEADAS hasta tener ejecución y evidencia del ambiente de producción. No basta con crear cuentas por SQL y marcar como comprobado el registro público.

Preparación requerida por ejecución:

1. Identificador aleatorio único, registro privado de recursos creados y credenciales aleatorias fuera del informe y de Git.
2. Administrador técnico con rol explícito, viajero y persona responsable de negocio; estos dos últimos se registran por el formulario público. Nunca utilizar ni cambiar la clave del administrador real.
3. Negocio técnico sin dueño para reclamar y otro técnico ocupado para probar que no se sustituye su propietario. No reclamar negocios reales para ensayar.
4. Captura privada del mensaje generado por el servicio de correo, limitada a las direcciones técnicas exactas, con vencimiento. Abrir los enlaces reales emitidos por el flujo, comprobar expiración, alteración y un solo uso. No usar Brevo ni interceptar correo de personas reales.
5. Instrucciones de pago simuladas limitadas a esas cuentas; no reemplazar QR, banco, cuenta o llave configurados para usuarios reales. Subir soporte ficticio, inspeccionar el importe calculado por servidor y revisar desde el administrador técnico. No registrar ingreso real ni incluirlo en rentabilidad.
6. Aprobar solicitudes mediante el mismo procedimiento administrativo existente: actualmente exige documentos y revisión. La simulación documental no certifica identidad ni puede presentarse como validación automática de comercio.
7. Probar gratuito, Destacado, Página y Premium y complemento de operador; todas las frecuencias. Mantener negocios técnicos fuera de resultados públicos reales durante el ensayo.
8. Limpiar solo las filas y archivos del identificador de ejecución, verificando que el conjunto original permanezca intacto. Limpieza fallida bloquea el pase. Una interrupción debe permitir recuperar la limpieza a partir del registro privado.

No incorporar una clave en parámetros URL, una autenticación alternativa ni un modo global que habilite simulaciones para cualquier usuario. El proceso comercial, permisos y CSRF deben ser los reales. Crear las tres cuentas en producción sin estos aislamientos todavía no permite ejecutar con seguridad el recorrido solicitado.

## Criterio de pase

Todas las pruebas implementadas y ejecutadas, cero fallos/bloqueos, limpieza confirmada y evidencia visual del mismo candidato. Las simulaciones comprueban integración y estados; entrega efectiva de correo y conciliación bancaria siguen siendo verificaciones externas distintas. Los borradores no se publican para hacer pasar pruebas. La aprobación humana y el flujo preprod → producción → main siguen vigentes.
