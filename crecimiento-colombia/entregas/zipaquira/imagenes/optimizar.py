"""Optimize independently generated masters. Never generates or changes depicted content."""
from pathlib import Path
from PIL import Image
import json, hashlib
P=Path(__file__).resolve().parent
out=[]
for path in sorted((P/'maestros').glob('*.png')):
 im=Image.open(path).convert('RGB')
 item=dict(id=path.stem,master_file=str(path.relative_to(P)),master_sha256=hashlib.sha256(path.read_bytes()).hexdigest(),master_bytes=path.stat().st_size,dimensions=list(im.size),web=[])
 for width in ([640,1200] if path.stem=='zipaquira-portada' else [640]):
  new=im.copy();new.thumbnail((width,round(width*2/3)))
  dest=P/'web'/f'{path.stem}-{width}.webp';new.save(dest,'WEBP',quality=79,method=6)
  item['web'].append(dict(file=str(dest.relative_to(P)),bytes=dest.stat().st_size,dimensions=list(new.size),sha256=hashlib.sha256(dest.read_bytes()).hexdigest()))
 out.append(item)
(P/'activos.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
print(f'{len(out)} masters optimized; '+str(sum(v['bytes'] for a in out for v in a['web']))+' web bytes')
