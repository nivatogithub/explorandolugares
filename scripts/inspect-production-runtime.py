#!/usr/bin/env python3
"""Fixed, temporary, token-protected runtime inspection. Never downloads private config or application data."""
import argparse,hashlib,importlib.util,json,secrets,tempfile,time,urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--inspect-production',action='store_true',required=True);args=parser.parse_args()
 spec=importlib.util.spec_from_file_location('deploy',ROOT/'scripts/desplegar-sftp.py');deploy=importlib.util.module_from_spec(spec);spec.loader.exec_module(deploy)
 config=deploy.load_config(ROOT.parent/'explorandolugares/.local/sftp.json');client=deploy.SFTP(config)
 token=secrets.token_hex(32);name='runtime-check-'+secrets.token_hex(16)+'.php';remote=deploy.public_path(config)+'/api/'+name
 php='''<?php
header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||time()>EXPIRY||!hash_equals('TOKENHASH',hash('sha256',(string)($_SERVER['HTTP_X_EL_RUNTIME']??'')))){http_response_code(404);exit;}
header('Content-Type: application/json');
try {
 $config=require CONFIGPATH;
 $pdo=new PDO($config['dsn'],$config['db_user']??null,$config['db_password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $extensions=get_loaded_extensions();sort($extensions);
 $ini=[];foreach(['memory_limit','upload_max_filesize','post_max_size','max_execution_time','max_input_vars','max_file_uploads','date.timezone','default_charset','display_errors','error_reporting','session.save_handler','session.gc_maxlifetime'] as $key)$ini[$key]=ini_get($key);
 $mysql=$pdo->query("SELECT VERSION() AS version,@@version_comment AS edition,@@sql_mode AS sql_mode,@@character_set_server AS server_charset,@@collation_server AS server_collation,@@character_set_connection AS connection_charset,@@collation_connection AS connection_collation,@@time_zone AS time_zone,@@system_time_zone AS system_time_zone,@@lower_case_table_names AS lower_case_table_names")->fetch(PDO::FETCH_ASSOC);
 $stmt=$pdo->query('SELECT DEFAULT_CHARACTER_SET_NAME AS charset,DEFAULT_COLLATION_NAME AS collation FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=DATABASE()');$schema=$stmt->fetch(PDO::FETCH_ASSOC);
 echo json_encode(['php'=>['version'=>PHP_VERSION,'sapi'=>PHP_SAPI,'extensions'=>$extensions,'ini'=>$ini],'web_server'=>$_SERVER['SERVER_SOFTWARE']??'unknown','os_family'=>PHP_OS_FAMILY,'architecture'=>php_uname('m'),'database'=>['driver'=>$pdo->getAttribute(PDO::ATTR_DRIVER_NAME),'runtime'=>$mysql,'schema'=>$schema]],JSON_THROW_ON_ERROR);
} catch(Throwable){http_response_code(503);echo '{"error":"Runtime inspection unavailable"}';}
'''
 php=php.replace('EXPIRY',str(int(time.time())+300)).replace('TOKENHASH',hashlib.sha256(token.encode()).hexdigest()).replace('CONFIGPATH',"'"+config['remote_base']+"/private/config.php'")
 with tempfile.TemporaryDirectory(prefix='el-runtime-') as temporary:
  file=Path(temporary)/'probe.php';file.write_text(php)
  try:
   client.run(['put '+deploy.quote(file)+' '+deploy.quote(remote)])
   req=urllib.request.Request('https://explorandolugares.com/api/'+name,data=b'',method='POST',headers={'X-EL-Runtime':token})
   with urllib.request.urlopen(req,timeout=30) as response:report=json.load(response)
   out=ROOT/'.local/preprod/production-runtime.json';out.parent.mkdir(parents=True,exist_ok=True);out.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
   print(json.dumps(report,ensure_ascii=False,indent=2))
  finally:client.run(['-rm '+deploy.quote(remote)])
 print('Temporary diagnostic removed; no credentials or application records copied or changed.')
if __name__=='__main__':main()
