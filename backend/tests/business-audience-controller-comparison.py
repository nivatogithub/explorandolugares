#!/usr/bin/env python3
"""Compare private audience controller on isolated synthetic SQLite fixtures."""
from pathlib import Path
import json, shutil, sqlite3, subprocess, sys, tempfile

old,new=map(lambda p:Path(p).resolve(),sys.argv[1:3])
fixture=old/'private/presentation.sqlite'
assert fixture.is_file(),'Prepare the disposable presentation fixture first.'
with tempfile.TemporaryDirectory(prefix='audience-controller-') as directory:
 roots=[]
 for package,name in ((old,'old'),(new,'new')):
  root=Path(directory)/name;root.mkdir();(root/'private').mkdir();(root/'public/api').mkdir(parents=True)
  shutil.copytree(package/'app',root/'app')
  shutil.copy2(package/'public/api/entity-audience.php',root/'public/api/entity-audience.php')
  shutil.copy2(fixture,root/'private/data.sqlite')
  config={'dsn':'sqlite:'+str(root/'private/data.sqlite'),'local_test':True,'business_accounts_enabled':True}
  (root/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);')
  roots.append(root)
 db=sqlite3.connect(roots[0]/'private/data.sqlite')
 account=db.execute('SELECT id FROM accounts ORDER BY id LIMIT 1').fetchone()[0]
 businesses=db.execute('SELECT id,public_id FROM businesses ORDER BY id LIMIT 2').fetchall()
 db.close()
 for root in roots:
  db=sqlite3.connect(root/'private/data.sqlite')
  db.execute("UPDATE accounts SET status='active' WHERE id=?",(account,))
  db.execute("INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES (?,?,'owner','active')",(account,businesses[0][0]));db.commit();db.close()
 own=businesses[0][1];other=businesses[1][1]
 cases=[('guest',{'type':'business','key':own},403),('owner',{'type':'business','key':own},200),
        ('owner',{'type':'business','key':other},403),('owner',{'type':'destination','key':'jerico'},403),
        ('owner',{'type':['business'],'key':['test']},400),('admin',{'type':'business','key':own},200),
        ('admin',{'type':'destination','key':'jerico','days':'90'},200),('expired',{'type':'business','key':own},403)]
 for mode,query,expected in cases:
  responses=[]
  for root in roots:
   session={} if mode=='guest' else {'business_account_id':account,'business_last_seen':1 if mode=='expired' else 2000000000}
   if mode=='admin':session.update({'admin':True,'last_seen':2000000000})
   php='register_shutdown_function(static function(){fwrite(STDERR,"HTTP_STATUS:".(http_response_code()?:200));});$_SERVER["REQUEST_METHOD"]="GET";$_GET=json_decode('+json.dumps(json.dumps(query))+',true);$_SESSION=json_decode('+json.dumps(json.dumps(session))+',true);require '+json.dumps(str(root/'public/api/entity-audience.php'))+';'
   result=subprocess.run(['php','-d','display_errors=stderr','-r',php],capture_output=True)
   assert result.returncode==0,(mode,result.stderr.decode())
   status=int(result.stderr.decode().split('HTTP_STATUS:')[-1]);responses.append((status,json.loads(result.stdout)))
  assert responses[0]==responses[1] and responses[1][0]==expected,(mode,query,responses)
print('OK: 8 audience controller cases, including own/other business, invalid input, guest, expired and administrator.')
