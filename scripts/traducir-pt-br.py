#!/usr/bin/env python3
"""Construye una memoria PT-BR reanudable a partir de todo el contenido público español."""
from pathlib import Path
from urllib.parse import urlencode
from urllib.request import urlopen
from html import unescape
from concurrent.futures import ThreadPoolExecutor, as_completed
import argparse, json
ROOT=Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser(); p.add_argument('--limit',type=int,default=1000);p.add_argument('--reset',action='store_true');args=p.parse_args()
base=json.loads((ROOT/'content/en.json').read_text())
source={v:None for v in base if isinstance(v,str) and any(c.isalpha() for c in v)}
def add(value):
 if isinstance(value,str) and any(c.isalpha() for c in value): source.setdefault(value,None)
for d in json.loads((ROOT/'content/destinos-piloto.json').read_text())['destinations']:
 for pair in [d['headline'],d['intro'],d['history'],*d['reasons'],*[p[k] for p in d['places'] for k in ['name','description']]]: add(pair['es'])
for photos in json.loads((ROOT/'content/destinos-fotografias.json').read_text()).values():
 for photo in photos:add(photo['caption']['es'])
for group in json.loads((ROOT/'backend/app/contact-options.json').read_text())['groups']:
 add(group['label'])
 for item in group['categories']: add(item['label'])
for item in json.loads((ROOT/'content/service-categories.json').read_text())['categories']:add(item['label'])
target_path=ROOT/'content/pt-br.json'; target={} if args.reset else (json.loads(target_path.read_text()) if target_path.exists() else {})
def translate(value):
 if value in {'Explorando','Lugares'}:return value
 url='https://translate.googleapis.com/translate_a/single?'+urlencode({'client':'gtx','sl':'es','tl':'pt','dt':'t','q':value})
 data=json.loads(urlopen(url,timeout=25).read().decode())
 return unescape(''.join(part[0] for part in data[0] if part and part[0]))
pending=[value for value in source if value not in target][:args.limit]
with ThreadPoolExecutor(max_workers=8) as pool:
 futures={pool.submit(translate,value):value for value in pending}
 for n,future in enumerate(as_completed(futures),1):
  value=futures[future]
  try: target[value]=future.result()
  except Exception as e: print(f'No traducido: {value[:70]} ({e})')
  if n%50==0 or n==len(pending):
   target_path.write_text(json.dumps(target,ensure_ascii=False,indent=2)+'\n')
   print(f'PT-BR: {len(target)}/{len(source)}')
target_path.write_text(json.dumps(target,ensure_ascii=False,indent=2)+'\n')
print(f'PT-BR: {len(target)} entradas listas; {len(source)-len(target)} pendientes.')
