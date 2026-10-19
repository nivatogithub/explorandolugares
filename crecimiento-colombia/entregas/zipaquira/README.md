# Zipaquirá · entrega editorial privada

Worker **Lugar 1**, rama `lugar/CO/cundinamarca/zipaquira`, base `8ff9dde`, 19-10-2026.

## Qué existe realmente

- 8 atractivos priorizados, 5 experiencias y textos breves completos ES/EN/PT.
- Historia en un solo párrafo por idioma, sin imagen ni motivos repetidos.
- 30 fichas gratuitas: 6 alojamientos, 16 restaurantes/cafés y 8 comercios artesanales; nombre, categoría y fuente institucional fechada. Sin descripciones, fotografías, contactos deducidos, alianzas o verificación operativa ficticia.
- Manifiesto de destino v2 compatible con el validador nativo; las 14 rutas de imagen están **pendientes**, no son archivos existentes.
- ZIP del directorio con manifiesto `business-import-v1`. Es estructuralmente válido; aún no se importó contra MariaDB y requiere que el destino exista.
- Revisión editorial HTML independiente, con faltantes explícitos. No es la página real de preproducción ni una prueba de integración.

## Qué falta

**0/14 ilustraciones generadas o aprobadas.** Faltan portada, ocho atractivos y cinco experiencias. Se conserva el estilo EL-SEMI-REALISTA-1: tinta/gouache semirrealista, formas orgánicas y colores naturales, sin filtro amarillo. Los encargos deben completar contraste visual específico antes de generar. No se descargaron fotografías nuevas ni se subieron referencias de terceros al generador.

El ZIP de destino exige una imagen por componente (`backend/app/DestinationPackage.php`, `imageReferences`). No se produjo un ZIP incompleto con imágenes genéricas o archivos inexistentes. El manifiesto pasa el contrato, **pero el destino todavía no es importable ni publicable**.

También faltan revisión humana, integración coordinada en preproducción, comprobación del diseño real, mapas exactos de los componentes y revisión visual/idiomática final. Ninguna aprobación histórica de otro destino se transfiere aquí.

## Top8 propuesto

1. Catedral de Sal.
2. Plaza de los Comuneros.
3. Catedral Diocesana.
4. Estación del Tren.
5. El Colegio de Gabo.
6. Plaza de la Independencia.
7. Casa Museo Quevedo Zornoza.
8. Museo Arqueológico.

Es orden **editorial razonado**, no ranking oficial ni estadística de afluencia. Se contrastó la guía turística municipal de 2026 con ProColombia, Gobernación, Diócesis y operadores institucionales. La auditoría documenta puntuación, fuentes y exclusiones. El centro histórico no se añade como una novena tarjeta del mismo recorrido; el Palacio Municipal queda contextualizado en Comuneros. Se distinguieron dos recintos religiosos genuinamente diferentes, no ocho iglesias de relleno. Nemocón y Jaime Duque son otros municipios.

El Colegio de Gabo está documentado históricamente; no se garantiza apertura actual. La presencia en una guía tampoco demuestra horarios ni disponibilidad vigente de museos, talleres o servicios. No se publican precios, duración, reservas ni calendarios no contrastados.

## Experiencias

Ruta del Minero y viaje en Tren de la Sabana son ofertas documentadas de operadores; horarios, restricciones, acceso y tarifas deben consultarse con ellos. Recorrido histórico, sabores locales y artesanía son propuestas editoriales autónomas, sin prometer un proveedor contratado o un taller reservable. La estación (edificio) y el viaje ferroviario tienen roles distintos y requerirán imágenes diferentes. No confundir el santuario subterráneo con la Catedral Diocesana de la plaza.

## Procedimiento y archivos

- `preparar.py`: reproduce todos los JSON y la revisión textual sin red, servidor ni base.
- `validar.py`: comprueba traducciones, límites de texto, Top8, estados, nombres únicos y contratos PHP nativos; guarda hashes en `qa.json`.
- `fuentes.json`: URLs primarias, alcance y fecha.
- `auditoria-top8.json`: selección/exclusiones y clasificación.
- `paquete-destino.borrador.json`: destino v2, condicionado a completar imágenes.
- `paquete-negocios.{json,zip}`: directorio independiente, 30 fichas en borrador al importar.
- `encargos-visuales.json`: 14 usos exactos, estado y fuentes; ningún activo aprobado.
- `checklist.json`: estado real por componente.
- `revision-editorial.html`: lectura privada de textos en los tres idiomas.

No se modificó preproducción, producción ni otra rama; no se hicieron contactos comerciales. El próximo paso no requiere credenciales del propietario: completar los encargos visuales con evidencia y someter la primera portada concreta a revisión, continuando integración editorial por separado.
