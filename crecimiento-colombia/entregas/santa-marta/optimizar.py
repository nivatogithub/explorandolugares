from pathlib import Path
import json,hashlib
from PIL import Image,ImageOps,__version__
r=Path(__file__).resolve().parent;rows=json.loads((r/'imagenes.json').read_text())
for m in rows:
 original=r/m['original_path'];im=ImageOps.exif_transpose(Image.open(original)).convert('RGB');m['original_dimensions']=list(im.size);m['derivatives']=[]
 for role,width,budget in [('portada',1600,300000),('tarjeta',800,120000)]:
  out=im.copy();out.thumbnail((width,width*2));p=r/'imagenes'/f"{m['id']}-{role}.webp";quality=82
  while True:
   out.save(p,'WEBP',quality=quality,method=6)
   if p.stat().st_size<=budget or quality<=50:break
   quality-=4
  m['derivatives'].append(dict(path=str(p.relative_to(r)),sha256=hashlib.sha256(p.read_bytes()).hexdigest(),bytes=p.stat().st_size,dimensions=list(out.size),quality=quality,tool='Pillow '+__version__,changes='Orientación EXIF, RGB, redimensionado proporcional sin recorte, WebP; derivado sin EXIF/GPS',license=m['license'],within_budget=p.stat().st_size<=budget))
 m['status']='derivados_preparados_revision_pendiente'
(r/'imagenes.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n');print('3 originales, 6 derivados; revisión humana pendiente')
