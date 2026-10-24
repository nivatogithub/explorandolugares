#!/usr/bin/env python3
"""Freeze owner-reviewed public content only. No DB, private config or protected data."""
import argparse,copy,hashlib,json,shutil
from pathlib import Path
R=Path(__file__).resolve().parents[1];a=argparse.ArgumentParser();a.add_argument('--editorial',type=Path,required=True);a.add_argument('--snapshots',type=Path,required=True);a.add_argument('--public-source',type=Path,required=True);v=a.parse_args();pack=json.loads(v.editorial.read_text());snaps=json.loads(v.snapshots.read_text());scope=['barranquilla','manizales','pereira','san-bernardo-del-viento','villavieja'];assert pack['scope']==scope and list(snaps)==scope;assert len(pack['tables']['businesses'])==162
# Nothing from accounts, subscriptions, measurements, credentials or operational logs.
allowed={'businesses','business_translations','business_categories','business_contacts','business_sources','business_media','media_assets','media_asset_translations'};assert not(set(pack['tables'])-allowed)
for d in pack['editorial'].values():d.pop('media_options',None)
for row in pack['tables']['media_asset_translations']:
 if not row.get('caption'):row['caption']={'es':'Ilustración editorial: ','en':'Editorial illustration: ','pt-BR':'Ilustração editorial: '}[row['locale']]+row['alt_text'].rstrip('.')+'.'

paths={};manifest=[]
for m in pack['tables']['media_assets']:
 old=m['asset_path'];src=v.public_source/old;h=hashlib.sha256(src.read_bytes()).hexdigest();new=f'assets/media/{h[:2]}/{h}.webp';assert src.suffix=='.webp';paths[old]=new;dst=R/'backend/public'/new;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(src,dst);manifest.append({'path':new,'sha256':h,'origin':m['origin']})
 m['asset_path']=new
 prov=json.loads(m.get('provenance_json')or'{}');prov.update(publication_authorized=True,approval_scope='Owner PUBLICAR five current DEV1 destinations 2026-10-24',sha256=h,source_active_path=old);m['provenance_json']=json.dumps(prov,ensure_ascii=False)
for d in pack['editorial'].values():
 for media in d['destination_media'].values():media['asset_path']=paths[media['asset_path']]
for slug,s in snaps.items():
 # Retain only public columns used by the established migration, not private metadata.
 safe_biz={'id','public_id','destination_id','category_code','slug','information_status','publication_status','display_order','record_origin','operation_status'}
 s['tables']['businesses']=[{k:v for k,v in b.items()if k in safe_biz}for b in s['tables']['businesses']]
 for d in s['tables']['destinations']:d.pop('internal_research_json',None)
 for m in s['tables']['media_assets']:
  match=next(x for x in pack['tables']['media_assets']if x['id']==m['id']);m.update(match)
 for row in s['tables']['media_asset_translations']:
  if not row.get('caption'):row['caption']={'es':'Ilustración editorial: ','en':'Editorial illustration: ','pt-BR':'Ilustração editorial: '}[row['locale']]+row['alt_text'].rstrip('.')+'.'
 (R/'content/destination-migration'/f'{slug}.json').write_text(json.dumps(s,ensure_ascii=False,indent=2)+'\n')
# Keep a private scoped release dataset; identifiers here are source references, not remote IDs.
folder=R/'content/releases';folder.mkdir(exist_ok=True);(folder/'cinco-destinos-20261024.json').write_text(json.dumps(pack,ensure_ascii=False,indent=2)+'\n')
# Canonical public catalog: append five once, preserving the existing fifteen untouched.
p=R/'content/release-places-businesses.json';release=json.loads(p.read_text());assert not(set(scope)&set(release['editorial'])),'Already integrated: use original main catalog as base'
# Allocate unique snapshot reference IDs so the combined fixture never collides with prior releases.
mapids={};nextmid=max(int(m['id'])for m in release['tables']['media_assets'])+1
for m in pack['tables']['media_assets']:mapids[m['id']]=nextmid;nextmid+=1
new=copy.deepcopy(pack)
for m in new['tables']['media_assets']:m['id']=mapids[m['id']]
for m in new['tables']['media_asset_translations']:m['media_asset_id']=mapids[m['media_asset_id']]
nextdid=max(int(d['id'])for d in release['editorial'].values())+1;dmap={};nextpoi=max(int(p['id'])for d in release['editorial'].values()for p in d['places'])+1
for slug,d in new['editorial'].items():
 old=d['id'];dmap[old]=nextdid;d['id']=nextdid;nextdid+=1
 for tr in d['translations'].values():tr['destination_id']=d['id']
 for m in d['destination_media'].values():m['media_asset_id']=mapids[m['media_asset_id']]
 for poi in d['places']:
  poi['id']=nextpoi;nextpoi+=1;poi['media_asset_id']=mapids.get(poi['media_asset_id'],0)
  for tr in poi['translations'].values():tr['id']=poi['id'];tr['media_asset_id']=poi['media_asset_id']
 release['editorial'][slug]=d
