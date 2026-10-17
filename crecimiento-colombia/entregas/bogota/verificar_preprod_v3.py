"""Comprueba requisitos reales de Bogotá en loopback:8 tarjetas,roles,fotos e idiomas."""
from pathlib import Path
from urllib.request import urlopen
from datetime import datetime,timezone
import json,hashlib,re,html,time
transient_errors=[]
def local_get(url):
 assert url.startswith("http://127.0.0.1:8088/")
 for attempt in range(3):
  try:
   with urlopen(url,timeout=15) as r:return r.status,r.headers,r.read()
  except Exception as e:
   transient_errors.append(dict(url=url,attempt=attempt+1,error=type(e).__name__))
   if attempt==2:raise
   time.sleep(0.3)
P=Path(__file__).resolve().parent
m=json.loads((P/'preprod-v3/paquete-destino.json').read_text());a=json.loads((P/'asignacion-imagenes-v3.json').read_text())['images']
expected=[hashlib.sha256((P/x['local_file']).read_bytes()).hexdigest() for x in a.values()]
assert len(expected)==len(set(expected))==10,'Portada,historia y ocho imágenes deben ser distintas'
rows=[]
for lang,prefix in [('es',''),('en','/en'),('pt-BR','/pt-br')]:
 url='http://127.0.0.1:8088'+prefix+'/destinos/bogota/'
 status,headers,data=local_get(url);assert status==200;s=data.decode()
 assert not any(x in s for x in ['Fatal error:','Uncaught PDOException','Warning:'])
 grid=re.search(r'id="atractivos-destino">(.*?)</div>',s,re.S).group(1);assert len(re.findall(r'<article(?:\s|>)',grid))==8
 for place in m['places']:assert place['translations'][lang]['name'] in html.unescape(grid)
 assert 'destination-history-photo' in s
 for h in expected:assert h+'.webp' in s
 images=re.findall(r'<img[^>]+src="([^"]+)"',s)
 for src in images:
  target='http://127.0.0.1:8088/'+src.lstrip('/')
  assert not src.startswith(('https:','http:')),'Las imágenes deben servirse localmente'
  status,headers,data=local_get(target)
  assert status==200 and headers['Content-Type'].startswith('image/') and len(data)>100
  h=re.search(r'/([0-9a-f]{64})\.webp$',src)
  if h:assert hashlib.sha256(data).hexdigest()==h.group(1),'Bytes de imagen incorrectos'
 rows.append(dict(language=lang,url=url,attractions=8,distinct_destination_images=10,history_image=True,images_http_ok=True))
report=dict(checked_at=datetime.now(timezone.utc).isoformat(),environment='loopback_preprod_only',production_accessed=False,editorial_approved=False,results=rows,transient_connection_errors=transient_errors)
(P/'verificacion-preprod-v3.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');print(json.dumps(report,ensure_ascii=False))
