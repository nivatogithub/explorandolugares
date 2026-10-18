"""Ejecutar coordinador con permiso de red. Solo miniaturas oficiales; sin reintentos."""
from pathlib import Path
from urllib.request import Request,urlopen
import json,hashlib
p=Path(__file__).parent;out=[]
a=json.loads((p/'candidatas-visuales.json').read_text())
for x in a['options']:
 if x['id'] not in ('simon-1','colpatria-2'):continue
 url=x['thumbnail_url'];r={'id':x['id'],'url':url,'tipo':'miniatura_oficial_no_original'}
 try:
  with urlopen(Request(url,headers={'User-Agent':'ExplorandoLugares editorial reference review; manual batch/1.0'}),timeout=35) as response:
   b=response.read();r['url_final']=response.url;r['content_type']=response.headers.get('Content-Type')
  assert r['content_type'].startswith('image/'),r['content_type']
  f=p/'referencias'/f"{x['id']}-thumb.jpg";f.write_bytes(b)
  r.update(local_file=str(f.relative_to(p)),bytes=len(b),sha256=hashlib.sha256(b).hexdigest(),estado='descargado_pendiente_inspeccion')
 except Exception as e:
  r.update(estado='bloqueado',error=str(e),retry_after=getattr(e,'headers',{}).get('Retry-After'))
 out.append(r)
(p/'descargas-miniaturas.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(out,ensure_ascii=False,indent=2))
