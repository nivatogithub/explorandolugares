#!/usr/bin/env python3
"""Version declared resources using final content; include dependency URLs in JS/CSS hashes."""
from pathlib import Path
import argparse,hashlib,json,re
URL=re.compile(r'(?P<path>/assets/[A-Za-z0-9_./-]+\.(?:css|js|json|svg|png|webp|avif|jpe?g|woff2?))(?:\?v=[A-Za-z0-9_-]+)?')
def apply(public):
 public=Path(public);assets={'/'+p.relative_to(public).as_posix():p for p in (public/'assets').rglob('*') if p.is_file()};versions={};visiting=set()
 def update(text):
  return URL.sub(lambda m:m['path']+'?v='+version(m['path']) if m['path'] in assets else m[0],text)
 def version(name):
  if name in versions:return versions[name]
  if name in visiting:raise ValueError('Dependencia cíclica de recursos: '+name)
  visiting.add(name);path=assets[name]
  if path.suffix in {'.css','.js'}:
   original=path.read_text();text=update(original)
   if text!=original:path.write_text(text)
  visiting.remove(name);versions[name]=hashlib.sha256(path.read_bytes()).hexdigest()[:12];return versions[name]
 for name in sorted(assets):version(name)
 for path in public.rglob('*'):
  if path.suffix not in {'.html','.php'} or not path.is_file():continue
  original=path.read_text();text=update(original)
  if text!=original:path.write_text(text)
 (public/'asset-manifest.json').write_text(json.dumps(versions,ensure_ascii=False,indent=2,sort_keys=True)+'\n')
 return versions
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--public',type=Path,required=True);apply(p.parse_args().public)
