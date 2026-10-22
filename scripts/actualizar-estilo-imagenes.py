#!/usr/bin/env python3
"""Fixed approved media metadata operation; defaults to rollback-only preview."""
from pathlib import Path
import secrets,time,json,base64,hashlib,urllib.request,importlib.util,sys,tempfile
r=Path(__file__).resolve().parents[1];spec=importlib.util.spec_from_file_location('d',r/'scripts/desplegar-sftp.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);c=m.load_config(r/'.local/sftp.json');client=m.SFTP(c);nonce=secrets.token_hex(12);token=secrets.token_hex(32);remote=c['remote_base']+'/private/.transfer-'+nonce+'.php';endpoint=m.public_path(c)+'/api/transfer-'+nonce+'.php'
options={'config':c['remote_base']+'/private/config.php','app':c['remote_base']+'/app','public_root':m.public_path(c),'apply':'--apply' in sys.argv,'publish':'--publish' in sys.argv}
options['fixed_plan']=base64.b64encode((r/'content/image-review/estilo-replacements-20261021.json').read_bytes()).decode()
body=(r/'scripts/php/apply-approved-media-style.php').read_text().replace('<?php','',1).replace('declare(strict_types=1);','',1)
guard="<?php\nheader('Cache-Control: no-store');header('X-Robots-Tag: noindex,nofollow');if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||time()>"+str(int(time.time())+600)+"||!hash_equals('"+hashlib.sha256(token.encode()).hexdigest()+"',hash('sha256',(string)($_SERVER['HTTP_X_TRANSFER_TOKEN']??'')))){http_response_code(404);exit;}header('Content-Type: application/json');define('EL_TRANSFER_FIXED',true);$options=json_decode(base64_decode('"+base64.b64encode(json.dumps(options).encode()).decode()+"'),true);require '"+remote+"';"
with tempfile.TemporaryDirectory() as tmp:
 helper=Path(tmp)/'helper.php';helper.write_text('<?php\n'+body);entry=Path(tmp)/'entry.php';entry.write_text(guard)
 try:
  client.run(['put '+m.quote(helper)+' '+m.quote(remote),'chmod 600 '+m.quote(remote),'put '+m.quote(entry)+' '+m.quote(endpoint)])
  req=urllib.request.Request('https://explorandolugares.com/api/transfer-'+nonce+'.php',data=b'',method='POST',headers={'X-Transfer-Token':token})
  with urllib.request.urlopen(req,timeout=120) as response:result=json.load(response)
  p=r/'.local'/('estilo-migracion-result.json' if options['apply'] else 'estilo-migracion-preview.json');p.write_text(json.dumps(result,ensure_ascii=False,indent=2));print(json.dumps(result,ensure_ascii=False))
 finally:client.run(['-rm '+m.quote(endpoint),'-rm '+m.quote(remote)])
