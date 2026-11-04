#!/usr/bin/env python3
"""Download a specifically identified reference for private editorial comparison.
No inferred license or publication permission. Usage follows explicit user instruction
removing permission-confirmation as a blocker to draft generation; rights remain recorded.
"""
import json,sys,urllib.request,hashlib
from pathlib import Path
slug,key,source,image,title=sys.argv[1:6]
p=Path(__file__).resolve().parents[1]/'places'/slug/'references';p.mkdir(exist_ok=True)
with urllib.request.urlopen(urllib.request.Request(image,headers={'User-Agent':'Mozilla/5.0'}),timeout=40) as r:b=r.read()
(p/(key+'.jpg')).write_bytes(b)
(p/(key+'.json')).write_text(json.dumps({'title':title,'source_url':source,'original_url':image,'license':'No confirmada; referencia de revisión privada','license_url':None,'author_html':'Consultar fuente original','checked_at':'2026-11-04','sha256':hashlib.sha256(b).hexdigest(),'approval':None,'publication_rights_status':'unconfirmed','user_scope':'Explicit user override of confirmation prerequisite for draft generation; does not establish photographer permission'},ensure_ascii=False,indent=2)+'\n')
print(p/(key+'.jpg'))
