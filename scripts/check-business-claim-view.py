#!/usr/bin/env python3
"""Compare the real claim controller against a disposable, identical database."""
from pathlib import Path
from importlib.util import spec_from_file_location, module_from_spec
import json, os, shutil, sqlite3, subprocess, tempfile

ROOT = Path(__file__).resolve().parents[1]
spec = spec_from_file_location('auth', ROOT/'scripts/check-account-auth-views.py')
auth = module_from_spec(spec)
spec.loader.exec_module(auth)
BEFORE = ROOT/'.local/architecture-claim-selector-candidate'
AFTER = ROOT/'.local/architecture-claim-writer-final'
PHP = r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
ini_set('session.save_path',$i['sessions']);session_id(bin2hex(random_bytes(16)));session_start();
$_SESSION=['csrf'=>'claim-test-token'];
if($i['owner'])$_SESSION+=['business_account_id'=>1,'business_last_seen'=>time()];
$_GET=$i['get'];$_POST=$i['post']??[];$_FILES=[];
$_SERVER=['REQUEST_METHOD'=>$i['post']===null?'GET':'POST','REQUEST_URI'=>'/business-claim.php?lang='.$i['get']['lang'],'HTTP_HOST'=>'127.0.0.1','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];
ob_start();register_shutdown_function(static function(){while(ob_get_level()>1)ob_end_flush();echo json_encode(['http'=>http_response_code()?:200,'html'=>ob_get_clean()],JSON_THROW_ON_ERROR);});
require $i['package'].'/public/business-claim.php';
'''

with tempfile.TemporaryDirectory(prefix='claim-view-',dir=ROOT/'.local') as temporary:
    root=Path(temporary)
    packages=[]
    for label,source in [('before',BEFORE),('after',AFTER)]:
        package=root/label
        shutil.copytree(source/'app',package/'app')
        shutil.copytree(source/'public',package/'public',ignore=shutil.ignore_patterns('assets'))
        (package/'public/assets').symlink_to(source/'public/assets',target_is_directory=True)
        (package/'private/sessions').mkdir(parents=True)
        fixture=package/'private/fixture.sqlite'
        config={'dsn':'sqlite:'+str(fixture),'app_key':'isolated-claim-key-01234567890123456789','registration_enabled':True,'privacy':{'operator':'Synthetic','contact':'test@example.invalid','retention':'Three years','retention_en':'Three years','retention_pt':'Três anos'},'local_test':True,'business_accounts_enabled':True,'unified_admin_only':True,'account_mail':{'enabled':False}}
        (package/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);')
        packages.append(package)
    with sqlite3.connect(BEFORE/'private/presentation.sqlite') as db:
        business=db.execute("SELECT id FROM businesses WHERE destination_id=1 AND category_code='alimentacion' AND publication_status='published' AND claim_status='NO_RECLAMADO' AND operation_status<>'CERRADO_DEFINITIVO' LIMIT 1").fetchone()[0]
    cases=[]
    for locale in ['es','en','pt-br']:
        for owner in [False,True]:
            for name,query,post in [
                ('empty',{},None),
                ('place',{'destino':'1'},None),
                ('results',{'destino':'1','categoria':'alimentacion'},None),
                ('no-results',{'destino':'1','categoria':'alimentacion','nombre':'xyz-does-not-exist'},None),
                ('json',{'destino':'1','categoria':'alimentacion','search':'1'},None),
                ('submit',{'destino':'1','categoria':'alimentacion'},{'csrf':'claim-test-token','business_id':str(business),'relationship_code':'owner','consent':'1'}),
                ('invalid-post',{'destino':'1','categoria':'alimentacion'},{'csrf':'claim-test-token','phone_number':'<300&>'}),
            ]:
                results=[]
                for package in packages:
                    shutil.copy2(BEFORE/'private/presentation.sqlite',package/'private/fixture.sqlite')
                    with sqlite3.connect(package/'private/fixture.sqlite') as db:
                        db.execute("UPDATE accounts SET account_type='business' WHERE id=1")
                    payload={'package':str(package),'sessions':str(package/'private/sessions'),'owner':owner,'get':{'lang':locale,**query},'post':post}
                    process=subprocess.run(['php','-d','display_errors=stderr','-r',PHP],input=json.dumps(payload),text=True,capture_output=True,env=dict(os.environ,EL_PREPROD_LOCAL='1'),check=True)
                    assert not process.stderr.strip(),(locale,owner,name,process.stderr)
                    response=json.loads(process.stdout)
                    if name=='json':parsed=json.loads(response.pop('html'))
                    else:
                        parsed=auth.AuthMarkup(package/'public','/business-claim.php?lang='+locale)
                        parsed.feed(response.pop('html'));parsed=parsed.events
                    with sqlite3.connect(package/'private/fixture.sqlite') as db:
                        rows=db.execute('SELECT business_id,account_id,relationship_code,status FROM business_claim_requests ORDER BY id').fetchall()
                        state=db.execute('SELECT claim_status FROM businesses WHERE id=?',(business,)).fetchone()[0]
                    if name=='submit' and owner:
                        assert len(rows)==1 and state=='EN_TRAMITE', (response,rows,state,[e for e in parsed if e[0]=='text'][20:45])
                    results.append((response,parsed,rows,state))
                if results[0]!=results[1]:
                    a,b=results[0][1],results[1][1]
                    if isinstance(a,list) and isinstance(b,list):
                        for index,(left,right) in enumerate(zip(a,b)):
                            if left!=right:raise AssertionError((locale,owner,name,index,a[max(0,index-2):index+4],b[max(0,index-2):index+4]))
                    raise AssertionError((locale,owner,name,results[0][0],results[1][0],len(a),len(b),results[0][2:],results[1][2:]))
                cases.append((locale,owner,name))
    print('OK:',len(cases),'claim controller comparisons across locales, sessions, filters, JSON and errors.')
