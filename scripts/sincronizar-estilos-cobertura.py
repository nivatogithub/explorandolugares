#!/usr/bin/env python3
"""Version the page's local styles/scripts by content, not by manually incremented numbers."""
import argparse,hashlib,re,subprocess,sys
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
