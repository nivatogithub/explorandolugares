from pathlib import Path
from PIL import Image
import json,zipfile,hashlib
p=Path(__file__).resolve().parent
h=lambda f:hashlib.sha256(f.read_bytes()).hexdigest()
base=p/'paquete-destino-comparacion-v5.zip'
with zipfile.ZipFile(base) as z:d=json.loads(z.read('paquete-destino.json'))
specs=[
 ('colegio-gabo','colegio-gabo-hechos-v6b',{
 'source_urls':['https://zipaquiraturistica.com/la-ciudad/centro-cultural-casa-del-nobel-gabriel-garcia-marquez','https://fontur.com.co/sites/default/files/2020-12/CAPITULO_ZIPAQUIRA.PDF'],
 'observed_image_url':'https://zipaquiraturistica.com/dashboard-zipa-turis/storage/ciudades/17.webp',
 'facts':['Patio central sin jardín ni fuente','Dos pisos con galerías en U y teja de barro','Postes de madera naranja/rojo y estructura azul','Muros blancos con zócalo rojo','Ventanas rectangulares azules con reja a derecha','Puertas rectangulares y tres pequeñas placas circulares al fondo'],
 'prompt_summary':'Patio U de dos pisos, galerías y postes naranja/cobalto, muros blanco/rojo, teja, patio pavimentado sin jardín, puertas y ventanas rectangulares. Prohibidos fuentes, arcos, libros gigantes y hotel genérico.',
 'comparison':'Conserva estructura U/dos pisos, postes, colores y vanos rectangulares. Una corrección ajustó a tres placas y cuatro vanos inferiores; la baranda aún tiene balaustros más torneados y piso más irregular que evidencia. No exactitud de carpintería, separación de vanos o pavimento.',
 'attempts':2,'approval_status':'approximate_proposal_human_review_required'},
 {'es':'Patio del antiguo liceo de Zipaquirá · ilustración aproximada basada en rasgos documentados','en':'Courtyard of the former Zipaquirá school · approximate illustration based on documented features','pt-BR':'Pátio do antigo liceu de Zipaquirá · ilustração aproximada baseada em características documentadas'}),
 ('museo-quevedo','museo-quevedo-hechos-v6',{
 'source_urls':['https://www.zipaquira.travel/casa-museo-guillermoquevedo-zornoza/','https://fontur.com.co/sites/default/files/2020-12/CAPITULO_ZIPAQUIRA.PDF'],
 'observed_image_url':'https://www.zipaquira.travel/wp-content/uploads/2025/12/1-6.jpg',
 'facts':['Calle3 número7-69 identificado por municipio y FONTUR','Una planta blanca bajo teja de barro','Portón central oscuro con jambas de grandes bloques de piedra beige','Ventana alta azul enrejada a cada lado del acceso','Acera estrecha en adoquín junto a calle asfaltada'],
 'prompt_summary':'Encuadre frontal acotado de portón entre dos ventanas azules; jambas segmentadas beige con remates simples, blanco, teja, cadena baja y dos bolardos. Sin patio inventado, segundo piso, jardín, arcos, letras o piezas interiores.',
 'comparison':'Coinciden composición puerta/ventanas, jambas, teja y acera. Se simplificaron placas, molduras, proporciones y cableado; no se representa la totalidad del inmueble ni colección interior.',
 'attempts':1,'approval_status':'approximate_proposal_human_review_required'},
 {'es':'Entrada de la Casa Museo Quevedo · ilustración aproximada basada en rasgos documentados','en':'Entrance to the Quevedo House Museum · approximate illustration based on documented features','pt-BR':'Entrada da Casa Museu Quevedo · ilustração aproximada baseada em características documentadas'})]
assets=[];patch=[]
for slug,stem,facts,alts in specs:
    master=p/'imagenes/maestros'/f'{stem}.png';web=p/'imagenes/web'/f'{stem}.webp'
    im=Image.open(master);im.thumbnail((960,640));im.save(web,'WEBP',quality=83,method=6)
    image={'file':f'images/{stem}.webp','image_origin':'ai_generated','image_creator':'Explorando Lugares · ilustración IA desde hechos documentados; revisión pendiente','image_source_url':'','image_license':'Propuesta IA aproximada; no se subieron fotografías de terceros como entrada','image_license_url':'','alt':alts,'caption':{'es':'','en':'','pt-BR':''}}
    item=next(x for x in d['places']if x['slug']==slug);item['image']=image
    patch.append({'slug':slug,'image':image,'local_web':str(web.relative_to(p))})
    facts.update(component=slug,checked_at='2026-10-20',tool='builtin image_gen',third_party_photo_uploaded=False,reference_file_uploaded=None,master_file=str(master.relative_to(p)),master_sha256=h(master),web_file=str(web.relative_to(p)),web_sha256=h(web),web_bytes=web.stat().st_size,dimensions=list(im.size),human_approved=False)
    assets.append(facts)
raw=(json.dumps(d,ensure_ascii=False,indent=2)+'\n').encode()
(p/'paquete-destino-hechos-v6.json').write_bytes(raw)
with zipfile.ZipFile(base)as old,zipfile.ZipFile(p/'paquete-destino-hechos-v6.zip','w',zipfile.ZIP_DEFLATED)as z:
    z.writestr('paquete-destino.json',raw)
    for ref in [d['hero_image']['file']]+[x['image']['file']for x in d['places']]:
        local=p/'imagenes/web'/Path(ref).name
        z.writestr(ref,local.read_bytes()if any(ref==x['image']['file']for x in patch)else old.read(ref))
(p/'PATCH_SOLO_COLEGIO_QUEVEDO_V6.json').write_text(json.dumps({'destination':'zipaquira','selective_integration_only':True,'preserve_catedral_crop':True,'preserve_train_original_v5':True,'places':patch},ensure_ascii=False,indent=2)+'\n')
report={'schema_version':1,'destination':'zipaquira','date':'2026-10-20','method':'Observación pública para hechos; generación independiente desde ficha, sin cargar las fotos protegidas','policy':'docs/CIERRE_OPERATIVO_DESTINOS.md','assets':assets,'base_zip_sha256':h(base),'candidate_sha256':h(p/'paquete-destino-hechos-v6.zip'),'preprod_imported':False,'production_changed':False,'human_approved':False,'closed_pending':['Estación: no se acreditó encuadre conjunto edificio-vías/tren. CUA no ofrece navegador y Maps/StreetView no fue observado; no generar relación espacial supuesta.','Revisión humana de dos propuestas aproximadas. No certificar carpintería, vanos o pavimento exactos del colegio.'],'cartography_checked':['https://mapcarta.com/es/N761227000'],'cartography_scope':'Nodo ferroviario5.02164,-74.00115 y operador. No prueba orientación de andén ni fachada lateral.'}
(p/'FICHAS_IDENTIDAD_V6.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'candidate_sha256':report['candidate_sha256'],'images':len(d['places'])+1,'assets':[{'component':a['component'],'bytes':a['web_bytes']}for a in assets]},ensure_ascii=False))
