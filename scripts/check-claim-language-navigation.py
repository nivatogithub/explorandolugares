#!/usr/bin/env python3
"""Verify locale retention in the real claim controller using disposable data."""
from pathlib import Path
from html.parser import HTMLParser
import json,os,shutil,sqlite3,subprocess,sys,tempfile

ROOT=Path(__file__).resolve().parents[1]
package=Path(sys.argv[1]).resolve()
assert package.is_relative_to(ROOT/'.local')
fixture=package/'private/presentation.sqlite';assert fixture.is_file()
php=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
ini_set('session.save_path',$i['sessions']);session_id(bin2hex(random_bytes(16)));session_start();
$_SESSION=['csrf'=>'claim-test-token'];if($i['owner'])$_SESSION+=['business_account_id'=>1,'business_last_seen'=>time()];
$_GET=['lang'=>$i['locale'],'destino'=>'1','categoria'=>'alimentacion'];if($i['json'])$_GET['search']='1';$_POST=[];
$_SERVER=['REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/business-claim.php?lang='.$i['locale'],'HTTP_HOST'=>'127.0.0.1','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];
ob_start();register_shutdown_function(static function(){while(ob_get_level()>1)ob_end_flush();echo json_encode(['http'=>http_response_code()?:200,'body'=>ob_get_clean()],JSON_THROW_ON_ERROR);});
require $i['package'].'/public/business-claim.php';
'''
class Page(HTMLParser):
 def __init__(self):super().__init__();self.links=[];self.inputs=[]
 def handle_starttag(self,tag,attrs):
  data=dict(attrs)
  if tag=='a':self.links.append(data.get('href',''))
  if tag=='input':self.inputs.append(data)
with tempfile.TemporaryDirectory(prefix='claim-nav-',dir=ROOT/'.local') as tmp:
 root=Path(tmp);shutil.copytree(package/'app',root/'app')
 shutil.copytree(package/'public',root/'public',ignore=shutil.ignore_patterns('assets'))
 (root/'public/assets').symlink_to(package/'public/assets',target_is_directory=True)
 (root/'private/sessions').mkdir(parents=True);shutil.copy2(fixture,root/'private/fixture.sqlite')
 with sqlite3.connect(root/'private/fixture.sqlite') as db:db.execute("UPDATE accounts SET account_type='business' WHERE id=1")
 config={'dsn':'sqlite:'+str(root/'private/fixture.sqlite'),'app_key':'isolated-claim-key-01234567890123456789','registration_enabled':True,'privacy':{'operator':'Synthetic','contact':'test@example.invalid','retention':'Test','retention_en':'Test','retention_pt':'Teste'},'local_test':True,'business_accounts_enabled':True,'unified_admin_only':True}
 (root/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);')
 for locale in ('es','en','pt-br'):
  for owner in (False,True):
   for is_json in (False,True):
    payload={'package':str(root),'sessions':str(root/'private/sessions'),'owner':owner,'locale':locale,'json':is_json}
    result=subprocess.run(['php','-d','display_errors=stderr','-r',php],input=json.dumps(payload),text=True,capture_output=True,env=dict(os.environ,EL_PREPROD_LOCAL='1'),check=True)
    assert not result.stderr.strip(),result.stderr
    response=json.loads(result.stdout);assert response['http']==200
    if is_json:
     assert isinstance(json.loads(response['body'])['results'],list)
     continue
    page=Page();page.feed(response['body']);prefix='' if locale=='es' else '/'+locale
    assert prefix+'/negocios-locales/interes/' in page.links,(locale,'interest')
    assert any(x.get('name')=='lang' and x.get('value')==locale for x in page.inputs),(locale,'filter')
    if owner:assert prefix+'/privacidad/' in page.links,(locale,'privacy')
    else:
     suffix='' if locale=='es' else '?lang='+locale
     for route in ('registro','acceso'):assert '/negocios/'+route+'/'+suffix in page.links,(locale,route)
print('OK: 12 claim responses preserve language in navigation, filters, account links and JSON search.')
