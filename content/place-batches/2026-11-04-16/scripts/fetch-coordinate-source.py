#!/usr/bin/env python3
import sys,urllib.request,json,re
from pathlib import Path
slug,key,url=sys.argv[1:4];p=Path(__file__).resolve().parents[1]/'places'/slug/'coordinate-sources';p.mkdir(exist_ok=True)
with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0'}),timeout=40) as r:raw=r.read()
(p/(key+'.html')).write_bytes(raw);s=raw.decode('utf-8','replace');matches=re.findall(r'.{0,100}(?:latitude|longitude|latLng|coordinates|lat:|lon:|lng:).{0,150}',s,re.I)
(p/(key+'.json')).write_text(json.dumps({'source_url':url,'checked_at':'2026-11-04','coordinate_candidates_need_identity_check':matches},ensure_ascii=False,indent=2));print('\n'.join(matches)[:12000])
