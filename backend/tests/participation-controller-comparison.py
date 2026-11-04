#!/usr/bin/env python3
"""Compare participation responses and isolated synthetic writes, old vs new."""
from pathlib import Path
import json,shutil,sqlite3,subprocess,sys,tempfile

old,new=map(lambda p:Path(p).resolve(),sys.argv[1:3])
fixture=old/'private/presentation.sqlite'
assert fixture.is_file(),'Prepare the disposable presentation fixture first.'
with tempfile.TemporaryDirectory(prefix='participation-controller-') as directory:
 roots=[]
 for package,name in ((old,'old'),(new,'new')):
  root=Path(directory)/name;root.mkdir();(root/'private').mkdir();(root/'public/api').mkdir(parents=True)
  shutil.copytree(package/'app',root/'app')
  shutil.copy2(package/'public/api/participation.php',root/'public/api/participation.php')
  shutil.copy2(fixture,root/'private/data.sqlite')
  config={'dsn':'sqlite:'+str(root/'private/data.sqlite'),'local_test':True,'registration_enabled':True,'app_key':'a'*64,
          'privacy':{'operator':'Test','contact':'test@example.invalid','retention':'Prueba','retention_en':'Test','retention_pt':'Teste'}}
  (root/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);')
  roots.append(root)
 key='a'*48;csrf='synthetic-csrf'
 valid={'csrf':csrf,'request_key':key,'name':'Persona de prueba','business':'Negocio de prueba','municipality':'Jericó','category':'hospedaje','email':'test@example.invalid','phone':'','description':'Descripción de prueba','consent':'1','understanding':'1'}
 cases=[('GET',{}, {},200),('GET',{'lang':'en'},{},200),('GET',{'lang':'pt-br'},{},200),
        ('PUT',{}, {},405),('POST',{}, {**valid,'csrf':'wrong'},403),('POST',{}, {**valid,'request_key':'wrong'},403),
        ('POST',{}, {**valid,'website':'bot'},422),('POST',{}, {**valid,'category':'inventada'},422),
        ('POST',{},valid,200),('POST',{},valid,200),
        ('POST',{}, {**valid,'request_key':'b'*48,'business':'Impulsor test','request_type':'impulsor_local'},200)]
 for method,query,post,expected in cases:
  responses=[]
  for root in roots:
   session={'request_key':post.get('request_key',key) if post.get('request_key')!='wrong' else key,'csrf':csrf}
   code='register_shutdown_function(static function(){fwrite(STDERR,"HTTP_STATUS:".(http_response_code()?:200));});$_SERVER["REQUEST_METHOD"]='+json.dumps(method)+';$_SERVER["REQUEST_URI"]="/api/participation.php";$_SERVER["REMOTE_ADDR"]="127.0.0.1";$_GET=json_decode('+json.dumps(json.dumps(query))+',true);$_POST=json_decode('+json.dumps(json.dumps(post))+',true);$_SESSION=json_decode('+json.dumps(json.dumps(session))+',true);require '+json.dumps(str(root/'public/api/participation.php'))+';'
   result=subprocess.run(['php','-d','display_errors=stderr','-r',code],capture_output=True)
   assert result.returncode==0,(method,post,result.stderr.decode())
   status=int(result.stderr.decode().split('HTTP_STATUS:')[-1])
   try:body=json.loads(result.stdout)
   except json.JSONDecodeError:raise AssertionError((method,post,result.stdout.decode(),result.stderr.decode()))
   responses.append((status,body))
  assert responses[0]==responses[1] and responses[1][0]==expected,(method,post,responses)
 rows=[]
 for root in roots:
  db=sqlite3.connect(root/'private/data.sqlite')
  rows.append(db.execute('SELECT request_key,name,business,municipality,category,email,phone,phone_type,participation_version,has_brand,brand_name,description,interest,wants_contact,consent_version FROM leads ORDER BY request_key').fetchall());db.close()
 assert rows[0]==rows[1] and len(rows[0])==2,rows
print('OK: 11 controller responses and two synthetic submissions match, including duplicate handling and promoter.')
