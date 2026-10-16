#!/usr/bin/env python3
"""Version the page's local styles/scripts by content, not by manually incremented numbers."""
import argparse,hashlib,re,subprocess,sys,importlib.util
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser();p.add_argument('--check',action='store_true');args=p.parse_args()
subprocess.run([sys.executable,str(ROOT/'scripts/generar-tokens-portal.py'),*(['--check'] if args.check else [])],check=True)
page=ROOT/'backend/public/cobertura/colombia/index.html';actual=page.read_text();expected=actual
for name in ['portal-tokens.css','cobertura.css','cobertura.js','medicion.js']:
 digest=hashlib.sha256((ROOT/'backend/public/assets/cobertura'/name).read_bytes()).hexdigest()[:12]
 pattern=r'(assets/cobertura/'+re.escape(name)+r')\?v=[^"\s]+'
 expected,count=re.subn(pattern,lambda m:m[1]+'?v='+digest,expected)
 if count!=1:raise SystemExit('Missing/duplicate page resource: '+name)
if args.check:
 if actual!=expected:raise SystemExit('Coverage asset versions are out of date')
 print('Coverage resource versions: OK')
else:page.write_text(expected)

# A fresh HTML/PHP page must not reload a stale shared loader or measurement file.
spec=importlib.util.spec_from_file_location('measurement_versions',ROOT/'scripts/versiones-medicion.py');versioner=importlib.util.module_from_spec(spec);spec.loader.exec_module(versioner)
menu=ROOT/'backend/integration-medicion/public/assets/menu-principal.js'
measurement_version,menu_text,menu_version=versioner.versions(ROOT/'backend/public/assets/cobertura/medicion.js',menu)
updates={menu:menu_text}
for path in [page,*list((ROOT/'backend/integration-medicion/public').rglob('*'))]:
 if path.is_file() and path.suffix in {'.html','.php'}:
  updates[path]=versioner.stamp(versioner.stamp(path.read_text(),'medicion.js',measurement_version),'menu-principal.js',menu_version)
for path,expected_text in updates.items():
 if args.check and path.read_text()!=expected_text:raise SystemExit('Shared measurement loader versions are out of date: '+str(path.relative_to(ROOT)))
 if not args.check:path.write_text(expected_text)
