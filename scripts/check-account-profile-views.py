#!/usr/bin/env python3
"""Compare the real profile controller on disposable CLI data and file-backed sessions."""
from contextlib import closing
from importlib.util import spec_from_file_location,module_from_spec
from pathlib import Path
from urllib.parse import urljoin,urlsplit,parse_qs
import argparse,hashlib,json,os,shutil,sqlite3,subprocess,tempfile

ROOT=Path(__file__).resolve().parents[1]
spec=spec_from_file_location('auth_views',ROOT/'scripts/check-account-auth-views.py');auth=module_from_spec(spec);spec.loader.exec_module(auth)
ROUTE='/negocios/portal/'
PHP=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
ini_set('session.save_path',$i['sessions']);session_id(bin2hex(random_bytes(16)));session_start();$initialSession=session_id();
$_SESSION=['csrf'=>'signed-profile-token'];
if($i['mode']!=='guest'){
 $id=$i['mode']==='admin'?$i['admin']:$i['owner'];
 $_SESSION+=['business_account_id'=>$id,'business_last_seen'=>$i['mode']==='expired'?time()-3600:time()];
 require $i['package'].'/app/AccountSessionSecurity.php';
 $p=new PDO('sqlite:'.$i['fixture']);AccountSessionSecurity::bind($p,$_SESSION,$id);
 if($i['mode']==='invalid_proof')$_SESSION['account_credential_proof']='invalid-profile-proof';
 if($i['mode']==='admin')$_SESSION+=['admin'=>true,'admin_account_id'=>$id,'last_seen'=>time()];
}
if($i['remembered']!==null)$_SESSION['account_locale']=$i['remembered'];
// The HTTP bootstrap skips this guard under CLI; invoke the same validator explicitly.
require_once $i['package'].'/app/AccountSessionValidator.php';
$validationPdo=new PDO('sqlite:'.$i['fixture']);AccountSessionValidator::synchronize(static fn():PDO=>$validationPdo,$_SESSION,true);
$_GET=$i['get'];$_POST=$i['post']??[];$_FILES=[];
$_SERVER=['REQUEST_METHOD'=>$i['post']===null?'GET':'POST','REQUEST_URI'=>$i['route'],'HTTP_HOST'=>'127.0.0.1','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];
ob_start();register_shutdown_function(static function()use($initialSession){while(ob_get_level()>1)ob_end_flush();echo json_encode(['http'=>http_response_code()?:200,'html'=>ob_get_clean(),'authenticated'=>!empty($_SESSION['business_account_id']),'credential_proof'=>isset($_SESSION['account_credential_proof']),'locale'=>$_SESSION['account_locale']??null,'space'=>$_SESSION['account_space']??null,'session_regenerated'=>session_id()!==$initialSession],JSON_THROW_ON_ERROR);});
require $i['package'].'/public/business/dashboard.php';
'''

class ProfileMarkup(auth.AuthMarkup):
    def __init__(self,public):super().__init__(public,ROUTE);self.resources=[]
    def handle_starttag(self,tag,attrs):
        for key,value in attrs:
            if key not in ['href','src'] or not value:continue
            url=urlsplit(urljoin(ROUTE,value))
            if not url.netloc and url.path.startswith('/assets/'):
                digest=hashlib.sha256((self.public/url.path.lstrip('/')).read_bytes()).hexdigest()
                self.resources.append((url.path,digest,parse_qs(url.query).get('v')))
        super().handle_starttag(tag,attrs)

def run(before,after,database,output):
    before,after,database=before.resolve(),after.resolve(),database.resolve()
    assert all(p.is_relative_to(ROOT/'.local') for p in [before,after,database])
    prefixes=[]
    for package in [before,after]:
        source=(package/'public/business/traveler.php').read_text()
        markers=["require_once __DIR__.'/../_account-page.php';","require_once dirname(__DIR__).'/_portal-shell.php';"]
        prefixes.append(source[:min(source.index(m) for m in markers if m in source)])
    assert prefixes[0]==prefixes[1],'Profile actions changed'
    dispatch=[]
    for package in [before,after]:
        source=(package/'public/business/dashboard.php').read_text()
        boundary="if($requestedTab==='personal'){require __DIR__.'/traveler.php';exit;}"
        source=source[:source.index(boundary)+len(boundary)]
        for dependency in ["require_once dirname(__DIR__).'/admin/_asset.php';\n","require_once __DIR__.'/_workspace-icon.php';\n"]:source=source.replace(dependency,'')
        dispatch.append(source)
    assert dispatch[0]==dispatch[1],'Dispatch/authorization changed'
    old_translator='account_page(' in (before/'public/business/traveler.php').read_text()
    checks=[];data_checks=[]
    with tempfile.TemporaryDirectory(prefix='account-profile-',dir=ROOT/'.local') as temporary:
        root=Path(temporary);seed=root/'seed.sqlite';shutil.copy2(database,seed)
        password='Profile-regression-482!'
        password_hash=subprocess.check_output(['php','-r','echo password_hash(stream_get_contents(STDIN),PASSWORD_DEFAULT);'],input=password.encode()).decode()
        with closing(sqlite3.connect(seed)) as db:
            admin=db.execute('SELECT account_id FROM account_roles WHERE role="administrator" ORDER BY account_id LIMIT 1').fetchone()[0]
            db.execute('UPDATE accounts SET password_hash=? WHERE id=?',(password_hash,admin))
            db.execute('DELETE FROM account_roles WHERE role="administrator" AND account_id<>?',(admin,))
            owner=db.execute('INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status,email_verified_at) VALUES("profile-owner","traveler",?,?,?,"active",CURRENT_TIMESTAMP)',('Persona <prueba> & "segura"','profile@example.invalid',password_hash)).lastrowid
            db.execute('INSERT INTO account_consents(account_id,consent_version) VALUES(?,"traveler-registration-v1")',(owner,))
            db.execute('INSERT INTO account_residences(account_id,country_code,region_code,region_name,city_code,city_name) VALUES(?,"CO","05","ANTIOQUIA","05368","JERICÓ")',(owner,))
            for table in ['account_password_resets','account_email_verifications','account_reactivations']:
                db.execute('INSERT INTO '+table+'(account_id,token_hash,expires_at) VALUES(?,?,"2035-01-01 00:00:00")',(owner,hashlib.sha256(table.encode()).hexdigest()))
            place=db.execute('SELECT id FROM destinations ORDER BY id LIMIT 1').fetchone()[0]
            business=db.execute('INSERT INTO businesses(public_id,destination_id,category_code,slug,information_status,record_origin,publication_status) VALUES("profile-business",?,"hospedaje","profile-business","VERIFICADO","owner_submitted","published")',(place,)).lastrowid
            for locale in ['es','en','pt-BR']:db.execute('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,"Negocio de prueba","","")',(business,locale))
            db.execute('INSERT INTO business_subscriptions(business_id,plan_code,status,starts_at) VALUES(?,"free","active",CURRENT_TIMESTAMP)',(business,));db.commit()
        packages=[]
        for label,source in [('before',before),('after',after)]:
            package=root/label;shutil.copytree(source/'app',package/'app');shutil.copytree(source/'public',package/'public',ignore=shutil.ignore_patterns('assets'))
            (package/'public/assets').symlink_to(source/'public/assets',target_is_directory=True)
            (package/'private/sessions').mkdir(parents=True);fixture=package/'private/fixture.sqlite'
            config={'dsn':'sqlite:'+str(fixture),'app_key':'isolated-profile-key','local_test':True,'business_accounts_enabled':True,'unified_admin_only':True,'account_mail':{'enabled':False}}
            (package/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);');packages.append(package)

        def compare(name,query=None,post=None,mode='owner',member=False,remembered=None,status=200,saved=None,closed=False,stored_name=None):
            results=[]
            for index,package in enumerate(packages):
                fixture=package/'private/fixture.sqlite';shutil.copy2(seed,fixture);selected=admin if mode=='admin' else owner
                with closing(sqlite3.connect(fixture)) as db:
                    if member:db.execute('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,"owner","active")',(selected,business))
                    if mode=='disabled':db.execute('UPDATE accounts SET status="disabled" WHERE id=?',(owner,))
                    if stored_name is not None:db.execute('UPDATE accounts SET display_name=? WHERE id=?',(stored_name,owner))
                    db.commit()
                    protected={table:list(db.execute('SELECT * FROM '+table+' ORDER BY 1,2')) for table in ['businesses','business_subscriptions','business_account_memberships','account_consents']}
                    initial_accounts=list(db.execute('SELECT id,public_id,account_type,display_name,email FROM accounts ORDER BY id'))
                    initial_residences=list(db.execute('SELECT * FROM account_residences ORDER BY account_id'))
                    other_residences=list(db.execute('SELECT * FROM account_residences WHERE account_id<>? ORDER BY account_id',(selected,)))
                payload={'package':str(package),'fixture':str(fixture),'sessions':str(package/'private/sessions'),'owner':owner,'admin':admin,'mode':mode,'remembered':remembered,'get':{'tab':'personal',**(query or {})},'post':post,'route':ROUTE+'?tab=personal'}
                result=subprocess.run(['php','-d','display_errors=stderr','-r',PHP],input=json.dumps(payload),text=True,capture_output=True,env=dict(os.environ,EL_PREPROD_LOCAL='1'),check=True)
                assert not result.stderr.strip(),(name,result.stderr[:700])
                response=json.loads(result.stdout);assert response['http']==status,(name,response['http'])
                html=response.pop('html')
                if stored_name is not None:
                    translated={'en':'Name','pt-br':'Nome'}[query['lang']]
                    assert '<dd>'+(translated if index==0 and old_translator else 'Nombre')+'</dd>' in html,(name,'stored name behavior')
                    if index==0 and old_translator:
                        assert html.count('<dd>'+translated+'</dd>')==1
                        html=html.replace('<dd>'+translated+'</dd>','<dd>Nombre</dd>')
                markup=ProfileMarkup(package/'public');markup.feed(html)
                with closing(sqlite3.connect(fixture)) as db:
                    assert list(db.execute('SELECT id,public_id,account_type,display_name,email FROM accounts ORDER BY id'))==initial_accounts,(name,'account identity/data changed')
                    for table,rows in protected.items():assert list(db.execute('SELECT * FROM '+table+' ORDER BY 1,2'))==rows,(name,table,'protected data changed')
                    row=db.execute('SELECT password_hash,status FROM accounts WHERE id=?',(selected,)).fetchone()
                    if closed:
                        assert row[1]=='disabled' and not response['authenticated'] and not response['credential_proof'] and response['session_regenerated'],(name,'closure/session')
                        assert db.execute('SELECT event_type FROM account_access_events WHERE account_id=? ORDER BY id DESC LIMIT 1',(selected,)).fetchone()[0]=='closed'
                        for table in ['account_password_resets','account_email_verifications','account_reactivations']:assert db.execute('SELECT COUNT(*) FROM '+table+' WHERE account_id=? AND used_at IS NULL',(selected,)).fetchone()[0]==0
                        valid=subprocess.check_output(['php','-r','$i=json_decode(stream_get_contents(STDIN),true);echo password_verify($i["password"],$i["hash"])?"yes":"no";'],input=json.dumps({'password':password,'hash':row[0]}).encode()).decode();assert valid=='yes'
                    else:
                        assert row[0]==password_hash,(name,'unexpected credential change')
                        assert row[1]==('disabled' if mode=='disabled' else 'active'),(name,'unexpected account status')
                    if saved:
                        residence=db.execute('SELECT country_code,region_code,region_name,city_code,city_name FROM account_residences WHERE account_id=?',(selected,)).fetchone();assert residence==saved,(name,residence)
                        assert list(db.execute('SELECT * FROM account_residences WHERE account_id<>? ORDER BY account_id',(selected,)))==other_residences,(name,'foreign account residence changed')
                    else:assert list(db.execute('SELECT * FROM account_residences ORDER BY account_id'))==initial_residences,(name,'unexpected residence write')
                results.append((response,markup.events,markup.resources))
            assert results[0]==results[1],(name,next(((a,b) for a,b in zip(results[0][1],results[1][1]) if a!=b),'response/resource/length differs'))
            item={'case':name,'http':status,'profile_structure_fields_resources_sessions_and_data_preserved':True}
            (data_checks if stored_name is not None else checks).append(item)

        residence={'csrf':'signed-profile-token','action':'save_residence','residence_country_code':'CO','residence_region_code':'05','residence_city_code':'05368'}
        closure={'csrf':'signed-profile-token','action':'close_account','current_password':password,'confirm_closure':'1'}
        for locale in ['es','en','pt-br']:
            q={'lang':locale}
            compare('default/'+locale,q);compare('businesses/'+locale,q,member=True)
            compare('administrator/'+locale,q,mode='admin');compare('administrator-businesses/'+locale,q,mode='admin',member=True)
            compare('saved-notice/'+locale,q|{'ubicacion':'guardada'});compare('remembered/'+locale,remembered=locale)
            compare('invalid-action/'+locale,q,{'csrf':'signed-profile-token','action':'unknown'},status=400)
            compare('invalid-country/'+locale,q,residence|{'residence_country_code':'XX','residence_region_name':'<region> & "safe"','residence_city_name':'<script>alert(1)</script>'},status=400)
            compare('cross-region/'+locale,q,residence|{'residence_region_code':'25'},status=400)
            compare('invalid-foreign/'+locale,q,residence|{'residence_country_code':'US','residence_region_code':'','residence_region_name':'Florida','residence_city_name':''},status=400)
            compare('save-colombia/'+locale,q,residence,status=303,saved=('CO','05','Antioquia','05368','JERICÓ'))
            compare('save-spoofed-account/'+locale,q,residence|{'account_id':str(admin)},status=303,saved=('CO','05','Antioquia','05368','JERICÓ'))
            compare('save-foreign/'+locale,q,residence|{'residence_country_code':'US','residence_region_code':'','residence_region_name':'Florida','residence_city_name':'Miami'},status=303,saved=('US','','Florida','','Miami'))
            compare('csrf-residence/'+locale,q,residence|{'csrf':'invalid'},status=303)
            compare('csrf-closure/'+locale,q,closure|{'csrf':'invalid'},status=303)
            compare('closure-unconfirmed/'+locale,q,closure|{'confirm_closure':''},status=400)
            compare('closure-wrong-password/'+locale,q,closure|{'current_password':'wrong'},status=400)
            compare('closed/'+locale,q,closure,status=303,closed=True)
            compare('closed-business-owner/'+locale,q,closure,status=303,member=True,closed=True)
            compare('final-administrator/'+locale,q,closure,status=400,mode='admin')
            for mode in ['guest','expired','invalid_proof','disabled']:compare(mode+'/'+locale,q,mode=mode,status=303)
        compare('invalid-locale',{'lang':'invalid'});compare('remembered-invalid',remembered='invalid')
        for locale in ['en','pt-br']:compare('stored-name/'+locale,{'lang':locale},stored_name='Nombre')
        output.parent.mkdir(parents=True,exist_ok=True);output.write_text(json.dumps({'production_accessed':False,'isolated_cli_sqlite_fixtures':True,'real_mail_sent':False,'controller_processing_identical':True,'dashboard_dispatch_identical':True,'http_session_validator_invoked_explicitly_for_cli':True,'checks':checks,'stored_data_checks':data_checks},indent=2)+'\n')
    print(f'OK: {len(checks)} profile-controller comparisons and {len(data_checks)} stored-name regressions; residence, closure, permissions, sessions, identities and subscriptions preserved.')

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__)
    for name in ['before','after','database','output']:p.add_argument('--'+name,type=Path,required=True)
    run(**vars(p.parse_args()))
