"""Paquete local de ocho atractivos y foto histórica independiente en sidecar."""
from pathlib import Path
from urllib.parse import quote
import json,hashlib,zipfile,shutil
P=Path(__file__).resolve().parent
read=lambda n:json.loads((P/n).read_text())
def save(n,d):(P/n).write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n')
langs=['es','en','pt-BR'];selection=read('seleccion-editorial-v3.json')['selection'];sites={s['id']:s for s in read('sitios.json')};a=read('paquete-borrador.json');sources=read('fuentes.json');assignment=read('asignacion-imagenes-v3.json');images=assignment['images']
assert len(selection)==8 and len(set(selection))==8
Q=P/'preprod-v3';(Q/'images').mkdir(parents=True,exist_ok=True)
def asset(key,filename):
 x=images[key];f=P/x['local_file'];assert f.is_file();shutil.copyfile(f,Q/'images'/filename)
 return dict(file='images/'+filename,image_origin='external',image_creator=x['author'],image_source_url=quote(x['source_page'],safe=':/?=&%#-_.~'),image_license=x['license'],image_license_url=x['license_url'],alt=x['alt'],caption={l:x['caption'][l] for l in langs})
assert len({images[k]['source_page'] for k in ['hero',*selection]})==9,'No reutilizar original entre portada y tarjetas'
hero=asset('hero','bogota-portada-v3.webp')
dest={'slug':'bogota','destination_type':'city','country_code':'CO','department':'Bogotá D.C.','subregion':'Distrito Capital','translations':{},'history':{},'reasons':{},'sources':{}}
reasons={'es':['Recorrer el centro histórico y la vida cultural de La Candelaria.','Conocer colecciones de arte y arqueología.','Descubrir Usaquén, sus calles y su gastronomía.','Combinar miradores, parques y diversidad vegetal.'],'en':['Explore the historic centre and La Candelaria’s cultural life.','Discover collections of art and archaeology.','Enjoy Usaquén’s streets and food scene.','Combine viewpoints, parks and plant diversity.'],'pt-BR':['Explorar o centro histórico e a vida cultural de La Candelaria.','Conhecer coleções de arte e arqueologia.','Descobrir as ruas e a gastronomia de Usaquén.','Combinar mirantes, parques e diversidade vegetal.']}
labels={'es':'AVANCE LOCAL · imágenes y textos por revisar','en':'LOCAL PREVIEW · images and texts for review','pt-BR':'PRÉVIA LOCAL · imagens e textos em revisão'}
for l in langs:
 t=a['contenido'][l];dest['translations'][l]=dict(display_name='Bogotá',eyebrow=labels[l],headline=t['titulo'],intro=t['introduccion'],meta_description=t['introduccion'][:155]);dest['history'][l]=t['historia_parrafos'];dest['reasons'][l]=reasons[l]
 dest['sources'][l]=[dict(label=s['titulo'],url=quote(s['url'],safe=':/?=&%#-_.~'),section_key='history' if s['id'] in ['S01','S39','S40'] else 'places') for s in sources if s['id'] in {'S01','S02','S03','S04','S27','S34','S35','S36','S37','S38','S39','S40'}]
places=[]
for i in selection:
 s=sites[i];places.append(dict(slug=i.removeprefix('bogota-'),type='place',publication_status='draft',translations={l:dict(name=s['nombres'][l],description=s['textos'][l],map_url='https://www.google.com/maps/search/?api=1&query='+quote(s['nombres']['es']+', Bogotá, Colombia'),external_url='',external_label='') for l in langs},image=asset(i,i+'.webp')))
assert len({hashlib.sha256((Q/m['file']).read_bytes()).hexdigest() for m in [hero,*[p['image'] for p in places]]})==9,'No repetir foto entre portada y tarjetas'
local={'schema_version':1,'researched_at':'2026-10-17','positioning':'Ocho atractivos curados; cultura, patrimonio, barrios, parques y naturaleza.','competitors':[],'potential_allies':[],'business_prospects':read('directorio.json'),'acquisition_strategies':[],'sources':[{'label':s['titulo'],'url':quote(s['url'],safe=':/?=&%#-_.~'),'consulted_at':s['consulta']} for s in sources],'notes':['Revisión local autorizada; ninguna aprobación editorial o de producción.','Portada separada de Monserrate. Ocho principales; inventario investigado20,12 fuera de selección conservados.','Fotografías provisionales con licencia y origen; ilustraciones finales no generadas.','Historia independiente en sidecar para enlazar campo existente de administración; ZIPschema2 aún no soporta historia.','Actividades y directorio investigados no integrados aún.']}
manifest=dict(schema_version=2,destination=dest,hero_image=hero,places=places,local_research=local);(Q/'paquete-destino.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
with zipfile.ZipFile(P/'paquete-preprod.zip','w',zipfile.ZIP_DEFLATED) as z:
 z.write(Q/'paquete-destino.json','paquete-destino.json')
 for m in [hero,*[p['image'] for p in places]]:z.write(Q/m['file'],m['file'])
if 'history' in images:
 x=images['history'];f=P/x['local_file'];h=dict(file='images/bogota-history.webp',image_origin='external',image_creator=x['author'],image_source_url=quote(x['source_page'],safe=':/?=&%#-_.~'),image_license=x['license'],image_license_url=x['license_url'],alt=x['alt'],caption=x['caption']);shutil.copyfile(f,P/'bogota-history.webp');save('bogota-history.json',dict(destination='bogota',sha256=hashlib.sha256(f.read_bytes()).hexdigest(),image=h))
save('reporte-preprod-v3.json',dict(scope='solo_preprod_local',selected=selection,places=8,images=9,history_image_prepared='history' in images,hero_not_monserrate=True,photographs_approved=False,texts_approved=False,generated_illustrations=0,production_authorized=False,sha256_zip=hashlib.sha256((P/'paquete-preprod.zip').read_bytes()).hexdigest()))
print('Preparado:8 principales,portada independiente,historia separada; no BD ni publicación.')
