"""Verifica el ZIP editorial y su página real local; no publica ni cambia datos."""
import argparse,json,hashlib,zipfile,re,html
from pathlib import Path
from urllib.request import urlopen
from datetime import datetime,timezone
p=argparse.ArgumentParser();p.add_argument('slug',choices=['medellin','santa-marta','bogota']);p.add_argument('zip',type=Path);p.add_argument('--output',type=Path,required=True);a=p.parse_args()
with zipfile.ZipFile(a.zip) as z:
 m=json.loads(z.read('paquete-destino.json'));places=m['places'];assert len(places)==8
 images=[m['hero_image']]+[x['image'] for x in places]
 hashes=[hashlib.sha256(z.read(x['file'])).hexdigest() for x in images]
 assert len(set(hashes))==9,'Se repiten bytes de portada/tarjetas'
 sources=[x['image_source_url'] for x in images];assert len(set(sources))==9,'Se repite fuente original'
 if a.slug=='medellin':assert not any('coltejer' in x['slug'] for x in places),'Coltejer no es tarjeta de visita'
history=a.zip.parent/(a.slug+"-history.json")
if history.exists():
 h=json.loads(history.read_text());assert h["destination"]==a.slug
 hb=(a.zip.parent/(a.slug+"-history.webp")).read_bytes();hh=hashlib.sha256(hb).hexdigest()
 assert hh==h["sha256"] and hh not in hashes
 assert h["image"]["image_source_url"] not in sources
 hashes.append(hh)
rows=[]
for lang,prefix in [('es',''),('en','/en'),('pt-BR','/pt-br')]:
 url='http://127.0.0.1:8088'+prefix+'/destinos/'+a.slug+'/'
 with urlopen(url,timeout=20) as r:s=r.read().decode();assert r.status==200
 assert not any(x in s for x in ['Fatal error:','Uncaught PDOException','<b>Warning'])
 grid=re.search(r'id="atractivos-destino">(.*?)</div>',s,re.S).group(1)
 assert len(re.findall(r'<article(?:\s|>)',grid))==8
 for x in places:assert x['translations'][lang]['name'] in html.unescape(grid)
 for h in hashes:assert h+'.webp' in s,'Imagen esperada ausente'
 for src in set(re.findall(r'<img[^>]+src="([^"]+)"',s)):
  assert not src.startswith(('https:','http:'))
  with urlopen('http://127.0.0.1:8088/'+src.lstrip('/'),timeout=20) as r:
   data=r.read();assert r.status==200 and r.headers['Content-Type'].startswith('image/')
  h=re.search(r'/([0-9a-f]{64})\.webp$',src)
  if h:assert hashlib.sha256(data).hexdigest()==h.group(1)
 rows.append(dict(locale=lang,url=url,attractions=8,distinct_zip_images=9,distinct_original_sources=len(hashes),history_image=history.exists(),images_http_hashes_ok=True))
out=dict(checked_at=datetime.now(timezone.utc).isoformat(),zip_sha256=hashlib.sha256(a.zip.read_bytes()).hexdigest(),results=rows,production_accessed=False,final_editorial_approval=False)
a.output.write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n');print(json.dumps(out,ensure_ascii=False))
