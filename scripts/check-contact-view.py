#!/usr/bin/env python3
"""Compare contact request handling and rendered output in disposable fixtures."""
from pathlib import Path
from importlib.util import spec_from_file_location,module_from_spec
import argparse,json,os,shutil,sqlite3,subprocess,tempfile
ROOT=Path(__file__).resolve().parents[1]
spec=spec_from_file_location('auth_views',ROOT/'scripts/check-account-auth-views.py');auth=module_from_spec(spec);spec.loader.exec_module(auth)
PHP=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
ini_set('session.save_path',$i['sessions']);session_id(bin2hex(random_bytes(16)));session_start();
$_SESSION=['csrf'=>'contact-test-token'];
if($i['mode']==='owner'||$i['mode']==='expired')$_SESSION+=['business_account_id'=>$i['owner'],'business_last_seen'=>$i['mode']==='expired'?time()-3600:time()];
if($i['mode']==='admin')$_SESSION+=['admin'=>true,'last_seen'=>time()];
if($i['receipt'])$_SESSION['public_request_receipt']='SYNTHETIC-REFERENCE';
$_GET=['lang'=>$i['locale']];$_POST=$i['post']??[];$_FILES=[];
$_SERVER=['REQUEST_METHOD'=>$i['post']===null?'GET':'POST','REQUEST_URI'=>'/contact.php?lang='.$i['locale'],'HTTP_HOST'=>'127.0.0.1','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];
ob_start();register_shutdown_function(static function(){while(ob_get_level()>1)ob_end_flush();echo json_encode(['http'=>http_response_code()?:200,'html'=>ob_get_clean(),'authenticated'=>!empty($_SESSION['business_account_id']),'locale'=>$_SESSION['account_locale']??null,'has_receipt'=>!empty($_SESSION['public_request_receipt'])],JSON_THROW_ON_ERROR);});
require $i['package'].'/public/contact.php';
'''
def run(before,after,output):
 before,after=before.resolve(),after.resolve()
 assert all(p.is_relative_to(ROOT/'.local') and (p/'private/presentation.sqlite').is_file() for p in [before,after])
 old=(before/'public/contact.php').read_text().split("require_once __DIR__.'/_account-page.php';",1)[0]
 new=(after/'public/contact.php').read_text().split('// Preserve the legacy account-header',1)[0]
 assert old==new,'Contact request processing changed.'
 checks=[]
 with tempfile.TemporaryDirectory(prefix='contact-view-',dir=ROOT/'.local') as temporary:
  root=Path(temporary);packages=[]
  for label,source in [('before',before),('after',after)]:
   package=root/label;shutil.copytree(source/'app',package/'app');shutil.copytree(source/'public',package/'public',ignore=shutil.ignore_patterns('assets'))
   (package/'public/assets').symlink_to(source/'public/assets',target_is_directory=True)
   (package/'private/sessions').mkdir(parents=True)
   fixture=package/'private/fixture.sqlite';shutil.copy2(before/'private/presentation.sqlite',fixture)
   config={'dsn':'sqlite:'+str(fixture),'app_key':'isolated-contact-key','local_test':True,'business_accounts_enabled':True,'unified_admin_only':True,'account_mail':{'enabled':False}}
   (package/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);');packages.append(package)
  with sqlite3.connect(packages[0]/'private/fixture.sqlite') as db:owner=db.execute('SELECT id FROM accounts ORDER BY id LIMIT 1').fetchone()[0]
  valid={'csrf':'contact-test-token','request_type':'suggestion','name':'Persona Ejemplo','email':'synthetic@example.invalid','message':'Una solicitud sintética para verificar el flujo.','consent':'1'}
  cases=[]
  for locale in ['es','en','pt-br']:
   for mode in ['guest','owner','admin','expired']:cases.append((locale,mode,None,False,200,'read'))
   cases.extend([(locale,'guest',None,True,200,'receipt'),(locale,'guest',{**valid,'website':'bot.invalid'},False,400,'honeypot'),(locale,'guest',{**valid,'request_type':'invalid'},False,400,'invalid'),(locale,'guest',{**valid,'request_type':'invalid','name':'<Test & "demo">','message':'Nombre'},False,400,'escaped'),(locale,'guest',{**valid,'csrf':'invalid'},False,303,'csrf'),(locale,'guest',valid,False,303,'submitted')])
  for locale,mode,post,receipt,status,name in cases:
   outcomes=[]
   for package in packages:
    fixture=package/'private/fixture.sqlite';shutil.copy2(before/'private/presentation.sqlite',fixture)
    payload={'package':str(package),'sessions':str(package/'private/sessions'),'owner':owner,'mode':mode,'locale':locale,'post':post,'receipt':receipt}
    result=subprocess.run(['php','-d','display_errors=stderr','-r',PHP],input=json.dumps(payload),text=True,capture_output=True,env=dict(os.environ,EL_PREPROD_LOCAL='1'),check=True)
    assert not result.stderr.strip(),(locale,mode,name,result.stderr[:700]);response=json.loads(result.stdout);assert response['http']==status,(locale,mode,name,response['http'])
    html=response.pop('html');markup=auth.AuthMarkup(package/'public','/contact.php?lang='+locale);markup.feed(html)
    with sqlite3.connect(fixture) as db:rows=db.execute('SELECT request_type,name,email,message,status,consent_version FROM public_requests ORDER BY id').fetchall()
    outcomes.append((response,markup.events,rows))
   if outcomes[0]!=outcomes[1]:
    for index,(left,right) in enumerate(zip(outcomes[0][1],outcomes[1][1])):
     if left!=right:raise AssertionError((locale,mode,name,'markup',index,outcomes[0][1][max(0,index-3):index+6],outcomes[1][1][max(0,index-3):index+6]))
    raise AssertionError((locale,mode,name,'response/length/rows',outcomes[0][0],outcomes[1][0],len(outcomes[0][1]),len(outcomes[1][1]),outcomes[0][2],outcomes[1][2]))
   checks.append({'locale':locale,'mode':mode,'case':name,'status':status})
 output.write_text(json.dumps({'cases':checks,'database':'disposable SQLite','production_accessed':False},indent=2,ensure_ascii=False)+'\n')
 print('OK:',len(checks),'contact controller comparisons across languages, sessions, receipt, errors and isolated submission.')
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--before',type=Path,required=True);p.add_argument('--after',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args();run(a.before,a.after,a.output)
