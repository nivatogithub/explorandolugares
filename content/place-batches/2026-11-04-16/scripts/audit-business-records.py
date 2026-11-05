"""Non-destructive evidence audit; never equates a mapped feature with a verified business."""
from pathlib import Path
import json,math,unicodedata,re
ROOT=Path(__file__).resolve().parents[1]
def norm(s):return re.sub(r'[^a-z0-9]','',unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode().lower())
def dist(a,b):
 r=math.pi/180;x,y,u,v=map(float,[a['latitude'],a['longitude'],b['latitude'],b['longitude']]);h=math.sin((u-x)*r/2)**2+math.cos(x*r)*math.cos(u*r)*math.sin((v-y)*r/2)**2;return 6371*2*math.asin(min(1,math.sqrt(h)))
summary=[]
for path in sorted((ROOT/'places').glob('*/research.json')):
 d=json.loads(path.read_text());sources={};names={}
 for cache in path.parent.glob('osm-*.json'):
  for x in json.loads(cache.read_text()).get('candidates',[]):sources[x['source_url']]=x;names.setdefault(norm(x['name']),[]).append(x)
 seen={};rows=[]
 for i,b in enumerate(d['businesses']):
  issues=[];g=b.get('geolocation');source=(g or {}).get('source_url') or b.get('source_url');x=sources.get(source)
  if not x and len(names.get(norm(b['name']),[]))==1:x=names[norm(b['name'])][0]
  if x and norm(x['name'])!=norm(b['name']):issues.append('source_name_mismatch')
  if x and norm(x['name'])==norm(b['name']):
   if not g:
    b['geolocation']=g={k:x[k] for k in ['latitude','longitude','point_kind','source_url']};g['checked_at']='2026-11-04';g['evidence']='Exact normalized name in cached OSM feature; municipality and current activity require review'
   elif abs(g['latitude']-x['latitude'])>0.00001 or abs(g['longitude']-x['longitude'])>0.00001:issues.append('coordinate_differs_from_source')
   tags=x.get('tags',{})
   for field,keys in [('phone',['contact:phone','phone']),('website',['contact:website','website'])]:
    value=next((tags[k] for k in keys if tags.get(k)),None)
    if value and not b.get(field):b[field]=value;b.setdefault('field_evidence',{})[field]={'source_url':x['source_url'],'status':'mapped_not_independently_confirmed'}
  if not g:issues.append('coordinates_missing')
  elif not(-90<=g['latitude']<=90 and -180<=g['longitude']<=180):issues.append('coordinates_invalid')
  elif d.get('geolocation') and d['geolocation'].get('latitude') is not None:
   km=round(dist(g,d['geolocation']),2)
   if km>30:issues.append('distant_from_locality_requires_territorial_review')
  key=norm(b['name'])
  if key in seen:issues.append('possible_duplicate_name')
  seen[key]=i
  if b.get('source_type')=='municipal_public_directory' and path.parent.name=='inirida':issues.append('directory_page_did_not_expose_business_entries_on_live_check')
  if b.get('source_type') in ['secondary_directory','secondary_local_directory']:issues.append('secondary_directory_requires_independent_confirmation')
  review={'checked_at':'2026-11-04','coordinate_evidence':'cached_named_map_feature_matched' if x and norm(x['name'])==norm(b['name']) else 'needs_source_validation','issues':issues,'current_operation':'not_independently_confirmed','municipality_membership':'not_certified','publication_ready':False}
  existing=b.get('editorial_validation',{})
  review={**review,**existing}
  review['issues']=sorted(set(review.get('issues',[])+[issue for issue in issues if issue!='directory_page_did_not_expose_business_entries_on_live_check']))
  if b.get('geolocation'):review['issues']=[issue for issue in review['issues'] if issue!='coordinates_missing']
  b['editorial_validation']=review
  rows.append({'name':b['name'],'category':b['category'],'source_url':b.get('source_url'),**review})
 path.write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n');(path.parent/'business-validation.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
 summary.append({'place':d['name'],'records':len(rows),'mapped_evidence_matched':sum(r['coordinate_evidence']=='cached_named_map_feature_matched' for r in rows),'coordinates_missing':sum('coordinates_missing' in r['issues'] for r in rows),'flagged':sum(bool(r['issues']) for r in rows)})
(ROOT/'business-validation-summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2)+'\n');print(json.dumps(summary,ensure_ascii=False))
