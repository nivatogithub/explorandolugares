#!/usr/bin/env python3
import json,sys,time,urllib.request,urllib.parse,hashlib
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
slug,key,title=sys.argv[1:4];p=ROOT/'places'/slug/'references';p.mkdir(exist_ok=True)
if (p/(key+'.json')).exists() and (p/(key+'.jpg')).exists():raise SystemExit('Already cached')
url='https://commons.wikimedia.org/w/api.php?'+urllib.parse.urlencode({'action':'query','format':'json','titles':title,'prop':'imageinfo|coordinates','iiprop':'url|size|extmetadata','iiurlwidth':1280})
def get(u):
 with urllib.request.urlopen(urllib.request.Request(u,headers={'User-Agent':'ExplorandoLugaresEditorialResearch/1.0'}),timeout=30) as r:return r.read()
time.sleep(6);data=json.loads(get(url));page=next(iter(data['query']['pages'].values()));im=page['imageinfo'][0];m=im['extmetadata'];lic=m.get('LicenseShortName',{}).get('value','')
if not any(x in lic for x in ['CC BY','CC0','Public domain']):raise SystemExit('License needs review: '+lic)
r={'title':title,'source_url':im['descriptionurl'],'original_url':im['url'],'preview_url':im.get('thumburl'),'width':im['width'],'height':im['height'],'license':lic,'license_url':m.get('LicenseUrl',{}).get('value'),'author_html':m.get('Artist',{}).get('value'),'description_html':m.get('ImageDescription',{}).get('value'),'captured_at':m.get('DateTimeOriginal',{}).get('value'),'coordinates':page.get('coordinates',[]),'metadata':m,'approval':None,'checked_at':'2026-11-04'}
b=get(im.get('thumburl') or im['url']);(p/(key+'.jpg')).write_bytes(b);r['sha256']=hashlib.sha256(b).hexdigest();(p/(key+'.json')).write_text(json.dumps(r,ensure_ascii=False,indent=2)+'\n');print(p/(key+'.jpg'))
