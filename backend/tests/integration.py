#!/usr/bin/env python3
"""Pruebas HTTP con SQLite aislada, sin datos o credenciales de producción."""
from pathlib import Path
import subprocess, json, secrets, sqlite3, shutil, socket, time, urllib.request, urllib.error, urllib.parse, http.cookiejar, re, base64
root=Path(__file__).resolve().parents[2]
work=root/'.local'/('test-'+secrets.token_hex(6));work.mkdir(parents=True)
shutil.copytree(root/'backend/app',work/'app');shutil.copytree(root/'backend/public',work/'public');(work/'private').mkdir()
for locale,source in [('es','negocios-locales'),('en','en/negocios-locales'),('pt-br','pt-br/negocios-locales')]:
 target=work/'public'/source;target.parent.mkdir(parents=True,exist_ok=True);shutil.copytree(root/'ensayos/v1'/source,target)
db=sqlite3.connect(work/'private/test.sqlite');db.executescript((root/'backend/database/sqlite.sql').read_text())
password=secrets.token_urlsafe(24)
hash_value=subprocess.check_output(['php','-r','echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_DEFAULT);'],input=password.encode()).decode()
config={'dsn':'sqlite:'+str(work/'private/test.sqlite'),'app_key':secrets.token_hex(32),'admin_user':'local-test','admin_password_hash':hash_value,'local_test':True,'registration_enabled':False,'privacy':{'operator':'Operador ficticio de prueba','contact':'privacidad@example.invalid','retention':'Solo durante esta prueba'}}
def save():
 encoded=base64.b64encode(json.dumps(config).encode()).decode()
 (work/'private/config.php').write_text("<?php return json_decode(base64_decode('"+encoded+"'), true);")
