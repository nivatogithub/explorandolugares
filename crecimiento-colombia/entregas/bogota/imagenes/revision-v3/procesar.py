from pathlib import Path
import json,hashlib,html
from PIL import Image,ImageOps,__version__
p=Path(__file__).parent;delivery=p.parent.parent
m=json.loads((p/'manifest.json').read_text());rows=m['activos']+m['adicionales'];byid={x['id']:x for x in rows}
lookup={
'hero':('portada-colpatria',['Vista urbana del centro de Bogotá con Torre Colpatria, fotografía de 2009.','Urban view of central Bogotá with Colpatria Tower, photographed in 2009.','Vista urbana do centro de Bogotá com Torre Colpatria, fotografia de 2009.']),
'history':('historia-bolivar-legible',['Estatua de Simón Bolívar y pedestal en Plaza de Bolívar, Bogotá.','Simón Bolívar statue and pedestal in Bolívar Square, Bogotá.','Estátua de Simón Bolívar e pedestal na Praça Bolívar, Bogotá.']),
'bogota-candelaria':('chorro-quevedo',['Plazoleta del Chorro de Quevedo y fachadas de La Candelaria, fotografía de 2010.','Chorro de Quevedo square and façades in La Candelaria, photographed in 2010.','Praça Chorro de Quevedo e fachadas de La Candelaria, fotografia de 2010.']),
'bogota-museo-oro':('museo-oro',['Entrada y fachada del Museo del Oro junto al Parque Santander, foto de 2010.','Gold Museum entrance and façade beside Santander Park, 2010 photo.','Entrada e fachada do Museu do Ouro junto ao Parque Santander, foto de 2010.']),
'bogota-botero':('museo-botero',['Fachada y entrada del Museo Botero, Bogotá, fotografía de 2019.','Façade and entrance of Museo Botero, Bogotá, photographed in 2019.','Fachada e entrada do Museu Botero, Bogotá, fotografia de 2019.']),
'bogota-usaquen':('usaquen-mercado',['Puestos de artesanías del Mercado de Pulgas de Usaquén, fotografía de 2015.','Craft stalls at Usaquén flea market, photographed in 2015.','Bancas de artesanato no Mercado de Pulgas de Usaquén, fotografia de 2015.']),
'bogota-simon':('parque-simon',['Referencia del Parque Simón Bolívar de Bogotá, fotografía de 2006, pendiente inspección.','Reference of Bogotá Simón Bolívar Park, 2006 photograph, inspection pending.','Referência do Parque Simón Bolívar de Bogotá, fotografia de 2006, inspeção pendente.']),
'bogota-monserrate':('monserrate-elevado',['Vista aérea frontal del santuario de Monserrate y escalinatas: fotograma del video de Ricardo Perez publicado en 2023.','Frontal aerial view of Monserrate sanctuary and stairs: frame from Ricardo Perez’s video published in 2023.','Vista aérea frontal do santuário de Monserrate e escadarias: quadro do vídeo de Ricardo Perez publicado em 2023.'])}
images={};blocked=[]
for key,(id,alts) in lookup.items():
 x=byid[id];f=p/'originales'/(id+'.jpg')
 if not f.exists():blocked.append({'key':key,'id':id,'reason':'429 miniatura960;coordinador gestiona320','source_page':x.get('pagina_evidencia'),'download_url':x.get('thumbnail_url')});continue
 with Image.open(f) as raw:
  assert raw.format in ['JPEG','PNG'];im=ImageOps.exif_transpose(raw).convert('RGB')
 im.thumbnail((1200,1200));dest=p/'derivados'/(id+'.webp');im.save(dest,'WEBP',quality=82,method=6,exif=b'')
 data=f.read_bytes();d=dest.read_bytes();x.update(local_file=str(dest.relative_to(delivery)),download_local=str(f.relative_to(delivery)),sha256=hashlib.sha256(data).hexdigest(),sha256_derivado=hashlib.sha256(d).hexdigest(),dimensiones=list(im.size),bytes=len(data),bytes_derivado=len(d),herramienta='Pillow '+__version__,parametros={'max_dimension':1200,'quality':82,'method':6,'crop':None,'remove_exif':True},estado='pendiente_aprobacion_humana',revision_visual='sujeto_correcto_inspeccionado_contact_sheet;no_aprobacion_humana',download_kind='original_fotografia' if id in ['portada-colpatria','chorro-quevedo','museo-oro'] else 'poster_fotograma_video_no_video_completo' if id=='monserrate-elevado' else 'miniatura_oficial_Wikimedia_original_completo_pendiente',aprobado=False)
 author=x.get('autor',x.get('author'));license=x.get('licencia',x.get('license'));url=x.get('licencia_url',x.get('license_url'));source=x.get('pagina_evidencia',x.get('source_page'))
 alt=dict(zip(['es','en','pt-BR'],alts));images[key]={'local_file':x['local_file'],'author':author,'source_page':source,'license':license,'license_url':url,'alt':alt,'caption':{lang:t+' · '+author+' · '+license+' · '+{'es':'Referencia provisional no aprobada; miniatura técnica sin recorte.','en':'Unapproved provisional reference; technical thumbnail without cropping.','pt-BR':'Referência provisória não aprovada; miniatura técnica sem recorte.'}[lang] for lang,t in alt.items()},'sha256':x['sha256_derivado'],'source_download_local':x['download_local'],'download_kind':x['download_kind'],'approved':False}
byid['historia-bolivar']['estado']='descartada_por_contraluz_negro_no_usar';byid['historia-bolivar']['revision_visual']='Inspeccionada: silueta negra sin detalle de escultura, mala para historia; alternativa Felipe2013 conserva estatua/pedestal visibles.'
m['faltantes']=blocked;m['asignaciones']=list(images);(p/'manifest.json').write_text(json.dumps(m,ensure_ascii=False,indent=2)+'\n')
out={'destino_id':'CO-11-bogota','status':'pendiente_aprobacion_no_importar_automaticamente','images':images,'missing':blocked,'notes':['Solo referencias; no sustituye paquete,tarjetas,checklist ni BD.','Monserrate es poster/fotogramaPexelslicenciado, no video descargado ni foto independiente. Muestra frontal aéreo santuario y escalinatas, no afirma totalidad del complejo.','Fotografías históricas no prueban estado actual. Originales completos faltan para miniaturasCommons; conservar URLs/licencia.','La foto historiaFelipe ficha2013-04-07 EXIF2013-01-11: discrepancia conservada.','Parque necesita inspección visual después de descargar.']}
(p/'asignacion-imagenes-v3.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
print('Asignaciones',len(images),'faltantes',len(blocked))
