# Revisión local de la primera tanda

Preferencia del propietario: revisar en ventanas de prueba local o por URLs internas; no abrir Chrome para esta revisión.

Índice: http://127.0.0.1:8061/

- Cartagena: http://127.0.0.1:8061/cartagena/
- Bogotá: http://127.0.0.1:8061/bogota/
- Medellín: http://127.0.0.1:8061/medellin/
- Santa Marta: http://127.0.0.1:8061/santa-marta/

El servidor solo escucha en 127.0.0.1 y permite lectura de los archivos de entrega de los cuatro checkouts. No publica, importa, modifica datos ni sirve el resto del repositorio. Cada URL sigue consultando la rama/carpeta de su worker; el índice muestra el commit actual. Al corregir un borrador, recargar la ventana para ver los cambios. No mezcla ramas ni crea aprobación implícita.

Inicio desde la raíz de la rama del plan: `python3 scripts/preview-workers.py`. Puede necesitar permiso técnico para escuchar en localhost. El proceso debe permanecer activo mientras el usuario revise; detenerlo con Ctrl+C cierra las URLs. Puede cambiarse el puerto mediante `--port`.

Verificación inicial por HTTP realizada: índice y las cuatro páginas devolvieron 200. También respondieron las 11 imágenes locales referenciadas (Cartagena 4, Bogotá 1, Medellín 3 y Santa Marta 3). Esta comprobación no sustituye la revisión visual del usuario ni certifica contenido completo. No se abrió Chrome.
