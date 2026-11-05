#!/usr/bin/env python3
import json,hashlib,struct
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def point(x):return isinstance(x,dict) and isinstance(x.get('latitude'),(int,float)) and isinstance(x.get('longitude'),(int,float)) and -90<=x['latitude']<=90 and -180<=x['longitude']<=180
rows=[];errors=[]
for p in sorted(ROOT.glob('places/*/research.json')):
 d=json.loads(p.read_text());assets=[('hero','Portada')]+[(a.get('image_key',f'attraction-{i+1:02}'),a['name']) for i,a in enumerate(d['attractions'])];missing=[];checks=0
 used={key for key,name in assets}
 for e in d.get('experiences',[]):
  if e.get('image_key') and e['image_key'] not in used:
   assets.append((e['image_key'],'Experiencia · '+e['name']));used.add(e['image_key'])
 for key,name in assets:
  m=p.parent/'images'/f'{key}.manifest.json'
  if not m.exists():missing.append(name);continue
  manifest=json.loads(m.read_text())
  for o in manifest.get('outputs',[]):
   path=p.parent/o['file']
   if not path.exists():errors.append(str(path)+' absent');continue
   if hashlib.sha256(path.read_bytes()).hexdigest()!=o['sha256']:errors.append(str(path)+' hash mismatch')
   if path.read_bytes()[:4]!=b'RIFF' or path.read_bytes()[8:12]!=b'WEBP':errors.append(str(path)+' not WebP')
   checks+=1
  if not (p.parent/'references'/f'{key}.jpg').exists():errors.append(d['slug']+'/'+key+' reference missing')
 row={'slug':d['slug'],'name':d['name'],'images_expected':len(assets),'images_ready':len(assets)-len(missing),'missing_images':missing,'webp_hashes_checked':checks,'destination_coordinate':point(d.get('geolocation')),'attractions':len(d['attractions']),'attractions_with_coordinates':sum(point(a.get('geolocation')) for a in d['attractions']),'attraction_coordinate_pending':[a['name'] for a in d['attractions'] if not point(a.get('geolocation'))],'experiences':len(d['experiences']),'experiences_with_reference_coordinate':sum(point(e.get('geolocation')) for e in d['experiences']),'businesses':len(d['businesses']),'businesses_with_coordinates':sum(point(b.get('geolocation')) for b in d['businesses']),'business_target':d['requirements'].get('business_target',30),'approval':'pending_human_review'}
 if d.get('public') is not False:errors.append(d['slug']+' public must be false')
 rows.append(row)
report={'batch':'2026-11-04-16','status':'private_review_prepared_not_release_ready','places':rows,'totals':{k:sum(r[k] for r in rows) for k in ['images_expected','images_ready','webp_hashes_checked','attractions','attractions_with_coordinates','experiences','experiences_with_reference_coordinate','businesses','businesses_with_coordinates']},'errors':errors,'public':False,'deployment_performed':False,'checks':['WebP header and output SHA256','References exist','All destinations remain unpublished'],'limitations':['Visual browser rendering not checked: file URL blocked by browser policy.','Public named Maps links and complete publication translations remain release gates.','Business candidates are not verified owners, allies or currently operating establishments.']}
(ROOT/'validation.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');print(json.dumps(report['totals']));print('errors',errors)
if errors:raise SystemExit(1)
