"""Stamp actual presentation assets in final HTML/PHP; no content or routing changes."""
from pathlib import Path
import argparse,hashlib,re
import sys
sys.path.insert(0,str(Path(__file__).resolve().parent))
from resource_markup import stamp_url
ASSETS=['admin-business-location.js','style.css','identidad-neutral.css','identidad-neutral.js','bogota-compacta.js','cartagena-compacta.js','medellin-compacta.js','santa-marta-compacta.js','destinos-semirrealistas.js']
def stamp(public):
 versions={name:hashlib.sha256((public/'assets'/name).read_bytes()).hexdigest()[:12] for name in ASSETS if (public/'assets'/name).is_file()}
 for page in [*public.rglob('*.html'),*public.rglob('*.php')]:
  original=page.read_text();updated=original
  for name,version in versions.items():updated=stamp_url(updated,r'(assets/'+re.escape(name)+r')(?:\?v=[A-Za-z0-9_-]+)?',lambda m:m[1]+'?v='+version)
  if updated!=original:page.write_text(updated)
 return versions
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--public',type=Path,required=True);stamp(p.parse_args().public)
