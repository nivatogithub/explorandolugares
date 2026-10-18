#!/usr/bin/env python3
"""Regression gate: no obsolete identity assets or palettes in the active release."""
from pathlib import Path
import re, urllib.request, sys
ROOT=Path(__file__).resolve().parents[1]
PUBLIC=Path(sys.argv[1]) if len(sys.argv)>1 else ROOT/'.local/preprod/release/public'
old_assets=re.compile(r'(?:[\"\'/])(?:logo(?:-completo)?|favicon)\.svg(?:[?\"\'])')
old_ui=re.compile(r'#(?:173f35|054b4d|064d4e|043e42|365744|315e5c|fff8ed|fff0dc|f2c77e|e9b75f)\b',re.I)
for p in PUBLIC.rglob('*'):
 if p.suffix not in {'.html','.php','.css','.js'}:continue
 source=p.read_text()
 assert not old_assets.search(source),f'Logotipo anterior: {p}'
 if p.suffix=='.css' and p.name not in {'leaflet.css','identidad-neutral.css'}:
  assert not old_ui.search(source),f'Paleta anterior: {p}'
for name in ['logo.svg','logo-completo.svg','favicon.svg']:
 assert not (PUBLIC/'assets'/name).exists(),name
for route in ([] if len(sys.argv)>1 else ['/assets/brand/explorando-lugares-logo-v2-transparent.png','/assets/cobertura/portal-tokens.css','/assets/account-access.css','/assets/account-entry.css','/negocios/acceso/','/negocios/recuperar-clave/','/negocios/cambiar-clave/']):
 with urllib.request.urlopen('http://127.0.0.1:8088'+route,timeout=20) as response:
  assert response.status==200,route
print('OK: recursos antiguos ausentes; paleta anterior retirada de las hojas activas; identidad y accesos servidos correctamente.')
