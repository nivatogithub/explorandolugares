#!/usr/bin/env python3
"""Compare cancellation screen and synthetic reviews in isolated fixtures."""
from pathlib import Path
import json,shutil,sqlite3,subprocess,sys,tempfile
ROOT=Path(__file__).resolve().parents[1];before,after=(Path(x).resolve() for x in sys.argv[1:3]);assert all(x.is_relative_to(ROOT/'.local') for x in (before,after));fixture=ROOT/'.local/architecture-public-requests-selector/private/presentation.sqlite';assert fixture.exists()
php=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$_GET=$i['get'];$_POST=$i['post']??[];$_SESSION=['csrf'=>'isolated-test'];if($i['auth'])$_SESSION+=['admin'=>true,'last_seen'=>time()-($i['auth']==='expired'?3600:0)];$_SERVER=['REQUEST_METHOD'=>$i['post']===null?'GET':'POST','REQUEST_URI'=>'/admin/cancellations.php','HTTP_HOST'=>'candidate.localhost','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];ob_start();register_shutdown_function(static function(){echo json_encode(['http'=>http_response_code()?:200,'html'=>ob_get_clean()],JSON_THROW_ON_ERROR);});require $i['package'].'/public/admin/cancellations.php';
'''
with tempfile.TemporaryDirectory(prefix='cancellation-view-',dir=ROOT/'.local') as tmp:
 roots=[]
 for label,source in [('before',before),('after',after)]:
  root=Path(tmp)/label;shutil.copytree(source/'app',root/'app');shutil.copytree(source/'public',root/'public',ignore=shutil.ignore_patterns('assets'));(root/'public/assets').symlink_to(source/'public/assets',target_is_directory=True);(root/'private').mkdir();config={'dsn':'sqlite:'+str(root/'private/fixture.sqlite'),'app_key':'a'*64,'local_test':True,'unified_admin_only':True,'account_mail':{'enabled':False}};(root/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);');roots.append(root)
 cases=[('empty',{},None,True,False),('list',{},None,True,True),('saved',{'guardado':'1'},None,True,True),('guest',{},None,False,True),('expired',{},None,'expired',True),('invalid_note',{}, {'csrf':'isolated-test','id':'1','action':'review','note':''},True,True),('csrf',{}, {'csrf':'bad','id':'1','action':'review','note':'test'},True,True),('review',{}, {'csrf':'isolated-test','id':'1','action':'review','note':'Synthetic <note>'},True,True)]
 for name,get,post,auth,populated in cases:
  outcomes=[]
  for root in roots:
   shutil.copy2(fixture,root/'private/fixture.sqlite')
   if populated:
    with sqlite3.connect(root/'private/fixture.sqlite') as db:
     business=db.execute("SELECT business_id FROM business_translations WHERE locale='es' ORDER BY business_id LIMIT 1").fetchone()[0];db.execute("INSERT INTO subscription_cancellation_requests(id,subscription_id,business_id,account_id,reason_code,reason_text) VALUES(1,999,?,1,'other','Synthetic <reason>')",(business,))
   payload={'get':get,'post':post,'auth':auth,'package':str(root)};p=subprocess.run(['php','-d','display_errors=stderr','-r',php],input=json.dumps(payload),capture_output=True,text=True,check=True);assert not p.stderr.strip(),(name,p.stderr);output=json.loads(p.stdout)
   with sqlite3.connect(root/'private/fixture.sqlite') as db:rows=db.execute('SELECT status,review_note,reviewed_at IS NOT NULL FROM subscription_cancellation_requests ORDER BY id').fetchall()
   outcomes.append((output,rows))
  if outcomes[0]!=outcomes[1]:
   a,b=outcomes[0][0]['html'],outcomes[1][0]['html'];i=next((i for i,(x,y) in enumerate(zip(a,b)) if x!=y),min(len(a),len(b)));print(name,'DIFF',i,repr(a[max(0,i-80):i+140]),repr(b[max(0,i-80):i+140]));raise AssertionError(name)
print('OK: 8 cancellation cases, review writes and output match in disposable fixtures.')
