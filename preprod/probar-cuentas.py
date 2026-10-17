#!/usr/bin/env python3
"""Exercise local recovery/login/logout with an isolated synthetic account; no real mail."""
import fcntl,http.cookiejar,json,re,secrets,subprocess,urllib.request,urllib.parse,urllib.error
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8088';email='test-auth-'+secrets.token_hex(8)+'@example.invalid'
password='LocalTestPassword123';new_password='AnotherLocalPassword123'
def php(code):
    source='<?php $c=require "/srv/state/private/config.php"; $p=new PDO($c["dsn"],$c["db_user"],$c["db_password"]); '+code
    return subprocess.check_output(['podman','exec','-i','explorando-preprod','php'],input=source.encode()).decode()
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(path,fields=None):
    data=None if fields is None else urllib.parse.urlencode(fields).encode()
    try:
        with client.open(BASE+path,data=data,timeout=30) as r:return r.status,r.read().decode()
    except urllib.error.HTTPError as e:return e.code,e.read().decode()
def csrf(body):return re.search(r'name="csrf" value="([^"]+)"',body)[1]
account=None
try:
    assert call('/negocios/registro/')[0]==200
    account=int(php('$q=$p->prepare("INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status) VALUES(?,\'business\',?, ?, ?,\'active\')"); $q->execute([bin2hex(random_bytes(16)),"Prueba automática local",'+json.dumps(email)+',password_hash('+json.dumps(password)+',PASSWORD_DEFAULT)]); echo $p->lastInsertId();'))
    status,body=call('/negocios/recuperar-clave/');assert status==200
    status,body=call('/negocios/recuperar-clave/',{'csrf':csrf(body),'email':email});assert status==200 and 'Si existe una cuenta' in body
    mailbox=ROOT/'.local/preprod/private/mailbox.json'
    messages=json.loads(mailbox.read_text());message=next(m for m in reversed(messages) if m['to']==email)
    token=urllib.parse.parse_qs(urllib.parse.urlsplit(message['url']).query)['token'][0]
    status,body=call('/negocios/cambiar-clave/?token='+token);assert status==200 and 'Nueva contraseña' in body
    status,body=call('/negocios/cambiar-clave/',{'csrf':csrf(body),'token':token,'password':new_password,'password_confirm':new_password});assert status==200 and 'Contraseña actualizada' in body
    assert 'Enlace vencido o no válido' in call('/negocios/cambiar-clave/?token='+token)[1]
    status,body=call('/negocios/acceso/');assert status==200 and 'Olvidé mi contraseña' in body
    status,body=call('/negocios/acceso/',{'csrf':csrf(body),'email':email,'password':new_password});assert status==200 and 'Solicitudes asociadas a tu cuenta' in body
    status,body=call('/business/logout.php',{'csrf':csrf(body)});assert status==200 and 'Inicia sesión' in body
    assert 'Inicia sesión' in call('/negocios/portal/')[1]
    print('OK: public registration available; recovery form/local mailbox; one-use reset; login with new password; logout revokes portal access. No real mail sent.')
finally:
    if account is not None:
        php('$p->beginTransaction();$p->prepare("DELETE FROM account_password_resets WHERE account_id=?")->execute(['+str(account)+']);$p->prepare("DELETE FROM accounts WHERE id=? AND email=?")->execute(['+str(account)+','+json.dumps(email)+']);$p->commit();')
    mailbox=ROOT/'.local/preprod/private/mailbox.json'
    if mailbox.exists():
        with mailbox.open('r+') as f:
            fcntl.flock(f,fcntl.LOCK_EX);messages=json.load(f);f.seek(0);json.dump([m for m in messages if m['to']!=email],f);f.truncate();fcntl.flock(f,fcntl.LOCK_UN)
