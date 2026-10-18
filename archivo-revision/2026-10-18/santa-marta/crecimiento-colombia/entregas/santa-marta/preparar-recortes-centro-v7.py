"""Comparaciones técnicas para inspección; no ilustraciones ni activos finales."""
from pathlib import Path
import json
from PIL import Image,ImageOps,ImageDraw
p=Path(__file__).resolve().parent
rows=json.loads((p/'centro-bahia-v7.json').read_text());out=p/'imagenes/centro-bahia-v7/revision';out.mkdir(parents=True,exist_ok=True)
for r in rows:
 if not r.get('path'):continue
 im=Image.open(p/r['path']).convert('RGB');r['actual_dimensions']=list(im.size)
 original=im.copy();original.thumbnail((1200,800));original.save(out/(r['id']+'-original.webp'),quality=92)
 sheet=Image.new('RGB',(990,420),'white');sheet.paste(ImageOps.fit(im,(600,330)),(10,40));sheet.paste(ImageOps.fit(im,(342,240)),(630,40));ImageDraw.Draw(sheet).text((10,10),r['id']+' | COMPARACION TECNICA CENTRADA | IDENTIDAD Y APTITUD PENDIENTES',fill='black');sheet.save(out/(r['id']+'-comparacion.jpg'),quality=90)
 r['technical_crop']='generado_para_inspeccion_no_aprobado'
(p/'centro-bahia-v7.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
print('Comparaciones listas para inspección visual; no implica aptitud.')
