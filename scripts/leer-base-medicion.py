#!/usr/bin/env python3
"""Read only the declared public integration files and deployment manifest."""
import importlib.util,json,hashlib
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('deploy',ROOT/'scripts/desplegar-sftp.py');mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)
c=mod.load_config(ROOT.parent/'explorandolugares/.local/sftp.json');client=mod.SFTP(c);out=ROOT/'.local/analytics-base';out.mkdir(parents=True,exist_ok=True)
paths=['public/assets/menu-principal.js','public/destino.php','public/negocio.php']+[f'public/{prefix}privacidad{suffix}' for prefix in ['', 'en/','pt-br/'] for suffix in ['.html','/index.html']]
client.run([f'get {mod.quote(c["remote_base"]+"/.explorandolugares-deploy.json")} {mod.quote(out/"manifest.json")}'])
m=json.loads((out/'manifest.json').read_text());assert m['site']=='explorandolugares';entries={f['relative']:f for f in m['files']}
commands=[]
for name in paths:
 assert name in entries
 dest=out/name;dest.parent.mkdir(parents=True,exist_ok=True)
 remote=mod.public_path(c)+'/'+name.removeprefix('public/')
 commands.append(f'get {mod.quote(remote)} {mod.quote(dest)}')
client.run(commands)
for name in paths:
 assert hashlib.sha256((out/name).read_bytes()).hexdigest()==entries[name]['sha256'], 'Remote differs from manifest: '+name
print('Verified public integration base:',len(paths),'files')
