#!/usr/bin/env python3
"""Read-only HTTP validation of the four-destination local review batch."""
import json,urllib.request,urllib.parse,re,concurrent.futures,zipfile,argparse
from html.parser import HTMLParser
from pathlib import Path
BASE='http://127.0.0.1:8088'
STAGING=Path(__file__).resolve().parents[2]/'preprod/.local/preprod/worker-review/lote-20261019'
SLUGS=['zipaquira','santa-fe-de-antioquia','jardin','covenas']
parser=argparse.ArgumentParser();parser.add_argument('--destination',choices=SLUGS);args=parser.parse_args()
if args.destination:SLUGS=[args.destination]
class Page(HTMLParser):
 def __init__(self):super().__init__();self.images=[];self.businesses=0;self.links=[];self.free=0
 def handle_starttag(self,tag,attrs):
  a=dict(attrs);classes=a.get('class','').split()
  if tag=='img':self.images.append(a)
  if tag=='article' and 'directory-card' in classes:self.businesses+=1
  if tag=='a':self.links.append(a.get('href',''))
def get(path):
 u=urllib.parse.urljoin(BASE+'/',path)
 if urllib.parse.urlparse(u).netloc!='127.0.0.1:8088':raise ValueError('Only local URLs allowed')
 with urllib.request.urlopen(u,timeout=30) as r:
  assert r.status==200,u
  return r.read(),r.headers.get('Content-Type','')
report={'origin':BASE,'scope':SLUGS,'production_accessed':False,'pages':[]};images=set()
for slug in SLUGS:
 for locale,prefix in [('es',''),('en','en/'),('pt-BR','pt-br/')]:
  path='/'+prefix+'destinos/'+slug+'/'
  body,_=get(path);html=body.decode();assert not re.search(r'Fatal error|Uncaught |Warning:|sesión activa de administración|Destino no encontrado',html),path
  parser=Page();parser.feed(html);assert parser.businesses>=30,(path,parser.businesses)
  destimages=[i for i in parser.images if '/assets/destinos/preprod-'+slug+'/' in i.get('src','')]
  with zipfile.ZipFile(STAGING/(slug+'.zip')) as z: manifest=json.loads(z.read('paquete-destino.json'))
  expected=1+len(manifest['places'])
  assert len(destimages)==expected,(path,len(destimages),expected)
  assert len({i['src'] for i in destimages})==expected,'Duplicate image in destination'
  assert all(i.get('alt','').strip() for i in destimages),'Missing alt'
  assert 'destino-lectura-directa.js?v=3' in html and 'cargar-mas-tarjetas.js' in html,'Compact layout missing'
  assert 'identidad-neutral.js' in html,'Image navigation protection missing; verify rendered DOM separately'
  images.update(i['src'] for i in destimages)
  report['pages'].append({'slug':slug,'locale':locale,'status':200,'images':len(destimages),'businesses':parser.businesses,'session_required':False})
def check_image(path):
 body,mime=get(path);assert mime.startswith('image/'),(path,mime);assert len(body)>1000,path
 return len(body)
with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:sizes=list(pool.map(check_image,sorted(images)))
report.update(unique_destination_images=len(images),all_image_requests_passed=True,web_image_bytes=sum(sizes),editorial_approval='pending',success=True)
out=Path(__file__).resolve().parents[1]/'crecimiento-colombia/lotes/2026-10-19';out.mkdir(parents=True,exist_ok=True);(out/('validacion-http-'+args.destination+'.json' if args.destination else 'validacion-http.json')).write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(report,ensure_ascii=False,indent=2))
