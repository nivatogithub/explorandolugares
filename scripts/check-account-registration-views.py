#!/usr/bin/env python3
"""Compare account-registration controllers on disposable CLI fixtures, never a new server."""
from contextlib import closing
from importlib.util import spec_from_file_location,module_from_spec
from pathlib import Path
import argparse,hashlib,json,os,shutil,sqlite3,subprocess,tempfile

ROOT=Path(__file__).resolve().parents[1]
spec=spec_from_file_location('auth_views',ROOT/'scripts/check-account-auth-views.py');auth=module_from_spec(spec);spec.loader.exec_module(auth)
ROUTES={'register':'/negocios/registro/','verify-email':'/cuenta/verificar-correo/'}
PHP=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
ini_set('session.save_path',$i['sessions']);session_id(bin2hex(random_bytes(16)));session_start();
$_SESSION=['csrf'=>'signed-auth-token'];
if($i['mode']==='owner'){
 $_SESSION+=['business_account_id'=>$i['owner'],'business_last_seen'=>time()];
 require $i['package'].'/app/AccountSessionSecurity.php';
 $p=new PDO('sqlite:'.$i['fixture']);AccountSessionSecurity::bind($p,$_SESSION,$i['owner']);
}
if($i['remembered']!==null)$_SESSION['account_locale']=$i['remembered'];
if($i['continuation'])$_SESSION['business_registration_return']='/negocios/registro/?tipo=business';
$_GET=$i['get'];$_POST=$i['post']??[];$_FILES=[];
$_SERVER=['REQUEST_METHOD'=>$i['post']===null?'GET':'POST','REQUEST_URI'=>$i['route'],'HTTP_HOST'=>'127.0.0.1','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];
ob_start();register_shutdown_function(static function(){while(ob_get_level()>1)ob_end_flush();$html=ob_get_clean();echo json_encode(['http'=>http_response_code()?:200,'html'=>$html,'authenticated'=>!empty($_SESSION['business_account_id']),'locale'=>$_SESSION['account_locale']??null,'continuation'=>$_SESSION['business_registration_return']??null],JSON_THROW_ON_ERROR);});
require $i['package'].'/public/business/'.$i['page'].'.php';
'''

def run(before,after,database,output,pages):
    before,after,database=before.resolve(),after.resolve(),database.resolve()
    assert all(path.is_relative_to(ROOT/'.local') for path in [before,after,database])
    processing=['registration-choice'] if 'register' in pages else []
    if 'verify-email' in pages:processing+=['verify-email']
    for page in processing:
        prefixes=[]
        for source in [before,after]:
            text=(source/'public/business'/f'{page}.php').read_text()
            markers=["require_once __DIR__.'/../_account-page.php';","require_once dirname(__DIR__).'/_portal-shell.php';"]
            prefixes.append(text[:min(text.index(m) for m in markers if m in text)])
        assert prefixes[0]==prefixes[1],(page,'processing changed')
    # The registration entry point retains business authorization and dispatch unchanged.
    if 'register' in pages:
        legacy="    require_once __DIR__.'/../_account-page.php';account_page(false);\n    require __DIR__.'/business-account-gate.php';"
        explicit="    require_once dirname(__DIR__).'/_portal-shell.php';\n    require_once dirname(__DIR__,2).'/app/AccountAuthPresentation.php';\n    AccountAuthPresentation::render('business-account-gate',account_layout_context(),['error'=>$error],dirname(__DIR__));"
        def registration_processing(package):
            source=(package/'public/business/register.php').read_text().replace(legacy,explicit)
            marker="// A rejected submission must keep the private files visibly recoverable too."
            source=source.split(marker,1)[0]
            return source.replace("require_once __DIR__.'/../_account-page.php';account_page(false);\n",'')
        assert registration_processing(before)==registration_processing(after),'Registration authorization or dispatch changed'

    checks=[]
    with tempfile.TemporaryDirectory(prefix='account-registration-',dir=ROOT/'.local') as temporary:
        root=Path(temporary);seed=root/'seed.sqlite';shutil.copy2(database,seed)
        with closing(sqlite3.connect(seed)) as db:
            owner=db.execute('SELECT id FROM accounts WHERE status="active" AND email_verified_at IS NOT NULL ORDER BY id LIMIT 1').fetchone()[0]
            owner_email=db.execute('SELECT email FROM accounts WHERE id=?',(owner,)).fetchone()[0]
            pending=db.execute('INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status) VALUES("registration-pending","traveler","Cuenta pendiente","pending@example.invalid","synthetic-hash","pending_activation")').lastrowid
            for token,expiry,used in [('d'*64,'2035-01-01 00:00:00',None),('e'*64,'2020-01-01 00:00:00',None),('f'*64,'2035-01-01 00:00:00','2026-01-01 00:00:00')]:
                db.execute('INSERT INTO account_email_verifications(account_id,token_hash,expires_at,used_at,created_at) VALUES(?,?,?,?,"2020-01-01 00:00:00")',(pending,hashlib.sha256(token.encode()).hexdigest(),expiry,used))
            db.commit();initial_count=db.execute('SELECT COUNT(*) FROM accounts').fetchone()[0]
        packages=[]
        for label,source in [('before',before),('after',after)]:
            package=root/label;shutil.copytree(source/'app',package/'app');shutil.copytree(source/'public',package/'public',ignore=shutil.ignore_patterns('assets'))
            (package/'public/assets').symlink_to(source/'public/assets',target_is_directory=True)
            (package/'private/sessions').mkdir(parents=True);fixture=package/'private/fixture.sqlite'
            config={'dsn':'sqlite:'+str(fixture),'app_key':'isolated-registration-key','local_test':True,'business_accounts_enabled':True,'unified_admin_only':True,'account_mail':{'enabled':False},'privacy':{'contact':'synthetic@example.invalid'}}
            (package/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);');packages.append(package)
        def compare(page,name,query=None,post=None,mode='guest',remembered=None,continuation=False,status=200,created=False,verified=False,mail=False):
            results=[]
            for package in packages:
                fixture=package/'private/fixture.sqlite';shutil.copy2(seed,fixture)
                mailbox=package/'private/mailbox.json'
                if mailbox.exists():mailbox.unlink()
                payload={'package':str(package),'fixture':str(fixture),'sessions':str(package/'private/sessions'),'page':page,'route':ROUTES[page],'get':query or {},'post':post,'mode':mode,'remembered':remembered,'owner':owner,'continuation':continuation}
                result=subprocess.run(['php','-d','display_errors=stderr','-r',PHP],input=json.dumps(payload),text=True,capture_output=True,env=dict(os.environ,EL_PREPROD_LOCAL='1'),check=True)
                assert not result.stderr.strip(),(page,name,result.stderr[:500])
                response=json.loads(result.stdout);assert response['http']==status,(page,name,response['http'])
                html=response.pop('html');parsed=auth.AuthMarkup(package/'public',ROUTES[page]);parsed.feed(html)
                with closing(sqlite3.connect(fixture)) as db:
                    count=db.execute('SELECT COUNT(*) FROM accounts').fetchone()[0];assert count==initial_count+int(created),(page,name,'unexpected account write')
                    if created:
                        row=db.execute('SELECT id,display_name,email,password_hash,status FROM accounts WHERE email="new@example.invalid"').fetchone();assert row and row[1]==post['display_name'] and row[4]=='pending_activation'
                        assert db.execute('SELECT COUNT(*) FROM account_consents WHERE account_id=?',(row[0],)).fetchone()[0]==1
                        residence=db.execute('SELECT country_code,city_code,city_name FROM account_residences WHERE account_id=?',(row[0],)).fetchone();assert residence[0]==post['residence_country_code']
                        assert residence[1]==('05368' if residence[0]=='CO' else '')
                        valid=subprocess.check_output(['php','-r','$i=json_decode(stream_get_contents(STDIN),true);echo password_verify($i["password"],$i["hash"])?"yes":"no";'],input=json.dumps({'password':post['password'],'hash':row[3]}).encode()).decode();assert valid=='yes'
                    state=db.execute('SELECT status,email_verified_at IS NOT NULL FROM accounts WHERE id=?',(pending,)).fetchone();assert state==('active',1) if verified else state==('pending_activation',0),(page,name,state)
                    messages=json.loads(mailbox.read_text()) if mailbox.exists() else [];assert bool(messages)==mail,(page,name,'mailbox outcome')
                    for message in messages:
                        token=message['url'].split('token=')[-1];table='account_email_verifications' if message['kind']=='verification' else 'account_password_resets'
                        assert db.execute('SELECT COUNT(*) FROM '+table+' WHERE token_hash=? AND used_at IS NULL',(hashlib.sha256(token.encode()).hexdigest(),)).fetchone()[0]==1
                results.append((response,parsed.events,[(m['to'],m['kind']) for m in messages]))
            assert results[0]==results[1],(page,name,next(((a,b) for a,b in zip(results[0][1],results[1][1]) if a!=b),'response/length differs'))
            checks.append({'page':page,'case':name,'http':status,'presentation_and_outcomes_preserved':True})
        base={'csrf':'signed-auth-token','account_type':'traveler','display_name':'Persona <prueba> & "segura"','email':'new@example.invalid','password':'Registration-test-123!','password_confirm':'Registration-test-123!','consent':'1','residence_country_code':'CO','residence_region_code':'05','residence_city_code':'05368'}
        for page in pages:
            for locale in ['es','en','pt-br']:
                q={'lang':locale};compare(page,'default/'+locale,q);compare(page,'remembered/'+locale,remembered=locale)
                compare(page,'csrf/'+locale,q,{'csrf':'invalid','account_type':'traveler'},status=303)
                if page=='register':
                    compare(page,'traveler-alias/'+locale,q|{'tipo':'traveler'})
                    compare(page,'unknown-type/'+locale,q|{'tipo':'unknown'})
                    compare(page,'authenticated/'+locale,q,mode='owner',status=303)
                    compare(page,'business-gate/'+locale,q|{'tipo':'business'})
                    compare(page,'business-guest-search/'+locale,q|{'tipo':'business','search':'1'},status=303)
                    compare(page,'business-guest-post/'+locale,q,{'account_type':'business','csrf':'signed-auth-token'},status=303)
                    for name,changes in [('invalid-country',{'residence_country_code':'XX'}),('cross-region',{'residence_region_code':'25'}),('name',{'display_name':'x'}),('email',{'email':'bad"<email>'}),('password',{'password':'short','password_confirm':'short'}),('confirmation',{'password_confirm':'different'}),('consent',{'consent':''}),('foreign-invalid',{'residence_country_code':'US','residence_region_code':'','residence_region_name':'<region> & "text"','residence_city_name':''})]:
                        compare(page,name+'/'+locale,q,base|changes,status=400)
                    compare(page,'created-colombia/'+locale,q,base,status=303,created=True,mail=True)
                    compare(page,'created-foreign/'+locale,q,base|{'residence_country_code':'US','residence_region_code':'','residence_region_name':'Florida','residence_city_name':'Miami'},status=303,created=True,mail=True)
                    compare(page,'existing-active/'+locale,q,base|{'email':owner_email},status=303,mail=True)
                    compare(page,'existing-pending/'+locale,q,base|{'email':'pending@example.invalid'},status=303,mail=True)
                else:
                    compare(page,'sent/'+locale,q|{'enviado':'1'})
                    compare(page,'invalid-token/'+locale,q|{'token':'invalid'},status=400)
                    compare(page,'expired-token/'+locale,q|{'token':'e'*64},status=400)
                    compare(page,'used-token/'+locale,q|{'token':'f'*64},status=400)
                    compare(page,'verified/'+locale,q|{'token':'d'*64},verified=True)
                    compare(page,'verified-business/'+locale,q|{'token':'d'*64},continuation=True,verified=True)
                    compare(page,'invalid-email/'+locale,q,{'csrf':'signed-auth-token','email':'invalid'},status=400)
                    compare(page,'unknown-email/'+locale,q,{'csrf':'signed-auth-token','email':'unknown@example.invalid'})
                    compare(page,'resend/'+locale,q,{'csrf':'signed-auth-token','email':'pending@example.invalid'},mail=True)
            compare(page,'invalid-locale',{'lang':'invalid'})
        component_checks=[]
        residence_php=r"""
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
function db(){return null;}function esc($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$_POST=$i['post'];$residenceValues=$i['stored'];require $i['package'].'/public/business/residence-fields.php';
"""
        for name,post,stored in [
            ('default',{},{}),('stored-colombia',{},dict(country_code='CO',region_code='05',city_code='05368')),
            ('post-overrides-stored',dict(residence_country_code='US',residence_region_name='Florida',residence_city_name='Miami'),dict(country_code='CO',region_code='05',city_code='05368')),
            ('stored-foreign',{},dict(country_code='BR',region_name='São Paulo',city_name='São Paulo')),
            ('numeric-region-code',dict(residence_country_code='CO',residence_region_code='18'),{}),
            ('escaped-values',dict(residence_country_code='US',residence_region_name='<region> & "safe"',residence_city_name='<script>alert(1)</script>'),{}),
            ('invalid-country',dict(residence_country_code='XX'),{}),('empty-country',dict(residence_country_code=''),{}),
        ]:
            markup=[]
            for package in [before,after]:
                result=subprocess.run(['php','-d','display_errors=stderr','-r',residence_php],input=json.dumps({'package':str(package),'post':post,'stored':stored}),text=True,capture_output=True,check=True)
                assert not result.stderr.strip(),(name,result.stderr[:500])
                parser=auth.AuthMarkup(package/'public',ROUTES['register']);parser.feed(result.stdout);markup.append(parser.events)
            assert markup[0]==markup[1],(name,'shared residence markup changed')
            component_checks.append({'case':name,'shared_residence_fields_preserved':True})
        output.parent.mkdir(parents=True,exist_ok=True);output.write_text(json.dumps({'production_accessed':False,'isolated_cli_sqlite_fixtures':True,'real_mail_sent':False,'processing_identical':processing,'shared_residence_checks':component_checks,'checks':checks},indent=2)+'\n')
    print(f'OK: {len(checks)} registration comparisons preserve presentation, CSRF, account, residence, consent and verification outcomes; {len(component_checks)} shared residence comparisons.')

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__)
    for key in ['before','after','database','output']:p.add_argument('--'+key,type=Path,required=True)
    p.add_argument('--pages',nargs='+',choices=list(ROUTES),default=list(ROUTES));run(**vars(p.parse_args()))
