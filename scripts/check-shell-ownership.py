"""Reject duplicated shell selectors without modifying stylesheets."""
import argparse
import json
from pathlib import Path
from css_components import split

parser = argparse.ArgumentParser()
parser.add_argument('--public', required=True, type=Path)
assets = parser.parse_args().public / 'assets'
conflicts = []
for path in assets.rglob('*.css'):
    if path.name in {'portal-shell.css', 'leaflet.css'}:
        continue
    if split(path.read_text())[1]:
        conflicts.append(path.relative_to(assets).as_posix())
if conflicts:
    raise ValueError('Los estilos del shell pertenecen a portal-shell.css: ' + ', '.join(conflicts))
tokens = json.loads((Path(__file__).resolve().parents[1] / 'design/portal/tokens.json').read_text())['colors']
identity = (assets / 'portal-identity.css').read_text().lower()
missing = [name for name, value in tokens.items() if f'--el-{name}:{value}'.lower() not in identity]
if missing:
    raise ValueError('Los colores declarados no coinciden con portal-identity.css: ' + ', '.join(missing))
print('Estilos compartidos verificados sin reescribir hojas.')
