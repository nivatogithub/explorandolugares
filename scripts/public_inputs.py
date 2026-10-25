"""One and only one producer for every public path."""
from pathlib import Path
import hashlib,json,shutil
STATIC_EXTENSIONS={'.html','.css','.js','.json','.svg','.webp','.png','.txt','.xml','.php'}


def is_retired_static_destination_page(name):
 """Destinations, including their directory, are PHP-only."""
 return bool(__import__('re').fullmatch(r'(?:(?:en|pt-br)/)?destinos(?:/[^/]+)*/index\.html',name))


def collect(root,public):
 root=Path(root);public=Path(public);candidates={}
 layers=[('generated-static',root/'ensayos/v1'),('backend',root/'backend/public'),('integration',root/'backend/integration-medicion/public')]
 for label,base in layers:
  for source in sorted(base.rglob('*')):
   if not source.is_file():continue
   if label=='generated-static' and source.suffix not in STATIC_EXTENSIONS and source.name!='.htaccess':continue
   name=source.relative_to(base).as_posix()
   # Never stage a retired static implementation only to delete it later.
   # The dynamic PHP route owns every destination detail URL.
   if label=='generated-static' and is_retired_static_destination_page(name):
    continue
   candidates.setdefault(name,[]).append((label,source))
 producers={}
 for name,options in candidates.items():
  if len(options)!=1:
   origins=', '.join(source.relative_to(root).as_posix() for _,source in options)
   raise ValueError('La ruta pública tiene más de un productor: '+name+': '+origins+'. Elimina el duplicado; el orden de copia no decide la versión publicada.')
  producers[name]=options[0][1]
 carried=json.loads((root/'backend/app/presentation/existing-media.json').read_text())['files']
 approved={row['path']:row['sha256'] for row in json.loads((root/'backend/app/content/release-media-manifest.json').read_text())['media']}
 media_allowed=carried|approved;report={};excluded=[]
 for name,source in sorted(producers.items()):
  if source.suffix.lower() in {'.webp','.png','.jpg','.jpeg'}:
   expected=media_allowed.get(name)
   if expected is None:excluded.append(name);continue
   if hashlib.sha256(source.read_bytes()).hexdigest()!=expected:raise ValueError('Medio sin aprobación de bytes nuevos: '+name)
  target=public/name;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(source,target)
  report[name]={'producer':source.relative_to(root).as_posix()}
 (public.parent/'public-producers.json').write_text(json.dumps({'schema_version':1,'paths':report,'unapproved_media_excluded':excluded},ensure_ascii=False,indent=2)+'\n')
 return report
