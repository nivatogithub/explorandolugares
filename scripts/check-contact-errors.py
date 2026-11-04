#!/usr/bin/env python3
"""Compare contact failures and writes in isolated fixtures across all three locales."""
from pathlib import Path
from html.parser import HTMLParser
import hashlib,hmac,json,os,shutil,sqlite3,subprocess,sys,tempfile,time
ROOT=Path(__file__).resolve().parents[1]
before,after=(Path(x).resolve() for x in sys.argv[1:3])
assert all(p.is_relative_to(ROOT/'.local') for p in (before,after))
fixture=before/'private/presentation.sqlite';assert fixture.is_file()
catalog=json.loads((after/'app/presentation/contact-errors.json').read_text())
php=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
ini_set('session.save_path',$i['sessions']);session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['csrf'=>'contact-test-token'];
$_GET=['lang'=>$i['locale']];$_POST=$i['post'];$_SERVER=['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/contact.php?lang='.$i['locale'],'HTTP_HOST'=>'127.0.0.1','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];
ob_start();register_shutdown_function(static function(){while(ob_get_level()>1)ob_end_flush();echo json_encode(['status'=>http_response_code()?:200,'html'=>ob_get_clean(),'receipt'=>!empty($_SESSION['public_request_receipt'])],JSON_THROW_ON_ERROR);});require $i['package'].'/public/contact.php';
'''
class Alerts(HTMLParser):
 def __init__(self):super().__init__();self.active=False;self.alert=''
 def handle_starttag(self,tag,attrs):
  if tag=='p' and dict(attrs).get('role')=='alert':self.active=True
 def handle_endtag(self,tag):
  if tag=='p':self.active=False
 def handle_data(self,data):
  if self.active:self.alert+=data
valid={'csrf':'contact-test-token','request_type':'suggestion','name':'Persona Ejemplo','email':'synthetic@example.invalid','message':'Solicitud sintética <segura>','consent':'1'}
cases={'invalid_type':{'request_type':'invalid'},'invalid_name':{'name':'A'},'invalid_email':{'email':'incorrecto'},'invalid_message':{'message':'Corto'},'consent_required':{'consent':'0'},'validation_failed':{'website':'bot.invalid'},'rate_limited':{},'storage_error':{},'success':{}}
with tempfile.TemporaryDirectory(prefix='contact-errors-',dir=ROOT/'.local') as tmp:
 root=Path(tmp);packages=[]
 for label,source in [('before',before),('after',after)]:
  pkg=root/label;shutil.copytree(source/'app',pkg/'app');shutil.copytree(source/'public',pkg/'public',ignore=shutil.ignore_patterns('assets'));(pkg/'public/assets').symlink_to(source/'public/assets',target_is_directory=True);(pkg/'private/sessions').mkdir(parents=True)
  config={'dsn':'sqlite:'+str(pkg/'private/fixture.sqlite'),'app_key':'isolated-contact-key','local_test':True,'business_accounts_enabled':True,'unified_admin_only':True,'account_mail':{'enabled':False}}
  (pkg/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);');packages.append(pkg)
 for locale in ('es','en','pt-br'):
  for case,change in cases.items():
   outcomes=[]
   for label,pkg in zip(('before','after'),packages):
    dbfile=pkg/'private/fixture.sqlite';shutil.copy2(fixture,dbfile)
    with sqlite3.connect(dbfile) as db:
     if case=='storage_error':db.execute('ALTER TABLE public_requests RENAME TO synthetic_requests')
     if case=='rate_limited':
      window=int(time.time())//3600;key=hmac.new(b'isolated-contact-key',f'public_request|127.0.0.1||{window}'.encode(),hashlib.sha256).hexdigest();db.execute('INSERT INTO rate_limits(bucket_key,hits,expires_at) VALUES(?,?,?)',(key,5,(window+1)*3600))
    payload={'package':str(pkg),'sessions':str(pkg/'private/sessions'),'locale':locale,'post':valid|change}
    result=subprocess.run(['php','-d','display_errors=stderr','-r',php],input=json.dumps(payload),text=True,capture_output=True,env=dict(os.environ,EL_PREPROD_LOCAL='1'),check=True)
    assert not result.stderr.strip(),(locale,case,label,result.stderr)
    data=json.loads(result.stdout);page=Alerts();page.feed(data['html']);expected=303 if case=='success' else 400
    assert data['status']==expected,(locale,case,label,data['status'])
    if case!='success':assert page.alert==catalog[case]['es' if label=='before' and not (before/'app/presentation/contact-errors.json').exists() else locale],(locale,case,label,page.alert)
    else:assert data['receipt']
    with sqlite3.connect(dbfile) as db:
     table='synthetic_requests' if case=='storage_error' else 'public_requests';rows=db.execute('SELECT request_type,name,email,message,status,consent_version FROM '+table+' ORDER BY id').fetchall()
    outcomes.append((data['status'],data['receipt'],rows))
   assert outcomes[0]==outcomes[1],(locale,case,outcomes)
   assert len(outcomes[1][2])==(1 if case=='success' else 0),(locale,case,outcomes)
print('OK: 54 controller responses compared; 8 error causes and success in ES/EN/PT-BR, isolated writes unchanged.')
