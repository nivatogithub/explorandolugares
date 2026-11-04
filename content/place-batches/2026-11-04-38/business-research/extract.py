#!/usr/bin/env python3
"""Reproducible OSM research extraction; never auto-publishes candidates."""
import json,sys,unicodedata,re,hashlib,math,collections
from pathlib import Path
import osmium
from shapely import wkb
from shapely.geometry import Point,mapping
ROOT=Path(__file__).resolve().parent
B=ROOT.parent
DATE='2026-11-04'
def norm(s):return re.sub(r'[^a-z0-9]+',' ',unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode().lower()).strip()
def dump(path,v):path.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
def category(t):
 a=t.get('amenity','');s=t.get('shop','');tour=t.get('tourism','')
 if any(k.startswith(('disused:','abandoned:','demolished:')) for k in t) or s in ['vacant','no'] or t.get('disused')=='yes':return None
 if tour in ['hotel','hostel','guest_house','motel','apartment','chalet','camp_site']:return 'hospedaje'
 if a in ['restaurant','cafe','fast_food','food_court','ice_cream'] or s in ['bakery','pastry']:return 'alimentacion'
 if a in ['bar','pub','nightclub']:return 'vida-nocturna'
 if s in ['alcohol','wine','beverages']:return 'licores'
 if a in ['pharmacy','clinic','dentist','doctors','veterinary'] or s in ['chemist','optician'] or t.get('healthcare') in ['clinic','dentist','doctor','physiotherapist']:return 'salud'
 if a in ['car_rental','taxi'] or s in ['car_rental','motorcycle_rental','bicycle_rental']:return 'transporte'
 if s=='travel_agency':return 'experiencias'
 if a in ['cinema','theatre','arts_centre'] or s in ['art','music','musical_instrument']:return 'cultura'
 if a in ['bank','bureau_de_change','post_office','veterinary'] or s in ['laundry','dry_cleaning','hairdresser','beauty','car_repair','copyshop'] or t.get('craft'):return 'servicios'
 if s:return 'comercio'
 return None
manifests=[json.loads(p.read_text()) for p in sorted((B/'places').glob('*/manifest.json'))]
geo=json.loads((B/'geolocation.json').read_text())
targets={m['destination']['slug']:{'name':m['destination']['translations']['es']['display_name'].split(',')[0],'department':m['destination']['department']} for m in manifests}
alias={'manaure':['manaure','manaure balcon del cesar'],'pueblo-viejo':['puebloviejo','pueblo viejo'],'santa-rosa-de-lima':['santa rosa','santa rosa de lima'],'cucuta':['cucuta','san jose de cucuta']}
for slug,t in targets.items():
 t['names']=alias.get(slug,[norm(t['name'])]);pts=[g for g in geo if g['municipality']==slug];t['anchor']=(sum(g['longitude'] for g in pts)/len(pts),sum(g['latitude'] for g in pts)/len(pts))
factory=osmium.geom.WKBFactory();boundaries=[];records=[];errors=[]
print('Reading extract and assembling municipal boundaries',flush=True)
for o in osmium.FileProcessor(sys.argv[1]).with_locations().with_areas().with_filter(osmium.filter.KeyFilter('name')):
 t=dict(o.tags)
 if o.is_area() and t.get('boundary')=='administrative' and t.get('admin_level')=='6':
  names=[s for s,v in targets.items() if norm(t['name']) in v['names']]
  if not names:continue
  try:p=wkb.loads(factory.create_multipolygon(o),hex=True)
  except Exception as e:errors.append({'boundary':o.orig_id(),'error':str(e)});continue
  for slug in names:
   if p.distance(Point(targets[slug]['anchor']))<0.15:boundaries.append((slug,p,{'osm_relation_id':o.orig_id(),'tags':t}))
  continue
 cat=category(t)
 if not cat:continue
 try:
  if o.is_node():pt=Point(o.lon,o.lat);typ='node';oid=o.id;precision='mapped_point'
  elif o.is_area():pt=wkb.loads(factory.create_multipolygon(o),hex=True).representative_point();typ='way' if o.from_way() else 'relation';oid=o.orig_id();precision='point_inside_mapped_footprint_not_entrance'
  else:continue
 except Exception:continue
 records.append({'name':t['name'],'category_code':cat,'latitude':pt.y,'longitude':pt.x,'coordinate_type':precision,'source_url':f'https://www.openstreetmap.org/{typ}/{oid}','osm_type':typ,'osm_id':oid,'source_tags':t,'source_object_updated_at':str(o.timestamp),'checked_at':DATE,'publication_ready':False,'verification_status':'single_cartographic_source_requires_crosscheck'})
print('Raw named businesses',len(records),'boundaries',len(boundaries),flush=True)
by=collections.defaultdict(list)
for slug,p,meta in boundaries:by[slug].append((p,meta))
selected={s:rs[0] for s,rs in by.items() if len(rs)==1}
dump(ROOT/'boundaries.geojson',{'type':'FeatureCollection','features':[{'type':'Feature','geometry':mapping(p),'properties':dict(meta,destination_slug=s)} for s,(p,meta) in selected.items()]})
rows=[];duplicates=[];seen={}
for r in records:
 pt=Point(r['longitude'],r['latitude']);matches=[s for s,(p,m) in selected.items() if p.covers(pt)]
 if len(matches)!=1:continue
 r['destination_slug']=matches[0];key=(matches[0],norm(r['name']))
 dupe=next((v for v in seen.get(key,[]) if math.hypot((v['longitude']-r['longitude'])*math.cos(math.radians(r['latitude'])),v['latitude']-r['latitude'])*111320<35),None)
 if dupe:duplicates.append({'retained':dupe['source_url'],'duplicate':r['source_url'],'name':r['name']});continue
 seen.setdefault(key,[]).append(r);rows.append(r)
rows.sort(key=lambda r:(r['destination_slug'],r['category_code'],norm(r['name']),r['osm_id']))
counts={s:dict(collections.Counter(r['category_code'] for r in rows if r['destination_slug']==s)) for s in targets}
out={'checked_at':DATE,'status':'research_not_publication','source_extract':'https://download.geofabrik.de/south-america/colombia-261103.osm.pbf','extract_sha256':hashlib.file_digest(open(sys.argv[1],'rb'),'sha256').hexdigest(),'attribution':'© OpenStreetMap contributors','license':'ODbL 1.0','license_url':'https://www.openstreetmap.org/copyright','candidate_count':len(rows),'municipalities':38,'boundary_matched':len(selected),'missing_boundaries':[s for s in targets if s not in selected],'counts_by_place_category':counts,'businesses':rows,'potential_duplicates':duplicates,'errors':errors}
dump(ROOT/'candidates.json',out)
dump(ROOT/'coverage.json',{'target_min':1300,'target_max':1500,'located_candidates':len(rows),'publication_ready':0,'municipalities':[dict(destination_slug=s,name=t['name'],department=t['department'],boundary_confirmed=s in selected,located_candidates=sum(counts[s].values()),categories=counts[s]) for s,t in targets.items()]})
print(json.dumps({k:out[k] for k in ['candidate_count','boundary_matched','missing_boundaries']},ensure_ascii=False),flush=True)
