#!/usr/bin/env python3
"""Compare localized claim failures and isolated writes against an earlier package."""
from pathlib import Path
import html,json,os,shutil,sqlite3,subprocess,sys,tempfile

ROOT=Path(__file__).resolve().parents[1]
before,after=map(lambda p:Path(p).resolve(),sys.argv[1:3])
fixture=before/'private/presentation.sqlite'
assert fixture.is_file() and before.is_relative_to(ROOT/'.local') and after.is_relative_to(ROOT/'.local')
catalog=json.loads((after/'app/presentation/business-claim.json').read_text())
php=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
ini_set('session.save_path',$i['sessions']);session_id(bin2hex(random_bytes(16)));session_start();
$_SESSION=['csrf'=>'claim-test-token'];if($i['owner'])$_SESSION+=['business_account_id'=>1,'business_last_seen'=>time()];
$_GET=['lang'=>$i['locale']];$_POST=$i['post'];$_FILES=[];
$_SERVER=['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/business-claim.php?lang='.$i['locale'],'HTTP_HOST'=>'127.0.0.1','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];
ob_start();register_shutdown_function(static function(){while(ob_get_level()>1)ob_end_flush();echo json_encode(['http'=>http_response_code()?:200,'html'=>ob_get_clean()],JSON_THROW_ON_ERROR);});
require $i['package'].'/public/business-claim.php';
'''
with tempfile.TemporaryDirectory(prefix='claim-errors-',dir=ROOT/'.local') as temporary:
 roots=[]
 for source,name in ((before,'before'),(after,'after')):
  root=Path(temporary)/name;root.mkdir();shutil.copytree(source/'app',root/'app')
  shutil.copytree(source/'public',root/'public',ignore=shutil.ignore_patterns('assets'))
  (root/'public/assets').symlink_to(source/'public/assets',target_is_directory=True)
  (root/'private/sessions').mkdir(parents=True)
  config={'dsn':'sqlite:'+str(root/'private/fixture.sqlite'),'app_key':'isolated-claim-key-01234567890123456789','registration_enabled':True,'privacy':{'operator':'Synthetic','contact':'test@example.invalid','retention':'Test','retention_en':'Test','retention_pt':'Teste'},'local_test':True,'business_accounts_enabled':True,'unified_admin_only':True,'account_mail':{'enabled':False}}
  (root/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);')
  roots.append(root)
 with sqlite3.connect(fixture) as db:
  business=db.execute("SELECT id FROM businesses WHERE destination_id=1 AND category_code='alimentacion' AND publication_status='published' AND claim_status='NO_RECLAMADO' AND operation_status<>'CERRADO_DEFINITIVO' LIMIT 1").fetchone()[0]
 base={'csrf':'claim-test-token','business_id':str(business),'relationship_code':'owner','consent':'1'}
 cases=[('account_required',False,base,'Crea una cuenta empresarial o inicia sesión para solicitar la reclamación.'),
        ('name_required',True,{**base,'business_id':'0'},'Completa tu nombre para continuar.'),
        ('phone_invalid',True,{**base,'phone_number':'abc'},'El teléfono solo admite números y separadores.'),
        ('relationship_required',True,{**base,'relationship_code':'invalid'},'Selecciona tu relación con el negocio.'),
        ('message_too_long',True,{**base,'message':'a'*1001},'El mensaje supera los 1.000 caracteres.'),
        ('consent_required',True,{**base,'consent':'0'},'Autoriza el uso de estos datos para revisar la solicitud.'),
        ('business_unavailable',True,{**base,'business_id':'999999999'},'Este negocio ya no está disponible para reclamar. Actualiza la búsqueda.'),
        ('success',True,base,None)]
 for locale in ('es','en','pt-br'):
  for reason,owner,post,spanish in cases:
   results=[]
   for root in roots:
    shutil.copy2(fixture,root/'private/fixture.sqlite')
    with sqlite3.connect(root/'private/fixture.sqlite') as db:db.execute("UPDATE accounts SET account_type='business' WHERE id=1")
    payload={'package':str(root),'sessions':str(root/'private/sessions'),'owner':owner,'locale':locale,'post':post}
    result=subprocess.run(['php','-d','display_errors=stderr','-r',php],input=json.dumps(payload),text=True,capture_output=True,env=dict(os.environ,EL_PREPROD_LOCAL='1'),check=True)
    response=json.loads(result.stdout)
    with sqlite3.connect(root/'private/fixture.sqlite') as db:
     rows=db.execute('SELECT business_id,account_id,relationship_code,status FROM business_claim_requests ORDER BY id').fetchall()
     state=db.execute('SELECT claim_status FROM businesses WHERE id=?',(business,)).fetchone()[0]
    results.append((response,rows,state))
   previous,current=results
   assert previous[1:]==current[1:],(locale,reason,'writes')
   assert previous[0]['http']==current[0]['http']==(200 if reason=='success' else 400),(locale,reason,'status')
   expected=previous[0]['html']
   if spanish and locale!='es':
    old=html.escape(spanish,quote=True);new=html.escape(catalog[locale]['error_'+reason],quote=True)
    assert old in expected and new in current[0]['html'],(locale,reason,'missing message')
    expected=expected.replace(old,new)
   assert expected==current[0]['html'],(locale,reason,'unexpected response difference')
   assert len(current[1])==(1 if reason=='success' else 0),(locale,reason,'unexpected submission')
print('OK: 24 claim responses and isolated writes match, except the intended localized error messages.')