bmap={};nextbid=max(int(b['id'])for b in release['tables']['businesses'])+1
for b in new['tables']['businesses']:bmap[b['id']]=nextbid;b['id']=nextbid;nextbid+=1;b['destination_id']=dmap[b['destination_id']]
for table,rows in new['tables'].items():
 if table.startswith('business_'):
  for row in rows:
   row['business_id']=bmap[row['business_id']]
   if 'media_asset_id'in row:row['media_asset_id']=mapids[row['media_asset_id']]
 release['tables'].setdefault(table,[]).extend(rows)
release['scope']+=scope;release['public_places']+=scope;release.setdefault('destination_ids',{}).update({s:d['id']for s,d in new['editorial'].items()}) if isinstance(release.get('destination_ids'),dict) else None
p.write_text(json.dumps(release,ensure_ascii=False,indent=2)+'\n')
p=R/'backend/app/content/release-media-manifest.json';j=json.loads(p.read_text());existing={m['path']for m in j['media']};j['media']+=[m for m in manifest if m['path']not in existing];p.write_text(json.dumps(j,ensure_ascii=False,indent=2)+'\n')
p=R/'content/lugares-publicados.json';j=json.loads(p.read_text());j['slugs']+=scope;p.write_text(json.dumps(j,ensure_ascii=False,indent=2)+'\n')
# Seed catalog is for empty local installations only; production uses scoped snapshots.
p=R/'content/destinos-dinamicos.json';seed=json.loads(p.read_text());byid={m['id']:m for m in pack['tables']['media_assets']};mt={(m['media_asset_id'],m['locale']):m for m in pack['tables']['media_asset_translations']}
def image(mid,l):
 m=byid[mid];t=mt[mid,l];return {'path':m['asset_path'],'alt':t['alt_text'],'caption':t.get('caption',''),'width':m['width'],'height':m['height']}
for slug,d in pack['editorial'].items():
 n={k:d[k]for k in ['slug','destination_type','country_code','department','subregion','publication_status','display_order']};n['translations']={}
 for l,t in d['translations'].items():
  n['translations'][l]={'name':t['display_name'],'title':t['display_name']+' | Explorando Lugares','meta_description':t['meta_description'],'eyebrow':t['eyebrow'],'headline':t['headline'],'intro':t['intro'],'history':d['history'][l],'reasons':d['reasons'][l],'hero_media':image(d['destination_media']['hero']['media_asset_id'],l),'history_media':None,'places':[dict(slug=poi['slug'],type=poi['type'],publication_status='published',**{k:poi['translations'][l].get(k,'')for k in ['name','description','map_url','external_url','external_label']},media=image(poi['media_asset_id'],l)if poi['media_asset_id']else None)for poi in d['places']],'sources':[dict(url=s['source_url'],label=s['source_label'],section_key=s.get('section_key','places'))for s in d['sources'][l]]}
 seed['destinations'].append(n)
for m in pack['tables']['media_assets']:seed['media'].append({k:m[k]for k in ['origin','creator','source_url','license_name','license_url','width','height']}|{'path':m['asset_path'],'translations':{l:{'alt':mt[m['id'],l]['alt_text'],'caption':mt[m['id'],l].get('caption','')}for l in ['es','en','pt-BR']}})
placements={}
for d in seed['destinations']:
 if d['slug']not in scope:continue
 tr=d['translations']['es'];placements.setdefault(tr['hero_media']['path'],[]).append({'destination_slug':d['slug'],'role':'hero'})
 for i,poi in enumerate(tr['places'],1):
  if poi['media']:placements.setdefault(poi['media']['path'],[]).append({'destination_slug':d['slug'],'role':f'poi:{i}'})
for m in seed['media']:
 if m['path']in placements:m['placements']=placements[m['path']]
p.write_text(json.dumps(seed,ensure_ascii=False,indent=2)+'\n');print(json.dumps({'destinations':5,'businesses':162,'media':len(manifest),'public_total':len(release['public_places'])}))
