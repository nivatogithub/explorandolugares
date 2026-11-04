"""Build deterministic import packages exclusively from versioned batch sources."""
from pathlib import Path
import argparse, hashlib, json, zipfile
ROOT=Path(__file__).resolve().parent

def digest(data):return hashlib.sha256(data).hexdigest()
def encoded(value):return (json.dumps(value,ensure_ascii=False,indent=2,sort_keys=True)+'\n').encode()
def build(output):
 output=output.resolve()
 if output==ROOT or ROOT in output.parents:raise ValueError('Output must be outside the canonical sources')
 output.mkdir(parents=True,exist_ok=True);inventory=[];publication=[]
 geography=json.loads((ROOT/'verified-geography.json').read_text())
 for manifest in sorted((ROOT/'places').glob('*/manifest.draft.json')):
  data=json.loads(manifest.read_text());slug=data['destination']['slug'];assert slug==manifest.parent.name
  files={'paquete-destino.json':encoded(data)}
  for asset in [data['hero_image'],*[poi['image'] for poi in data['places']]]:
   relative=Path(asset['file']);path=(manifest.parent/relative).resolve()
   if not path.is_relative_to(manifest.parent.resolve()) or relative.suffix!='.webp':raise ValueError('Invalid media path')
   raw=path.read_bytes()
   if raw[:4]!=b'RIFF' or raw[8:12]!=b'WEBP':raise ValueError('Invalid WebP')
   files[relative.as_posix()]=raw
  states=[]
  for poi in data['places']:
   g=geography.get(poi['slug']);ready=poi.get('type')=='experience' or bool(g and g.get('verified_identity') and poi.get('latitude')==g['latitude'] and poi.get('longitude')==g['longitude'])
   states.append({'slug':poi['slug'],'publication_status':'published' if ready else 'draft'})
  publication.append({'slug':slug,'publication_status':'published','attractions':states})
  path=output/(slug+'.zip')
  with zipfile.ZipFile(path,'w') as z:
   for name,raw in sorted(files.items()):
    info=zipfile.ZipInfo(name,(2026,11,4,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;z.writestr(info,raw,compresslevel=6)
  inventory.append({'slug':slug,'file':path.name,'sha256':digest(path.read_bytes()),'files':{name:digest(raw) for name,raw in sorted(files.items())}})
 assert len(inventory)==30
 businesses=[]
 for f in sorted((ROOT/'businesses').glob('*.json')):
  package=json.loads(f.read_text())
  if isinstance(package,dict) and package.get('schema_version')=='business-import-v1':businesses.append(package)
 # Research candidates are intentionally excluded. Current verified points are independent from claim/legal status.
 verified=json.loads((ROOT/'businesses/verified-geolocation.json').read_text())
 for row in verified:assert any(p['destination_slug']==row['destination_slug'] and any(b['name']==row['name'] for b in p['businesses']) for p in businesses)
 (output/'businesses.json').write_bytes(encoded({'packages':businesses,'verified_locations':verified,'information_status':'PUBLICO','claim_status':'NO_RECLAMADO','operation_status':'DESCONOCIDO','plan_code':'free'}))
 (output/'publication-intent.json').write_bytes(encoded({'environment_validated':'dev1','approved_for_preprod':False,'places':publication,'matching':'Preserve existing IDs; match place/attraction by slug, business by place and reviewed identity. Never replace a database.'}))
 (output/'inventory.json').write_bytes(encoded({'schema_version':1,'places':inventory,'businesses_sha256':digest((output/'businesses.json').read_bytes()),'publication_intent_sha256':digest((output/'publication-intent.json').read_bytes())}))
 print(json.dumps({'packages':len(inventory),'businesses':sum(len(p['businesses']) for p in businesses),'located_businesses':len(verified),'pending_attractions':sum(x['publication_status']=='draft' for p in publication for x in p['attractions'])}))
if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('--output',type=Path,required=True);build(parser.parse_args().output)
