"""Coordinador: lote acotado de referencias verificadas. No BD, no IA, no reemplazos."""
from pathlib import Path
from urllib.request import Request,urlopen
from urllib.error import HTTPError
from urllib.parse import quote
import hashlib,json,time
p=Path(__file__).parent
rows=[('jardin-1','Domos del Tropicario de Bogotá.jpg'),('jardin-2','Tropicario del Jardín Botánico de Bogotá.jpg'),('simon-1','BOG Lago Parque Simón Bolívar.JPG'),('colpatria-2','Bogotá, Monserrate, 2023-06 CN-02.jpg')]
result=[]
for id,name in rows:
 n=name.replace(' ','_');h=hashlib.md5(n.encode()).hexdigest();enc=quote(n,safe='');base='https://upload.wikimedia.org/wikipedia/commons/';url=base+h[0]+'/'+h[:2]+'/'+enc
 target=p/'referencias'/(id+'.jpg')
 if target.exists():print(id,'already_local',flush=True);continue
 try:
  with urlopen(Request(url,headers={'User-Agent':'ExplorandoLugaresReview/1.0'}),timeout=25) as res:data=res.read(22000000)
  target.write_bytes(data);result.append({'id':id,'download_url':url,'download_kind':'original','bytes':len(data),'sha256':hashlib.sha256(data).hexdigest()});print(id,len(data),flush=True)
 except HTTPError as e:
  result.append({'id':id,'error':str(e),'retry_after':e.headers.get('Retry-After'),'download_url':url,'thumbnail_url':base+'thumb/'+h[0]+'/'+h[:2]+'/'+enc+'/960px-'+enc});print(id,'blocked',e.code,'Retry-After',e.headers.get('Retry-After'),flush=True)
 except Exception as e:result.append({'id':id,'error':str(e)});print(id,str(e),flush=True)
 (p/'descargas.json').write_text(json.dumps(result,indent=2)+'\n');time.sleep(2)
