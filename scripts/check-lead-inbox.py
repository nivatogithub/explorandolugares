#!/usr/bin/env python3
"""Compare inbox filters, access and actions on disposable synthetic requests."""
from pathlib import Path
import json,shutil,sqlite3,subprocess,sys,tempfile
ROOT=Path(__file__).resolve().parents[1];before,after=(Path(x).resolve() for x in sys.argv[1:3]);assert all(x.is_relative_to(ROOT/'.local') for x in (before,after));fixture=ROOT/'.local/architecture-public-requests-selector/private/presentation.sqlite';assert fixture.exists()
php=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$_GET=$i['get'];$_POST=$i['post']??[];$_SESSION=['csrf'=>'isolated-test'];if($i['auth'])$_SESSION+=['admin'=>true,'last_seen'=>time()-($i['auth']==='expired'?3600:0)];$_SERVER=['REQUEST_METHOD'=>$i['post']===null?'GET':'POST','REQUEST_URI'=>'/admin/index.php','HTTP_HOST'=>'candidate.localhost','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];ob_start();register_shutdown_function(static function(){echo json_encode(['http'=>http_response_code()?:200,'html'=>ob_get_clean(),'notice'=>$_SESSION['lead_status_notice']??null],JSON_THROW_ON_ERROR);});require $i['package'].'/public/admin/index.php';
'''
with tempfile.TemporaryDirectory(prefix='lead-inbox-',dir=ROOT/'.local') as tmp:
 roots=[]
 for label,source in [('before',before),('after',after)]:
  root=Path(tmp)/label;shutil.copytree(source/'app',root/'app');shutil.copytree(source/'public',root/'public',ignore=shutil.ignore_patterns('assets'));(root/'public/assets').symlink_to(source/'public/assets',target_is_directory=True);(root/'private').mkdir();config={'dsn':'sqlite:'+str(root/'private/fixture.sqlite'),'app_key':'a'*64,'local_test':True,'unified_admin_only':True,'account_mail':{'enabled':False}};(root/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);');roots.append(root)
 cases=[('empty',{},None,True,False),('list',{},None,True,True),('page2',{'page':'2'},None,True,True),('overflow',{'page':'999'},None,True,True),('status',{'status':'revisado'},None,True,True),('search',{'q':'synthetic 2'},None,True,True),('combined',{'q':'synthetic','status':'nuevo','page':'2'},None,True,True),('empty_search',{'q':'nonexistent'},None,True,True),('escape',{'q':'<tag>'},None,True,True),('guest',{},None,False,True),('expired',{},None,'expired',True),('invalid_status',{}, {'csrf':'isolated-test','action':'status','id':'1','status':'bad'},True,True),('csrf',{}, {'csrf':'bad','action':'delete','ids':['1']},True,True),('delete_invalid',{}, {'csrf':'isolated-test','action':'delete','ids':['bad']},True,True),('delete',{}, {'csrf':'isolated-test','action':'delete','ids':['1','2','2']},True,True),('status_save',{}, {'csrf':'isolated-test','action':'status','id':'1','status':'revisado'},True,True)]
 for name,get,post,auth,populated in cases:
  outcomes=[]
  for root in roots:
   shutil.copy2(fixture,root/'private/fixture.sqlite')
   if populated:
    with sqlite3.connect(root/'private/fixture.sqlite') as db:
     for i in range(1,61):db.execute("INSERT INTO leads(id,request_key,name,business,municipality,category,has_brand,description,interest,consent_version,created_at,status) VALUES(?,?,? ,?,'Jericó','hospedaje','no','Synthetic <description>','contacto','test','2026-01-01',?)",(i,'synthetic-'+str(i),'Persona '+str(i),'synthetic '+str(i),'nuevo' if i%2 else 'revisado'))
   payload={'get':get,'post':post,'auth':auth,'package':str(root)};p=subprocess.run(['php','-d','display_errors=stderr','-r',php],input=json.dumps(payload),capture_output=True,text=True,check=True);assert not p.stderr.strip(),(name,p.stderr);output=json.loads(p.stdout)
   with sqlite3.connect(root/'private/fixture.sqlite') as db:rows=db.execute('SELECT id,status FROM leads ORDER BY id').fetchall()
   outcomes.append((output,rows))
  if outcomes[0]!=outcomes[1]:
   a,b=outcomes[0][0]['html'],outcomes[1][0]['html'];i=next((i for i,(x,y) in enumerate(zip(a,b)) if x!=y),min(len(a),len(b)));print(name,'DIFF',i,repr(a[max(0,i-80):i+140]),repr(b[max(0,i-80):i+140]));raise AssertionError(name)
print('OK: 16 inbox cases; filters, pages, access, status and synthetic deletes preserve output and rows.')
