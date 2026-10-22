#!/usr/bin/env python3
"""Remove obsolete shell ownership from other modules; portal-shell.css is authoritative."""
from pathlib import Path
import argparse,json
from css_components import split

def apply(public):
 public=Path(public);report=[]
 for path in (public/'assets').rglob('*.css'):
  if path.name in {'portal-shell.css','leaflet.css'}:continue
  original=path.read_text();remaining,owned=split(original)
  if owned:
   path.write_text(remaining);report.append({'path':path.relative_to(public).as_posix(),'removed_bytes':len(original.encode())-len(remaining.encode())})
 (public.parent/'shell-css-migration.json').write_text(json.dumps(report,indent=2)+'\n')
 return report
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--public',type=Path,required=True);apply(p.parse_args().public)
