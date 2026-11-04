#!/usr/bin/env python3
"""Collect reference candidates only. No publication, contact, or verified-business claims."""
import concurrent.futures,json,re,time,urllib.parse,urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
UA='ExplorandoLugaresEditorialResearch/1.0 (contacto@explorandolugares.com)'
DEPS={'91':'AMA','81':'ARA','18':'CAQ','19':'CAU','27':'CHO','94':'GUA','95':'GUV','50':'MET','52':'NAR','86':'PUT','63':'QUI','88':'SAP','97':'VAU','99':'VID'}
def get(url,data=None):
 req=urllib.request.Request(url,data=data,headers={'User-Agent':UA});
 with urllib.request.urlopen(req,timeout=35) as response:return json.load(response)
def collect(folder):
 d=json.loads((folder/'research.json').read_text());report={'slug':d['slug'],'errors':[]};stamp=time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime())
 try:
  search=d['name']+' Colombia';params={'action':'query','format':'json','generator':'search','gsrsearch':search,'gsrnamespace':6,'gsrlimit':4,'prop':'imageinfo','iiprop':'url|size|extmetadata','iiurlwidth':1000}
  raw=get('https://commons.wikimedia.org/w/api.php?'+urllib.parse.urlencode(params));images=[]
  for page in raw.get('query',{}).get('pages',{}).values():
   for image in page.get('imageinfo',[]):
    meta=image.get('extmetadata',{});images.append({'title':page['title'],'url':image['url'],'preview_url':image.get('thumburl'),'source_page':image.get('descriptionurl'),'width':image.get('width'),'height':image.get('height'),'license':meta.get('LicenseShortName',{}).get('value'),'license_url':meta.get('LicenseUrl',{}).get('value'),'author_html':meta.get('Artist',{}).get('value'),'description_html':meta.get('ImageDescription',{}).get('value'),'identity_review':'pending','approval':None})
  (folder/'photo-candidates.json').write_text(json.dumps({'retrieved_at':stamp,'source':'Wikimedia Commons','candidates':images},ensure_ascii=False,indent=2)+'\n');report['photo_candidates']=len(images)
 except Exception as e:report['errors'].append('photos: '+str(e))
 try:
  municipality=re.escape(d['municipality_name']);iso='CO-'+DEPS[d['department_code']]
  if d['slug']=='capurgana':
   area='node["place"]["name"="Capurganá"]->.v;';loc='around.v:8000'
  else:
   area=f'area["ISO3166-2"="{iso}"]->.dep;rel(area.dep)["boundary"="administrative"]["name"~"^{municipality}$",i];map_to_area->.a;';loc='area.a'
  query=f'[out:json][timeout:25];{area}(nwr({loc})["name"]["tourism"~"^(hotel|hostel|guest_house|motel|camp_site|attraction|museum|viewpoint)$"];nwr({loc})["name"]["amenity"~"^(restaurant|cafe|fast_food|bar|ice_cream)$"];nwr({loc})["name"]["shop"~"^(travel_agency|craft|gift)$"];);out center tags;'
  raw=get('https://overpass-api.de/api/interpreter',urllib.parse.urlencode({'data':query}).encode());items=[]
  for e in raw.get('elements',[]):
   tags=e.get('tags',{});point=e.get('center',e)
   items.append({'source_id':f"osm:{e['type']}:{e['id']}",'name':tags['name'],'latitude':point.get('lat'),'longitude':point.get('lon'),'coordinate_kind':'mapped_node' if e['type']=='node' else 'mapped_object_center_not_entrance','source_url':f"https://www.openstreetmap.org/{e['type']}/{e['id']}",'tags':tags,'municipality_assignment':'pending_boundary_check','status':'candidate','claimed':False,'verified_business':False,'commercial_plan':'free_candidate'})
  (folder/'mapped-candidates.json').write_text(json.dumps({'retrieved_at':stamp,'source':'OpenStreetMap contributors','license':'ODbL 1.0','query':query,'note':'Discovery only: mapped coordinates are not verified entrances or named Google Maps links. No operating-status or ownership assertion.','candidates':items},ensure_ascii=False,indent=2)+'\n');report['mapped_candidates']=len(items)
 except Exception as e:report['errors'].append('mapped: '+str(e))
 print(json.dumps(report,ensure_ascii=False),flush=True);return report
if __name__=='__main__':
 folders=sorted((ROOT/'places').iterdir())
 with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:reports=list(pool.map(collect,folders))
 (ROOT/'collection-report.json').write_text(json.dumps(reports,ensure_ascii=False,indent=2)+'\n')