save()
with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
proc=subprocess.Popen(['php','-d','opcache.enable=0','-S',f'127.0.0.1:{port}','-t',str(work/'public')],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
base=f'http://127.0.0.1:{port}'
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(path,data=None,agent=client):
 payload=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None
 try:
  with agent.open(base+path,payload,timeout=10) as resp:return resp.status,resp.read().decode(),resp.headers
 except urllib.error.HTTPError as exc:return exc.code,exc.read().decode(),exc.headers
try:
 for _ in range(100):
  try:call('/api/participacion.php');break
  except urllib.error.URLError:time.sleep(.05)
 status,body,_=call('/api/participacion.php');assert status==200 and not json.loads(body)['enabled']
 status,body,_=call('/business/index.php');assert status==200 and 'Para negocios locales' in body and 'Crear cuenta de negocio' not in body
 status,_,_=call('/api/participacion.php',{});assert status==503
 config['registration_enabled']=True;save()
 status,body,_=call('/api/participacion.php');setup=json.loads(body);assert setup['enabled']
 assert not json.loads(call('/api/participacion.php?lang=en')[1])['enabled']
 config['privacy']['retention_en']='Only during this test';save()
 english_setup=json.loads(call('/api/participacion.php?lang=en')[1]);assert english_setup['enabled'] and english_setup['privacy']['retention']=='Only during this test'
 valid={'csrf':setup['csrf'],'request_key':setup['request_key'],'name':'Persona de prueba','business':'<script>alert(1)</script>','municipality':'Jericó','category':'hospedaje','email':'prueba@example.invalid','phone':'','description':'Descripción de prueba','consent':'1','understanding':'1'}
 assert call('/api/participacion.php',{**valid,'csrf':'incorrecto'})[0]==403
 assert call('/api/participacion.php',{**valid,'category':'inventada'})[0]==422
 assert call('/api/participacion.php',{**valid,'municipality':'Municipio inventado'})[0]==422
 assert call('/api/participacion.php',{**valid,'municipality':'Envigado'})[0]==422
 assert call('/api/participacion.php',{**valid,'consent':'0'})[0]==422
 for value in ['0','']:
  assert call('/api/participacion.php',{**valid,'understanding':value})[0]==422
 invalid_ack={k:v for k,v in valid.items() if k!='understanding'}
 assert call('/api/participacion.php',{**invalid_ack,'understanding[]':'1'})[0]==422
 missing_ack={k:v for k,v in valid.items() if k!='understanding'}
 assert call('/api/participacion.php',missing_ack)[0]==422
 assert call('/api/participacion.php',{**valid,'email':'mal-formado'})[0]==422
 for phone_type,number in [('celular','1234567890'),('celular','300123'),('fijo','6041234567'),('fijo','abc1234'),('whatsapp','1234567890'),('desconocido','3001234567')]:
  assert call('/api/participacion.php',{**valid,'phone_type':phone_type,'phone_number':number})[0]==422
 assert call('/api/participacion.php',{**valid,'email':'','phone_type':'celular','phone_number':''})[0]==422
 assert call('/api/participacion.php',{**valid,'email':''})[0]==422
 en_status,en_body,_=call('/api/participacion.php?lang=en',{**valid,'email':'invalid'})
 assert en_status==422 and json.loads(en_body)['errors']['email']=='Please check your email address.'
 assert call('/api/participacion.php',valid)[0]==200
 assert call('/api/participacion.php',valid)[0]==200
 assert db.execute('SELECT COUNT(*) FROM leads').fetchone()[0]==1
 assert db.execute('SELECT wants_contact,interest,status,participation_version FROM leads').fetchone()==(1,'contacto','nuevo','participacion-informativa-v1')
 # Contactos telefónicos sin correo; ignorar cualquier prefijo enviado por el cliente.
 for phone_type,number,expected in [('celular','3001234567','+573001234567'),('fijo','1234567','+576041234567'),('whatsapp','3007654321','+573007654321')]:
  agent=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
  tokens=json.loads(call('/api/participacion.php',agent=agent)[1])
  record={**valid,'business':'Prueba '+phone_type,'csrf':tokens['csrf'],'request_key':tokens['request_key'],'email':'','phone_type':phone_type,'phone_number':number,'phone_prefix':'+999','phone':'+999000'}
  assert call('/api/participacion.php',record,agent=agent)[0]==200
  assert db.execute('SELECT phone,phone_type FROM leads WHERE business=?',(record['business'],)).fetchone()==(expected,phone_type)
 assert db.execute('SELECT COUNT(*) FROM leads').fetchone()[0]==4
 # Una postulación de impulsor se marca de forma distinta a un registro comercial.
 agent=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 tokens=json.loads(call('/api/participacion.php',agent=agent)[1])
 promoter={**valid,'business':'Impulsor de prueba','csrf':tokens['csrf'],'request_key':tokens['request_key'],'request_type':'impulsor_local'}
 status,body,_=call('/api/participacion.php',promoter,agent=agent)
 assert status==200 and 'postulación como impulsor local' in json.loads(body)['message']
 assert db.execute('SELECT interest,participation_version FROM leads WHERE business=?',('Impulsor de prueba',)).fetchone()==('impulsor_local','impulsor-local-propuesta-v1')
 status,body,headers=call('/admin/index.php');assert '<script>alert(1)</script>' not in body and 'name="password"' in body
 csrf=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
 assert call('/admin/index.php',{'action':'status','csrf':csrf,'id':1,'status':'contactado'})[0]==403
 status,body,headers=call('/admin/index.php',{'action':'login','csrf':csrf,'username':'local-test','password':password})
 assert status==200 and '&lt;script&gt;alert(1)&lt;/script&gt;' in body and '<script>alert(1)</script>' not in body
 guest=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 assert call('/admin/base-datos.php',agent=guest)[0]==403
 status,body,_=call('/admin/base-datos.php')
 assert status==200 and 'Estado de la base de datos' in body and 'sqlite' in body
 assert 'confirm_migrations' in body and config['app_key'] not in body and password not in body
 db_csrf=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
 before_columns=[column[1] for column in db.execute('PRAGMA table_info(leads)').fetchall()]
 status,body,_=call('/admin/base-datos.php',{'action':'migrate','csrf':db_csrf,'confirm_migrations':'1'})
 assert status==400 and 'requieren MySQL/MariaDB' in body
 assert before_columns==[column[1] for column in db.execute('PRAGMA table_info(leads)').fetchall()]
 status,body,_=call('/admin/index.php')
 assert 'WhatsApp' in body and 'Celular' in body and 'Fijo' in body and 'participacion-informativa-v1' in body
 status,body,_=call('/admin/index.php?tab=sistema')
 assert status==200 and 'Sistema y base de datos' in body and 'Conectada' in body and 'Tabla accesible' in body
 assert 'Acceso al portal de negocios' in body and 'Cerrado al público' in body and 'Acceso privado para pruebas' in body
 assert 'test.sqlite' not in body and config['app_key'] not in body
 csrf=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
 # Public access requires the explicit confirmation phrase and the admin CSRF token.
 assert call('/admin/index.php?tab=sistema',{'action':'business_key','operation':'generate','csrf':'incorrecto'})[0]==403
 status,body,_=call('/admin/index.php?tab=sistema',{'action':'business_access','csrf':csrf,'business_accounts_enabled':'1','confirm_public':'incorrecta'})
 assert status==200 and 'Para abrir el registro al público' in body
 saved=subprocess.check_output(['php','-r',"$c=require $argv[1]; echo json_encode([$c['business_accounts_enabled']??false,$c['business_accounts_test_key']??'']);",str(work/'private/config.php')]).decode()
 assert json.loads(saved)==[False,'']
 # Generate a private test key; show it only in the immediate post/redirect response.
 status,body,_=call('/admin/index.php?tab=sistema',{'action':'business_key','operation':'generate','csrf':csrf})
 match=re.search(r'id="generated-business-test-key" type="text" readonly value="([a-f0-9]{64})"',body)
 assert status==200 and match and 'Llave nueva guardada' in body
 test_key=match.group(1)
 url_match=re.search(r'id="generated-business-test-url" type="text" readonly value="([^"]+)"',body)
 assert url_match and url_match.group(1)==urllib.parse.quote('/negocios/registro/?prueba='+test_key,safe='/?:=&')
 saved=subprocess.check_output(['php','-r',"$c=require $argv[1]; echo json_encode([$c['business_accounts_enabled']??false,$c['business_accounts_test_key']??'']);",str(work/'private/config.php')]).decode()
 assert json.loads(saved)==[False,test_key]
 assert test_key not in call('/admin/index.php?tab=sistema')[1]
 test_account=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 status,body,_=call('/business/index.php?prueba='+test_key,agent=test_account)
 assert status==200 and 'Crear cuenta de negocio' in body and 'Ya tengo cuenta' in body
 public_guest=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 assert 'Crear cuenta de negocio' not in call('/business/index.php',agent=public_guest)[1]
 private_guest=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 assert call('/business/register.php',agent=private_guest)[0]==404
 # Key removal requires confirmation and revokes any session carrying its old fingerprint.
 status,body,_=call('/admin/index.php?tab=sistema',{'action':'business_key','operation':'revoke','csrf':csrf})
 assert status==200 and 'Confirma la eliminación' in body
 status,body,_=call('/admin/index.php?tab=sistema',{'action':'business_key','operation':'revoke','confirm_revoke':'1','csrf':csrf})
 assert status==200 and 'Llave de prueba eliminada' in body
 assert 'Crear cuenta de negocio' not in call('/business/index.php',agent=test_account)[1]
 saved=subprocess.check_output(['php','-r',"$c=require $argv[1]; echo json_encode([$c['business_accounts_enabled']??false,$c['business_accounts_test_key']??'']);",str(work/'private/config.php')]).decode()
 assert json.loads(saved)==[False,'']
 # Opening the public flow clears the private key, and closing it leaves all access shut.
 status,body,_=call('/admin/index.php?tab=sistema',{'action':'business_access','csrf':csrf,'business_accounts_enabled':'1','confirm_public':'ABRIR REGISTRO PUBLICO'})
 assert status==200 and 'Abierto al público' in body
 assert 'Crear cuenta de negocio' in call('/business/index.php',agent=public_guest)[1]
 saved=subprocess.check_output(['php','-r',"$c=require $argv[1]; echo json_encode([$c['business_accounts_enabled']??false,$c['business_accounts_test_key']??'']);",str(work/'private/config.php')]).decode()
 assert json.loads(saved)==[True,'']
 status,body,_=call('/admin/index.php?tab=sistema',{'action':'business_access','csrf':csrf,'business_accounts_enabled':'0'})
 saved=subprocess.check_output(['php','-r',"$c=require $argv[1]; echo json_encode([$c['business_accounts_enabled']??false,$c['business_accounts_test_key']??'']);",str(work/'private/config.php')]).decode()
 assert status==200 and json.loads(saved)==[False,'']
 assert 'Crear cuenta de negocio' not in call('/business/index.php',agent=public_guest)[1]
 assert call('/business/register.php',agent=private_guest)[0]==404
 assert call('/admin/index.php',{'action':'status','csrf':'incorrecto','id':1,'status':'contactado'})[0]==403
 assert call('/admin/index.php',{'action':'status','csrf':csrf,'id':1,'status':'contactado'})[0]==200
 assert db.execute('SELECT status FROM leads').fetchone()[0]=='contactado'
 assert call('/admin/index.php',{'action':'delete','csrf':'incorrecto','ids[]':['1','2']})[0]==403
 assert call('/admin/index.php',{'action':'delete','csrf':csrf,'ids[]':['1','2']})[0]==200
 assert db.execute('SELECT COUNT(*) FROM leads').fetchone()[0]==3
 status,body,_=call('/admin/index.php',{'action':'logout','csrf':csrf});assert status==200 and 'name="password"' in body
 # Sesión nueva y límite de intentos compartido por IP.
 login_csrf=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
 for _ in range(10):status,_,_=call('/admin/index.php',{'action':'login','csrf':login_csrf,'username':'invalido','password':'invalida'})
 assert status==429
 config['privacy']['operator']='';save()
 assert not json.loads(call('/api/participacion.php')[1])['enabled']
 print('OK: registro deshabilitado sin configuración; consentimiento obligatorio; correo y teléfono válidos; fijo, celular y WhatsApp sin correo; prefijo calculado en servidor; validación; CSRF; guardado real; reintento sin duplicados; ajustes privados del registro empresarial; generación, copia de una sola vista, rotación y revocación de llave; confirmación reforzada para abrir al público; escape HTML; seguimiento; cierre de sesión; límite de login; bloqueo sin datos de privacidad.')
finally:
 proc.terminate();proc.wait(timeout=5);db.close();shutil.rmtree(work)
