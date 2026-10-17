"""Lote coordinador2originales licenciados. Sin reintentos ni modificación de paquetes."""
from pathlib import Path
from urllib.request import Request,urlopen
import json,hashlib
P=Path(__file__).parent;(P/'originales').mkdir(exist_ok=True);result=[]
items=[('planetario-sala-1','https://upload.wikimedia.org/wikipedia/commons/e/ea/Bogota_Planetario_01.jpg'),('planetario-muestras-2','https://upload.wikimedia.org/wikipedia/commons/3/35/Colombia_Exhibition_moon_rock_samples.jpg')]
for ident,url in items:
 r={'id':ident,'url':url,'tipo':'original_solicitado'}
 try:
  with urlopen(Request(url,headers={'User-Agent':'ExplorandoLugares editorial reference review manual batch/1.0'}),timeout=30) as response:
   data=response.read();r['content_type']=response.headers.get('Content-Type');r['url_final']=response.url
  assert r['content_type'].startswith('image/')
  f=P/'originales'/f'{ident}.jpg';f.write_bytes(data);r.update(estado='descargado_pendiente_inspeccion',file=str(f.relative_to(P)),sha256=hashlib.sha256(data).hexdigest(),bytes=len(data))
 except Exception as e:r.update(estado='bloqueado',error=str(e),retry_after=getattr(e,'headers',{}).get('Retry-After'))
 result.append(r)
(P/'descargas.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');print(json.dumps(result,ensure_ascii=False,indent=2))
