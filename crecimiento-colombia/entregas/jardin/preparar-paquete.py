"""Rebuild only Jardín draft artifacts; no DB access or deployment."""
import hashlib,json,pathlib,zipfile
from PIL import Image,ImageDraw,ImageFont
P=pathlib.Path(__file__).resolve().parent
read=lambda n:json.loads((P/n).read_text())
def save(n,o):(P/n).write_text(json.dumps(o,ensure_ascii=False,indent=2)+'\n')
(P/'maestros').mkdir(exist_ok=True)
for f in (P/'images').glob('*.png'):f.rename(P/'maestros'/f.name)
m=read('paquete-destino.json'); reasons=['Disfruta del patrimonio urbano y la naturaleza andina.','Explore urban heritage and Andean nature.','Conheça o patrimônio urbano e a natureza andina.']
for l,v in zip(['es','en','pt-BR'],reasons):m['destination']['reasons'][l]=[v]
generations=read('generaciones.json');records=[]
notes={
'parque-el-libertador':'Se retiró una cúpula ajena del fondo. El parque/fuente se representan editorialmente; no levantamiento fotográfico exacto.',
'basilica-inmaculada-concepcion':'Dos torres de piedra con agujas metálicas; fachada visible completa. Detalles ornamentales interpretados; requiere revisión humana local.',
'cueva-del-esplendor':'La caída de agua entra por abertura superior; piscina pequeña y roca, sin paisaje fantástico.',
'reserva-jardin-de-rocas':'Gallito de las rocas naranja, cresta discoidal y alas negras con panel gris. No se promete avistamiento garantizado.',
'la-garrucha':'Corregido templo/palmeras y cañón excesivo. Cabina artesanal y ladera; no esquema técnico de operación.',
'camino-de-la-herrera':'Camino de piedra con paisaje rural; no atribuye casa exacta o condición actual del acceso.',
'casa-museo-clara-rojas':'Patio cuadrado, corredores y fuente documentados por museo. Color/disposición fina interpretados; no fotografía de inventario.',
'reserva-loro-orejiamarillo':'Ave verde con parche auricular amarillo; bosque montano y palmas de cera, no ambiente costero.',
'cafe-en-finca':'Corregidos monolito/iglesia inventados; cultivo de café en ladera. No se atribuye finca específica.',
'observacion-aves':'Eliminado templo genérico. Observación distante sin manipular fauna; personas ficticias.',
'caminata-cueva':'Eliminado poblado/templo del valle. Actividad de aproximación; no documenta trazado exacto del sendero.',
'paseo-patrimonial':'Interacción caminando entre arquitectura local, escena independiente; no domicilio preciso.',
'sabores-jardinenos':'Escena de degustación de dulces/café, no menú o restaurante específico.',
'recorrido-en-chiva':'Chiva abierta y carretera rural, sin operador ni ruta/horario ficticio.'}
for g in generations:
 id=g['id'];master=P/'maestros'/f'{id}.png';im=Image.open(master)
 for width in [800,480]:
  out=im.copy();out.thumbnail((width,10000));out.save(P/'images'/f'{id}-{width}.webp','WEBP',quality=77,method=6)
 (P/'images'/f'{id}.webp').write_bytes((P/'images'/f'{id}-800.webp').read_bytes())
 for x in m['places']:
  if x['slug']==id:x['image']['image_creator']='OpenAI · composición independiente ilustrada';x['image']['image_license']='Borrador IA; revisión editorial humana pendiente'
 files=[]
 for file in [master,P/'images'/f'{id}.webp',P/'images'/f'{id}-480.webp']:
  with Image.open(file) as z:wh=list(z.size)
  files.append({'path':str(file.relative_to(P)),'sha256':hashlib.sha256(file.read_bytes()).hexdigest(),'bytes':file.stat().st_size,'dimensions':wh})
 records.append({'id':id,'role':next(x['type'] for x in m['places'] if x['slug']==id),'status':'draft_for_local_review','source':{'url':g['url'],'checked_at':'2026-10-19','role':'factual-description-only'},'method':'independent-composition-no-third-party-image-upload','generation_tool':'builtin image_gen','prompt':g['prompt'],'edit_prompt':g.get('edit_prompt'),'edit_input':'own-generated-draft' if g.get('edit_prompt') else None,'original_generation_file':pathlib.Path(g['path']).name,'rejected_generation_file':pathlib.Path(g['previous_path']).name if g.get('previous_path') else None,'inspection':{'full_image':'inspected','thumbnail':'inspected-on-review-sheet','notes':notes[id],'human_approval':'pending'},'files':files})
save('procedencia-imagenes.json',records)
save('paquete-destino.json',m)
# hero provenance follows moved master
prov=read('procedencia-portada-v1.json')
for x in prov['files']:
 if x['path'].endswith('.png'):x['path']='maestros/'+pathlib.Path(x['path']).name
save('procedencia-portada-v1.json',prov)
# artifact only; it never ships masters or pending alternative sizes
with zipfile.ZipFile(P/'paquete-destino-jardin.zip','w',zipfile.ZIP_DEFLATED) as z:
 z.write(P/'paquete-destino.json','paquete-destino.json')
 for x in [m['hero_image'],*[v['image'] for v in m['places']]]:z.write(P/x['file'],x['file'])
# role-captioned contact sheet for internal visual inspection only
entries=[('PORTADA',m['hero_image']['file'])]+[(('CONOCER' if x['type']=='place' else 'EXPERIENCIA')+' · '+x['translations']['es']['name'],x['image']['file']) for x in m['places']]
sheet=Image.new('RGB',(1008,5*250),(247,248,249));d=ImageDraw.Draw(sheet)
try:font=ImageFont.truetype('/System/Library/Fonts/Supplemental/Arial.ttf',14)
except OSError:font=ImageFont.load_default()
for i,(name,file) in enumerate(entries):
 x=(i%3)*336;y=(i//3)*250;im=Image.open(P/file);im.thumbnail((324,216));sheet.paste(im,(x+6,y));d.text((x+6,y+219),name[:43],fill=(23,43,66),font=font)
sheet.save(P/'revision-imagenes.jpg',quality=90)
check=read('checklist.json');check['components'].update({'hero':'borrador-generado-inspeccionado-no-aprobado','attraction_images':'8-generadas-inspeccionadas-derivados-y-hashes','experience_images':'6-generadas-inspeccionadas-derivados-y-hashes','archive':'ZIP-completo-listo-validacion-nativa','integration':'pendiente-coordinador-no-tocar-preprod','qa_site':'pendiente-coordinador'});save('checklist.json',check)
# Keep sources added during imagery fact-checking
sources=read('fuentes.json')
for id,url in [('museo-propio','https://mcrpjardin.blogspot.com/p/historia-de-la-casa-museo-clara-rojas.html'),('camino-operador','https://otiumtornus.com/circuito-la-cascada-del-amor/')]:
 if not any(v['id']==id for v in sources):sources.append({'id':id,'url':url,'checked_at':'2026-10-19','scope':'Descripción factual consultada; no se descarga ni suministra fotografía de terceros.'})
save('fuentes.json',sources)
print(json.dumps({'images':len(entries),'zip_bytes':(P/'paquete-destino-jardin.zip').stat().st_size,'masters_bytes':sum(x.stat().st_size for x in (P/'maestros').glob('*'))}))
