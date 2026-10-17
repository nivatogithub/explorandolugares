"""Derivado técnico para actividad MAMM, después de inspección explícita del original."""
from pathlib import Path
import json,hashlib
from PIL import Image,ImageOps
r=Path(__file__).resolve().parent
read=lambda f:json.loads((r/f).read_text())
def save(f,x):(r/f).write_text(json.dumps(x,ensure_ascii=False,indent=2)+'\n')
sha=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
d=read('descargas-actividades-v5.json')[0];p=r/d['target']
ins=read('inspeccion-actividad-mamm-v5.json')
assert ins['inspected'] and ins['subject_matches'] and p.is_file()
q=r/'images/mamm-fachada-2016.webp'
with Image.open(p) as im:
 im=ImageOps.exif_transpose(im).convert('RGB');im.thumbnail((800,800),Image.Resampling.LANCZOS);quality=82;im.save(q,'WEBP',quality=quality,method=6)
 while q.stat().st_size>120000 and quality>54:quality-=4;im.save(q,'WEBP',quality=quality,method=6)
 dims=list(im.size)
a={**d,'file':str(q.relative_to(r)),'original_file':d['target'],'original_sha256':sha(p),'sha256':sha(q),'bytes':q.stat().st_size,'dimensions':dims,'commercial_use':True,'adaptation':True,'original_downloaded':True,'conversion_parameters':dict(tool='Pillow',orientation='exif_transpose',resize='thumbnail800 LANCZOS sin recorte ni ampliación',quality=quality,method=6,metadata='sin EXIF/GPS'),'human_approved':False,'generated_illustration':False,'inspected':ins}
save('imagen-actividad-mamm-v5.json',a)
acts=read('experiencias.json')
for x in acts:
 if x['id']=='mamm-visita':x.update(image_file=a['file'],image_scope='Fachada del MAMM para visita autónoma editorial; no representa una exposición específica, taller ni guía comercial.',photo_status='referencia_real_lugar_actividad_editorial_sin_aprobar',image_manifest='imagen-actividad-mamm-v5.json')
save('experiencias.json',acts)
print('MAMM: original y derivado técnico listos, sin aprobación ni generación IA.')
