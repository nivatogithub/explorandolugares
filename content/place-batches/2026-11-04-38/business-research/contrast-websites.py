#!/usr/bin/env python3
"""Cross-check selected cartographic POIs against their recorded public website."""
import json,re,urllib.request,urllib.parse,hashlib,concurrent.futures,html,math,unicodedata
from pathlib import Path
P=Path(__file__).resolve().parent;cache=P/'website-evidence';cache.mkdir(exist_ok=True)
rows=json.loads((P/'selection-1500.json').read_text())['businesses']
def walk(v):
 if isinstance(v,dict):
  if isinstance(v.get('geo'),dict):yield v
  for a in v.values():yield from walk(a)
 elif isinstance(v,list):
  for a in v:yield from walk(a)
def normal(s):return re.sub(r'[^a-z0-9]','',unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode().lower())
def fetch(url):
 f=cache/(hashlib.sha256(url.encode()).hexdigest()+'.json')
 if f.exists():return json.loads(f.read_text())
 d={'url':url,'checked_at':'2026-11-04','locations':[]}
 try:
  req=urllib.request.Request(url,headers={'User-Agent':'ExplorandoLugares-EditorialResearch/1.0 (+https://explorandolugares.com)'})
  with urllib.request.urlopen(req,timeout=12) as res:s=res.read(2000000).decode('utf8','replace');d.update(http_status=res.status,resolved_url=res.url)
  title=re.search(r'<title[^>]*>(.*?)</title>',s,re.I|re.S);d['page_title']=html.unescape(re.sub('<.*?>','',title.group(1))).strip() if title else ''
  for raw in re.findall(r'<script[^>]*type=[\"\']application/ld\+json[\"\'][^>]*>(.*?)</script>',s,re.I|re.S):
   try:j=json.loads(html.unescape(raw))
   except Exception:continue
   for v in walk(j):
    if v.get('@type') in ['City','Country','AdministrativeArea']:continue
    try:lat=float(v['geo']['latitude']);lon=float(v['geo']['longitude'])
    except Exception:continue
    d['locations'].append({'name':v.get('name',''),'latitude':lat,'longitude':lon,'address':v.get('address')})
  d['status']='website_read'
 except Exception as e:d.update(status='unavailable',error=str(e)[:200])
 f.write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n');return d
jobs={}
for r in rows:
 url=r['source_tags'].get('website') or r['source_tags'].get('contact:website')
 if not url:continue
 if not url.startswith(('https://','http://')):url='https://'+url
 parsed=urllib.parse.urlparse(url)
 if not parsed.hostname or parsed.hostname in ['localhost','127.0.0.1'] or parsed.username:continue
 jobs.setdefault(url,[]).append(r)
print('Unique website sources:',len(jobs),flush=True)
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:results=dict(zip(jobs,pool.map(fetch,jobs)))
checks=[]
for url,rs in jobs.items():
 d=results[url]
 for r in rs:
  matches=[]
  for loc in d['locations']:
   distance=111320*math.hypot((loc['longitude']-r['longitude'])*math.cos(math.radians(r['latitude'])),loc['latitude']-r['latitude'])
   n=normal(r['name']);other=normal(loc['name']);name_match=bool(n and other and (n in other or other in n))
   if distance<=150 and name_match:matches.append(dict(loc,distance_m=round(distance)))
  checks.append({'source_url':r['source_url'],'destination_slug':r['destination_slug'],'name':r['name'],'website':url,'website_status':d['status'],'status':'website_name_and_coordinate_correspondence' if matches else 'website_not_location_confirmation','matched_locations':matches})
(P/'website-contrasts.json').write_text(json.dumps(checks,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'websites_consulted':len(jobs),'businesses_checked':len(checks),'coordinates_correspond':sum(bool(x['matched_locations']) for x in checks)}),flush=True)
