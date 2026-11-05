import json,pathlib,re,urllib.request,xml.etree.ElementTree as E,datetime,concurrent.futures
root=pathlib.Path(__file__).resolve().parents[1]
ids={'node':set(),'way':set()}
for f in (root/'places').glob('*/research.json'):
 for b in json.loads(f.read_text())['businesses']:
  g=b.get('geolocation') or {};m=re.search(r'openstreetmap.org/(node|way)/(\d+)',g.get('source_url',''))
  if m:ids[m[1]].add(m[2])
jobs=[]
for kind,values in ids.items():
 values=sorted(values)
 for i in range(0,len(values),50):jobs.append((kind,values[i:i+50]))
def fetch(job):
 kind,values=job;url=f'https://api.openstreetmap.org/api/0.6/{kind}s?{kind}s='+','.join(values)
 try:
  raw=urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'ExplorandoLugares/1.0 business data validation'}),timeout=30).read();tree=E.fromstring(raw)
  return {'url':url,'elements':[{'type':e.tag,**e.attrib,'tags':{t.attrib['k']:t.attrib['v'] for t in e.findall('tag')}} for e in tree if e.tag in ('node','way')]}
 except Exception as e:return {'url':url,'error':str(e)}
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:results=list(pool.map(fetch,jobs))
(root/'business-live-map-evidence.json').write_text(json.dumps({'checked_at':'2026-11-04','results':results},ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'requests':len(results),'elements':sum(len(r.get('elements',[])) for r in results),'errors':[r['error'] for r in results if 'error' in r]}))
