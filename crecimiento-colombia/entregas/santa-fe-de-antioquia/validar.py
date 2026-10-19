#!/usr/bin/env python3
"""Checks editorial completeness; deliberately refuses to call missing-image ZIP ready."""
import json,pathlib,hashlib,subprocess
p=pathlib.Path(__file__).resolve().parent
pkg=json.loads((p/('paquete-destino.json' if (p/'paquete-destino.json').exists() else 'paquete-destino.borrador.json')).read_text()); d=json.loads((p/'directorio-preprod-30.json').read_text())
assert len(pkg['places'])==12
for locale in ['es','en','pt-BR']:
 assert pkg['destination']['reasons'][locale] and all(x.strip() for x in pkg['destination']['reasons'][locale])
assert sum(x['type']=='place' for x in pkg['places'])==8
assert len(d['businesses'])==30
assert len({x['name'].casefold() for x in d['businesses']})==30
for x in pkg['places']:
 assert x['publication_status']=='draft'
 for l in ['es','en','pt-BR']:
  assert x['translations'][l]['name'] and 0<len(x['translations'][l]['description'])<150
for x in d['businesses']:
 assert x['sources'] and not x['description'] 
 assert all(s['url'].startswith('https://') and s['checked_at']=='2026-10-19' for s in x['sources'])
refs=[pkg['hero_image']['file']]+[x['image']['file'] for x in pkg['places']]
assert len(refs)==len(set(refs))
missing=[r for r in refs if not (p/r).is_file()]
r={'editorial_checks':'pass','attractions':8,'experiences':4,'free_directory':30,'unique_media_roles':len(refs),'missing_images':missing,'zip_ready':not missing,'preprod_tested':False,'publication_approved':False,'files':{f.name:hashlib.sha256(f.read_bytes()).hexdigest() for f in p.glob('*.json') if f.name!='VALIDACION.json'}}
(p/'VALIDACION.json').write_text(json.dumps(r,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({k:v for k,v in r.items() if k!='files'},ensure_ascii=False))
