#!/usr/bin/env python3
"""Collect public schema.org location evidence from already documented business sites."""
import json,re,urllib.request,urllib.parse,concurrent.futures,html
from pathlib import Path
ROOT=Path(__file__).resolve().parent;B=ROOT.parent
rows=json.loads((B/'business-ledger.json').read_text())
cache=ROOT/'official-evidence';cache.mkdir(exist_ok=True)
def walk(x):
 if isinstance(x,dict):
  if isinstance(x.get('geo'),dict):yield x
  for v in x.values():yield from walk(v)
 elif isinstance(x,list):
  for v in x:yield from walk(v)
def job(pair):
 i,r=pair;dest=cache/f'{i:03}.json'
 if dest.exists():return json.loads(dest.read_text())
 url=r.get('source_url','');result={'business_name':r['name'],'destination_slug':r['destination_slug'],'category_code':r['category_code'],'source_url':url,'checked_at':'2026-11-04','coordinates_candidates':[]}
 try:
  req=urllib.request.Request(url,headers={'User-Agent':'ExplorandoLugares-EditorialResearch/1.0 (+https://explorandolugares.com)'})
  with urllib.request.urlopen(req,timeout=18) as res:body=res.read(2500000).decode('utf-8','replace');result['resolved_url']=res.url;result['http_status']=res.status
  for raw in re.findall(r'<script[^>]*type=[\"\']application/ld\+json[\"\'][^>]*>(.*?)</script>',body,re.S|re.I):
   try:d=json.loads(html.unescape(raw))
   except Exception:continue
   for x in walk(d):
    try:lat=float(x['geo']['latitude']);lon=float(x['geo']['longitude'])
    except Exception:continue
    if not (-5<lat<14 and -82<lon<-66):continue
    item={'name':x.get('name'),'latitude':lat,'longitude':lon,'address':x.get('address'),'schema_type':x.get('@type'),'status':'candidate_requires_business_and_municipality_match'}
    if item not in result['coordinates_candidates']:result['coordinates_candidates'].append(item)
  result['status']='coordinates_found' if result['coordinates_candidates'] else 'no_structured_coordinates'
 except Exception as e:result['status']='source_unavailable';result['error']=str(e)[:220]
 dest.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');return result
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
 out=[]
 for r in pool.map(job,enumerate(rows)):
  
  for c in r['coordinates_candidates']:
   if c['schema_type'] in ['City','AdministrativeArea','Country']:
    c.update(status='rejected_municipal_center_not_business',rejection_reason='Coordenada de la localidad, no del establecimiento.')
  out.append(r);print(r['destination_slug'],r['business_name'],r['status'],flush=True)
(ROOT/'official-coordinates.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
print('Sources',len(out),'with candidate coordinates',sum(bool(r['coordinates_candidates']) for r in out),flush=True)
