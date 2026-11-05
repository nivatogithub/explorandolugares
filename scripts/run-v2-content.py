#!/usr/bin/env python3
"""Fixed private V2 content operation; never imports local configuration or test data."""
from pathlib import Path
import importlib.util,json,secrets,hashlib,time,tempfile,urllib.request,urllib.error
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('el_deploy',ROOT/'scripts/desplegar-sftp.py');deploy=importlib.util.module_from_spec(spec);spec.loader.exec_module(deploy)
CONFIG=deploy.load_config(ROOT/'.local/sftp.json')
def run_private_operation(body,files=None):
 files=files or {};client=deploy.SFTP(CONFIG);secret=secrets.token_hex(32);nonce=secrets.token_hex(12);name='release-'+nonce+'.php';remote=deploy.public_path(CONFIG)+'/api/'+name;scratch=CONFIG['remote_base']+'/private/.deployment-'+nonce
 guard="""<?php
header('Cache-Control: no-store');header('X-Robots-Tag: noindex,nofollow');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||time()>EXP||!hash_equals('HASH',hash('sha256',(string)($_SERVER['HTTP_X_RELEASE_TOKEN']??'')))){http_response_code(404);exit;}
header('Content-Type: application/json');
try {
$c=require CFG;
if(!empty($c['local_test']))throw new RuntimeException('Unexpected local config on production');
$db=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$releaseTools=TOOLS;
BODY
} catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();http_response_code(503);echo json_encode(['error'=>$e instanceof PDOException?'Database operation failed: '.$e->getCode():$e->getMessage(),'type'=>get_class($e)]);}
""".replace('EXP',str(int(time.time())+600)).replace('HASH',hashlib.sha256(secret.encode()).hexdigest()).replace('CFG',repr(CONFIG['remote_base']+'/private/config.php')).replace('TOOLS',repr(scratch)).replace('BODY',body)
 with tempfile.TemporaryDirectory(prefix='el-production-') as t:
  local=Path(t)/'action.php';local.write_text(guard);commands=[];dirs=set()
  for rel in files:
   p=Path(rel)
   for parent in p.parents:
    if str(parent)!='.':dirs.add(str(parent))
  if files:commands.extend(['-mkdir '+deploy.quote(scratch),'chmod 700 '+deploy.quote(scratch)])
  for directory in sorted(dirs,key=lambda x:(x.count('/'),x)):commands.append('-mkdir '+deploy.quote(scratch+'/'+directory))
  for rel,path in files.items():commands.extend(['put '+deploy.quote(Path(path).resolve())+' '+deploy.quote(scratch+'/'+rel),'chmod 600 '+deploy.quote(scratch+'/'+rel)])
  commands.append('put '+deploy.quote(local)+' '+deploy.quote(remote))
  try:
   client.run(commands)
   req=urllib.request.Request('https://explorandolugares.com/api/'+name,data=b'',headers={'X-Release-Token':secret},method='POST')
   try:
    with urllib.request.urlopen(req,timeout=180) as response:result=json.load(response)
   except urllib.error.HTTPError as e:
    error=e.read().decode(errors='replace');raise RuntimeError('Production action failed: '+error[:700]) from None
   return result
  finally:
   cleanup=['-rm '+deploy.quote(remote)]+['-rm '+deploy.quote(scratch+'/'+rel) for rel in files]
   cleanup+=['-rmdir '+deploy.quote(scratch+'/'+directory) for directory in sorted(dirs,key=lambda x:(x.count('/'),x),reverse=True)]
   if files:cleanup+=['-rmdir '+deploy.quote(scratch)]
   client.run(cleanup)

if __name__=='__main__':
 import argparse
 parser=argparse.ArgumentParser(description=__doc__)
 parser.add_argument('--mode',required=True,choices=['probe','migrate','apply','verify'])
 parser.add_argument('--package',required=True,type=Path)
 parser.add_argument('--approved-preprod')
 parser.add_argument('--report',required=True,type=Path)
 args=parser.parse_args()
 if args.mode!='probe':deploy.require_approved_preprod(args.approved_preprod)
 package=args.package.resolve()
 if not (package/'build-manifest.json').is_file() or not (package/'app/ProductionContentTransfer.php').is_file():raise SystemExit('A verified built package is required')
 inventory=json.loads((package/'build-manifest.json').read_text())['files']
 files={}
 for path in (package/'app').rglob('*'):
  if not path.is_file():continue
  rel=path.relative_to(package).as_posix()
  if rel not in inventory or hashlib.sha256(path.read_bytes()).hexdigest()!=inventory[rel]['sha256']:raise SystemExit('Package changed: '+rel)
  files[rel]=path
 bundle=ROOT/'content/releases/2026-11-05-87'
 for name in ['manifest.json','scope.json','public-content.json']:files['bundle/'+name]=bundle/name
 files['bundle/heritage.sql']=ROOT/'content/place-labels/heritage-colombia-20261104.sql'
 files['V2PlaceRelease.php']=ROOT/'scripts/php/V2PlaceRelease.php'
 content_hash=json.loads((bundle/'manifest.json').read_text())['sha256']
 config_path=repr(CONFIG['remote_base']+'/private/config.php')
 body="$configDigest=hash_file('sha256',"+config_path+");require $releaseTools.'/V2PlaceRelease.php';$executor=new V2PlaceRelease($db,$releaseTools.'/app',"+repr(deploy.public_path(CONFIG))+",$releaseTools.'/bundle',"+repr(CONFIG['remote_base']+'/private/v2-content-'+content_hash+'.json')+");$result=$executor->run("+repr(args.mode)+");if(!hash_equals($configDigest,hash_file('sha256',"+config_path+")))throw new RuntimeException('Private production configuration changed');$result['private_config_unchanged']=true;echo json_encode($result,JSON_THROW_ON_ERROR);"
 result=run_private_operation(body,files)
 args.report.parent.mkdir(parents=True,exist_ok=True);args.report.write_text(json.dumps(result,indent=2)+'\n')
 print(json.dumps(result))
 if result.get('error'):raise SystemExit('V2 operation failed; application deployment stopped')
