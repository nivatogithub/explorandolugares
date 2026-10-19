#!/usr/bin/env python3
"""Consistent private database backup over SSH. Never imports or resets production."""
from pathlib import Path
import argparse,base64,gzip,hashlib,importlib.util,json,os,secrets,subprocess,tempfile,time,urllib.request,urllib.error
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('backup_deploy',ROOT/'scripts/desplegar-sftp.py');api=importlib.util.module_from_spec(spec);spec.loader.exec_module(api)
def ssh(config):
 cmd=['ssh','-F','/dev/null','-p',str(config['port']),'-oBatchMode=yes','-oStrictHostKeyChecking=yes','-oConnectTimeout=20','-oForwardAgent=no','-oClearAllForwardings=yes','-oUserKnownHostsFile='+str(Path(config['known_hosts_file']).expanduser().resolve())]
 if config.get('identity_file'):cmd+=['-oIdentitiesOnly=yes','-i',str(Path(config['identity_file']).expanduser().resolve())]
 return cmd+[config['user']+'@'+config['host']]
def private_php(config,options,action="database-snapshot.php"):
    # GoDaddy permits SFTP while interactive shell is disabled. One fixed operation,
    # no command or SQL supplied by the HTTP caller, token in header, ten-minute expiry.
    token=secrets.token_hex(32);nonce=secrets.token_hex(12);client=api.SFTP(config)
    remote=config['remote_base']+'/private/.snapshot-'+nonce+'.php'
    endpoint=api.public_path(config)+'/api/snapshot-'+nonce+'.php'
    if action not in {'database-snapshot.php','prepare-account-migration.php'}:raise ValueError('Operación no permitida.')
    source=(ROOT/'scripts/php'/action).read_text()
    scratch=config['remote_base']+'/private/.migration-'+nonce
    dependencies={}
    if action=='prepare-account-migration.php':
        options=dict(options,app_root=scratch,public_root=api.public_path(config))
        dependencies={'DatabaseMaintenance.php':ROOT/'backend/app/DatabaseMaintenance.php','database/migrations/043-account-lifecycle.mysql.sql':ROOT/'backend/database/migrations/043-account-lifecycle.mysql.sql'}
    guard="""<?php
header('Cache-Control: no-store');header('X-Robots-Tag: noindex,nofollow');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||time()>EXP||!hash_equals('HASH',hash('sha256',(string)($_SERVER['HTTP_X_SNAPSHOT_TOKEN']??'')))){http_response_code(404);exit;}
header('Content-Type: application/json');define('EL_PRIVATE_SNAPSHOT',true);
$options=json_decode(base64_decode('OPTIONS'),true);require 'SOURCE';
""".replace('EXP',str(int(time.time())+600)).replace('HASH',hashlib.sha256(token.encode()).hexdigest()).replace('OPTIONS',base64.b64encode(json.dumps(options).encode()).decode()).replace('SOURCE',remote)
    with tempfile.TemporaryDirectory(prefix='el-snapshot-') as t:
        helper=Path(t)/'snapshot.php';helper.write_text(source);entry=Path(t)/'entry.php';entry.write_text(guard)
        try:
            if dependencies:
                commands=['-mkdir '+api.quote(scratch),'chmod 700 '+api.quote(scratch),'-mkdir '+api.quote(scratch+'/database'),'-mkdir '+api.quote(scratch+'/database/migrations')]
                for relative,local in dependencies.items():commands+=['put '+api.quote(local)+' '+api.quote(scratch+'/'+relative),'chmod 600 '+api.quote(scratch+'/'+relative)]
                client.run(commands)
            client.run(['put '+api.quote(helper)+' '+api.quote(remote),'chmod 600 '+api.quote(remote),'put '+api.quote(entry)+' '+api.quote(endpoint)])
            request=urllib.request.Request('https://explorandolugares.com/api/snapshot-'+nonce+'.php',data=b'',method='POST',headers={'X-Snapshot-Token':token})
            with urllib.request.urlopen(request,timeout=240) as response:return json.load(response)
        finally:
            commands=['-rm '+api.quote(endpoint),'-rm '+api.quote(remote)]
            if dependencies:commands+=['-rm '+api.quote(scratch+'/'+r) for r in dependencies]+['-rmdir '+api.quote(scratch+'/database/migrations'),'-rmdir '+api.quote(scratch+'/database'),'-rmdir '+api.quote(scratch)]
            client.run(commands)

def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--config',type=Path,default=ROOT/'.local/sftp.json');parser.add_argument('--transport',choices=['sftp-php','ssh'],default='sftp-php');parser.add_argument('--create',action='store_true',help='Sin esta opción solo consulta si el respaldo es viable.');args=parser.parse_args()
 config=api.load_config(args.config);options={'config':config['remote_base']+'/private/config.php','operation':'create' if args.create else 'probe'}
 encoded=base64.b64encode(json.dumps(options).encode()).decode()
 source=(ROOT/'scripts/php/database-snapshot.php').read_text().replace('<?php','<?php\n$options=json_decode(base64_decode("'+encoded+'"),true);',1)
 # strict_types must be first statement, including when injecting CLI options.
 source=source.replace('declare(strict_types=1);','')
 if args.transport=='sftp-php':result=private_php(config,options)
 else:
  response=subprocess.run(ssh(config)+['php'],input=source,text=True,capture_output=True,timeout=600)
  if response.returncode or not response.stdout.lstrip().startswith('{'):raise SystemExit('No hay shell PHP disponible; utiliza --transport sftp-php.')
  result=json.loads(response.stdout)
 if not args.create:print(json.dumps(result,ensure_ascii=False));return
 destination=ROOT/'.local/database-backups';destination.mkdir(parents=True,exist_ok=True,mode=0o700);os.chmod(destination,0o700)
 remote=Path(result['remote_path']);local=destination/remote.name
 # No public download URLs, no credentials in command arguments, no database data in logs.
 api.SFTP(config).run(['get '+api.quote(remote)+' '+api.quote(local)])
 os.chmod(local,0o600)
 if hashlib.sha256(local.read_bytes()).hexdigest()!=result['sha256']:raise SystemExit('El respaldo descargado no coincide con el original.')
 with gzip.open(local,'rb') as f:
  while f.read(1048576):pass
 result['local_path']=str(local);result['gzip_verified']=True
 meta=local.with_suffix('.json');meta.write_text(json.dumps(result,indent=2,ensure_ascii=False)+'\n');os.chmod(meta,0o600)
 print(json.dumps({'backup_created':True,'local_path':str(local),'bytes':result['bytes'],'tables':result['tables'],'sha256_verified':True,'statistics_modified':False},ensure_ascii=False))
if __name__=='__main__':main()
