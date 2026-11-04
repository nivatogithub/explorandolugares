#!/usr/bin/env python3
import json,subprocess,urllib.parse,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
for f in ROOT.glob('places/*/references/*.json'):
 if f.stem not in ['hero','attraction-01','attraction-02','attraction-03','attraction-04']:continue
 d=json.loads(f.read_text()); jpg=f.with_suffix('.jpg')
 if jpg.exists() and not d.get('download_requested') and d.get('status')!='identified_reference_pending_download':continue
 if jpg.exists():jpg.rename(jpg.with_name(jpg.stem+'-previous.jpg'))
 source=d.get('source_url') or d.get('source_page','')
 if 'commons.wikimedia.org/wiki/File:' in source:
  title=urllib.parse.unquote(source.split('/wiki/')[1]);args=['fetch-commons-file.py',f.parent.parent.name,f.stem,title]
 else:
  url=d.get('original_url') or d.get('image_url') or d.get('download_url')
  if not url:continue
  args=['fetch-reviewed-reference.py',f.parent.parent.name,f.stem,source,url,d.get('title',f.stem)]
 r=subprocess.run([sys.executable,str(ROOT/'scripts'/args[0]),*args[1:]],capture_output=True,text=True)
 print(f.parent.parent.name,f.stem,r.returncode,(r.stdout or r.stderr)[-250:],flush=True)
