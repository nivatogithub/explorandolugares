"""Rebuild reviewed batch solely from versioned manifests and WebP assets."""
import argparse,hashlib,json,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parent

def encoded(v):return (json.dumps(v,ensure_ascii=False,indent=2,sort_keys=True)+'\n').encode()
def sha(v):return hashlib.sha256(v).hexdigest()
def build(out):
 out=out.resolve()
 if out==ROOT or ROOT in out.parents:raise ValueError('Output must be outside sources')
 out.mkdir(parents=True,exist_ok=True)
 retired={x['id'].lower() for x in json.loads((ROOT/'retired.json').read_text())['records']}
 geo={x['id'].lower():x for x in json.loads((ROOT/'geolocation.json').read_text())}
 inventory=[];states=[];seen=set()
 for path in sorted((ROOT/'places').glob('*/manifest.json')):
  d=json.loads(path.read_text());slug=d['destination']['slug'];assert slug==path.parent.name
  assert set(d['destination']['translations'])=={'es','en','pt-BR'}
  for lang in ['es','en','pt-BR']:assert d['destination']['reasons'][lang]
  files={'paquete-destino.json':encoded(d)}
  for a in [d['hero_image'],*[x['image'] for x in d['places']]]:
   p=(path.parent/a['file']).resolve();assert p.is_relative_to(path.parent.resolve()) and p.suffix=='.webp'
   raw=p.read_bytes();assert raw[:4]==b'RIFF' and raw[8:12]==b'WEBP';files[a['file']]=raw
  for x in d['places']:
   k=x['slug'];assert k not in seen and k not in retired;seen.add(k)
   assert set(x['translations'])=={'es','en','pt-BR'}
   for t in x['translations'].values():assert t['name'] and t['description']
   g=geo[k]
   if g['ready_for_import']:assert (x['latitude'],x['longitude'])==(g['latitude'],g['longitude'])
   states.append({'destination_slug':slug,'slug':k,'review_status':'identified' if g['ready_for_import'] else 'precision_pending','entrance_verified':False})
  target=out/(slug+'.zip')
  with zipfile.ZipFile(target,'w') as z:
   for n,raw in sorted(files.items()):
    i=zipfile.ZipInfo(n,(2026,11,4,0,0,0));i.compress_type=zipfile.ZIP_DEFLATED;i.external_attr=0o100644<<16;z.writestr(i,raw)
  inventory.append({'slug':slug,'file':target.name,'sha256':sha(target.read_bytes()),'assets':{n:sha(raw) for n,raw in files.items()},'attractions':len(d['places'])})
 assert len(inventory)==38 and len(seen)==75
 businesses=[]
 for p in sorted((ROOT/'businesses').glob('*.json')):
  d=json.loads(p.read_text());assert d['schema_version']=='business-import-v1';businesses.append(d)
 (out/'businesses.json').write_bytes(encoded(businesses))
 (out/'publication-intent.json').write_bytes(encoded({'approved_for_preprod':False,'attractions':states,'retirements':[{'slug':s,'action':'archive_preserving_identity'} for s in sorted(retired)],'note':'Existing identities must be updated, not recreated. The four precision exceptions remain pending. No full-batch cross-environment deployment has been validated.'}))
 (out/'inventory.json').write_bytes(encoded({'places':inventory,'businesses_sha256':sha((out/'businesses.json').read_bytes()),'publication_intent_sha256':sha((out/'publication-intent.json').read_bytes())}))
 print(json.dumps({'places':len(inventory),'attractions':len(seen),'businesses':sum(len(x['businesses']) for x in businesses),'retired':len(retired),'precision_pending':sum(x['review_status']=='precision_pending' for x in states)}))
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--output',required=True,type=Path);build(p.parse_args().output)
