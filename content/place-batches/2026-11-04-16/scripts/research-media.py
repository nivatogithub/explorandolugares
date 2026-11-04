#!/usr/bin/env python3
"""Cache source photographs and exact-place search evidence; never auto-approve."""
import sys,json,time,urllib.request,urllib.parse,hashlib,concurrent.futures
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
UA='ExplorandoLugaresEditorialResearch/1.0 (editorial research)'
def request(url):
 time.sleep(6)
 with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':UA}),timeout=25) as r:return json.load(r)
def item(job):
 folder,key,label,query=job
 target=folder/'reference-searches'/f'{key}.json';target.parent.mkdir(exist_ok=True)
 if target.exists():return
 params={'action':'query','format':'json','generator':'search','gsrsearch':query+' filetype:bitmap','gsrnamespace':6,'gsrlimit':6,'prop':'imageinfo|coordinates','iiprop':'url|size|extmetadata','iiurlwidth':1280}
 url='https://commons.wikimedia.org/w/api.php?'+urllib.parse.urlencode(params)
 try:
  raw=request(url);images=[]
  for page in raw.get('query',{}).get('pages',{}).values():
   for im in page.get('imageinfo',[]):
    m=im.get('extmetadata',{});images.append({'title':page['title'],'source_url':im.get('descriptionurl'),'original_url':im['url'],'preview_url':im.get('thumburl'),'width':im['width'],'height':im['height'],'license':m.get('LicenseShortName',{}).get('value'),'license_url':m.get('LicenseUrl',{}).get('value'),'author_html':m.get('Artist',{}).get('value'),'description_html':m.get('ImageDescription',{}).get('value'),'captured_at':m.get('DateTimeOriginal',{}).get('value'),'coordinates':page.get('coordinates',[]),'approval':None})
  target.write_text(json.dumps({'label':label,'query':query,'retrieved_at':time.strftime('%Y-%m-%d'),'source_query_url':url,'candidates':images},ensure_ascii=False,indent=2)+'\n');print(folder.name,key,len(images),flush=True)
 except Exception as e:
  print(folder.name,key,'ERROR',str(e),flush=True)
  if getattr(e,'code',None)==429:raise SystemExit('Rate limited: stop collection; preserve cached results.')
if __name__=='__main__':
 if len(sys.argv)>3:
  item((ROOT/'places'/sys.argv[1],sys.argv[2],sys.argv[2],sys.argv[3]));raise SystemExit
 jobs=[]
 overrides={'florencia':'Florencia Caquetá','la-macarena':'Caño Cristales landscape','providencia':'Providencia island Colombia','leticia':'Leticia Amazonas park','bahia-solano':'Bahía Solano coast','nuqui':'Nuquí Guachalito','arauca':'Arauca cathedral','mitu':'Mitú Vaupés river','puerto-carreno':'Puerto Carreño Vichada','ipiales':'Ipiales plaza','salento':'Salento Quindío town'}
 for folder in sorted((ROOT/'places').iterdir()):
  d=json.loads((folder/'research.json').read_text());jobs.append((folder,'hero',d['name'],overrides.get(d['slug'],d['name']+' Colombia')))
  for i,a in enumerate(d['attractions']):
   q=a['name']
   if any(s in q.lower() for s in ['parque','muelle','malecón','centro','puentes']):q+=' '+d['name']
   jobs.append((folder,f'attraction-{i+1:02}',a['name'],q))
 with concurrent.futures.ThreadPoolExecutor(max_workers=1) as pool:list(pool.map(item,jobs))
