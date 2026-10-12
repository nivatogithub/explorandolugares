#!/usr/bin/env python3
"""Pruebas HTTP con SQLite aislada, sin datos o credenciales de producción."""
from pathlib import Path
import subprocess, json, secrets, sqlite3, shutil, socket, time, urllib.request, urllib.error, urllib.parse, http.cookiejar, re, base64
root=Path(__file__).resolve().parents[2]
work=root/'.local'/('test-'+secrets.token_hex(6));work.mkdir(parents=True)
shutil.copytree(root/'backend/app',work/'app');shutil.copytree(root/'backend/public',work/'public');(work/'private').mkdir()
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
 payload=urllib.parse.urlencode(data).encode() if data is not None else None
 try:
  with agent.open(base+path,payload,timeout=10) as resp:return resp.status,resp.read().decode(),resp.headers
 except urllib.error.HTTPError as exc:return exc.code,exc.read().decode(),exc.headers
try:
 for _ in range(100):
  try:call('/api/participacion.php');break
  except urllib.error.URLError:time.sleep(.05)
 status,body,_=call('/api/participacion.php');assert status==200 and not json.loads(body)['enabled']
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
 for value in ['0','', ['1']]:
  assert call('/api/participacion.php',{**valid,'understanding':value})[0]==422
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
 status,body,headers=call('/admin/index.php');assert '<script>alert(1)</script>' not in body and 'name="password"' in body
 csrf=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
 assert call('/admin/index.php',{'action':'status','csrf':csrf,'id':1,'status':'contactado'})[0]==403
 status,body,headers=call('/admin/index.php',{'action':'login','csrf':csrf,'username':'local-test','password':password})
 assert status==200 and '&lt;script&gt;alert(1)&lt;/script&gt;' in body and '<script>alert(1)</script>' not in body
 assert 'WhatsApp' in body and 'Celular' in body and 'Fijo' in body and 'participacion-informativa-v1' in body
 status,body,_=call('/admin/index.php?tab=sistema')
 assert status==200 and 'Sistema y base de datos' in body and 'Conectada' in body and 'Tabla lista' in body
 assert 'test.sqlite' not in body and config['app_key'] not in body
 csrf=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
 assert call('/admin/index.php',{'action':'status','csrf':'incorrecto','id':1,'status':'contactado'})[0]==403
 assert call('/admin/index.php',{'action':'status','csrf':csrf,'id':1,'status':'contactado'})[0]==200
 assert db.execute('SELECT status FROM leads').fetchone()[0]=='contactado'
 status,body,_=call('/admin/index.php',{'action':'logout','csrf':csrf});assert status==200 and 'name="password"' in body
 # Sesión nueva y límite de intentos compartido por IP.
 login_csrf=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
 for _ in range(10):status,_,_=call('/admin/index.php',{'action':'login','csrf':login_csrf,'username':'invalido','password':'invalida'})
 assert status==429
 config['privacy']['operator']='';save()
 assert not json.loads(call('/api/participacion.php')[1])['enabled']
 print('OK: registro deshabilitado sin configuración; consentimiento obligatorio; correo y teléfono válidos; fijo, celular y WhatsApp sin correo; prefijo calculado en servidor; validación; CSRF; guardado real; reintento sin duplicados; acceso privado; escape HTML; seguimiento; cierre de sesión; límite de login; bloqueo sin datos de privacidad.')
finally:
 proc.terminate();proc.wait(timeout=5);db.close();shutil.rmtree(work)
