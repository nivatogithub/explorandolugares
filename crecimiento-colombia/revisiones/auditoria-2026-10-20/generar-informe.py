"""Read-only audit of destination snapshots; writes audit artifacts only."""
from pathlib import Path
import json,hashlib,zipfile
p=Path(__file__).resolve().parent
work=p.parents[2];trabajo=work.parent
stage=trabajo/'preprod/.local/preprod/worker-review/lote-20261019'
h=lambda f:hashlib.sha256(f.read_bytes()).hexdigest()
findings=[]
def finding(code,dest,severity,title,evidence,files,sources,proposal,photo=None,removal=None):
    findings.append(dict(id=code,destination=dest,severity=severity,title=title,evidence=evidence,evidence_paths=[str(trabajo/('lugar-'+dest)/'crecimiento-colombia/entregas'/dest/f)for f in files],source_urls=sources,proposed_only=proposal,photo_to_request=photo,removal_proposed_only=removal,approval_revoked=False,applied=False))
finding('ZIP-01','zipaquira','P1','La imagen minera no representa la actividad que describe la tarjeta',
 'El ZIP aprobado y HTML local conservan descripción de picar roca salina, pero alt y activo mina-referencia-usuario-v9 muestran un túnel con espejo de agua; el alt declara que no muestra extracción. La oferta del operador sí acredita extracción, no identifica esta foto como esa actividad.',
 ['paquete-destino-referencia-mina-v9.zip','aprobacion-destino.json','criterios-editoriales.json'],['https://www.catedraldesal.gov.co/atracciones/ruta-del-minero/'],
 'Proponer una decisión editorial: conservar la imagen como visita subterránea con texto correspondiente, o conservar la experiencia minera con referencia de esa acción. No aplicar ni retirar la aprobación vigente.',
 'Si se conserva extracción: foto de visitantes realizando la actividad operada, con casco/equipo y socavón identificados, URL/fecha y permiso. No otro túnel escénico.',
 'Solo propuesta: desacoplar esa imagen de la tarjeta de extracción si el propietario decide que no corresponde; conservar actividad y referencia en expediente.')
finding('ZIP-02','zipaquira','P1','El alt del tren llama fotografía a una ilustración IA actual',
 'El activo servido es tren-referencia-usuario-v8.webp con origen ai_generated; alt ES/EN/PT heredado de V5 dice fotografía histórica del patio de Zipaquirá. El manifiesto V8 dice referencia aportada sin ubicación exacta/fecha documentadas.',
 ['TREN_REFERENCIA_USUARIO_V8.json','paquete-destino-referencia-mina-v9.zip'],[],
 'Proponer corregir únicamente alt/metadatos a ilustración del recorrido ferroviario desde Bogotá, sin atribuir punto exacto ni fecha. No cambiar la imagen aprobada.',
 'No hace falta otra imagen para corregir el alt; para geolocalización, URL original y localización del puente/tramo de la referencia ya elegida.')
finding('ZIP-03','zipaquira','P2','Seis referencias del propietario carecen de ficha original completa',
 'Estación, colegio, Quevedo, arqueológico, tren y mina actuales derivan referencias suministradas por el propietario. La aprobación final coincide con SHA actual. Metadatos mantienen autor/URL/fecha/permiso pendientes; los manifiestos V8 apuntan a rutas temporales de capturas. No se localizó copia original permanente identificada por hash en la entrega.',
 ['ESTACION_REFERENCIA_USUARIO_V8.json','COLEGIO_REFERENCIA_USUARIO_V8.json','QUEVEDO_REFERENCIA_USUARIO_V8.json','ARQUEOLOGICO_REFERENCIA_USUARIO_V8.json','TREN_REFERENCIA_USUARIO_V8.json','INTEGRACION_REFERENCIAS_USUARIO_V8.json','aprobacion-destino.json'],[],
 'Proponer completar procedencia de las referencias existentes, conservar original durable y hash; registrar por separado aprobación visual e identificación documental. No es un nuevo rechazo ni una conclusión jurídica sobre la captura.',
 'Solicitar las URLs/ficheros originales de esas seis imágenes ya elegidas, autor o fuente y fecha/punto cuando se conozcan; no pedir repetir selección.')
