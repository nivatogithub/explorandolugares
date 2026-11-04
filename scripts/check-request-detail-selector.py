#!/usr/bin/env python3
"""Compare administrative request details on disposable data before/after selector extraction."""
from pathlib import Path
import json,shutil,sqlite3,subprocess,sys,tempfile
ROOT=Path(__file__).resolve().parents[1]
before,after=(Path(x).resolve() for x in sys.argv[1:3]);assert all(x.is_relative_to(ROOT/'.local') for x in (before,after))
fixture=before/'private/presentation.sqlite';assert fixture.exists()
php=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$_GET=$i['get'];$_POST=$i['post']??[];$_SESSION=['csrf'=>'isolated-test'];if($i['auth'])$_SESSION+=['admin'=>true,'last_seen'=>time()-($i['auth']==='expired'?3600:0)];
$_SERVER=['REQUEST_METHOD'=>$i['post']===null?'GET':'POST','REQUEST_URI'=>'/admin/request-details.php','HTTP_HOST'=>'candidate.localhost','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];
ob_start();register_shutdown_function(static function(){echo json_encode(['http'=>http_response_code()?:200,'html'=>ob_get_clean()],JSON_THROW_ON_ERROR);});require $i['package'].'/public/admin/request-details.php';
'''
with tempfile.TemporaryDirectory(prefix='request-detail-',dir=ROOT/'.local') as tmp:
 roots=[]
 for label,source in [('before',before),('after',after)]:
  root=Path(tmp)/label;shutil.copytree(source/'app',root/'app');shutil.copytree(source/'public',root/'public',ignore=shutil.ignore_patterns('assets'));(root/'public/assets').symlink_to(source/'public/assets',target_is_directory=True);(root/'private').mkdir();shutil.copy2(fixture,root/'private/fixture.sqlite')
  with sqlite3.connect(root/'private/fixture.sqlite') as db:
   business,place=db.execute('SELECT id,destination_id FROM businesses ORDER BY id LIMIT 1').fetchone();account=db.execute('SELECT id FROM accounts ORDER BY id LIMIT 1').fetchone()[0]
   db.execute("INSERT INTO leads(id,request_key,name,business,municipality,category,has_brand,description,interest,consent_version,created_at) VALUES (1,'synthetic-key','Persona','Negocio','Jericó','hospedaje','no','Detalle <sintético>','contacto','test','2026-01-01')")
   db.execute("INSERT INTO business_account_applications(id,account_id,application_type,destination_id,category_code,business_name,relationship_code,consent_version,business_id,business_details_json) VALUES (1,?,'claim_existing',?,'hospedaje','Negocio','owner','test',?,?)",(account,place,business,json.dumps({'categories':['hospedaje'],'text':{'summary':'Dato sintético'}})))
   db.execute("INSERT INTO lead_business_links(lead_id,business_id,linked_by) VALUES (1,?,'synthetic')",(business,))
  config={'dsn':'sqlite:'+str(root/'private/fixture.sqlite'),'app_key':'a'*64,'local_test':True,'unified_admin_only':True,'account_mail':{'enabled':False}}
  (root/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);');roots.append(root)
 cases=[('lead',{'type':'lead','id':'1'},None,True),('legacy',{'tipo':'participation','id':'1'},None,True),('search',{'type':'lead','id':'1','search':'hotel'},None,True),('empty',{'type':'lead','id':'1','search':'nomatchXYZ'},None,True),('account',{'type':'account','id':'1'},None,True),('missing',{'type':'lead','id':'999999'},None,True),('bad_type',{'type':'other','id':'1'},None,True),('invalid_link',{'type':'lead','id':'1'},{'csrf':'isolated-test','action':'link'},True),('guest',{'type':'lead','id':'1'},None,False),('expired',{'type':'lead','id':'1'},None,'expired')]
 for name,get,post,auth in cases:
  output=[]
  for root in roots:
   payload={'get':get,'post':post,'auth':auth,'package':str(root)}
   result=subprocess.run(['php','-d','display_errors=stderr','-r',php],input=json.dumps(payload),capture_output=True,text=True,check=True)
   assert not result.stderr.strip(),(name,result.stderr[:500]);output.append(json.loads(result.stdout))
  if output[0]!=output[1]:
   import difflib
   a,b=output[0]['html'],output[1]['html']; i=next((i for i,(x,y) in enumerate(zip(a,b)) if x!=y),min(len(a),len(b))); print('DIFF',i,repr(a[max(0,i-100):i+150]),repr(b[max(0,i-100):i+150]))
   raise AssertionError((name,output[0]['http'],output[1]['http']))
print('OK: 10 request-detail controller cases match, including linked business, account, filters, errors and access.')
