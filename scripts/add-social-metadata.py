"""Add social metadata to public static HTML with canonical URLs."""
import argparse,re,html
from pathlib import Path
from html.parser import HTMLParser
class Head(HTMLParser):
 def __init__(self):super().__init__();self.title='';self.in_title=False;self.description='';self.canonical='';self.lang='es'
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='html':self.lang=a.get('lang','es')
  if tag=='title':self.in_title=True
  if tag=='meta' and a.get('name')=='description':self.description=a.get('content','')
  if tag=='link' and a.get('rel')=='canonical':self.canonical=a.get('href','')
 def handle_endtag(self,tag):
  if tag=='title':self.in_title=False
 def handle_data(self,data):
  if self.in_title:self.title+=data
p=argparse.ArgumentParser();p.add_argument('--public',required=True);args=p.parse_args();count=0
for f in Path(args.public).rglob('*.html'):
 s=f.read_text();h=Head();h.feed(s)
 if not h.canonical.startswith('https://explorandolugares.com/') or not h.title or not h.description or 'noindex' in s[:s.find('</head>')]:continue
 if 'property="og:title"' in s:continue
 image='https://explorandolugares.com/assets/brand/explorando-lugares-logo-v2-transparent.png'
 values={'og:type':'website','og:site_name':'Explorando Lugares','og:title':h.title,'og:description':h.description,'og:url':h.canonical,'og:image':image,'og:image:alt':'Explorando Lugares','twitter:card':'summary','twitter:title':h.title,'twitter:description':h.description,'twitter:image':image}
 tags='\n'.join('<meta '+('name' if k.startswith('twitter:') else 'property')+'="'+k+'" content="'+html.escape(v,quote=True)+'">' for k,v in values.items())
 s=s.replace('</head>',tags+'\n</head>',1);f.write_text(s);count+=1
print(f'Metadatos sociales: {count} páginas estáticas.')
