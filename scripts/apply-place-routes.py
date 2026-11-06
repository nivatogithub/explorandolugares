"""Install canonical /lugares/ redirects in the hosting configuration."""
import re,json,argparse
from pathlib import Path
root=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser();parser.add_argument('--release',required=True);args=parser.parse_args()
release=Path(args.release).resolve()
public=release/'public'
h=public/'.htaccess'
s=h.read_text()
s=re.sub(r'# Canonical routes: redirect old public routes, preserve query strings\.\nRewriteCond[^\n]*\nRewriteRule[^\n]*\n', '', s)
s=s.replace('destinos/', 'lugares/')
redirect='''# Canonical routes: redirect old public routes, preserve query strings.
# Former image-provenance pages are private records, not public pages.
RewriteRule ^(?:(?:en|pt-br)/)?creditos-(?:fotograficos|imagenes-caribe)(?:/.*)?$ - [G,L]
RewriteRule ^((?:en/|pt-br/)?)jerico\\.html$ /$1lugares/jerico/ [R=301,L,NE]
RewriteRule ^((?:en/|pt-br/)?)(?:destinos|lugares)/jerico/agenda(?:/index\\.html)?/?$ /$1lugares/jerico/ [R=301,L,NE]
RewriteRule ^((?:en/|pt-br/)?)(?:destinos|lugares)/jerico/(?:alojamiento|bares-y-vida-nocturna|comercios-y-productores|cultura-y-patrimonio|experiencias-y-naturaleza|gastronomia|informacion-practica|servicios|transporte-y-movilidad)(?:/index\\.html)?/?$ /$1lugares/jerico/#catalogo-local [R=301,L,NE]
RewriteCond %{THE_REQUEST} \\s/+(?:en/|pt-br/)?destinos(?:/|[?\\s]) [NC]
RewriteRule ^((?:en/|pt-br/)?)destinos(?:/(.*))?$ /$1lugares/$2 [R=301,L,NE]
'''
s=s.replace('RewriteEngine On','RewriteEngine On\n'+redirect,1)
h.write_text(s)
(release/'route-migration-report.json').write_text(json.dumps({'database_modified':False,'files_changed':[]},indent=2))
print('Redirecciones canónicas configuradas sin reescribir páginas ni datos.')
