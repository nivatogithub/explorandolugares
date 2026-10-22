"""Explicit authoritative producers; one copy per public path, independent of copy order."""
from pathlib import Path
import hashlib,json,shutil
STATIC_EXTENSIONS={'.html','.css','.js','.json','.svg','.webp','.png','.txt','.xml','.php'}
BACKEND_AUTHORITATIVE={'assets/nomenclatura-lugares.js','assets/menu-principal.js','assets/account-access.css','assets/account-registration.css','assets/account-entry.css','assets/header-controls.js','assets/header-controls.css','assets/language-navigation.js','assets/portada-atracciones.js','assets/portada-atracciones.css','assets/subscription-comparison.js','assets/subscription-comparison.css'}
def collect(root,public):
 root=Path(root);public=Path(public);producers={};candidates={}
 layers=[('generated-static',root/'ensayos/v1'),('backend',root/'backend/public'),('integration',root/'backend/integration-medicion/public')]
 for label,base in layers:
  for source in sorted(base.rglob('*')):
   if not source.is_file():continue
   if label=='generated-static' and source.suffix not in STATIC_EXTENSIONS and source.name!='.htaccess':continue
   name=source.relative_to(base).as_posix();candidates.setdefault(name,[]).append(source.relative_to(root).as_posix());producers[name]=source
 # Explicit authority for current backend controls/coverage; never depend on a later copy.
 for name in list(producers):
  source=root/'backend/public'/name
  if source.is_file() and (name in BACKEND_AUTHORITATIVE or name.startswith(('cobertura/','assets/cobertura/'))):producers[name]=source
 carried=json.loads((root/'backend/app/presentation/existing-media.json').read_text())['files']
 approved={row['path']:row['sha256'] for row in json.loads((root/'backend/app/content/release-media-manifest.json').read_text())['media']}
 media_allowed=carried|approved;report={};excluded=[]
 for name,source in sorted(producers.items()):
  if source.suffix.lower() in {'.webp','.png','.jpg','.jpeg'}:
   expected=media_allowed.get(name)
   if expected is None:excluded.append(name);continue
   if hashlib.sha256(source.read_bytes()).hexdigest()!=expected:raise ValueError('Medio sin aprobación de bytes nuevos: '+name)
  target=public/name;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(source,target)
  report[name]={'producer':source.relative_to(root).as_posix(),'other_inputs':[item for item in candidates.get(name,[]) if item!=source.relative_to(root).as_posix()]}
 (public.parent/'public-producers.json').write_text(json.dumps({'schema_version':1,'paths':report,'unapproved_media_excluded':excluded},ensure_ascii=False,indent=2)+'\n')
 return report
