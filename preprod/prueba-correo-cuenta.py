#!/usr/bin/env python3
"""Flujo asistido local. El usuario abre activación y recuperación; nunca se leen sus nuevas claves."""
import argparse,base64,datetime,hashlib,http.cookiejar,json,re,secrets,subprocess,urllib.request,urllib.parse,urllib.error
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];PRIVATE=ROOT/'.local/preprod/private';STATE=PRIVATE/'account-mail-human-flow.json';BASE='http://127.0.0.1:8088'
p=argparse.ArgumentParser();p.add_argument('fase',choices=['iniciar','estado','reenviar','recuperar','finalizar']);a=p.parse_args()
settings=json.loads((PRIVATE/'account-mail-test.json').read_text());email=settings['recipient'];assert settings['enabled'] is True and email=='nivato@msn.com','Destinatario no autorizado para este flujo'
def query(sql,params=[]):
 v=base64.b64encode(json.dumps([sql,params]).encode()).decode()
 source='<?php $c=require "/srv/state/private/config.php";if(empty($c["local_test"]))exit(80);$p=new PDO($c["dsn"],$c["db_user"],$c["db_password"],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$v=json_decode(base64_decode("'+v+'"),true);$q=$p->prepare($v[0]);$q->execute($v[1]);echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));'
 return json.loads(subprocess.check_output(['podman','exec','-i','explorando-preprod','php'],input=source.encode()))
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(path,fields=None):
 try:
  with client.open(BASE+path,data=urllib.parse.urlencode(fields).encode() if fields else None,timeout=20) as r:return r.status,r.read().decode()
 except urllib.error.HTTPError as e:return e.code,e.read().decode()
def csrf(body):return re.search(r'name="csrf"\s+value="([^"]+)"',body)[1]
def account():
 rows=query('SELECT id,status,email_verified_at FROM accounts WHERE email=?',[email]);return rows[0] if rows else None
def persist(data):STATE.write_text(json.dumps(data,ensure_ascii=False,indent=2));STATE.chmod(0o600)
def receipts(kind,since):
 file=PRIVATE/'account-mail-receipts.jsonl';records=[json.loads(x) for x in file.read_text().splitlines()] if file.exists() else []
 return [r for r in records if r['recipient_sha256']==hashlib.sha256(email.encode()).hexdigest() and r['kind']==kind and datetime.datetime.fromisoformat(r['at'].replace('Z','+00:00'))>=datetime.datetime.fromisoformat(since.replace('Z','+00:00'))]
row=account();state=json.loads(STATE.read_text()) if STATE.exists() else {}
if a.fase=='iniciar':
 if row:raise SystemExit('La cuenta ya existe; no se modificó. Estado: '+row['status'])
 state={'email':email,'password':secrets.token_urlsafe(24)+'A9','started_at':datetime.datetime.now(datetime.timezone.utc).isoformat(timespec='seconds').replace('+00:00','Z')};persist(state)
 _,page=call('/negocios/registro/');code,body=call('/negocios/registro/',{'csrf':csrf(page),'account_type':'traveler','display_name':'Prueba De Correo','email':email,'password':state['password'],'password_confirm':state['password'],'consent':'1','residence_country_code':'CO','residence_region_code':'05','residence_city_code':'05368'})
 row=account();print(json.dumps({'registration_http':code,'account_status':row['status'] if row else None,'verification_receipts':receipts('verification',state['started_at'])},ensure_ascii=False))
 if row:
  _,login=call('/negocios/acceso/');code,_=call('/negocios/acceso/',{'csrf':csrf(login),'email':email,'password':state['password']});status=json.loads(call('/api/account-session.php')[1]);assert not status['authenticated'],'Acceso previo a verificación';print('PASS: cuenta pendiente no puede iniciar sesión')
 if not row or code<400:raise SystemExit(1)
elif a.fase=='estado':print(json.dumps({'account':row,'verification_receipts':receipts('verification',state.get('started_at','')),'recovery_receipts':receipts('recovery',state.get('recovery_at','9999-01-01T00:00:00+00:00'))},ensure_ascii=False))
elif a.fase=='reenviar':
 assert row and row['status']=='pending_activation','La cuenta no está pendiente de activación'
 state['resent_at']=datetime.datetime.now(datetime.timezone.utc).isoformat(timespec='seconds');persist(state)
 _,page=call('/cuenta/verificar-correo/');code,_=call('/cuenta/verificar-correo/',{'csrf':csrf(page),'email':email});print(json.dumps({'resend_http':code,'verification_receipts':receipts('verification',state['resent_at'])},ensure_ascii=False))
elif a.fase=='recuperar':
 assert row and row['status']=='active' and row['email_verified_at'],'Esperar a que el usuario active la cuenta'
 assert state.get('email')==email,'Falta estado de prueba propia'
 _,login=call('/negocios/acceso/');code,_=call('/negocios/acceso/',{'csrf':csrf(login),'email':email,'password':state['password']});session=json.loads(call('/api/account-session.php')[1]);assert session['authenticated'] and not session['administrator'],'No inicia sesión o tiene privilegios incorrectos';print('PASS: acceso posterior a activación, identidad y ausencia de rol administrador')
 assert call('/admin/usuarios.php')[0]==403,'Usuario obtuvo acceso administrativo'
 state['recovery_at']=datetime.datetime.now(datetime.timezone.utc).isoformat(timespec='seconds').replace('+00:00','Z');persist(state)
 _,page=call('/negocios/recuperar-clave/');code,_=call('/negocios/recuperar-clave/',{'csrf':csrf(page),'email':email});resets=query('SELECT id FROM account_password_resets WHERE account_id=? ORDER BY id DESC LIMIT 1',[row['id']]);state['reset_id']=resets[0]['id'] if resets else None;persist(state);print(json.dumps({'recovery_http':code,'recovery_receipts':receipts('recovery',state['recovery_at'])},ensure_ascii=False))
else:
 assert row and row['status']=='active' and state.get('recovery_at'),'Flujo aún incompleto'
 resets=query('SELECT used_at FROM account_password_resets WHERE account_id=? AND id=?',[row['id'],state.get('reset_id')]);assert resets and resets[0]['used_at'],'El usuario aún no completó el restablecimiento'
 _,login=call('/negocios/acceso/');code,_=call('/negocios/acceso/',{'csrf':csrf(login),'email':email,'password':state['password']});assert not json.loads(call('/api/account-session.php')[1])['authenticated'],'La contraseña anterior sigue funcionando';print('PASS: enlace consumido, cuenta activa y contraseña anterior rechazada. El usuario debe comprobar acceso con su nueva contraseña.');state['completed_at']=datetime.datetime.now(datetime.timezone.utc).isoformat();state.pop('password',None);persist(state)
