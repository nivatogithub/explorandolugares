#!/usr/bin/env python3
"""Compare account controllers with disposable data and sessions, without another server."""
from pathlib import Path
from contextlib import closing
from html.parser import HTMLParser
from urllib.parse import urljoin,urlsplit,parse_qs
import argparse,hashlib,json,os,shutil,sqlite3,subprocess,tempfile

ROOT=Path(__file__).resolve().parents[1]
ROUTES={'login':'/negocios/acceso/','recover':'/negocios/recuperar-clave/','password':'/negocios/cambiar-clave/'}
PHP=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
ini_set('session.save_path',$i['sessions']);session_id(bin2hex(random_bytes(16)));session_start();
$_SESSION=['csrf'=>'signed-auth-token'];
if($i['mode']==='legacy')$_SESSION+=['admin'=>true,'last_seen'=>time()];
if($i['mode']==='admin')$_SESSION+=['admin'=>true,'admin_account_id'=>$i['admin'],'business_account_id'=>$i['admin'],'last_seen'=>time(),'business_last_seen'=>time()];
if($i['remembered']!==null)$_SESSION['account_locale']=$i['remembered'];
$_GET=$i['get'];$_POST=$i['post']??[];$_FILES=[];
$_SERVER=['REQUEST_METHOD'=>$i['post']===null?'GET':'POST','REQUEST_URI'=>$i['route'],'HTTP_HOST'=>'127.0.0.1','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];
ob_start();register_shutdown_function(static function(){while(ob_get_level()>1)ob_end_flush();$html=ob_get_clean();echo json_encode(['http'=>http_response_code()?:200,'html'=>$html,'authenticated'=>!empty($_SESSION['business_account_id']),'admin'=>!empty($_SESSION['admin']),'locale'=>$_SESSION['account_locale']??null],JSON_THROW_ON_ERROR);});
require $i['package'].'/public/business/'.$i['page'].'.php';
'''

class AuthMarkup(HTMLParser):
    def __init__(self,public,route):
        super().__init__(convert_charrefs=True);self.public=public;self.route=route;self.events=[]
    def handle_starttag(self,tag,attrs):
        values=[]
        for key,value in attrs:
            if key in ['open','required','checked','selected','disabled','hidden']:value='present'
            elif value is None:value=''
            if key in ['href','src','action'] and value:
                url=urlsplit(urljoin(self.route,value));value=url.geturl()
                if not url.netloc and url.path.startswith('/assets/'):
                    asset=self.public/url.path.lstrip('/');assert asset.is_file(),value
                    digest=hashlib.sha256(asset.read_bytes()).hexdigest();version=parse_qs(url.query).get('v')
                    if version and not version[0].isdigit() and (url.path,version[0])!=('/assets/admin.css','business-representation-1'):assert digest.startswith(version[0]),value
                    value='resource:'+url.path+':'+digest
            values.append((key,value))
        self.events.append(('start',tag,sorted(values)))
    def handle_startendtag(self,tag,attrs):self.handle_starttag(tag,attrs)
    def handle_endtag(self,tag):self.events.append(('end',tag))
    def handle_data(self,data):
        if data.strip():self.events.append(('text',data.strip()))
    def handle_decl(self,decl):self.events.append(('decl',decl.lower()))

def run(before,after,database,output,pages):
    before,after,database=before.resolve(),after.resolve(),database.resolve()
    assert all(p.is_relative_to(ROOT/'.local') for p in [before,after,database])
    for page in pages:
        processing=[]
        for package in [before,after]:
            source=(package/'public/business'/f'{page}.php').read_text()
            markers=["require_once __DIR__.'/../_account-page.php';", "require_once dirname(__DIR__).'/_portal-shell.php';"]
            boundaries=[source.index(marker) for marker in markers if marker in source];assert boundaries,(page,'missing presentation boundary')
            processing.append(source[:min(boundaries)])
        assert processing[0]==processing[1],(page,'authentication/token/redirect processing changed')
    checks=[]
    with tempfile.TemporaryDirectory(prefix='account-auth-',dir=ROOT/'.local') as temporary:
        root=Path(temporary);seed=root/'seed.sqlite';shutil.copy2(database,seed)
        password='Synthetic-auth-layout-482!'
        password_hash=subprocess.check_output(['php','-r','echo password_hash(stream_get_contents(STDIN),PASSWORD_DEFAULT);'],input=password.encode()).decode()
        with closing(sqlite3.connect(seed)) as db:
            owner=db.execute('SELECT id FROM accounts ORDER BY id LIMIT 1').fetchone()[0]
            admin=db.execute('SELECT account_id FROM account_roles WHERE role="administrator" LIMIT 1').fetchone()[0]
            db.execute('UPDATE accounts SET password_hash=?',(password_hash,))
            db.execute('INSERT INTO account_password_resets(account_id,token_hash,created_by,expires_at) VALUES(?,?,?,?)',(owner,hashlib.sha256(b'a'*64).hexdigest(),'synthetic-auth','2035-01-01 00:00:00'))
            db.execute('INSERT INTO account_password_resets(account_id,token_hash,created_by,expires_at) VALUES(?,?,?,?)',(owner,hashlib.sha256(b'b'*64).hexdigest(),'synthetic-auth','2020-01-01 00:00:00'))
            owner_email=db.execute('SELECT email FROM accounts WHERE id=?',(owner,)).fetchone()[0]
            admin_email=db.execute('SELECT email FROM accounts WHERE id=?',(admin,)).fetchone()[0]
            closed=db.execute('INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status,email_verified_at) VALUES("auth-closed-fixture","traveler","Cuenta sintética cerrada","closed@example.invalid",?,"disabled",CURRENT_TIMESTAMP)',(password_hash,)).lastrowid
            db.execute('INSERT INTO account_access_events(account_id,event_type,actor_account_id,reason) VALUES(?,"closed",?,"Fixture aislada")',(closed,owner))
            db.execute('INSERT INTO account_reactivations(account_id,token_hash,expires_at) VALUES(?,?,?)',(closed,hashlib.sha256(b'c'*64).hexdigest(),'2035-01-01 00:00:00'))
            db.commit()
        packages=[]
        for label,source in [('before',before),('after',after)]:
            package=root/label;shutil.copytree(source/'app',package/'app')
            shutil.copytree(source/'public',package/'public',ignore=shutil.ignore_patterns('assets'))
            (package/'public/assets').symlink_to(source/'public/assets',target_is_directory=True)
            (package/'private/sessions').mkdir(parents=True);fixture=package/'private/fixture.sqlite'
            config={'dsn':'sqlite:'+str(fixture),'app_key':'isolated-auth-layout-key','local_test':True,'business_accounts_enabled':True,'unified_admin_only':True,'account_mail':{'enabled':False},'privacy':{'contact':'synthetic@example.invalid'}}
            (package/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);')
            packages.append(package)

        def compare(page,name,query=None,post=None,mode='guest',remembered=None,local=True,status=200):
            results=[]
            for package in packages:
                fixture=package/'private/fixture.sqlite';shutil.copy2(seed,fixture)
                env=dict(os.environ,EL_PREPROD_LOCAL='1' if local else '0')
                payload={'package':str(package),'sessions':str(package/'private/sessions'),'page':page,'route':ROUTES[page],'get':query or {},'post':post,'mode':mode,'remembered':remembered,'admin':admin}
                result=subprocess.run(['php','-d','display_errors=stderr','-r',PHP],input=json.dumps(payload),text=True,capture_output=True,env=env,check=True)
                assert not result.stderr.strip(),(page,name,result.stderr[:500])
                response=json.loads(result.stdout);assert response['http']==status,(page,name,response['http'])
                parsed=AuthMarkup(package/'public',ROUTES[page]);parsed.feed(response.pop('html'))
                with closing(sqlite3.connect(fixture)) as db:
                    row=db.execute('SELECT password_hash,status FROM accounts WHERE id=?',(owner,)).fetchone()
                    if page=='password' and name.startswith('saved'):
                        valid=subprocess.check_output(['php','-r','$i=json_decode(stream_get_contents(STDIN),true);echo password_verify($i["password"],$i["hash"])?"yes":"no";'],input=json.dumps({'password':'Updated-auth-layout-834!','hash':row[0]}).encode()).decode()
                        assert valid=='yes'
                        assert db.execute('SELECT COUNT(*) FROM account_password_resets WHERE account_id=? AND used_at IS NULL',(owner,)).fetchone()[0]==0
                    else:assert row[0]==password_hash,'Unexpected password write'
                    if page=='password' and name.startswith('reactivation-saved'):
                        closed_row=db.execute('SELECT password_hash,status FROM accounts WHERE id=?',(closed,)).fetchone();assert closed_row[1]=='active'
                        verified=subprocess.check_output(['php','-r','$i=json_decode(stream_get_contents(STDIN),true);echo password_verify($i["password"],$i["hash"])?"yes":"no";'],input=json.dumps({'password':'Updated-auth-layout-834!','hash':closed_row[0]}).encode()).decode();assert verified=='yes'
                        assert db.execute('SELECT COUNT(*) FROM account_reactivations WHERE account_id=? AND used_at IS NULL',(closed,)).fetchone()[0]==0
                        assert db.execute('SELECT event_type FROM account_access_events WHERE account_id=? ORDER BY id DESC LIMIT 1',(closed,)).fetchone()[0]=='reactivated'
                results.append((response,parsed.events))
            assert results[0]==results[1],(page,name,next(((a,b) for a,b in zip(results[0][1],results[1][1]) if a!=b),'response/length differs'))
            checks.append({'page':page,'case':name,'http':status,'fields_resources_copy_session_and_outcome_preserved':True})

        for page in pages:
            for locale in ['es','en','pt-br']:
                compare(page,'default/'+locale,{'lang':locale})
                compare(page,'remembered/'+locale,remembered=locale)
                compare(page,'legacy/'+locale,{'lang':locale},mode='legacy')
                compare(page,'admin/'+locale,{'lang':locale},mode='admin')
                compare(page,'csrf/'+locale,{'lang':locale},{'csrf':'invalid'},status=303)
                if page=='login':
                    compare(page,'closed/'+locale,{'lang':locale,'cuenta':'cerrada'})
                    compare(page,'invalid/'+locale,{'lang':locale},{'csrf':'signed-auth-token','email':'<script>"@invalid','password':'wrong'},status=400)
                    for name,email in [('owner',owner_email),('administrator',admin_email)]:compare(page,'success-'+name+'/'+locale,{'lang':locale},{'csrf':'signed-auth-token','email':email,'password':password},status=303)
                if page=='recover':
                    compare(page,'sent/'+locale,{'lang':locale,'solicitado':'1'})
                    compare(page,'unavailable/'+locale,{'lang':locale},local=False)
                    compare(page,'invalid-email/'+locale,{'lang':locale},{'csrf':'signed-auth-token','email':'invalid'},status=400)
                    compare(page,'unknown-email/'+locale,{'lang':locale},{'csrf':'signed-auth-token','email':'nobody@example.invalid'})
                if page=='password':
                    compare(page,'valid/'+locale,{'lang':locale,'token':'a'*64})
                    compare(page,'expired/'+locale,{'lang':locale,'token':'b'*64})
                    compare(page,'reactivation-invalid/'+locale,{'lang':locale,'reactivacion':'1','token':'x'})
                    compare(page,'reactivation-valid/'+locale,{'lang':locale,'reactivacion':'1','token':'c'*64})
                    compare(page,'reactivation-unconfirmed/'+locale,{'lang':locale},{'csrf':'signed-auth-token','reactivacion':'1','token':'c'*64,'password':'Updated-auth-layout-834!','password_confirm':'Updated-auth-layout-834!'},status=400)
                    compare(page,'reactivation-saved/'+locale,{'lang':locale},{'csrf':'signed-auth-token','reactivacion':'1','confirm_reactivation':'1','token':'c'*64,'password':'Updated-auth-layout-834!','password_confirm':'Updated-auth-layout-834!'})
                    compare(page,'mismatch/'+locale,{'lang':locale},{'csrf':'signed-auth-token','token':'a'*64,'password':'first','password_confirm':'second'},status=400)
                    compare(page,'saved/'+locale,{'lang':locale},{'csrf':'signed-auth-token','token':'a'*64,'password':'Updated-auth-layout-834!','password_confirm':'Updated-auth-layout-834!'})
            compare(page,'invalid-locale',{'lang':'invalid'})
        output.parent.mkdir(parents=True,exist_ok=True);output.write_text(json.dumps({'production_accessed':False,'disposable_cli_data_and_sessions':True,'real_mail_sent':False,'controller_processing_prefix_identical':pages,'checks':checks},indent=2)+'\n')
    print(f'OK: {len(checks)} account-controller comparisons preserve dialogs, locales, fields, escaping, errors, sessions and token outcomes.')

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__)
    for name in ['before','after','database','output']:p.add_argument('--'+name,type=Path,required=True)
    p.add_argument('--pages',nargs='+',choices=list(ROUTES),default=['login','recover','password'])
    run(**vars(p.parse_args()))
