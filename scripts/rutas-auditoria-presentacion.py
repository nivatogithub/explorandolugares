#!/usr/bin/env python3
"""Catalogue all built HTML routes plus application/menu entry points; no live access."""
from pathlib import Path
from html.parser import HTMLParser
import argparse,json,re
ROOT=Path(__file__).resolve().parents[1]
def catalogue(packages):
 routes={}
 for public in packages:
  for p in public.rglob('*.html'):
   rel=p.relative_to(public).as_posix();route='/'+rel
   if route.endswith('index.html'):route=route[:-10]
   family='public-static'
   if rel.startswith('admin/'):family='historical-admin-static'
   if rel.startswith('__'):family='historical-prototype'
   routes[route]={'route':route,'family':family,'source':rel,'roles':['anonymous','traveler','administrator']}
 release=json.loads((ROOT/'content/release-places-businesses.json').read_text())
 for lang,prefix in [('es',''),('en','/en'),('pt-BR','/pt-br')]:
  for slug in release['public_places']:
   route=prefix+'/lugares/'+slug+'/'
   routes[route]={'route':route,'family':'place','source':'destino.php','language':lang,'roles':['anonymous','traveler','administrator']}
 for route in ['/contacto.php','/reclamar-negocio/','/negocios/acceso/','/negocios/registro/','/negocios/recuperar-clave/','/negocios/portal/','/negocios/portal/?tab=personal','/negocios/portal/?tab=business']:
  routes[route]={'route':route,'family':'account' if route.startswith('/negocios/') else 'contact','source':'PHP application','roles':['anonymous','traveler','administrator']}
 nav=(ROOT/'backend/public/admin/_navigation.php').read_text()
 for link in re.findall('href="([^"]+)"',nav):
  if link.startswith('/') or '<?= ' in link:continue
  route='/admin/'+link
  routes[route]={'route':route,'family':'admin','source':'administration navigation','roles':['anonymous','traveler','administrator']}
 return sorted(routes.values(),key=lambda x:(x['family'],x['route']))
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--baseline',type=Path,required=True);p.add_argument('--candidate',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args();rows=catalogue([a.baseline/'public',a.candidate/'public']);a.output.parent.mkdir(parents=True,exist_ok=True);a.output.write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n');print(f'{len(rows)} routes ×3roles ×2packages; no form submissions or external checks.')
