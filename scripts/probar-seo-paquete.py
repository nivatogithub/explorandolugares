#!/usr/bin/env python3
"""Read-only final PHP/HTML SEO gate against a disposable package, never live data."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urljoin, urlsplit
import argparse, json, os, re, sqlite3, subprocess

ROOT=Path(__file__).resolve().parents[1]
ORIGIN='https://explorandolugares.com'

class Page(HTMLParser):
 def __init__(self):
  super().__init__();self.canonicals=[];self.alternates={};self.meta={};self.hrefs=[];self.h1=0;self.title='';self.in_title=False;self.json_blocks=[];self.schema=False;self.images=[];self.unlinked_anchors=0;self.heading_levels=[]
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='title':self.in_title=True
  if tag=='h1':self.h1+=1
  if re.fullmatch(r'h[1-6]',tag):self.heading_levels.append(int(tag[1]))
  if tag=='meta':self.meta.setdefault(a.get('name') or a.get('property'),[]).append(a.get('content',''))
  if tag=='link' and a.get('rel')=='canonical':self.canonicals.append(a.get('href'))
  if tag=='link' and a.get('rel')=='alternate':
   lang=a.get('hreflang');assert lang not in self.alternates,'Duplicate alternate';self.alternates[lang]=a.get('href')
  if tag=='a':
   self.hrefs.append(a.get('href',''))
   if not a.get('href') and a.get('role')!='button':self.unlinked_anchors+=1
  if tag=='img':self.images.append(a)
  if tag=='script' and a.get('type')=='application/ld+json':self.schema=True;self.json_blocks.append('')
 def handle_endtag(self,tag):
  if tag=='title':self.in_title=False
  if tag=='script':self.schema=False
 def handle_data(self,value):
  if self.in_title:self.title+=value
  if self.schema:self.json_blocks[-1]+=value

def check(html,route,locale,structured=True):
 page=Page();page.feed(html)
 assert page.canonicals==[ORIGIN+route],(route,'canonical',page.canonicals)
 assert page.h1==1 and page.title.strip(),(route,'title/H1')
 assert len(page.meta.get('description',[]))==1 and page.meta['description'][0].strip(),(route,'description')
 assert not any('noindex' in v for v in page.meta.get('robots',[])),(route,'unexpected noindex')
 for field in ['og:title','og:description','og:url','twitter:card']:
  assert len(page.meta.get(field,[]))==1,(route,field,'missing or duplicate')
 assert page.meta['og:url']==[ORIGIN+route],(route,'social canonical')
 assert page.alternates.get(locale)==ORIGIN+route,(route,'self alternate')
 assert set(page.alternates)=={'es','en','pt-BR','x-default'},(route,'languages',page.alternates)
 for href in page.hrefs:
  target=urlsplit(urljoin(ORIGIN+route,href))
  if target.netloc=='explorandolugares.com':
   assert not target.path.endswith('/index.html'),(route,'noncanonical link',href)
   assert '/destinos/' not in target.path,(route,'old place route',href)
   assert not target.path.startswith(('/en/negocios/registro','/pt-br/negocios/registro','/en/negocios/acceso','/pt-br/negocios/acceso')),(route,'invented account route',href)
 if structured:assert page.json_blocks,(route,'missing structured data')
 for block in page.json_blocks:
  schema=json.loads(block);assert schema['@context']=='https://schema.org' and schema['@graph'],(route,'schema')
 return page

def run(package,output):
 package=package.resolve();database=package/'private/presentation.sqlite';public=package/'public'
 if not package.is_relative_to(ROOT/'.local') or not database.is_file():raise ValueError('A disposable presentation fixture is required')
 release=json.loads((ROOT/'content/release-places-businesses.json').read_text());results=[];pages={};titles=set()
 def render(route,file,query,expected_status=200):
  code='register_shutdown_function(static function(){fwrite(STDERR,"HTTP_STATUS:".(http_response_code()?:200));});$_SESSION=[];$_SERVER["REQUEST_URI"]='+json.dumps(route)+';$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REMOTE_ADDR"]="127.0.0.1";$_SERVER["HTTP_HOST"]="candidate.localhost";$_GET=json_decode('+json.dumps(json.dumps(query))+',true);require '+json.dumps(str(public/file))+';'
  env={k:v for k,v in os.environ.items() if not k.startswith('EL_DESTINATIONS_') and k!='EL_PUBLIC_BASE_URL'}
  result=subprocess.run(['php','-d','display_errors=stderr','-r',code],text=True,capture_output=True,env=env)
  status=re.search(r'HTTP_STATUS:(\d+)$',result.stderr)
  if result.returncode or not status or result.stderr[:status.start()].strip():raise ValueError(route+': '+result.stderr[:500])
  assert int(status[1])==expected_status,(route,'status',status[1])
  return result.stdout
 for locale,prefix in [('es',''),('en','/en'),('pt-BR','/pt-br')]:
  for slug in release['public_places']:
   route=prefix+'/lugares/'+slug+'/';page=check(render(route,'destino.php',{'destino':slug,'idioma':locale}),route,locale)
   assert page.title not in titles,('duplicate title',route);titles.add(page.title)
   assert page.unlinked_anchors==0,(route,'anchor without a destination')
   assert all(next_level<=level+1 for level,next_level in zip(page.heading_levels,page.heading_levels[1:])),(route,'skipped heading level',page.heading_levels)
   assert any(i.get('fetchpriority')=='high' and i.get('width') and i.get('height') for i in page.images),(route,'hero dimensions/priority')
   pages[ORIGIN+route]=page;results.append({'route':route,'kind':'place','schema':True})
  route=prefix+'/';page=check(render(route,prefix.lstrip('/')+('/' if prefix else '')+'index.php',{}),route,locale)
  pages[ORIGIN+route]=page;results.append({'route':route,'kind':'home','schema':True})
  route=prefix+'/negocios-locales/';page=check(render(route,'business/index.php',{'lang':locale.lower()}),route,locale,False)
  pages[ORIGIN+route]=page;results.append({'route':route,'kind':'business-information'})
 # Synthetic paid page in this disposable database only; no production business is changed.
 db=sqlite3.connect(database)
 destination,category=db.execute("SELECT b.destination_id,b.category_code FROM businesses b JOIN destinations d ON d.id=b.destination_id WHERE d.publication_status='published' LIMIT 1").fetchone()
 public_id='00000000-0000-4000-8000-000000059059'
 cursor=db.execute("INSERT INTO businesses(public_id,destination_id,category_code,slug,information_status,operation_status,publication_status) VALUES(?,?,?,'seo-fixture','VERIFICADO','ABIERTO','published')",(public_id,destination,category))
 business_id=cursor.lastrowid
 for language in ['es','en','pt-BR']:
  db.execute('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)',(business_id,language,'SEO fixture '+language,'Café y tradición.','Café y tradición.'))
 db.execute("INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,starts_at,ends_at) VALUES(?,'page','monthly','active',datetime('now','-1 day'),datetime('now','+30 days'))",(business_id,));db.commit()
 for locale,prefix in [('es',''),('en','/en'),('pt-BR','/pt-br')]:
  route=prefix+'/negocios/'+public_id+'/'
  page=check(render(route,'negocio.php',{'id':public_id,'idioma':locale}),route,locale)
  pages[ORIGIN+route]=page;results.append({'route':route,'kind':'synthetic-paid-business','schema':True})
 # Missing translations must not be advertised through a Spanish fallback.
 db.execute("DELETE FROM business_translations WHERE business_id=? AND locale='en'",(business_id,));db.commit()
 partial=Page();partial.feed(render('/negocios/'+public_id+'/','negocio.php',{'id':public_id,'idioma':'es'}))
 assert 'en' not in partial.alternates and 'pt-BR' in partial.alternates,'Invented business translation'
 assert render('/en/negocios/'+public_id+'/','negocio.php',{'id':public_id,'idioma':'en'},301)=='','Missing translation must redirect to real content, not create an indexable Spanish duplicate'
 db.execute('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)',(business_id,'en','SEO fixture en','Café y tradición.','Café y tradición.'));db.commit();db.close()
 for url,page in pages.items():
  for lang,alternate in page.alternates.items():
   if lang=='x-default':continue
   assert alternate in pages and url in pages[alternate].alternates.values(),('nonreciprocal alternate',url,alternate)
 output.parent.mkdir(parents=True,exist_ok=True);output.write_text(json.dumps({'database':'disposable SQLite','live_database_accessed':False,'checks':results},ensure_ascii=False,indent=2)+'\n')
 print(f'OK SEO: {len(results)} final PHP pages; unique place titles, canonical links, complete metadata, reciprocal languages, safe JSON-LD and hero hints.')

if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--package',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args();run(a.package,a.output)
