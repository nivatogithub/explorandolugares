"""Technical resize only; does not generate, repaint or approve images."""
from pathlib import Path
from PIL import Image,ImageDraw
import json,hashlib,shutil
root=Path(__file__).resolve().parents[4]
p=Path(__file__).resolve().parent
public=root/'backend/public/assets/santa-marta-semirrealista';public.mkdir(parents=True,exist_ok=True)
data=json.load(open(p/'santa-marta-semirrealista.json'))
log={r['asset_id']:r for r in json.load(open(p/'GENERACIONES-FINALES.json'))['assets']}
rows=[{'id':'hero','asset_id':'hero','content_type':'hero','translations':{l:{'name':'Santa Marta'} for l in data['translations']},'source_url':'https://colombia.travel/es/santa-marta'}]+data['places']+data['experiences']
images=[]
sha=lambda f:hashlib.sha256(f.read_bytes()).hexdigest()
for row in rows:
 key=row['asset_id'];f=p/'maestros'/f'{key}.png';im=Image.open(f).convert('RGB');files=[]
 for width in ([640,1200] if key=='hero' else [480,800]):
  resized=im.resize((width,round(im.height*width/im.width)),Image.Resampling.LANCZOS)
  out=public/f'{key}-{width}.webp';resized.save(out,'WEBP',quality=84,method=6)
  files.append({'path':str(out.relative_to(root)),'width':resized.width,'height':resized.height,'bytes':out.stat().st_size,'sha256':sha(out)})
 limitation='Composición editorial independiente desde rasgos documentados, no fotografía ni reconstrucción exacta.'
 if row['content_type']=='experience':limitation+=' Escena representativa: no prueba recinto, ruta, operador, personas ni disponibilidad actuales.'
 images.append({'schema_version':'1.0','asset_id':key,'destination_id':'santa-marta','attraction_id':row['id'],'purpose':row['content_type'],'file':str(f.relative_to(root)),'media_type':'ai_generated_illustration','language':'shared_es_en_pt','style_id':'EL-SEMI-REALISTA-1','generation':log[key],'visual_references':[],'fact_sources':[{'url':row['source_url'],'checked_at':'2026-10-17','facts_verified':'Rasgos geográficos/actividad según expediente textual previo; no exactitud topográfica del resultado.'}] if row['source_url'] else [{'url':'https://www.discoversantamarta.travel/wp-content/uploads/2024/03/07032024-GUIA-GENERAL-DIGITAL-ESP.pdf','checked_at':'2026-10-17','facts_verified':'Mención histórica de motos acuáticas en guía 2024; no oferta vigente verificada.'}],'source_sha256':sha(f),'files':files,'limitations':limitation,'rights':{'route':'independent_composition','photographs_uploaded':False,'style_reference_uploaded':False,'third_party_image_license_applied':False,'provider_result_terms':'Condiciones aplicables de la cuenta/proveedor; no se afirma exclusividad ni ausencia universal de derechos adicionales.','human_generation_authority':'Encargo actual autoriza composición desde descripciones; no aprobación final.'},'review':{'status':'proposal_pending_human_review','identity_check':'agent_checked_representative_subject_not_exact_reconstruction','crop_check_desktop':'whole_image_resized_no_crop','crop_check_mobile':'whole_image_resized_no_crop','style_check':'high_realism_compare_chosen_style_pending_human_acceptance','human_result_approved':False},'publication':{'approved_by':None,'approved_at':None,'published_at':None}})
manifest={'style':'EL-SEMI-REALISTA-1','destination':'santa-marta','approved':False,'production_authorized':False,'history_image':None,'images':images,'counts':{'hero':1,'places':8,'experiences':9,'masters_selected':18,'webp':36},'rejected_masters':[f.name for f in (p/'maestros').glob('*-rechazado-v1.png')]}
(p/'MANIFIESTO.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
(p/'MAPA_INTEGRACION.json').write_text(json.dumps({'slug':'santa-marta','scripts':['/assets/santa-marta-compacta.js'],'stylesheets':['/assets/santa-marta-compacta.css'],'content':'/assets/santa-marta-semirrealista/contenido.json','roles':[{'role':i['purpose'],'key':i['asset_id'],'slug':i['attraction_id'],'paths':[f['path'] for f in i['files']]} for i in images]},ensure_ascii=False,indent=2)+'\n')
shutil.copyfile(p/'santa-marta-semirrealista.json',public/'contenido.json')
# Labelled contact sheet for agent QA only, never a publication asset.
out=Image.new('RGB',(1200,6*290),'white');draw=ImageDraw.Draw(out)
for i,r in enumerate(rows):
 im=Image.open(p/'maestros'/f"{r['asset_id']}.png");im.thumbnail((390,260));x=i%3*400;y=i//3*290;out.paste(im,(x,y));draw.text((x+8,y+265),r['asset_id'],fill='black')
out.save(p/'QA-contacto-final.jpg',quality=90)
print(json.dumps(manifest['counts']))
