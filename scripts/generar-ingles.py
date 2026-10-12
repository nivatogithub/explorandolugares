#!/usr/bin/env python3
"""Genera páginas estáticas en inglés, con assets compartidos y enlaces equivalentes."""
from pathlib import Path
from html.parser import HTMLParser
from html import escape
from urllib.parse import urlsplit,unquote
import json,os,re
ROOT=Path(__file__).resolve().parents[1]
SITE=ROOT/'ensayos/v1'
D=json.loads((ROOT/'content/en.json').read_text())
options=json.loads((ROOT/'backend/app/contact-options.json').read_text())
D.update({name:name for name in options['municipalities']})
for group in options['groups']:
 D[group['label']]=group['en']
 for item in group['categories']:D[item['label']]=item['en']
TYPES={item['value']:item for item in json.loads((ROOT/'content/service-categories.json').read_text())['categories']}
# El contenido editorial de cada participante aporta su propia versión inglesa.
for item in json.loads((SITE/'assets/catalogo.json').read_text()).get('publicaciones',[]):
 if item.get('tipoServicio') in TYPES:
  category=TYPES[item['tipoServicio']]
  item={**item,'categoria':category['label'],'categoriaEn':category['en']}
 town={'jerico':'Jericó', **{d['slug']:d['name'] for d in json.loads((ROOT/'content/destinos-piloto.json').read_text())['destinations']}}.get(item.get('destino'),'')
 if isinstance(item.get('nombre'),str):D[item['nombre']+' | '+town]=item.get('nombreEn',item['nombre'])+' | '+town
 if isinstance(item.get('categoria'),str):D[item['categoria']+' · '+town+', Antioquia']=item.get('categoriaEn',item['categoria'])+' · '+town+', Antioquia'
 for key in ['nombre','categoria','descripcion','direccion','horarios']:
  if isinstance(item.get(key),str):
   D[item[key]]=item.get(key+'En',item[key])
 if isinstance(item.get('descripcion'),str) and isinstance(item.get('descripcionEn'),str):D[item['descripcion'][:160]]=item['descripcionEn'][:160]
 for es,en in zip(item.get('servicios',[]),item.get('serviciosEn',[])):D[es]=en

# Las guías y fotografías contienen sus propias traducciones editoriales.
destinations=json.loads((ROOT/'content/destinos-piloto.json').read_text())['destinations']
destination_names={'jerico':'Jericó', **{d['slug']:d['name'] for d in destinations}}
for d in destinations:
 for pair in [d['headline'],d['intro'],d['history'],*d['reasons'],*[p[k] for p in d['places'] for k in ['name','description']]]:D[pair['es']]=pair['en']
 D[d['region']+' · Antioquia, Colombia']=D.get(d['region'],d['region'])+' · Antioquia, Colombia'
 D[d['name']+'.']=d['name']+'.'
 D[d['name']+', Antioquia']=d['name']+', Antioquia'
for name in destination_names.values():
 for es,en in [('La historia de ','The history of '),('¿Por qué visitar ','Why visit '),('Qué conocer en ','What to see in '),('Directorio de ','Local directory of '),('Ubicar ','Find '),('Eventos en ','Events in '),('¿Tienes un negocio en ','Do you have a business in '),('en ','in '),('Seguir explorando ','Keep exploring ')]:
  suffixes=['','?',' ↗',' en el mapa ↗']
  for suffix in suffixes:
   translated=' on the map ↗' if suffix==' en el mapa ↗' else suffix
   D[es+name+suffix]=en+name+translated
 D[name+'?']=name+'?'
 D[name+' · Antioquia, Colombia']=name+' · Antioquia, Colombia'
for photos in json.loads((ROOT/'content/destinos-fotografias.json').read_text()).values():
 for photo in photos:
  D[photo['caption']['es']]=photo['caption']['en']
  D[photo['caption']['es']+':']=photo['caption']['en']+':'
  D[photo['author']]=photo['author'];D[photo['license']]=photo['license']
for d in destinations:
 for source in d['sources']:D[urlsplit(source).netloc]=urlsplit(source).netloc

missing=set()
def translate(text):
 key=text.strip()
 if not key:return text
 if key in D:value=D[key]
 elif key.endswith(' | Explorando Lugares'):
  base=key.removesuffix(' | Explorando Lugares');value=translate(base)+' | Explorando Lugares'
 elif key.endswith(' en Jericó'):value=translate(key.removesuffix(' en Jericó'))+' in Jericó'
 elif not re.search(r'[A-Za-zÁ-ÿ]',key) or key in {'/ Jericó'}:return text
 else:missing.add(key);return text
 return text[:len(text)-len(text.lstrip())]+value+text[len(text.rstrip()):]
def relative(target,current):return os.path.relpath(target,(SITE/current).parent).replace(os.sep,'/')
def lang_links(relative_file,english):
 current=Path('en')/relative_file if english else relative_file
 es=relative(SITE/relative_file,current);en=relative(SITE/'en'/relative_file,current)
 return f'<span class="language-switch" aria-label="{"Language" if english else "Idioma"}"><a href="{escape(es)}" lang="es" hreflang="es" aria-current="{str(not english).lower()}">ES</a><a href="{escape(en)}" lang="en" hreflang="en" aria-current="{str(english).lower()}">EN</a></span>'
