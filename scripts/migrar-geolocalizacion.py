#!/usr/bin/env python3
"""Apply only the approved geolocation catalog; never accepts SQL or arbitrary migrations."""
from pathlib import Path
import argparse,hashlib,importlib.util,json,secrets,tempfile,time,urllib.request,urllib.error,subprocess
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('el_deploy',ROOT/'scripts/desplegar-sftp.py');deploy=importlib.util.module_from_spec(spec);spec.loader.exec_module(deploy)
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--config',type=Path,default=ROOT/'.local/sftp.json');p.add_argument('--apply',action='store_true');p.add_argument('--approved-preprod');p.add_argument('--preflight',type=Path);p.add_argument('--backup',type=Path);p.add_argument('--report',type=Path,required=True);a=p.parse_args()
 payload=ROOT/'content/geolocation/approved-20261024.json';sha=hashlib.sha256(payload.read_bytes()).hexdigest();expected=None
 if a.apply:
  deploy.require_approved_preprod(a.approved_preprod)
  if not a.preflight or not a.backup:raise SystemExit('Preflight y respaldo verificado obligatorios')
  pre=json.loads(a.preflight.read_text());meta=json.loads(a.backup.with_suffix('.json').read_text())
  if pre.get('payload_sha256')!=sha or pre.get('applied') or not pre.get('no_previously_populated_category_empty'):raise SystemExit('Preflight no corresponde al candidato')
  if not meta.get('restore_verification',{}).get('restore_test_passed') or hashlib.sha256(a.backup.read_bytes()).hexdigest()!=meta['sha256']:raise SystemExit('Respaldo sin restauración verificada')
  expected=pre['before_hash']
 config=deploy.load_config(a.config);client=deploy.SFTP(config);nonce=secrets.token_hex(12);token=secrets.token_hex(32);scratch=config['remote_base']+'/private/.geolocation-'+nonce;public=deploy.public_path(config)+'/api/geolocation-'+nonce+'.php'
 options={'config':config['remote_base']+'/private/config.php','scratch':scratch,'apply':a.apply,'expected':expected,'sha':sha}
 source='''<?php
header('Cache-Control: no-store');header('X-Robots-Tag: noindex,nofollow');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||time()>EXP||!hash_equals('__TOKEN_HASH__',hash('sha256',(string)($_SERVER['HTTP_X_RELEASE_TOKEN']??'')))){http_response_code(404);exit;}
header('Content-Type: application/json');$o=json_decode(base64_decode('OPTIONS'),true);
try{$c=require $o['config'];if(!empty($c['local_test']))throw new RuntimeException('Wrong environment');
$db=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if(!hash_equals($o['sha'],hash_file('sha256',$o['scratch'].'/payload.json')))throw new RuntimeException('Payload integrity failed');
require $o['scratch'].'/release.php';$payload=json_decode(file_get_contents($o['scratch'].'/payload.json'),true,512,JSON_THROW_ON_ERROR);
if((int)$db->query("SELECT GET_LOCK('el_approved_geolocation_release',5)")->fetchColumn()!==1)throw new RuntimeException('Concurrent catalog release');
try{$report=(new ApprovedGeolocationRelease($db))->run($payload,$o['apply'],$o['expected']);$report['payload_sha256']=$o['sha'];echo json_encode($report,JSON_THROW_ON_ERROR);}finally{$db->query("SELECT RELEASE_LOCK('el_approved_geolocation_release')");}
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();http_response_code(503);echo json_encode(['error'=>$e instanceof PDOException?'Database operation failed: '.$e->getCode():$e->getMessage()]);}
'''
 import base64
 source=source.replace('EXP',str(int(time.time())+600)).replace('__TOKEN_HASH__',hashlib.sha256(token.encode()).hexdigest()).replace('OPTIONS',base64.b64encode(json.dumps(options).encode()).decode())
 with tempfile.TemporaryDirectory(prefix='el-approved-geolocation-') as temp:
  helper=Path(temp)/'guard.php';helper.write_text(source)
  try:
   client.run(['mkdir '+deploy.quote(scratch),'chmod 700 '+deploy.quote(scratch),'put '+deploy.quote(payload)+' '+deploy.quote(scratch+'/payload.json'),'chmod 600 '+deploy.quote(scratch+'/payload.json'),'put '+deploy.quote(ROOT/'scripts/php/ApprovedGeolocationRelease.php')+' '+deploy.quote(scratch+'/release.php'),'chmod 600 '+deploy.quote(scratch+'/release.php'),'put '+deploy.quote(helper)+' '+deploy.quote(public)])
   req=urllib.request.Request('https://explorandolugares.com/api/geolocation-'+nonce+'.php',data=b'',headers={'X-Release-Token':token},method='POST')
   try:
    with urllib.request.urlopen(req,timeout=180) as response:report=json.load(response)
   except urllib.error.HTTPError as e:raise RuntimeError('Geolocation release rejected: '+e.read().decode()[:500]) from None
   a.report.parent.mkdir(parents=True,exist_ok=True);a.report.write_text(json.dumps(report,ensure_ascii=False,indent=2));a.report.chmod(0o600)
   print(json.dumps({k:v for k,v in report.items() if k not in ('before_rows','protected','new_business_ids')},ensure_ascii=False))
  finally:client.run(['-rm '+deploy.quote(public),'-rm '+deploy.quote(scratch+'/release.php'),'-rm '+deploy.quote(scratch+'/payload.json'),'-rmdir '+deploy.quote(scratch)])
if __name__=='__main__':main()
