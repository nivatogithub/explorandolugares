#!/usr/bin/env python3
"""Freeze explicitly approved destination content and resolved media; never changes a DB."""
import argparse,json,hashlib,shutil
from pathlib import Path
R=Path(__file__).resolve().parents[1]
a=argparse.ArgumentParser();a.add_argument('--input',type=Path,required=True);a=a.parse_args();d=json.loads(a.input.read_text());
def source(path):
 p=R/'.local/preprod/release/public'/path
 if p.is_file():return p
 for f in (R/'.local/preprod/worker-review/lote-20261019/cierre-nueve-destinos-20261020').glob('*/media/'+Path(path).name):return f
 return p
visual=json.loads((R/'backend/integration-medicion/public/assets/destinos-semirrealistas.json').read_text());assets=d['tables']['media_assets'];trs=d['tables']['media_asset_translations'];byid={int(x['id']):x for x in assets};n=-1
for slug,e in d['editorial'].items():
 e.pop('internal_research_json',None)
 e['places']=[p for p in e['places'] if p['publication_status']=='published']
 e['publication_status']='published'
 if slug in visual:
  e['destination_media'].pop('history',None)
  for role,pic,poi in [('hero',visual[slug]['hero'],None)]+[('place',visual[slug]['places'][p['slug']],p) for p in e['places'] if p['slug'] in visual[slug]['places']]:
   path=pic['path'];src=source(path)
   if not src.is_file():raise ValueError('Missing selected image '+path)
   m={'id':n,'asset_path':path,'origin':'ai_generated','creator':'Explorando Lugares · ilustración IA','source_url':'','license_name':'Procedencia conservada en entrega del destino','license_url':'','width':pic.get('width',960),'height':pic.get('height',640),'provenance_json':json.dumps({'sha256':hashlib.sha256(src.read_bytes()).hexdigest(),'destination':slug,'selection':'current reviewed preprod'})};assets.append(m);byid[n]=m
   for l in ['es','en','pt-BR']:trs.append({'media_asset_id':n,'locale':l,'alt_text':pic.get('alt',''),'caption':''})
   if poi is None:e['destination_media']['hero']={'role':'hero','media_asset_id':n,'asset_path':path}
   else:poi['media_asset_id']=n
   n-=1
used={int(m['media_asset_id']) for e in d['editorial'].values() for m in e['destination_media'].values()};used|={int(p['media_asset_id']) for e in d['editorial'].values() for p in e['places'] if p['media_asset_id']};used|={int(m['media_asset_id']) for m in d['tables']['business_media']}
d['tables']['media_assets']=[m for m in assets if int(m['id']) in used];d['tables']['media_asset_translations']=[m for m in trs if int(m['media_asset_id']) in used];manifest=[]
for m in d['tables']['media_assets']:
 path=m['asset_path'];src=source(path)
 if not src.is_file():raise ValueError('Missing media '+path)
 dest=R/'backend/public'/path;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(src,dest);manifest.append({'path':path,'sha256':hashlib.sha256(src.read_bytes()).hexdigest(),'origin':m['origin']})
# Do not carry pricing or subscriptions from a local environment into a release dataset.
for key in ['subscription_plans','subscription_plan_translations','subscription_features','subscription_feature_translations','subscription_plan_features','subscription_plan_prices','subscription_pricing_policy','experience_operator_prices','business_research_contacts']:d['tables'].pop(key,None)
d['public_places']=list(d['editorial']);(R/'content/release-places-businesses.json').write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n');(R/'backend/app/content/release-media-manifest.json').write_text(json.dumps({'schema_version':1,'media':manifest},ensure_ascii=False,indent=2)+'\n');print(json.dumps({'destinations':len(d['editorial']),'media':len(manifest),'database_changed':False}))
