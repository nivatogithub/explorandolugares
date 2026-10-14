"""Prueba privada HTTP de Advance Admin; no llama servicios externos."""
from pathlib import Path
import subprocess, json, secrets, sqlite3, shutil, socket, time, urllib.request, urllib.error, urllib.parse, http.cookiejar, re, base64
root=Path(__file__).resolve().parents[3]
work=root/'.local'/('advance-http-'+secrets.token_hex(6)); work.mkdir(parents=True)
shutil.copytree(root/'backend/app',work/'app');shutil.copytree(root/'backend/public',work/'public');(work/'private').mkdir()
db=sqlite3.connect(work/'private/test.sqlite')
db.executescript((root/'backend/database/sqlite.sql').read_text())
db.executescript((root/'backend/database/destinations.sqlite.sql').read_text())
db.executescript((root/'backend/database/migrations/007-advance-admin.sqlite.sql').read_text())
db.executescript((root/'backend/database/migrations/008-advance-shared-catalog.sqlite.sql').read_text())
db.executescript((root/'backend/database/migrations/010-advance-unsubscribe.sqlite.sql').read_text())
db.executescript((root/'backend/database/migrations/011-admin-reports.sqlite.sql').read_text())
db.executescript((root/'backend/tests/advance/shared-fixture.sql').read_text())
password=secrets.token_urlsafe(24)
hashed=subprocess.check_output(['php','-r','echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_DEFAULT);'],input=password.encode()).decode()
config={'dsn':'sqlite:'+str(work/'private/test.sqlite'),'app_key':secrets.token_hex(32),'admin_user':'advance-local','admin_password_hash':hashed,'local_test':True,'public_base_url':'https://example.invalid'}
encoded=base64.b64encode(json.dumps(config).encode()).decode();(work/'private/config.php').write_text("<?php return json_decode(base64_decode('"+encoded+"'), true);")
with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(work/'public')],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(path,data=None):
    try:
        with client.open(f'http://127.0.0.1:{port}'+path,urllib.parse.urlencode(data).encode() if data is not None else None,timeout=5) as r:return r.status,r.read().decode(),r.headers
    except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.headers
