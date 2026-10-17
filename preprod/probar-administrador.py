#!/usr/bin/env python3
"""Local authorization tests using only disposable identities; never knows the owner's password."""
import http.cookiejar,json,re,secrets,subprocess,urllib.request,urllib.parse,urllib.error
suffix=secrets.token_hex(8);ids=[];password='TemporaryTestPassword123'
def php(code):
 source='<?php $c=require "/srv/state/private/config.php";$p=new PDO($c["dsn"],$c["db_user"],$c["db_password"],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);'+code
 return subprocess.check_output(['podman','exec','-i','explorando-preprod','php'],input=source.encode()).decode()
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,path,data=None):
 try:
  with c.open('http://127.0.0.1:8088'+path,None if data is None else urllib.parse.urlencode(data).encode(),timeout=30) as r:return r.status,r.read().decode(),r.url
 except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.url
def csrf(body):return re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
def login(c,email):
 _,body,_=call(c,'/negocios/acceso/');return call(c,'/negocios/acceso/',{'csrf':csrf(body),'email':email,'password':password})
try:
 for kind in ['traveler','business']:
  email=kind+'-'+suffix+'@example.invalid'
  ident=int(php('$q=$p->prepare("INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status) VALUES(?,?,?,?,?,\'active\')");$q->execute([bin2hex(random_bytes(16)),'+json.dumps(kind)+',"Prueba temporal",'+json.dumps(email)+',password_hash('+json.dumps(password)+',PASSWORD_DEFAULT)]);echo $p->lastInsertId();'));ids.append(ident)
 admin,business=ids
 php('$p->prepare("INSERT INTO account_roles(account_id,role) VALUES(?,\'administrator\')")->execute(['+str(admin)+']);')
 c=client();assert call(c,'/admin/usuarios.php')[0]==403
 assert login(c,'traveler-'+suffix+'@example.invalid')[0]==200
 state=json.loads(call(c,'/__preprod-account.php')[1]);assert state['administrator'] is True
 assert call(c,'/admin/usuarios.php')[0]==200
 assert call(c,'/negocios/salir/',{'csrf':state['csrf']})[0]==200
 assert call(c,'/admin/usuarios.php')[0]==403
 assert login(c,'business-'+suffix+'@example.invalid')[0]==200
 assert json.loads(call(c,'/__preprod-account.php')[1])['administrator'] is False
 assert call(c,'/admin/usuarios.php')[0]==403
 c=client();assert login(c,'traveler-'+suffix+'@example.invalid')[0]==200
 php('$p->prepare("DELETE FROM account_roles WHERE account_id=?")->execute(['+str(admin)+']);')
 assert call(c,'/admin/usuarios.php')[0]==403
 # The real account is only inspected for presence/role, never logged in or modified.
 assert php('$q=$p->prepare("SELECT COUNT(*) FROM accounts a JOIN account_roles r ON r.account_id=a.id WHERE a.email=? AND a.status=\'active\' AND r.role=\'administrator\'");$q->execute([\'admin@explorandolugares.com\']);echo $q->fetchColumn();')=='1'
 print('OK: anonymous rejected; administrator login; session status; admin access; logout revokes access; business cannot administer; role revocation immediate; owner account present. No owner password used.')
finally:
 for ident in ids:php('$p->prepare("DELETE FROM accounts WHERE id=? AND email LIKE ?")->execute(['+str(ident)+','+json.dumps('%'+suffix+'@example.invalid')+']);')
