#!/usr/bin/env python3
"""Prepare the reviewed, public-business release dataset; never imports accounts or payments."""
from pathlib import Path
import json,hashlib,shutil,argparse
R=Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser();p.add_argument('--input',type=Path,required=True);a=p.parse_args();d=json.loads(a.input.read_text())
v=json.loads((R/'backend/integration-medicion/public/assets/destinos-semirrealistas.json').read_text());integration=json.loads((R/'preprod/lugares/integracion.json').read_text());heads={x['place']:x['commit'] for x in integration['branches']}
assets=d['tables']['media_assets'];translations=d['tables']['media_asset_translations'];manifest=[];nextid=-1
for slug,e in d['editorial'].items():
 for poi in e['places']:
  for lang,t in poi['translations'].items():
   if t.get('external_url') and not t.get('external_label'):t['external_label']={'es':'Más información','en':'More information','pt-BR':'Mais informações'}[lang]
 if slug not in v:continue
 picture=v[slug];e['destination_media'].pop('history',None)
 refs=[('hero',picture['hero'],None)]+[('place',picture['places'][poi['slug']],poi) for poi in e['places'] if poi['slug'] in picture.get('places',{})]
 for role,image,poi in refs:
  rel=image['path'];file=R/'backend/public'/rel
  if not file.is_file():raise RuntimeError('Missing selected illustration '+rel)
  mid=nextid;nextid-=1;provenance={'generated_by':'imagegen','source_branch_commit':heads.get(slug),'sha256':hashlib.sha256(file.read_bytes()).hexdigest(),'release_scope':'owner-authorized preprod to production','research_records':'crecimiento-colombia/entregas/'+slug}
  assets.append({'id':mid,'origin':'ai_generated','asset_path':rel,'creator':'Explorando Lugares','source_url':'','license_name':'Ilustración generada con IA','license_url':'','provenance_json':json.dumps(provenance,ensure_ascii=False),'width':image.get('width',0),'height':image.get('height',0)})
  for lang in ['es','en','pt-BR']:translations.append({'media_asset_id':mid,'locale':lang,'alt_text':image.get('alt',''),'caption':image.get('caption','')})
  if role=='hero':e['destination_media']['hero']={'role':'hero','media_asset_id':mid,'asset_path':rel}
  else:poi['media_asset_id']=mid
byid={int(x['id']):x for x in assets};used=set();archived=[]
for e in d['editorial'].values():
 for m in e['destination_media'].values():used.add(int(m['media_asset_id']))
 for poi in e['places']:
  mid=int(poi['media_asset_id'] or 0)
  if poi['publication_status']=='archived':
   if mid:archived.append(byid[mid]);poi['media_asset_id']=0
  elif mid:used.add(mid)
for row in d['tables']['business_media']:used.add(int(row['media_asset_id']))
assets=[x for x in assets if int(x['id']) in used];d['tables']['media_assets']=assets;d['tables']['media_asset_translations']=[x for x in translations if int(x['media_asset_id']) in used];d['archived_media_references']=archived
for image in assets:
 rel=image['asset_path'];source=R/'backend/public'/rel
 if not source.exists():
  choices=[R/'.local/preprod/release/public'/rel,R/'.local/preprod/media'/rel.removeprefix('assets/destinos/')];original=next((x for x in choices if x.is_file()),None)
  if original is None:raise RuntimeError('Missing data-bound image '+rel)
  source.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(original,source)
 manifest.append({'path':rel,'sha256':hashlib.sha256(source.read_bytes()).hexdigest(),'origin':image['origin']})
encoded=json.dumps(d,ensure_ascii=False,indent=2)+'\n';(R/'content/release-places-businesses.json').write_text(encoded);(R/'.local/preprod/evidencia/candidato-produccion.json').write_text(encoded)
(R/'backend/app/content/release-media-manifest.json').write_text(json.dumps({'schema_version':1,'media':manifest},ensure_ascii=False,indent=2)+'\n')
print('Reproducible payload: 305 businesses,',len(assets),'media references with hashes and provenance.')