class TranslatePage(HTMLParser):
 def __init__(self,file):super().__init__(convert_charrefs=False);self.file=file;self.output=[];self.skip=0;self.tag=''
 def rewrite(self,url):
  u=urlsplit(url)
  if u.scheme or u.netloc:return url
  target=((SITE/self.file).parent/unquote(u.path)).resolve() if u.path else (SITE/self.file).resolve()
  if not target.is_relative_to(SITE.resolve()):raise ValueError(url)
  if target.suffix=='.html':target=SITE/'en'/target.relative_to(SITE)
  path=relative(target,Path('en')/self.file) if u.path else ''
  return path+('?' + u.query if u.query else '')+('#'+u.fragment if u.fragment else '')
 def handle_decl(self,decl):self.output.append('<!'+decl+'>')
 def handle_starttag(self,tag,attrs):
  if tag in {'script','style'}:self.skip+=1
  self.tag=tag;result=[]
  attributes=dict(attrs)
  for k,v in attrs:
   if v is None:result.append(k);continue
   if tag=='html' and k=='lang':v='en'
   elif k in {'href','src','action','data-town-image','data-city-image'}:v=self.rewrite(v)
   elif k in {'alt','aria-label','placeholder','label'}:v=translate(v)
   elif tag=='meta' and k=='content' and attributes.get('name')=='description':v=translate(v)
   elif tag=='meta' and k=='content' and attributes.get('http-equiv')=='refresh':v='0;url='+self.rewrite(v.split('url=',1)[1])
   result.append(k+'="'+escape(v,quote=True)+'"')
  self.output.append('<'+tag+(' '+' '.join(result) if result else '')+'>')
 def handle_startendtag(self,tag,attrs):self.handle_starttag(tag,attrs)
 def handle_endtag(self,tag):
  self.output.append('</'+tag+'>')
  if tag in {'script','style'}:self.skip-=1
 def handle_data(self,data):self.output.append(data if self.skip else escape(translate(data),quote=False))
 def handle_entityref(self,name):self.output.append('&'+name+';')
 def handle_charref(self,name):self.output.append('&#'+name+';')
 def handle_comment(self,data):self.output.append('<!--'+data+'-->')
def mark_navigation(source,relative_file):
 # Resolver enlaces respecto a cada página: el estado queda disponible sin JS.
 def mark_menu(match):
  def mark_link(link):
   tag=link[0];href=re.search(r'href="([^"]+)"',tag)
   if not href:return tag
   tag=re.sub(r' aria-current="[^"]*"','',tag)
   url=urlsplit(href[1])
   if url.scheme or url.netloc:return tag
   target=((SITE/relative_file).parent/unquote(url.path)).resolve()
   current=(SITE/relative_file).resolve()
   state=None
   if target==current:state='page'
   elif target==SITE/'destinos/index.html' and relative_file.parts[0]=='destinos':state='location'
   if state:tag=tag[:-1]+' aria-current="'+state+'">'
   return tag
  return re.sub(r'<a\b[^>]*>',mark_link,match[0])
 source=re.sub(r'<nav aria-label="Principal">.*?</nav>',mark_menu,source,count=1,flags=re.S)
 if 'class="destination-nav"' in source and 'assets/navegacion.js' not in source:
  path=relative(SITE/'assets/navegacion.js',relative_file)
  source=source.replace('</body>','<script src="'+path+'" defer></script></body>')
 return source

files=[p for p in SITE.rglob('*.html') if 'en' not in p.relative_to(SITE).parts]
for p in files:
 rel=p.relative_to(SITE);source=p.read_text()
 source=re.sub(r'<span class="language-switch".*?</span>','',source,flags=re.S)
 source=re.sub(r'<link rel="alternate"[^>]*>','',source)
 source=mark_navigation(source,rel)
 page=TranslatePage(rel);page.feed(source);english=''.join(page.output)
 # Insert translated language links after parsing so no text is re-translated.
 source=source.replace('</nav>',lang_links(rel,False)+'</nav>',1)
 english=english.replace('</nav>',lang_links(rel,True)+'</nav>',1)
 def alternate(eng):
  current=Path('en')/rel if eng else rel
  return '<link rel="alternate" hreflang="es" href="'+relative(SITE/rel,current)+'"><link rel="alternate" hreflang="en" href="'+relative(SITE/'en'/rel,current)+'">'
 source=source.replace('</head>',alternate(False)+'</head>',1)
 english=english.replace('</head>',alternate(True)+'</head>',1)
 p.write_text(source);dest=SITE/'en'/rel;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_text(english)
if missing:
 print('Faltan traducciones:\n'+'\n'.join(sorted(missing)));raise SystemExit(1)
print(f'OK: {len(files)} páginas inglesas y enlaces de idioma equivalentes.')
