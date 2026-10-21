"""Create migration snapshots from the approved canonical release, without local IDs as remote identities."""
import json,hashlib
from pathlib import Path
root=Path(__file__).resolve().parents[1]
r=json.loads((root/'content/release-places-businesses.json').read_text())
raw=root/'.local/preprod/worker-review/lote-20261019/cierre-nueve-destinos-20261020'
out=root/'.local/preprod/worker-review/lote-20261019/snapshots-finales';out.mkdir(exist_ok=True)
assets={m['id']:m for m in r['tables']['media_assets']}
for slug in r['scope']:
 s=json.loads((raw/slug/'snapshot-public-content.json').read_text());t=s['tables'];d=r['editorial'][slug]
 allowed_business_ids={b['id'] for b in r['tables']['businesses']}
 for table in list(t):
  if table=='businesses':t[table]=[b for b in t[table] if b['id'] in allowed_business_ids]
  elif table.startswith('business_'):t[table]=[b for b in t[table] if b['business_id'] in allowed_business_ids]
 published={p['slug']:p for p in d['places']}
 for p in t['points_of_interest']:
  item=published.get(p['slug']);p['publication_status']='published' if item else 'draft'
  if item:p.update({k:v for k,v in item['translations']['es'].items() if k in ['display_order','poi_type','latitude','longitude']})
 t['point_of_interest_media']=[]
 for p in d['places']:
  t['point_of_interest_media'].append({'point_of_interest_id':p['id'],'media_asset_id':p['media_asset_id'],'role':'gallery','display_order':1})
  for locale,tr in p['translations'].items():
   for stored in t['point_of_interest_translations']:
    if stored['point_of_interest_id']==p['id'] and stored['locale']==locale:stored.update({k:v for k,v in tr.items() if k in ['name','description','map_url','external_url','external_label']})
 t['destination_media']=[{'destination_id':d['id'],'role':role,'media_asset_id':m['media_asset_id'],'display_order':1} for role,m in d['destination_media'].items()]
 ids={m['media_asset_id'] for m in t['destination_media']+t['point_of_interest_media']+t.get('business_media',[])}
 t['media_assets']=[assets[i] for i in ids]
 t['media_asset_translations']=[m for m in r['tables']['media_asset_translations'] if m['media_asset_id'] in ids]
 s['media_files']={str(m['id']):{'sha256':hashlib.sha256((root/'backend/public'/m['asset_path']).read_bytes()).hexdigest()} for m in t['media_assets']}
 (out/(slug+'.json')).write_text(json.dumps(s,ensure_ascii=False))
print('Nueve snapshots finales generados con los medios canónicos aprobados.')
