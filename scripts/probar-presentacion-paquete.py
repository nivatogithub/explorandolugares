#!/usr/bin/env python3
"""Read-only rendering checks against a disposable package fixture; no HTTP or external services."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urljoin,urlsplit
import argparse,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
class Page(HTMLParser):
 def __init__(self):super().__init__();self.headers=0;self.footers=0;self.resources=[];self.alternates=[];self.menus=0
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='header':self.headers+=1
  if tag=='footer':self.footers+=1
  if a.get('id')=='menu-principal':self.menus+=1
  if tag in ['img','script'] and a.get('src'):self.resources.append(a['src'])
  if tag=='link' and a.get('rel')=='stylesheet':self.resources.append(a['href'])
  if tag=='link' and a.get('rel')=='alternate':self.alternates.append(a.get('href',''))
  if tag=='img' and a.get('srcset'):self.resources.extend(item.strip().split(' ')[0] for item in a['srcset'].split(','))
def run(package,output):
 package=package.resolve();public=package/'public'
 if not package.is_relative_to(ROOT/'.local') or not (package/'private/presentation.sqlite').is_file():raise ValueError('New isolated fixture required')
 release=json.loads((ROOT/'content/release-places-businesses.json').read_text());results=[]
 cases=[]
 for locale,prefix in [('es',''),('en','/en'),('pt-BR','/pt-br')]:
  for slug in release['public_places']:
   cases.append((prefix+'/lugares/'+slug+'/','destino.php',{'destino':slug,'idioma':locale}))
  for suffix in ['/','/privacidad/']:
   cases.append((prefix+suffix,prefix.lstrip('/')+('/' if prefix else '')+suffix.lstrip('/')+'index.php',{}))
  cases.append(('/contacto.php?lang='+locale.lower(),'contacto.php',{'lang':locale.lower()}))
  cases.append(('/negocios/acceso/?lang='+locale.lower(),'business/login.php',{'lang':locale.lower()}))
 for route,file,query in cases:
  code='$_SESSION=["csrf"=>"isolated-render-test"];$_SERVER["REQUEST_URI"]='+json.dumps(route)+';$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REMOTE_ADDR"]="127.0.0.1";$_SERVER["HTTP_HOST"]="candidate.localhost";$_GET=json_decode('+json.dumps(json.dumps(query))+',true);require '+json.dumps(str(public/file))+';'
  result=subprocess.run(['php','-d','display_errors=stderr','-r',code],text=True,capture_output=True,check=True)
  if result.stderr.strip():raise ValueError('PHP warning while rendering '+route+': '+result.stderr[:180])
  page=Page();page.feed(result.stdout)
  if (page.headers,page.footers,page.menus)!=(1,1,1):raise ValueError(f'Shell missing/duplicated at {route}: {(page.headers,page.footers,page.menus)}')
  if any(not url.startswith('https://explorandolugares.com/') for url in page.alternates):raise ValueError('Relative/wrong alternate '+route)
  missing=[]
  for resource in page.resources:
   url=urlsplit(urljoin('https://example.invalid'+route,resource))
   if url.netloc!='example.invalid':continue
   target=public/url.path.lstrip('/')
   if not target.is_file():missing.append(resource)
  if missing:raise ValueError('Missing resources '+route+' '+str(missing))
  results.append({'route':route,'shared_shell':True,'resources':len(page.resources),'absolute_alternates':True})
 output.parent.mkdir(parents=True,exist_ok=True);output.write_text(json.dumps({'database':'disposable SQLite','production_accessed':False,'checks':results},ensure_ascii=False,indent=2)+'\n');print(f'OK: {len(results)} initial HTML responses, one shell/menu, resources and language alternates.')
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--package',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args();run(a.package,a.output)
