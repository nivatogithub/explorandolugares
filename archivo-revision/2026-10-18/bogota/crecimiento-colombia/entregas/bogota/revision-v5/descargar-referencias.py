"""Lote coordinador3originales licenciados. Sin reintentos ni modificación de paquetes."""
from pathlib import Path
from urllib.request import Request,urlopen
import json,hashlib
P=Path(__file__).parent;(P/'originales').mkdir(exist_ok=True);result=[]
items=[('nacional-1','https://upload.wikimedia.org/wikipedia/commons/7/79/Fachada_Museo_Nacional_de_Colombia.jpg'),('nacional-2','https://upload.wikimedia.org/wikipedia/commons/5/5e/2019_Bogot%C3%A1_-_Fachada_principal_del_Museo_Nacional.jpg'),('actividad-maloka-1','https://upload.wikimedia.org/wikipedia/commons/5/52/Maloka_in_Bogot%C3%A1_Colombia.jpg')]
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
