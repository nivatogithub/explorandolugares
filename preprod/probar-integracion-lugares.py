#!/usr/bin/env python3
"""Read-only local integration gate for the four place branches."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urljoin,urlsplit
import urllib.request,urllib.error,json,hashlib
ROOT=Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8088'
class Page(HTMLParser):
 def __init__(self):super().__init__();self.images=[];self.assets=[];self.links=[];self.h1=0
 def handle_starttag(self,tag,attrs):
  d=dict(attrs)
  if tag=='h1':self.h1+=1
  if tag=='img' and d.get('src'):self.images.append(d['src'])
  if tag=='script' and d.get('src'):self.assets.append(d['src'])
  if tag=='link' and d.get('rel')=='stylesheet':self.assets.append(d['href'])
  if tag=='a' and d.get('href'):self.links.append(d['href'])
def read(url):
 with urllib.request.urlopen(url,timeout=25) as r:return r.status,r.read(),r.headers.get_content_type()
results=[];resources=set();page_links=set()
for slug in ['cartagena','bogota','medellin','santa-marta']:
 for lang,prefix in [('es',''),('en','en/'),('pt-BR','pt-br/')]:
  url=BASE+'/'+prefix+'destinos/'+slug+'/'
  status,data,mime=read(url);p=Page();p.feed(data.decode())
  assert status==200 and p.h1==1,(url,status,p.h1)
  assert 'que-conocer' in data.decode() and 'lugares' in data.decode(),url
  resources.update(urljoin(url,x) for x in p.images+p.assets if urlsplit(urljoin(url,x)).netloc=='127.0.0.1:8088')
  page_links.update(urljoin(url,x).split('#')[0] for x in p.links if x.startswith(('/', '../','index.html')) and '/admin/' not in x and '/negocios/portal/' not in x)
  results.append({'place':slug,'locale':lang,'status':status,'h1':p.h1,'image_count':len(p.images)})
# Include images injected by the existing city components after JavaScript.
visual=ROOT/'.local/preprod/evidencia/integracion-lugares/despues/render.json'
if visual.exists():
 for page in json.loads(visual.read_text()).values():resources.update(x['src'] for x in page['images'] if x['src'].startswith(BASE))
for url in sorted(resources):
 status,data,mime=read(url);assert status==200 and data,(url,status)
 if any(x in urlsplit(url).path.lower() for x in ['.webp','.png','.svg']):assert mime.startswith('image/'),(url,mime)
for url in sorted(page_links):
 if urlsplit(url).netloc!='127.0.0.1:8088':continue
 status,data,mime=read(url);assert status==200,(url,status)
for slug in ['jardin','sonson','cauca-viejo']:
 try:read(BASE+'/destinos/'+slug+'/')
 except urllib.error.HTTPError as e:assert e.code==404,(slug,e.code)
 else:raise AssertionError('Borrador accesible: '+slug)
report={'pages':results,'resources_checked':len(resources),'public_links_checked':len(page_links),'drafts_remain_private':True,'database_changed':False,'production_changed':False}
p=ROOT/'.local/preprod/evidencia/integracion-lugares/http.json';p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print('OK: 12 páginas ES/EN/PT,',len(resources),'recursos y',len(page_links),'enlaces públicos. Jardín, Sonsón y Cauca Viejo permanecen privados.')
