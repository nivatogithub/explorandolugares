"""Read-only checks of imported worker pages in loopback preprod, including images/locales."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urljoin,urlsplit
import json,re,urllib.request
BASE='http://127.0.0.1:8088'
class LocalOnly(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,newurl):
  if not newurl.startswith(BASE+'/'):raise RuntimeError('External redirect blocked')
  return super().redirect_request(req,fp,code,msg,headers,newurl)
client=urllib.request.build_opener(LocalOnly())
class Page(HTMLParser):
 def __init__(self):super().__init__();self.images=[];self.heading=False;self.h1=[];self.lang=''
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='html':self.lang=a.get('lang','')
  if tag=='img':self.images.append(a.get('src',''))
  if tag=='h1':self.heading=True
 def handle_endtag(self,tag):
  if tag=='h1':self.heading=False
 def handle_data(self,s):
  if self.heading:self.h1.append(s)
report=[]
for slug in ['cartagena','bogota','medellin','santa-marta']:
 for lang,prefix in [('es',''),('en','/en'),('pt-BR','/pt-br')]:
  url=BASE+prefix+'/destinos/'+slug+'/'
  with client.open(url,timeout=25) as r:
   body=r.read().decode();assert r.status==200
  assert not re.search(r'Fatal error|Uncaught |<b>Warning',body),url
  page=Page();page.feed(body);assert page.lang==lang,(url,page.lang);assert page.h1,url
  assert len(page.images)>=2,(url,'No destination pictures')
  for src in page.images:
   media=urljoin(url,src)
   if urlsplit(media).netloc!=urlsplit(BASE).netloc:continue
   with client.open(media,timeout=25) as r:assert r.status==200,(url,media)
  row=dict(destination=slug,locale=lang,url=url,status=200,heading=''.join(page.h1),images=len(page.images));report.append(row);print(slug,lang,'OK',len(page.images),'images',flush=True)
root=Path(__file__).resolve().parents[1];(root/'crecimiento-colombia/PREPROD_PAGINAS_VERIFICADAS.json').write_text(json.dumps(dict(origin=BASE,pages=report,production_accessed=False),ensure_ascii=False,indent=2)+'\n')