try:
    for _ in range(100):
        try:call('/admin/index.php');break
        except urllib.error.URLError:time.sleep(.05)
    url='/admin/advance/index.php'
    assert call(url)[0]==403
    assert call('/admin/reports.php')[0]==403
    body=call('/admin/index.php')[1];csrf=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
    assert call('/admin/index.php',{'action':'login','username':'advance-local','password':password,'csrf':csrf})[0]==200
    body=call('/admin/index.php')[1];csrf=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
    status,body,_=call('/admin/reports.php');assert status==200 and 'Reportes generales' in body and 'id="overview"' in body and 'id="traffic"' not in body
    status,body,_=call('/admin/reports.php?tab=traffic');assert status==200 and 'Medición no conectada' in body and 'id="overview"' not in body
    assert call('/admin/reports.php',{'action':'subscription','csrf':'bad','confirmed':'1'})[0]==403
    sub={'action':'subscription','csrf':csrf,'confirmed':'1','business_id':1,'reference':'http-local','starts_on':'2026-10-01','ends_on':'2026-11-01'}
    assert call('/admin/reports.php',sub)[0]==200
    assert db.execute('SELECT COUNT(*) FROM business_subscriptions').fetchone()[0]==1
    assert call(url+'?section=contacts',{'action':'save','csrf':'bad'})[0]==403
    contact={'action':'save','csrf':csrf,'name':'<script>test</script>','email':'test@example.invalid','destination_id':'1','source':'Prueba local','relationship':'prospect','marketing_consent':'1','consent_evidence':'Prueba local autorizada'}
    status,body,headers=call(url+'?section=contacts',contact)
    assert status==200 and '&lt;script&gt;test&lt;/script&gt;' in body and '<script>test</script>' not in body
    assert headers['Cache-Control']=='no-store' and headers['X-Robots-Tag']=='noindex, nofollow'
    template={'action':'save','csrf':csrf,'name':'Plantilla local','subject':'Hola {{name}}','html':'<script>alert(1)</script><p>{{municipality}}</p>'}
    status,body,_=call(url+'?section=templates',template);assert status==200 and 'sandbox=""' in body and 'srcdoc="' in body
    assert '<script>alert(1)</script>' not in body and "default-src" in body
    status,body,_=call(url+'?section=campaigns',{'action':'create','csrf':csrf,'name':'Prueba','template_id':1,'contact_ids':'1'});assert status==200 and 'Borrador' in body
    assert call(url+'?section=campaigns',{'action':'approve','csrf':csrf,'id':1,'version':1})[0]==400
    assert call(url+'?section=campaigns',{'action':'approve','csrf':csrf,'id':1,'version':1,'confirm':'1'})[0]==200
    assert call(url+'?section=campaigns',{'action':'send','csrf':csrf,'delivery_id':1,'confirm_send':'1'})[0]==400
    assert db.execute('SELECT status FROM advance_deliveries').fetchone()[0]=='pending'
    assert call(url+'?section=dashboard')[0]==200
    status,body,_=call(url+'?section=establishments');assert status==200 and 'Negocio existente' in body
    status,body,_=call(url+'?section=establishments&id=1');assert status==200 and 'business@example.invalid' in body
    revision=re.search(r'name="revision" value="([^"]+)"',body).group(1)
    assert call(url+'?section=establishments',{'action':'link_contact','csrf':csrf,'id':1,'revision':revision,'contact_id':1})[0]==200
    assert db.execute('SELECT contact_id FROM advance_business_crm WHERE business_id=1').fetchone()[0]==1
    assert db.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]==1
    record={'action':'save','csrf':csrf,'id':0,'name':'Nuevo negocio de prueba','destination_id':1,'category_code':'cultura','slug':'nuevo-negocio-prueba','publication_status':'draft','source_note':'Prueba local'}
    assert call(url+'?section=establishments',record)[0]==200
    assert db.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]==2
    assert call(url+'?section=establishments',record)[0]==503 # unicidad por destino/slug
    assert db.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]==2
    assert call('/admin/index.php',{'action':'logout','csrf':csrf})[0]==200
    assert call(url)[0]==403
    unsubscribe_url=db.execute('SELECT unsubscribe_url FROM advance_deliveries WHERE id=1').fetchone()[0]
    signed=urllib.parse.parse_qs(urllib.parse.urlsplit(unsubscribe_url).query)['token'][0]
    path='/api/unsubscribe.php?token='+urllib.parse.quote(signed)
    assert call('/api/unsubscribe.php?token='+signed[:-1]+('0' if signed[-1]!='0' else '1'))[0]==400
    status,body,headers=call(path);assert status==200 and 'Confirma' in body and 'test@example.invalid' not in body
    assert headers['Referrer-Policy']=='no-referrer' and headers['Cache-Control']=='no-store'
    assert db.execute('SELECT suppressed FROM advance_contacts WHERE id=1').fetchone()[0]==0
    recipient_csrf=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
    assert call('/api/unsubscribe.php',{'token':signed,'csrf':'bad'})[0]==403
    status,body,_=call('/api/unsubscribe.php',{'token':signed,'csrf':recipient_csrf});assert status==200 and 'excluido' in body
    assert db.execute('SELECT suppressed,marketing_consent FROM advance_contacts WHERE id=1').fetchone()==(1,0)
    assert db.execute('SELECT status FROM advance_deliveries WHERE id=1').fetchone()[0]=='skipped'
    assert call('/api/unsubscribe.php',{'token':signed,'csrf':recipient_csrf})[0]==200
    assert db.execute('SELECT COUNT(*) FROM advance_email_suppressions').fetchone()[0]==1
    print('OK: HTTP privado, CSRF, escape HTML, preview sandbox, campañas aprobadas manualmente, Resend deshabilitado conserva pendientes, logout y baja pública confirmada con CSRF.')
finally:
    proc.terminate();proc.wait(timeout=5);db.close();shutil.rmtree(work)
