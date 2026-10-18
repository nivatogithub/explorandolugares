"""Miniaturas técnicas para revisión 1. No genera ilustraciones ni aprueba referencias."""
from pathlib import Path
import json,hashlib
from PIL import Image,ImageOps,__version__
p=Path(__file__).parent;m=json.loads((p/'imagenes.json').read_text());Image.MAX_IMAGE_PIXELS=40000000
for a in m['activos'][1:]:
 src=p/a['original_local']
 if not src.exists(): a['estado']='bloqueado_original_faltante';continue
 data=src.read_bytes()
 expected=a.get('sha1_original_declarado_commons')
 if expected and hashlib.sha1(data).hexdigest()!=expected:raise SystemExit('Hash original distinto: '+a['id'])
 with Image.open(src) as raw:
  if raw.format!='JPEG':raise SystemExit('MIME no JPEG '+a['id'])
  raw.verify()
 with Image.open(src) as raw:
  im=ImageOps.exif_transpose(raw).convert('RGB');a['dimensiones_original']=list(im.size)
 a['sha256']=hashlib.sha256(data).hexdigest();a['bytes_original']=len(data);a['derivados']=[]
 for label,width in [('revision',1200),('miniatura',600)]:
  out=im.copy();out.thumbnail((width,2000));dest=p/'imagenes/derivados'/f'{a["id"]}-{label}.webp';out.save(dest,'WEBP',quality=82,method=6,exif=b'')
  b=dest.read_bytes();a['derivados'].append({'archivo':str(dest.relative_to(p)),'dimensiones':list(out.size),'bytes':len(b),'sha256':hashlib.sha256(b).hexdigest(),'herramienta':'Pillow '+__version__,'parametros':{'quality':82,'method':6,'max_width':width,'exif_removed':True,'crop':None},'licencia':a['licencia'],'uso':'miniatura_tecnica_revision_1_no_ilustracion'})
(p/'imagenes.json').write_text(json.dumps(m,ensure_ascii=False,indent=2)+'\n')
print('Referencias técnicas preparadas; sin aprobación ni ilustración.')
