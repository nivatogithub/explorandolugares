"""Empaqueta borrador v2 para importación local serial por coordinador. No toca BD."""
from pathlib import Path
import json,hashlib,shutil,zipfile
from urllib.parse import quote
def https(url):return quote(url,safe=":/?=&%#-_.~")
p=Path(__file__).parent;q=p/'preprod-paquete';(q/'images').mkdir(parents=True,exist_ok=True)
r=lambda n:json.loads((p/n).read_text())
a=r('paquete-borrador.json');sites=r('sitios.json');photos={x['id']:x for x in r('imagenes.json')['activos']};sources={x['id']:x for x in r('fuentes.json')};langs=['es','en','pt-BR']
selected=['bogota-monserrate','bogota-plaza','bogota-jardin']
def image(id,file):
 x=photos[id];src=p/x['derivados'][0]['archivo'];shutil.copyfile(src,q/file)
 return {'file':file,'image_origin':'external','image_creator':x['autor'],'image_source_url':https(x['pagina_original']),'image_license':x['licencia'],'image_license_url':https(x['url_licencia']),'alt':x['alt'],'caption':{lang:x['alt'][lang]+' · '+x['autor']+' · '+x['licencia']+' · '+{'es':'Fotografía provisional para revisión local; no aprobada.','en':'Provisional photograph for local review; not approved.','pt-BR':'Fotografia provisória para revisão local; não aprovada.'}[lang] for lang in langs}}
dest={'slug':'bogota','destination_type':'city','country_code':'CO','department':'Bogotá D.C.','subregion':'Distrito Capital','translations':{},'history':{},'reasons':{},'sources':{}}
for lang in langs:
 t=a['contenido'][lang]
 dest['translations'][lang]={'display_name':'Bogotá','eyebrow':'Bogotá D.C. · Colombia','headline':t['titulo'],'intro':t['introduccion'],'meta_description':t['introduccion'][:155]}
 dest['history'][lang]=[t['historia']]
 dest['reasons'][lang]={'es':['Descubrir colecciones y espacios culturales.','Reconocer el centro histórico y sus plazas.','Explorar naturaleza urbana con condiciones verificadas.'],'en':['Discover collections and cultural venues.','Explore the historic centre and its squares.','Discover urban nature with checked conditions.'],'pt-BR':['Conhecer coleções e espaços culturais.','Explorar o centro histórico e suas praças.','Conhecer natureza urbana com condições verificadas.']}[lang]
 dest['sources'][lang]=[{'section':'history' if i=='S01' else 'places','label':sources[i]['titulo'],'url':https(sources[i]['url'])} for i in ['S01','S03','S04','S28']]
places=[]
for id in selected:
 x=next(x for x in sites if x['id']==id)
 places.append({'slug':id.removeprefix('bogota-'),'type':'place','publication_status':'draft','translations':{lang:{'name':x['nombre'],'description':x['textos'][lang]+' '+x['ubicacion'],'map_url':'','external_url':'','external_label':''} for lang in langs},'image':image(x['foto_id'],'images/'+id+'.webp')})
notes=['Solo PREPROD PHP/MySQL local, coordinador importa serialmente. No producción ni aprobación editorial.','Portada Monserrate sin cables elegida provisionalmente reversible, no aprobación humana.','Máximo8 principales: se importan3 con referencias locales disponibles;16sitios secundarios conservados en investigación. No rellenar8 con fotos ajenas.','Foto jardín detalle tortuga/plantas, no vista general ni Tropicario; provisional, pendiente aprobación de aptitud para ficha.','Foto centro fecha declarada2024 y EXIFsoftware2023 discrepante: pendiente revisión.','Actividad Ciclovía tiene foto histórica de programa, sin corredor exacto confirmado, no representa BikeTours; actividades permanecen investigación separada, no se importan como principales.','Sin ilustraciones IA finales; originales/textos revisión1 antes de generar y revisión2 posterior.','No reservas, pagos, alianza, disponibilidad o RNT confirmados.']
local={'schema_version':1,'researched_at':'2026-10-16','positioning':'Borrador local Bogotá: cultura, patrimonio y naturaleza urbana.','competitors':[],'potential_allies':[],'business_prospects':r('directorio.json'),'acquisition_strategies':[],'sources':[{'label':x['titulo'],'url':https(x['url']),'consulted_at':x['consulta']} for x in sources.values()],'notes':notes+[json.dumps({'sitios_secundarios':[{k:x[k] for k in ['id','nombre','inclusion','foto_estado','ubicacion']} for x in sites if x['id'] not in selected]},ensure_ascii=False)[:4900]]}
# structured inventories remain in original delivery; small notes carry explicit status into private backend.
manifest={'schema_version':2,'destination':dest,'hero_image':image('opcion-1-monserrate','images/bogota-portada-provisional.webp'),'places':places,'local_research':local}
(q/'paquete-destino.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
with zipfile.ZipFile(p/'paquete-preprod.zip','w',zipfile.ZIP_DEFLATED) as z:
 for f in sorted(q.rglob('*')):
  if f.is_file():z.write(f,str(f.relative_to(q)))
report={'destino_id':'CO-11-bogota','rama':'lugar/CO/bogota-dc/bogota','schema_version':2,'scope':'solo_preprod_local','archivo':'paquete-preprod.zip','sha256_zip':hashlib.sha256((p/'paquete-preprod.zip').read_bytes()).hexdigest(),'sha256_manifest':hashlib.sha256((q/'paquete-destino.json').read_bytes()).hexdigest(),'portada_provisional':'opcion-1-monserrate','portada_aprobada':False,'principales_importables':selected,'max_principales':8,'numero_principales':3,'experiencias_importadas':0,'experiencias_documentadas_separadas':18,'exclusiones':[{'id':x['id'],'nombre':x['nombre'],'motivo':'sin referencia local apta y/o candidato oculto; conservado inventario secundario','estado':x['inclusion']} for x in sites if x['id'] not in selected],'imagenes_ia_generadas':0,'publicable':False,'publicado_produccion':False,'BD_local_modificada_por_worker':False,'prueba_php_zip':'pendiente_ejecucion','limitaciones':notes,'inventarios_fuente':{'sitios':'sitios.json','actividades':'experiencias.json','imagenes':'imagenes.json','fuentes':'fuentes.json'}}
(p/'reporte-preprod.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(report['sha256_zip'])