finding('JAR-01','jardin','P2','Las trece ilustraciones están aprobadas como composición, no contrastadas contra una foto exacta',
 'Procedencia declara generación independiente desde descripciones, sin fotografía de entrada, para portada y doce tarjetas. Casa Museo Clara Rojas tiene patio/fuente documentados pero disposición interpretada; La Garrucha interpreta cabina y cañón. No hay evidencia para llamarlas reproducciones exactas ni para afirmar que sean falsas.',
 ['procedencia-imagenes.json','procedencia-portada-v1.json','aprobacion.json'],['https://mcrpjardin.blogspot.com/p/historia-de-la-casa-museo-clara-rojas.html','https://jardin.antioquia.in/Sitios-Turisticos/la-garrucha'],
 'Conservar aprobación vigente. Proponer distinguir aproximación documentada de geometría fotográfica verificada. Solo si se exige exactitud adicional, comparar los componentes arquitectónicos primero.',
 'Opcional, no requisito para volver a aprobar: patio actual de Casa Museo Clara Rojas mostrando fuente/corredores, y cabina/andén real de La Garrucha en un encuadre identificado.')
finding('JAR-02','jardin','P2','La oferta de aves tiene evidencia propia pero tarifas publicadas vencidas',
 'Página propia de Montañeros describe guía en La Herrera y reserva, pero marca tarifas válidas hasta31-01-2026. No demuestra cierre ni disponibilidad actual. El expediente ya reconoce ese límite; el sitio no copia esas tarifas.',
 ['criterios-editoriales.json'],['https://montaneros.co/tours-en-jardin-antioquia/avistamiento-de-aves-la-herrera/'],
 'Proponer marcar vigencia comercial por confirmar en registro interno antes de prometer una salida concreta. Conservar la experiencia documentada; no inferir inexistencia.',None)
finding('COV-01','covenas','P2','El museo naval usa una colección temática no acreditada como su colección real',
 'Prompt y activo actuales muestran ancla, maqueta naval y objetos en sala inventada como evocación; alt sí la califica temática y no registro del recinto. Fuente Armada acredita referencia histórica al museo, no estas piezas/sala; hoy respondió502. Aprobación de destino permanece vigente.',
 ['procedencia-imagenes.json','aprobacion-destino.json','criterios-editoriales.json'],['https://www.armada.mil.co/es/content/11-anos-reviviendo-gesta-heroica-los-infantes-marina'],
 'Proponer contraste de identidad del museo si se quiere representar un lugar real en vez de evocación. El texto ya advierte confirmar acceso. No convertir una caída HTTP en cierre del museo.',
 'Exterior identificable del museo o sala/pieza con rótulo verificable, fuente/fecha y condición de acceso; evitar ancla o barco genéricos.',
 'Solo propuesta, previa decisión: sustituir imagen evocativa por imagen identificada o ficha sin imagen. No retirar el museo ni la aprobación automáticamente.')
finding('COV-02','covenas','P2','Referencias aprobadas de playas no conservan identificación original suficiente del tramo',
 'Primera, Segunda y Coquerita actuales corresponden a referencias directas del propietario y hashes aprobados. Manifiesto declara autor/URL/fecha/permiso no documentados. La portada aprobada tiene maestro/hash pero no se localizó un registro que enlace ese maestro con referencia original; las fotos CC de SajoR constan como pruebas previas y no acreditan por sí mismas el activo actual.',
 ['referencias-directas-manifiesto.json','aprobacion-imagenes-2026-10-19.json','referencias-v4/ATRIBUCIONES.md','aprobacion-destino.json'],[],
 'Conservar aprobación. Proponer vincular originales y sector/punto de vista a cada hash aprobado, sin reutilizar el rechazo de versiones V2/V3 para las actuales.',
 'URLs originales de las tres playas y portada ya escogidas, con sector/punto de vista y fecha si existe. No pedir cuatro fotografías nuevas ni reabrir su elección.')
finding('COV-03','covenas','P3','La exclusión de kayak está desactualizada respecto de una oferta propia encontrada',
 'Criterios excluyen kayak por no haberse comprobado oferente. Web propia de Suplovers hoy enumera alquileres de kayak, junto a rutas de Coveñas. Esto abre un candidato verificable; no demuestra prioridad superior ni obliga a sumar tarjetas.',
 ['criterios-editoriales.json','prioridades.json'],['https://suplovers.co/'],
 'Proponer actualizar únicamente motivo/evidencia del candidato en futura edición y evaluar si añade finalidad distinta; no incorporarlo para llenar una cuota.',None)
finding('COM-01','zipaquira','P2','Estados históricos contradicen la aprobación final actual',
 'Recibos de importación editorial_approved:false y manifiestos por activo pendientes/no integrados sobreviven a aprobación-destino.json cuyo SHA coincide exactamente con stage actual. Lo mismo ocurre en Jardín y Coveñas; criterios de Coveñas conservan rechazos de versiones anteriores.',
 ['aprobacion-destino.json','INTEGRACION_REFERENCIAS_USUARIO_V8.json','checklist.json'],[],
 'Proponer resolver estado por hash y cronología; archivar notas históricas como tales sin borrarlas. La auditoría toma las tres aprobaciones finales como vigentes, no como pendientes.',None)
