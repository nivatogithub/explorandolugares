#!/usr/bin/env python3
"""Read-only static resolver. Writes reports only under --out. Does not mutate snapshots/DB."""
import argparse,json,pathlib,hashlib,re
ap=argparse.ArgumentParser();ap.add_argument('--public',type=pathlib.Path,required=True);ap.add_argument('--snapshots',type=pathlib.Path,required=True);ap.add_argument('--out',type=pathlib.Path,required=True);a=ap.parse_args();a.out.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text())
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest() if p.is_file() else None
visual=read(a.public/'assets/destinos-semirrealistas.json');dec=json.JSONDecoder();mdjs=(a.public/'assets/medellin-compacta.js').read_text();md=dec.raw_decode(mdjs.split('const data=',1)[1])[0]
bg=read(a.public/'assets/bogota-semirrealista/contenido.json');sm=read(a.public/'assets/santa-marta-semirrealista/contenido.json');reports={}
for slug in ['jerico','cartagena','bogota','medellin','santa-marta']:
 s=read(a.snapshots/slug/'snapshot-public-content.json');t=s['tables'];assets={x['id']:x for x in t['media_assets']};links={}
 for link in sorted(t['point_of_interest_media'],key=lambda x:x.get('display_order',0)):links.setdefault(link['point_of_interest_id'],assets[link['media_asset_id']])
 def info(path):
  local=a.public/path
  exported=next((v for v in s['media_files'].values() if v['source_asset_path']==path),None)
  fallback=a.snapshots/slug/exported['export_file'] if exported else None
  file=local if local.is_file() else fallback
  return {'path':path,'sha256':digest(file) if file else None,'exists':bool(file and file.is_file()),'bytes_location':str(file) if file else None,'resolved_from_export':not local.is_file() and bool(exported)}
 rows=[]
 def add(role,key,db,selected,chain):
  old=info(db) if db else None;new=info(selected) if selected else None;rows.append({'role':role,'slug':key,'db':old,'effective_static':new,'differs':bool(old and new and old['sha256']!=new['sha256']),'override_chain':chain,'included_in_snapshot_media':bool(new and any(x['sha256']==new['sha256'] for x in s['media_files'].values()))})
 heroes=[x for x in t['destination_media'] if x['role']=='hero'];h=assets[heroes[0]['media_asset_id']]['asset_path'] if heroes else None;sel=visual.get(slug,{}).get('hero',{}).get('path',h);chain=['DB']+(['PHP destinos-semirrealistas.json'] if slug in visual else [])
 if slug=='bogota':sel='assets/bogota-semirrealista/portada-v4-1200.webp';chain+=['JS bogota-compacta hero']
 if slug=='medellin':sel='assets/medellin-semirrealista/web/portada-v3-1200.webp';chain+=['JS medellin-compacta hero']
 if slug=='santa-marta':sel='assets/santa-marta-semirrealista/hero-v3-1200.webp';chain+=['JS santa-marta-compacta replaces figure']
 add('hero','hero',h,sel,chain)
 pub=[x for x in t['points_of_interest'] if x['publication_status']=='published'];dbslugs={x['slug'] for x in pub}
 for p in pub:
  key=p['slug'];db=links.get(p['id'],{}).get('asset_path');sel=visual.get(slug,{}).get('places',{}).get(key,{}).get('path',db);chain=['DB']+(['PHP visual map'] if key in visual.get(slug,{}).get('places',{}) else [])
  if slug=='medellin' and any(x['slug']==key for x in md['places']+md['experiences']):sel='assets/medellin-semirrealista/web/'+key+'-800.webp';chain+=['JS medellin-compacta']
  if slug=='bogota':
   if any(x['slug']==key for x in bg['places']):sel='assets/bogota-semirrealista/'+('bogota-jardin-aprobado.webp' if key=='jardin' else 'bogota-'+key+'-800.webp');chain+=['JS bogota-compacta place']
   if any(x['id']==key and x.get('review_ready') for x in bg['experiences']):sel='assets/bogota-semirrealista/'+key+'-800.webp';chain+=['JS bogota-compacta experience']
  if slug=='santa-marta':
   sr=next((x for x in sm['places']+sm['experiences'] if x['id']==key),None)
   if sr:sel='assets/santa-marta-semirrealista/'+sr['asset_id']+'-800.webp';chain+=['JS SM rebuilds whole sections']
   else:chain+=['WARNING JS SM removes DB card not listed in contenido.json'];sel=None
  add(p['poi_type'],key,db,sel,chain)
 if slug=='santa-marta':
  for row in sm['places']+sm['experiences']:
   if row['id'] not in dbslugs:add('JS-only',row['id'],None,'assets/santa-marta-semirrealista/'+row['asset_id']+'-800.webp',['JS creates card absent from published DB'])
 reports[slug]={'selection':rows,'history_image_hidden_by_php':slug in visual,'JS_overrides_text':slug!='jerico','limitations':'Static analysis, not live DOM. Main image path; srcset480/640 also needs preserving until renderer is refactored.','counts':{'rows':len(rows),'different':sum(r['differs'] for r in rows),'selected_not_exported':sum(bool(r['effective_static']) and not r['included_in_snapshot_media'] for r in rows)}}
(a.out/'resolucion-estatica.json').write_text(json.dumps(reports,ensure_ascii=False,indent=2)+'\n')
plan={'schema':'selected-media-materialization-plan-v1','apply':False,'notes':['Use selected paths only after browser verification. No deletion authorized by this report.','Create/reuse media by SHA256; repoint hero/POI links while preserving original metadata and originals in archive.','Remove hidden history links only from public candidate, preserve original snapshot.','Update destination texts and POI descriptions from JS, do not merely swap images.','Do not retain obsolete PHP/JS media overrides after materialization; otherwise DB is overridden again.'], 'operations':[{'destination':s,'role':r['role'],'slug':r['slug'],'selected':r['effective_static'],'previous':r['db'],'needs_change':r['differs']} for s,d in reports.items() for r in d['selection']]}
(a.out/'plan-materializacion-no-aplicado.json').write_text(json.dumps(plan,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({s:d['counts'] for s,d in reports.items()},indent=2))
