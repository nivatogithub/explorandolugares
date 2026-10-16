#!/usr/bin/env python3
"""Run only the declared additive install using a one-use, 10-minute deployment capability.
No shell, administrator login, credentials download, arbitrary SQL or command execution.
Both temporary artifacts are removed in finally; they are never part of the site manifest.
"""
import importlib.util,json,secrets,hashlib,tempfile,urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('deploy',ROOT/'scripts/desplegar-sftp.py');mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)
c=mod.load_config(ROOT.parent/'explorandolugares/.local/sftp.json');client=mod.SFTP(c)
import time
name='medicion-activar-'+secrets.token_hex(12)+'.php';remote=mod.public_path(c)+'/api/'+name;grant=c['remote_base']+'/app/analytics/activation-grant.json'
token=secrets.token_hex(32)
with tempfile.TemporaryDirectory(prefix='el-measurement-activation-') as temp:
 p=Path(temp);(p/'grant.json').write_text(json.dumps({'hash':hashlib.sha256(token.encode()).hexdigest(),'expires':int(time.time())+600}))
 (p/'entry.php').write_text("<?php require '"+c['remote_base']+"/app/analytics/activate-once.php';")
 try:
  client.run([f'put {mod.quote(p/"grant.json")} {mod.quote(grant)}',f'put {mod.quote(p/"entry.php")} {mod.quote(remote)}'])
  req=urllib.request.Request('https://explorandolugares.com/api/'+name,data=b'',method='POST',headers={'X-EL-Deployment':token})
  with urllib.request.urlopen(req,timeout=30) as r:result=json.load(r)
  if result!={'installed':True}:raise RuntimeError('Installation not confirmed')
  print('Fixed additive measurement installation confirmed. No credentials read or changed.')
 finally:
  client.run(['-rm '+mod.quote(remote),'-rm '+mod.quote(grant)])