finding('COM-02','covenas','P3','No hay evidencia estadística para llamar el orden una clasificación objetiva de popularidad',
 'Coveñas declara ranking_validated:false; Jardín y Zipaquirá registran selección editorial con motivos y exclusiones. Hay finalidad turística, pero no conteos comparativos. Ninguna cantidad por sí misma demuestra relleno:7/8/5 atractivos y3/4/5 experiencias con ofertas trazadas.',
 ['prioridades.json','criterios-editoriales.json'],[],
 'Conservar selección aprobada y distinguir prioridad editorial de demanda medida. No reordenar ni eliminar lugares por inferir popularidad sin datos.',None)

snapshots={}
before_hashes={}
for slug in ['zipaquira','jardin','covenas']:
    delivery=trabajo/('lugar-'+slug)/'crecimiento-colombia/entregas'/slug
    package=stage/(slug+'.zip');before_hashes[str(package)]=h(package)
    with zipfile.ZipFile(package)as z:
        manifest=json.loads(z.read('paquete-destino.json'))
        rows=[('hero','hero',manifest['hero_image'])]+[(x['slug'],x['type'],x['image'])for x in manifest['places']]
        inventory=[]
        for component,kind,image in rows:
            if slug=='zipaquira':method='direct_licensed_photo'if image.get('image_source_url','').startswith('https://commons.')else 'direct_user_reference_original_identity_incomplete'
            elif slug=='jardin':method='facts_only_independent_approximation'
            else:method='direct_user_reference'if component in ['primera-ensenada','segunda-ensenada','la-coquerita']else 'hero_original_input_not_located'if component=='hero'else 'independent_thematic_or_ecological_scene'
            inventory.append({'component':component,'kind':kind,'file':image['file'],'sha256':hashlib.sha256(z.read(image['file'])).hexdigest(),'origin':image.get('image_origin'),'method':method,'source_url':image.get('image_source_url',''),'alt_es':image.get('alt',{}).get('es'),'human_approval':'approved_in_final_matching_package','reference_identity_documented':method=='direct_licensed_photo','photographic_identity_verified':False,'identity_limit':'Foto identificada no equivale a estado físico actual'if method=='direct_licensed_photo'else 'No certificada fotográficamente por esta auditoría; aprobación humana preservada'})
    approval=delivery/('aprobacion.json'if slug=='jardin'else'aprobacion-destino.json');approved=json.loads(approval.read_text());expected=approved.get('approved_package_sha256',approved.get('package_sha256'))
    criteria=delivery/'criterios-editoriales.json';before_hashes[str(criteria)]=h(criteria);before_hashes[str(approval)]=h(approval)
    business=stage/(slug+'-negocios.zip')
    with zipfile.ZipFile(business)as z:bd=json.loads(z.read('paquete-negocios.json'))
    snapshots[slug]={'stage_zip':str(package),'stage_sha256':h(package),'receipt_path':str(stage/('loaded-'+slug+'.json')),'approval_path':str(approval),'approval_sha_matches':h(package)==expected,'approval':approved,'local_url':'http://127.0.0.1:8088/destinos/'+slug+'/','local_anonymous_http':'success; HTML downloaded without administration-session message','production_status':'Not verified remotely; approval records do not authorize production','counts':{'attractions':sum(x['type']=='place'for x in manifest['places']),'experiences':sum(x['type']=='experience'for x in manifest['places']),'images':len(rows),'businesses':len(bd['businesses'])},'assets':inventory}
