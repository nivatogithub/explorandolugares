#!/usr/bin/env python3
"""Actualiza en la memoria PT-BR frases españolas que llegaron desde guías o plantillas."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urlencode
from urllib.request import urlopen
from concurrent.futures import ThreadPoolExecutor,as_completed
from html import unescape
import json,re
ROOT=Path(__file__).resolve().parents[1]; SITE=ROOT/'ensayos/v1/pt-br'; out=ROOT/'content/pt-br.json'
pt=json.loads(out.read_text())
class Reader(HTMLParser):
 def __init__(self):super().__init__();self.items=[]
 def handle_data(self,data):
  v=re.sub(r'\s+',' ',data).strip()
  if len(v)>3:self.items.append(v)
 def handle_starttag(self,tag,attrs):
  for k,v in attrs:
   if k in {'alt','aria-label','placeholder','content'} and v and len(v.strip())>3:self.items.append(v.strip())
# Strong Spanish signals; keeps proper names, credits and brand intact.
signals=re.compile(r'\b(el|la|los|las|un|una|para|con|desde|entre|del|al|que|por|y|en|su|sus|más|cómo|qué|dónde|aquí|todavía|próximo|conoce|explorar|paisaje|historia|vida|lugares|destinos|ciudad|pueblo|montañas|parque|iglesia|río)\b',re.I)
keep=re.compile(r'^(?:Explorando Lugares|CC BY.*|https?://.*)$')
items=[]
for page in SITE.rglob('*.html'):
 r=Reader();r.feed(page.read_text())
 for value in r.items:
  if signals.search(value) and not keep.fullmatch(value):items.append(value)
items=list(dict.fromkeys(items))
def translate(v):
 url='https://translate.googleapis.com/translate_a/single?'+urlencode({'client':'gtx','sl':'es','tl':'pt','dt':'t','q':v})
 data=json.loads(urlopen(url,timeout=25).read().decode())
 return unescape(''.join(p[0] for p in data[0] if p and p[0]))
changed=0
with ThreadPoolExecutor(max_workers=10) as pool:
 futures={pool.submit(translate,v):v for v in items}
 for n,f in enumerate(as_completed(futures),1):
  source=futures[f]
  try:
   value=f.result()
   if value and value!=source:pt[source]=value;changed+=1
  except Exception as e:print('skip',source[:50],e)
  if n%50==0:out.write_text(json.dumps(pt,ensure_ascii=False,indent=2)+'\n')
out.write_text(json.dumps(pt,ensure_ascii=False,indent=2)+'\n')
print(f'Analizadas {len(items)} frases; actualizadas {changed}.')
