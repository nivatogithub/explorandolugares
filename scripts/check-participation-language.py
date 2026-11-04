#!/usr/bin/env python3
"""Compare localized participation responses and isolated storage in three languages."""
from pathlib import Path
import json,shutil,sqlite3,subprocess,sys,tempfile
ROOT=Path(__file__).resolve().parents[1]
before,after=(Path(x).resolve() for x in sys.argv[1:3]);assert all(p.is_relative_to(ROOT/'.local') for p in (before,after))
fixture=before/'private/presentation.sqlite';assert fixture.is_file()
catalog=json.loads((after/'app/presentation/participation-messages.json').read_text())
key='a'*48;csrf='synthetic-csrf'
valid={'csrf':csrf,'request_key':key,'name':'Persona de prueba','business':'Negocio de prueba','municipality':'Jericó','category':'hospedaje','email':'test@example.invalid','phone':'','description':'Descripción de prueba','consent':'1','understanding':'1'}
cases=[('invalid_csrf',{'csrf':'invalid'},403,'csrf_invalid',None),('invalid_key',{'request_key':'wrong'},403,'reload',None),('bot',{'website':'bot'},422,'validation_failed',None),('invalid_email',{'email':'bad'},422,'check_fields',('email','invalid_email')),('invalid_category',{'category':'bad'},422,'check_fields',('category','invalid_category')),('invalid_municipality',{'municipality':'Fake Place'},422,'check_fields',('municipality','invalid_municipality')),('invalid_name',{'name':''},422,'check_fields',('name','field_required')),('too_long',{'name':'a'*121},422,'check_fields',('name','field_too_long')),('invalid_phone',{'phone':'invalid'},422,'check_fields',('phone','invalid_phone')),('invalid_landline',{'phone_type':'fijo','phone_number':'123'},422,'check_fields',('phone','invalid_landline')),('invalid_mobile',{'phone_type':'celular','phone_number':'123'},422,'check_fields',('phone','invalid_mobile')),('no_contact',{'email':'','phone':''},422,'check_fields',('email','contact_required')),('invalid_type',{'request_type':'invalid'},422,'check_fields',('request_type','invalid_request_type')),('consent',{'consent':'0'},422,'check_fields',('consent','consent_required')),('understanding',{'understanding':'0'},422,'check_fields',('understanding','understanding_required')),('success',{},200,'business_received',None),('promoter',{'request_type':'impulsor_local'},200,'promoter_received',None)]
with tempfile.TemporaryDirectory(prefix='participation-language-',dir=ROOT/'.local') as tmp:
 roots=[]
 for label,source in [('before',before),('after',after)]:
  root=Path(tmp)/label;(root/'private').mkdir(parents=True);(root/'public/api').mkdir(parents=True);shutil.copytree(source/'app',root/'app');shutil.copy2(source/'public/api/participation.php',root/'public/api/participation.php')
  config={'dsn':'sqlite:'+str(root/'private/data.sqlite'),'local_test':True,'registration_enabled':True,'app_key':'a'*64,'privacy':{'operator':'Test','contact':'test@example.invalid','retention':'Prueba','retention_en':'Test','retention_pt':'Teste'}}
  (root/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);');roots.append(root)
 for locale in ('es','en','pt-br'):
  for name,change,status,message,field in cases:
   outcomes=[]
   for label,root in zip(('before','after'),roots):
    shutil.copy2(fixture,root/'private/data.sqlite');post=valid|change
    session={'request_key':key,'csrf':csrf}
    code='register_shutdown_function(static function(){fwrite(STDERR,"HTTP_STATUS:".(http_response_code()?:200));});$_SERVER["REQUEST_METHOD"]="POST";$_SERVER["REQUEST_URI"]="/api/participation.php";$_SERVER["REMOTE_ADDR"]="127.0.0.1";$_GET=json_decode('+json.dumps(json.dumps({'lang':locale}))+',true);$_POST=json_decode('+json.dumps(json.dumps(post))+',true);$_SESSION=json_decode('+json.dumps(json.dumps(session))+',true);require '+json.dumps(str(root/'public/api/participation.php'))+';'
    result=subprocess.run(['php','-d','display_errors=stderr','-r',code],capture_output=True,text=True);assert result.returncode==0,(locale,name,label,result.stderr);actual=int(result.stderr.split('HTTP_STATUS:')[-1]);body=json.loads(result.stdout);assert actual==status,(locale,name,label,actual,body)
    if label=='after':
     assert body['message']==catalog[message][locale],(locale,name,body)
     if field:assert body['errors'][field[0]]==catalog[field[1]][locale],(locale,name,body)
    with sqlite3.connect(root/'private/data.sqlite') as db:rows=db.execute('SELECT request_key,name,business,municipality,category,email,phone,phone_type,participation_version,has_brand,brand_name,description,interest,wants_contact,consent_version FROM leads ORDER BY request_key').fetchall()
    outcomes.append((actual,sorted(body.keys()),sorted(body.get('errors',{})),rows))
   assert outcomes[0]==outcomes[1],(locale,name,outcomes)
   assert len(outcomes[1][3])==(1 if name in ('success','promoter') else 0),(locale,name,outcomes)
print('OK: 102 controller responses, validation messages and identical isolated rows in ES/EN/PT-BR.')