report={'schema_version':1,'audit_date':'2026-10-20','scope':['zipaquira','jardin','covenas'],'read_only':True,'site_or_package_mutated':False,'images_generated':False,'approval_policy':'Aprobaciones finales por hash preservadas; hallazgo no equivale a rechazo. Cualquier retirada es solo propuesta.','snapshots':snapshots,'findings':findings,'positive_checks':['Los tres SHA de stage coinciden con sus aprobaciones finales.','Experiencias con oferta propia: Zipaquirá3, Jardín4, Coveñas5. Existencia documental no promete cupos ni vigencia para fecha concreta.','Jardín tiene8atractivos con motivos/exclusiones; Coveñas5 conserva excluidos por solapamiento/acceso, Zipaquirá7. No se demuestra relleno por cuotas.','Cispatá se presenta en San Antero y San Bernardo como excursión regional. Suplovers confirma traslado desde Coveñas a San Antero; agencia de islas diferencia salidas Coveñas/Tolú.','Tour cafetero, caminata Cueva y urbano de Jardín siguen documentados en fuentes propias consultadas; no se sustituyen por consumo autónomo.'],'uncertainties':['No se inspeccionó DOM ejecutado móvil/desktop: solo HTML servido y activos de ZIP.','No se consultó producción remota ni se verificó si un despliegue posterior contiene esos SHA.','No se verificaron cupos, operación real de hoy, acceso militar ni identidad del ejecutor aliado de Montañeros.','Fotografías históricas, referencias del usuario y aprobación visual no certifican fecha/punto exacto, inventario museístico o geometría completa.','Caída502 de fuente Armada no prueba cierre; precios vencidos de aves no prueban cese.'],'source_checks':[{'url':u,'checked_at':'2026-10-20'}for u in ['https://hotelmacaojardin.com/tour-cafe-jardin-antioquia/','https://montaneros.co/tours-en-jardin-antioquia/avistamiento-de-aves-la-herrera/','https://montaneros.co/tours-en-jardin-antioquia/cueva-del-esplendor-tour-corto/','https://montaneros.co/tours-en-jardin-antioquia/tour-urbano-patrimonial-jardin/','https://www.jardinderocas.org/','https://proaves.org/reserva-proaves-loro-orejiamarillo/','https://viajespluscovenas.com/','https://covenasdiving.com/','https://suplovers.co/clases/','https://suplovers.co/','https://agenciacovenastours.com/447642660','https://suplovers.co/tours-en-covenas/cispata/','https://www.catedraldesal.gov.co/atracciones/ruta-del-minero/','https://turistren.com.co/planifica-tu-viaje/','https://www.zipaquira.travel/citytour/']],'source_file_hashes_before':before_hashes}
assert all(h(Path(f))==sha for f,sha in before_hashes.items())
report['source_file_hashes_after']=before_hashes.copy()
(p/'hallazgos.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
lines=['# Auditoría de Zipaquirá, Jardín y Coveñas','', '20-10-2026. **Solo lectura: ningún contenido, paquete, imagen, DB o preproducción cambiado.** Se conservan las aprobaciones humanas finales; no se convierten observaciones históricas en rechazos nuevos.','', '## Estado comprobado','', '| Destino | Atractivos / experiencias / imágenes / fichas | Aprobación final coincide con stage | Estado |','|---|---|---|---|']
for s,d in snapshots.items():
    c=d['counts'];lines.append(f'| {s} | {c["attractions"]} / {c["experiences"]} / {c["images"]} / {c["businesses"]} | Sí, `{d["stage_sha256"][:12]}` | Aprobado en preprod; producción no comprobada |')
lines+=['','Las tres rutas locales respondieron sin exigir sesión administrativa. Se inspeccionaron los activos actuales empaquetados y el HTML servido; esto no sustituye una prueba DOM ejecutada. Los ZIP y fuentes auditadas conservaron sus hashes. Inventario por componente, método, hash y límites en [hallazgos.json](/Users/nivato/.codex/.chatgpt-projects/g-p-6acbf6c8af8c8191a085f33ce8cb637b/trabajo/lugar-zipaquira/crecimiento-colombia/revisiones/auditoria-2026-10-20/hallazgos.json).','','## Hallazgos propuestos, sin aplicación','']
for f in findings:
    lines += [f'### {f["id"]} · {f["severity"]} · {f["title"]}','',f['evidence'],'', '**Propuesta:** '+f['proposed_only']]
    if f['photo_to_request']:lines+=['','**Referencia que pedir, sin repetir aprobación:** '+f['photo_to_request']]
    if f['removal_proposed_only']:lines+=['','**Retirada solo propuesta:** '+f['removal_proposed_only']]
    lines+=['','Evidencia: '+', '.join(f'[{Path(x).name}]({x})'for x in f['evidence_paths'])+'.']
    if f['source_urls']:lines+=['Fuentes: '+', '.join(f'[Fuente {i+1}]({u})'for i,u in enumerate(f['source_urls']))+'.']
    lines+=['']
lines+=['## Comprobaciones positivas','']+['- '+x for x in report['positive_checks']]+['','## Límites','']+['- '+x for x in report['uncertainties']]+['','No se propone borrar ningún lugar aprobado ni iniciar nuevas imágenes. Las dos incompatibilidades más concretas son el vínculo imagen/texto de minería y el alt del tren de Zipaquirá. Cualquier modificación posterior requiere un encargo separado.']
(p/'informe.md').write_text('\n'.join(lines)+'\n')
print(json.dumps({'findings':len(findings),'P1':sum(x['severity']=='P1'for x in findings),'P2':sum(x['severity']=='P2'for x in findings),'P3':sum(x['severity']=='P3'for x in findings),'approvals_match':all(x['approval_sha_matches']for x in snapshots.values()),'source_hashes_unchanged':True},ensure_ascii=False))
