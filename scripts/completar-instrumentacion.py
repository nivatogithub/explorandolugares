#!/usr/bin/env python3
"""Inventory current managed public HTML; patch only pages without the shared loader."""
import importlib.util,json,hashlib,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('deploy',ROOT/'scripts/desplegar-sftp.py');mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)
c=mod.load_config(ROOT.parent/'explorandolugares/.local/sftp.json');client=mod.SFTP(c)
m=json.loads((ROOT/'.local/analytics-base/manifest.json').read_text());out=ROOT/'.local/analytics-pages';out.mkdir(parents=True,exist_ok=True)
paths=[f for f in m['files'] if f['relative'].startswith('public/') and f['relative'].endswith('.html') and not re.search(r'/(admin|business|negocios/(registro|portal|acceso|salir))/',f['relative'])]
commands=[]
for f in paths:
 dst=out/f['relative'];dst.parent.mkdir(parents=True,exist_ok=True);commands.append(f'get {mod.quote(mod.public_path(c)+"/"+f["relative"].removeprefix("public/"))} {mod.quote(dst)}')
client.run(commands)
guardPath=ROOT/'backend/integration-medicion/baseline.json';guards=json.loads(guardPath.read_text());patched=[];shared=[];redirects=[]
for f in paths:
 src=out/f['relative'];raw=src.read_bytes();assert hashlib.sha256(raw).hexdigest()==f['sha256'],'Manifest mismatch: '+f['relative']
 s=raw.decode()
 if re.search(r'http-equiv=["\']refresh',s,re.I):redirects.append(f['relative']);continue
 if 'menu-principal.js' in s or 'medicion.js' in s:shared.append(f['relative']);continue
 if '</body>' not in s:raise ValueError('No body: '+f['relative'])
 dst=ROOT/'backend/integration-medicion'/f['relative'];assert not dst.exists(),'Existing integration requires review'
 dst.parent.mkdir(parents=True,exist_ok=True);dst.write_text(s.replace('</body>','<script defer data-el-measurement="1" src="/assets/cobertura/medicion.js?v=1"></script></body>'))
 guards[f['relative']]=f['sha256'];patched.append(f['relative'])
guardPath.write_text(json.dumps(guards,indent=2)+'\n')
report={'managed_public_html':len(paths),'shared_or_direct_loader':len(shared),'redirects':len(redirects),'added_direct_loader':patched}
(ROOT/'backend/integration-medicion/inventory.json').write_text(json.dumps(report,indent=2)+'\n');print('Managed HTML:',len(paths),'already instrumented:',len(shared),'redirects:',len(redirects),'additional pages:',len(patched))
