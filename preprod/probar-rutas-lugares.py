"""Local-only URL experiment. Run after preprod/control.py up. No DB changes."""
import re,shutil,json
from pathlib import Path
root=Path(__file__).resolve().parents[1]
release=root/'.local/preprod/release'
public=release/'public'
if not (public/'__preprod-runtime.php').exists():raise SystemExit('Solo paquete local preprod')
changed=[]
for base in [public,release/'app']:
 for p in base.rglob('*'):
  if not p.is_file() or p.suffix not in {'.html','.php','.js','.json','.xml','.txt'}:continue
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
s=re.sub(r'# Local experiment: redirect old public routes, preserve query strings\.\nRewriteCond[^\n]*\nRewriteRule[^\n]*\n', '', s)
s=s.replace('destinos/', 'lugares/')
redirect='''# Local experiment: redirect old public routes, preserve query strings.
RewriteCond %{THE_REQUEST} \\s/+(?:en/|pt-br/)?destinos(?:/|[?\\s]) [NC]
RewriteRule ^((?:en/|pt-br/)?)destinos(?:/(.*))?$ /$1lugares/$2 [R=302,L,NE]
'''
s=s.replace('RewriteEngine On','RewriteEngine On\n'+redirect,1)
h.write_text(s)
(root/'.local/preprod/prueba-rutas-lugares.json').write_text(json.dumps({'local_only':True,'database_modified':False,'files_changed':changed},indent=2))
print(f'Prueba local aplicada: {len(changed)} archivos; sin cambiar base de datos.')
