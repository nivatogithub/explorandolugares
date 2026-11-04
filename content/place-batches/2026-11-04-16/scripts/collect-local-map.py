#!/usr/bin/env python3
import json,time,urllib.request,urllib.parse,xml.etree.ElementTree as ET,hashlib,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
UA='ExplorandoLugaresEditorialResearch/1.0 (contacto@explorandolugares.com)'
CENTERS={'florencia':(1.61318,-75.61448),'popayan':(2.44215,-76.60619),'salento':(4.63724,-75.56982),'ipiales':(.82371,-77.63495),'la-macarena':(2.18397,-73.78775)}
def get(url):
 for attempt in range(2):
  try:
   with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':UA}),timeout=45) as r:return r.read()
  except Exception:
   if attempt:raise
   time.sleep(8)
def collect(folder, center=None, output='osm-discovery.json', delta=.015):
 d=json.loads((folder/'research.json').read_text());out=folder/output
 if out.exists():return
 if center is None and folder.name not in CENTERS and (folder/'locality-search.json').exists():
  cached=json.loads((folder/'locality-search.json').read_text());results=cached['results']
  if not any(r.get('category')=='place' and r.get('type') in ['town','city','village','hamlet'] and folder.name not in ['providencia','florencia'] for r in results):return
 name=d['name'];geo=None
 if center is not None:lat,lon=center
 elif folder.name in CENTERS:lat,lon=CENTERS[folder.name]
 else:
  q=name+', Colombia';url='https://nominatim.openstreetmap.org/search?'+urllib.parse.urlencode({'q':q,'format':'jsonv2','countrycodes':'co','limit':5})
  time.sleep(1.2);geo=json.loads(get(url));(folder/'locality-search.json').write_text(json.dumps({'query_url':url,'results':geo,'use':'Discovery bounds only, not an approved destination point'},ensure_ascii=False,indent=2)+'\n')
  places=[r for r in geo if r.get('category')=='place' and r.get('type') in ['city','town','village','hamlet','island']]
  if not places:raise ValueError('No locality point; do not use municipal centroid')
  lat,lon=float(places[0]['lat']),float(places[0]['lon'])
 url=f'https://api.openstreetmap.org/api/0.6/map?bbox={lon-delta},{lat-delta},{lon+delta},{lat+delta}'
 raw=get(url);xml=ET.fromstring(raw);nodes={n.attrib['id']:(float(n.attrib['lat']),float(n.attrib['lon'])) for n in xml.findall('node')};items=[]
 for e in xml:
  tags={t.attrib['k']:t.attrib['v'] for t in e.findall('tag')};name=tags.get('name')
  if not name:continue
  category=None
  if tags.get('tourism') in ['hotel','hostel','guest_house','motel','camp_site']:category='accommodation'
  elif tags.get('amenity') in ['restaurant','fast_food','food_court']:category='restaurants'
  elif tags.get('amenity') in ['cafe','ice_cream'] or tags.get('shop')=='bakery':category='cafes'
  elif tags.get('amenity') in ['bar','pub','nightclub']:category='nightlife'
  elif tags.get('shop')=='travel_agency':category='tour_operators'
  elif tags.get('shop') in ['craft','gift']:category='crafts'
  elif tags.get('tourism') in ['attraction','museum','viewpoint'] or tags.get('leisure')=='park':category='attraction_candidate'
  if not category:continue
  if e.tag=='node':point=nodes[e.attrib['id']];kind='mapped_site_node'
  elif e.tag=='way':
   points=[nodes[n.attrib['ref']] for n in e.findall('nd') if n.attrib['ref'] in nodes]
   if not points:continue
   point=((min(p[0] for p in points)+max(p[0] for p in points))/2,(min(p[1] for p in points)+max(p[1] for p in points))/2);kind='mapped_building_or_site_reference_not_entrance'
  else:continue
  items.append({'name':name,'category':category,'latitude':point[0],'longitude':point[1],'point_kind':kind,'source_url':f"https://www.openstreetmap.org/{e.tag}/{e.attrib['id']}",'source_updated_at':e.attrib.get('timestamp'),'source_checked_at':'2026-11-04','tags':tags,'claimed':False,'verified_business':False,'public':False,'status':'mapped_candidate_requires_identity_and_boundary_review','maps_url':None})
 out.write_text(json.dumps({'query_url':url,'source':'OpenStreetMap contributors','license':'ODbL 1.0','discovery_center':[lat,lon],'raw_sha256':hashlib.sha256(raw).hexdigest(),'boundary_check':'Bounding-box discovery is not proof of municipality membership','candidates':items},ensure_ascii=False,indent=2)+'\n');print(folder.name,len(items),flush=True)
if __name__=='__main__':
 if len(sys.argv)>1:
  collect(ROOT/'places'/sys.argv[1],(float(sys.argv[2]),float(sys.argv[3])),sys.argv[4],float(sys.argv[5]) if len(sys.argv)>5 else .015)
  raise SystemExit()
 for f in sorted((ROOT/'places').iterdir()):
  try:collect(f)
  except Exception as e:print(f.name,'ERROR',str(e),flush=True)
