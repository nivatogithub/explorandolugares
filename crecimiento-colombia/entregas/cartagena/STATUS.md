# Cartagena · selección de ocho · revisión vigente

Rama exclusiva `lugar/CO/bolivar/cartagena`. Destino CO-13-cartagena. No publicación, panel, contacto comercial, siguiente destino ni generación IA en esta corrección.

## Decisiones recibidas y preservadas

Se recibió el exportado original del propietario para revisión `3fab25acd916eb8cb4e7b2ea71bbdc21b2eda6e7ca23396bfe42adb7dd183b5a`:116 decisiones,22 aprobadas,93 pendientes y1 solicitud de corrección. Se comprobó hash de revisión completo, cada ID/alcance y el hash canónico de cada valor del manifiesto original. El exportador antiguo no incluía componenthash individual; su vinculación se deriva del manifiesto íntegro que identifica esa revisión, sin fingir que ese campo venía recibido. Bytes originales y snapshot116 están en `historial/`.

Quince aprobaciones intactas siguen activas: portada original1, historia3 idiomas, San Felipe foto/textos3 idiomas, Getsemaní textos3 idiomas y MUHCA foto/textos3 idiomas. Otras siete siguen registradas en su versión histórica: Torre del Reloj3 y Oro Zenú ES pasan a investigación secundaria; La Popa3 cambian porque ahora describen claustro en vez de duplicar portada. No se transfieren aprobaciones a textos agrupados o modificados. La nueva foto de Getsemaní permanece pendiente; la foto anterior solicitada a corregir no está en paquete vigente.

`decisiones-usuario.json` conserva todas las decisiones/comentarios. `aprobaciones-vigentes.json` expone IDs, hashes y vinculación originalSHA+uso para las referencias aprobadas. La UI muestra «Ya aprobado» únicamente para componentes válidos y no pide repetirlos. Nuevas exportaciones incluyen también hash individual.

La instrucción de CTG-01 «hay que hacerla con ai siguiendo el skill de iimagnes que tenemos» autoriza condicionalmente esa ilustración. Se registra aparte que su ejecución está aplazada: primero corregir selección máximo8 según el mensaje actual. No se revoca esa autorización ni se inventa aprobación de la ilustración final. No se generó ninguna.

## Selección principal (ocho)

1. Centro histórico y murallas: patrimonio urbano; agrupa Torre del Reloj y Portal de los Dulces. San Diego se explica como barrio en ES/EN/PT-BR. Foto nueva local de murallas2016.
2. Castillo de San Felipe de Barajas: referencia y textos aprobados intactos.
3. Getsemaní: textos aprobados; referencia nueva Joe Ross2016, casas coloridas y calle sin personas dominantes, pendiente.
4. Islas del Rosario: incorporadas como dimensión insular fuera del núcleo urbano. Referencia específica del sector Cocolise, Isla Grande,2016, sin implicar alianza ni acceso al establecimiento.
5. Playa Blanca · Barú: península al sur de Cartagena, distinta del archipiélago; referencia aérea específica2019. Fuente institucional de acceso/ubicación conservada.
6. Cerro y convento de La Popa: claustro2011 distinto de panorama de portada; textos nuevos pendientes.
7. Museo Histórico de Cartagena: foto y textos aprobados intactos.
8. La Boquilla: territorio pesquero afrocaribeño y paisaje local; referencia2020 licenciada, pendiente de aprobación.

La selección completa sigue como propuesta, no aprobación inferida. Los19 sitios investigados no se borran: los11 restantes quedan secundarios, agrupados o pendientes. Nueve actividades permanecen aparte (2 editoriales,4 ofertas documentadas,3 candidatos ocultos), con bloqueos específicos de fotografías/permisos. Una oferta pública de operador no certifica alianza, vigencia, registro, precios, disponibilidad ni permiso operativo.

## Fotografías y skill

Quince originales conservados,30 derivados WebP técnicos reproducibles; solo nueve activos distintos se usan en paquete de referencias (portada+8 fichas). Son referencias para revisión, NO ilustraciones finales. Fotografías nuevas inspeccionadas visualmente; autores, fecha, originales, licencia comercial/adaptación, hashes, crédito y ShareAlike en manifiesto. El agente verifica esos permisos; se retiraron casillas administrativas de aprobación de licencia. Esto no elimina permisos ni aprobación humana del uso visual.

Rechazos y alternativas conservados: murallas2006 desenfocadas; Getsemaní anterior solicitado a corregir; Angosto solo letrero; Rosario9 showdelfines; Rosario140 sin isla individual identificada; Getsemaníbalcones2012 con personas en primer plano. No se presentan como propuestas nuevas aprobadas. `imagenes/referencias-descartadas.json` registra motivos.

Se leyó el skill existente `.agents/skills/imagenes-destinos/SKILL.md`, guías y procedimiento coordinador actualizado. Primero revisión de originales/textos, luego ilustración editorial fiel y segunda revisión de imagen final. Esta corrección no ejecuta esa fase.

## Paquete e integración

`paquete-v2.json` y ZIP actuales contienen los8 atractivos seleccionados y9 fotografías de referencia distintas. El ZIP previo de3 atractivos se conserva en `historial/cartagena-v2-parcial-obsoleto.zip`; no es entrega vigente. `paquete-estado.json` declara referencia-only, sin importación administrativa y con integración final bloqueada hasta imágenes finales aprobadas. El paquete pasa validación técnica, pero NO se ofrece como salida IA terminada o publicable.

## Validación y revisión visual

- Validación estática: integridad de originales/derivados, metadatos, presupuestos, fuentes, idiomas, exactamente8 seleccionados, Rosario/Barú, ausencia de duplicado de portada,79 componentes y conservación de22 históricas/15 activas. JavaScript de exportación parseado sin ejecutarlo en navegador.
- PHP real `DestinationPackage::readZip`:8 atractivos y9 imágenes de referencias aceptadas. No se guardó en base de datos.
- Ningún Chrome visible ni headless se ejecutó en esta corrección. El runner antiguo queda deshabilitado y su código/capturas previos archivados como históricos. QA visual actual corresponde a IAB del coordinador; no se reutilizan capturas Chrome116 como evidencia de vista8.
- PR remoto/push pendientes: gh ausente en sesión. No se declara acceso de escritura remoto ni publicación.

Reproducción: `preparar.py` produce referencias técnicas, inventario, `seleccionar.py` y `revision8.py`; `validar.py` comprueba la entrega. Todas las decisiones exactas sobreviven en ledger, y cualquier cambio de texto/uso o de original invalida solo el componente afectado.

## Avance autorizado para PREPROD local

Nueva autorización del propietario: cargar avances incompletos para observaciones en preprod local, sin producción. Preparados `paquete-preprod.zip` y `paquete-preprod.json`, separados del paquete editorial. Incluyen ocho atractivos, nueve fotografías de referencia provisional y tres idiomas; encabezados, presentación y leyendas explican que las ilustraciones finales no se han generado/aprobado.

`reporte-preprod.json` conserva hash de paquete/fuente, contenido y faltantes. Ambos lectores PHP reales (ramaCartagena y checkoutpreprod) aceptan el ZIP. Estadosdraft y fuenteslabel/url comprobados; captions no superan500caracteres. El importador semillaDestinationImporter requiere tablasvacías, por lo que no debe usarse directamente sobre MySQLlocalpoblado. La carga serial y previsualización PHP corresponden al coordinador.

No se tocó preprod compartido, BD, contenedores ni navegador. Final editorial/IA/aprobación/publicación siguen pendientes; cargar este avance local no los da por terminados. El ledger y paquete editorial anterior no fueron modificados.
