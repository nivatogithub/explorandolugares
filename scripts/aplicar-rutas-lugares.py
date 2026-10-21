"""Materialize canonical /lugares/ routes and legacy 301 redirects in a release."""
import re,shutil,json,argparse
from pathlib import Path
root=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser();parser.add_argument('--release',required=True);args=parser.parse_args()
release=Path(args.release).resolve()
public=release/'public'
changed=[]
for base in [public,release/'app']:
 for p in base.rglob('*'):
  if not p.is_file() or p.suffix not in {'.html','.php','.js','.json','.xml','.txt'}:continue
  if 'analytics' in p.parts:continue
  text=p.read_text(errors='strict')
  # Public URL paths only; keep image directories and internal identifiers.
  new=re.sub(r'(?<!assets)(/destinos/)', '/lugares/',text)
  new=new.replace('?>destinos/', '?>lugares/')
  new=new.replace(')?destinos/', ')?(?:destinos|lugares)/')
  if new!=text:p.write_text(new);changed.append(str(p.relative_to(release)))
for prefix in ['', 'en/', 'pt-br/']:
 source=public/prefix/'destinos'
 target=public/prefix/'lugares'
 if source.exists():shutil.copytree(source,target,dirs_exist_ok=True)
h=public/'.htaccess'
s=h.read_text()
s=re.sub(r'# Canonical routes: redirect old public routes, preserve query strings\.\nRewriteCond[^\n]*\nRewriteRule[^\n]*\n', '', s)
s=s.replace('destinos/', 'lugares/')
redirect='''# Canonical routes: redirect old public routes, preserve query strings.
RewriteCond %{THE_REQUEST} \\s/+(?:en/|pt-br/)?destinos(?:/|[?\\s]) [NC]
RewriteRule ^((?:en/|pt-br/)?)destinos(?:/(.*))?$ /$1lugares/$2 [R=301,L,NE]
'''
s=s.replace('RewriteEngine On','RewriteEngine On\n'+redirect,1)
h.write_text(s)
(release/'route-migration-report.json').write_text(json.dumps({'database_modified':False,'files_changed':changed},indent=2))
print(f'Rutas canónicas aplicadas: {len(changed)} archivos; sin cambiar base de datos.')
