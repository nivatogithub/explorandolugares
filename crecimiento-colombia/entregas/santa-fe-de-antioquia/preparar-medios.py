#!/usr/bin/env python3
"""Build optimized draft package from reviewed generation outputs; never deploys."""
import json,pathlib,hashlib,zipfile,shutil,urllib.parse
from PIL import Image,ImageOps
P=pathlib.Path(__file__).resolve().parent
jobs=json.loads((P/'GENERACIONES.json').read_text())
pkg=json.loads((P/'paquete-destino.borrador.json').read_text())
langs=['es','en','pt-BR'];(P/'images').mkdir(exist_ok=True);(P/'maestros').mkdir(exist_ok=True)
records=[]
for j in jobs:
 src=pathlib.Path(j['path']); key=j['key'];master=P/'maestros'/f'{key}.png'
 shutil.copyfile(src,master)
 with Image.open(master) as im:
  im=ImageOps.exif_transpose(im).convert('RGB')
  im.thumbnail((1200,800),Image.Resampling.LANCZOS);im.save(P/'images'/f'{key}.webp','WEBP',quality=82,method=6)
  small=im.copy();small.thumbnail((640,427),Image.Resampling.LANCZOS);small.save(P/'images'/f'{key}-640.webp','WEBP',quality=80,method=6)
 f=P/'images'/f'{key}.webp'
 records.append({'asset_id':'santa-fe-de-antioquia-'+key+'-v1','destination_id':'santa-fe-de-antioquia','role':'hero' if key=='portada' else next(x['type'] for x in pkg['places'] if x['slug']==key),'master':str(master.relative_to(P)),'file':str(f.relative_to(P)),'master_sha256':hashlib.sha256(master.read_bytes()).hexdigest(),'sha256':hashlib.sha256(f.read_bytes()).hexdigest(),'bytes':f.stat().st_size,'generation':{'provider':'OpenAI builtin image_gen','model':'not_exposed','generated_at':'2026-10-19','prompt':j['prompt'],'method':'independent composition; no photograph submitted'},'source_image_inputs':[],'source_facts':'FUENTES.json and ENCARGO_PORTADA.md; additional visual notes in REVISION_VISUAL.md','human_approval':False,'review_status':'draft_pending_owner_review','exact_topographic_reproduction':False})
for k in [pkg['hero_image']]+[x['image'] for x in pkg['places']]:
 k['image_creator']='Ilustración editorial generada con IA para Explorando Lugares'
 k['image_license']='Composición independiente generada con IA; borrador pendiente de revisión editorial humana; procedencia en manifiesto interno.'
 k['image_source_url']='';k['image_license_url']=''
# Names of experience headings translated; proper names preserved.
names={
'paseo-por-calles-y-patios':['Paseo por calles y patios','Walk through streets and courtyards','Passeio por ruas e pátios'],
'conocer-la-filigrana':['Conocer la filigrana','Discover filigree jewellery','Conheça a filigrana'],
'sabores-de-tamarindo':['Sabores de tamarindo','Tamarind flavours','Sabores de tamarindo'],
'recorrido-de-memoria-regional':['Recorrido de memoria regional','Discover regional history','Conheça a história regional']}
for x in pkg['places']:
 for l,n in zip(langs,names.get(x['slug'],[x['translations']['es']['name']]*3)):
  x['translations'][l]['name']=n
  x['image']['alt'][l]={'es':'Ilustración editorial: ','en':'Editorial illustration: ','pt-BR':'Ilustração editorial: '}[l]+n
for x in pkg['places']:
 if x['type']=='place':
  q=x['translations']['es']['name']+' Santa Fe de Antioquia Colombia'
  url='https://www.google.com/maps/search/?api=1&query='+urllib.parse.quote(q)
  for l,label in zip(langs,['Ver ubicación','View location','Ver localização']):
   x['translations'][l]['external_url']=url;x['translations'][l]['external_label']=label
pkg['local_research']['notes']=['Paquete íntegro para importación como borrador: ocho atractivos, cuatro experiencias y trece ilustraciones diferentes. Pendiente revisión humana y ensayo real en preproducción. Las ilustraciones de experiencias y recintos no son reconstrucciones exactas ni operadores contratados.']
(P/'paquete-destino.json').write_text(json.dumps(pkg,ensure_ascii=False,indent=2)+'\n')
(P/'PROCEDENCIA_IMAGENES.json').write_text(json.dumps(records,ensure_ascii=False,indent=2)+'\n')
with zipfile.ZipFile(P/'paquete-destino-santa-fe-de-antioquia.zip','w',zipfile.ZIP_DEFLATED) as z:
 z.write(P/'paquete-destino.json','paquete-destino.json')
 for j in jobs:z.write(P/'images'/f"{j['key']}.webp",f"images/{j['key']}.webp")
thumbs=[]
from PIL import ImageDraw
for j in jobs:
 with Image.open(P/'images'/f"{j['key']}.webp") as im:
  im=im.resize((360,240)); tile=Image.new('RGB',(380,280),'white');tile.paste(im,(10,10));ImageDraw.Draw(tile).text((10,254),j['key'][:45],fill='#172B42');thumbs.append(tile)
sheet=Image.new('RGB',(380*3,280*((len(thumbs)+2)//3)),'#F1F2F4')
for i,t in enumerate(thumbs):sheet.paste(t,((i%3)*380,(i//3)*280))
sheet.save(P/'CONTACTO_VISUAL.jpg',quality=90)
print(json.dumps({'generated':len(jobs),'webp_total_bytes':sum(x['bytes'] for x in records),'destination_zip_bytes':(P/'paquete-destino-santa-fe-de-antioquia.zip').stat().st_size}))
