"""Verify social metadata is declared by indexable static pages."""
import argparse
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
p=argparse.ArgumentParser();p.add_argument('--public',required=True);args=p.parse_args();checked=0
for f in Path(args.public).rglob('*.html'):
 s=f.read_text();h=Head();h.feed(s)
 if not h.canonical.startswith('https://explorandolugares.com/') or not h.title or not h.description or 'noindex' in s[:s.find('</head>')]:continue
 checked+=1
 if 'property="og:title"' not in s:
  raise ValueError(f'La página estática debe declarar sus metadatos sociales: {f}')
print(f'Metadatos sociales verificados sin recomposición: {checked} páginas estáticas indexables.')
