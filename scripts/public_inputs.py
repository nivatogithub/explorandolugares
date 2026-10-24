"""Explicit authoritative producers; one copy per public path, independent of copy order."""
from pathlib import Path
import hashlib,json,shutil
STATIC_EXTENSIONS={'.html','.css','.js','.json','.svg','.webp','.png','.txt','.xml','.php'}
BACKEND_AUTHORITATIVE={'assets/nomenclatura-lugares.js','assets/menu-principal.js','assets/account-access.css','assets/account-registration.css','assets/account-entry.css','assets/header-controls.js','assets/header-controls.css','assets/language-navigation.js','assets/portada-atracciones.js','assets/portada-atracciones.css','assets/subscription-comparison.js','assets/subscription-comparison.css'}
# A differing duplicate is forbidden unless its active producer is declared
# here. This list is deliberately small: it records the remaining migration
# boundary between the legacy public tree and the measurement integration.
DIVERGENT_AUTHORITIES={
 'assets/bogota-compacta.js':'integration',
 'assets/cartagena-compacta.js':'integration',
 'assets/destinos-semirrealistas.json':'integration',
 'assets/medellin-compacta.js':'integration',
 'assets/santa-marta-compacta.js':'integration',
 'destino.php':'integration',
 'destinos/index.html':'integration',
 'en/destinos/index.html':'integration',
 'en/privacidad/index.html':'integration',
 'negocio.php':'integration',
 'privacidad/index.html':'integration',
 'pt-br/destinos/index.html':'integration',
 'pt-br/privacidad/index.html':'integration',
}


def is_retired_static_destination_page(name):
 """Static destination detail pages are retired; PHP is their sole renderer."""
 return bool(__import__('re').fullmatch(r'(?:(?:en|pt-br)/)?destinos/[^/]+(?:/[^/]+)*/index\.html',name))


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
 producers={};duplicate_sources={}
 for name,options in candidates.items():
  labels={label for label,_ in options}
  authority=DIVERGENT_AUTHORITIES.get(name)
  if authority:
   selected=next((source for label,source in options if label==authority),None)
   if selected is None:raise ValueError('Falta el productor autorizado para '+name)
  elif name in BACKEND_AUTHORITATIVE or name.startswith(('cobertura/','assets/cobertura/')):
   selected=next((source for label,source in options if label=='backend'),None)
   if selected is None:raise ValueError('Falta el productor backend autorizado para '+name)
  else:
   hashes={hashlib.sha256(source.read_bytes()).hexdigest() for _,source in options}
   if len(hashes)>1:
    origins=', '.join(source.relative_to(root).as_posix() for _,source in options)
    raise ValueError('Dos productores con contenido distinto para '+name+': '+origins+'. Declara una única autoridad o elimina el duplicado.')
   selected=options[0][1]
  producers[name]=selected
  duplicate_sources[name]=[source.relative_to(root).as_posix() for _,source in options if source!=selected]
 carried=json.loads((root/'backend/app/presentation/existing-media.json').read_text())['files']
 approved={row['path']:row['sha256'] for row in json.loads((root/'backend/app/content/release-media-manifest.json').read_text())['media']}
 media_allowed=carried|approved;report={};excluded=[]
 for name,source in sorted(producers.items()):
  if source.suffix.lower() in {'.webp','.png','.jpg','.jpeg'}:
   expected=media_allowed.get(name)
   if expected is None:excluded.append(name);continue
   if hashlib.sha256(source.read_bytes()).hexdigest()!=expected:raise ValueError('Medio sin aprobación de bytes nuevos: '+name)
  target=public/name;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(source,target)
  report[name]={'producer':source.relative_to(root).as_posix(),'other_inputs':duplicate_sources[name]}
 (public.parent/'public-producers.json').write_text(json.dumps({'schema_version':1,'paths':report,'unapproved_media_excluded':excluded},ensure_ascii=False,indent=2)+'\n')
 return report
