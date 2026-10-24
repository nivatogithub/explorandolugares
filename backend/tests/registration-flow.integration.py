"""Client registration through real HTTP endpoints on a built disposable package."""
from pathlib import Path
import argparse, html, http.cookiejar, json, os, re, secrets, socket, sqlite3, subprocess, time, urllib.request, urllib.parse, urllib.error

ROOT=Path(__file__).resolve().parents[2]
parser=argparse.ArgumentParser();parser.add_argument('--package',type=Path,required=True);parser.add_argument('--serve',action='store_true');args=parser.parse_args()
package=args.package.resolve();assert package.is_relative_to(ROOT/'.local') and (package/'build-manifest.json').is_file()
private=package/'private';private.mkdir(exist_ok=True)
if (private/'config.php').exists() and not (private/'registration-fixture.marker').exists():raise SystemExit('Never replace an existing environment configuration.')
(private/'registration-fixture.marker').touch()
database=private/('registration-test-'+secrets.token_hex(4)+'.sqlite')
db=sqlite3.connect(database);db.row_factory=sqlite3.Row
db.executescript((ROOT/'backend/database/sqlite.sql').read_text());db.executescript((ROOT/'backend/database/destinations.sqlite.sql').read_text())
db.executescript((ROOT/'backend/database/migrations/006-poi-experience-links.sqlite.sql').read_text())
for file in sorted((ROOT/'backend/database/migrations').glob('*.sqlite.sql')):
    if int(file.name.split('-')[0])>=8:db.executescript(file.read_text())
password='RegistroLocal2026!';hashed=subprocess.check_output(['php','-r','echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);'],input=password.encode()).decode()
for i,name,status in [(1,'Jericó','published'),(2,'Medellín','published'),(3,'No publicado','draft')]:
    db.execute('INSERT INTO destinations(id,slug,department,publication_status,latitude,longitude) VALUES(?,?,"Antioquia",?,5.791,-75.786)',(i,'fixture-'+str(i),status))
    db.execute('INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(?,"es",?,"","")',(i,name))
db.executescript('INSERT INTO service_categories(code,is_active) VALUES("hospedaje",1);INSERT INTO service_category_translations(category_code,locale,label) VALUES("hospedaje","es","Hospedaje")')
for i,email in [(1,'registro@example.invalid'),(2,'otro@example.invalid')]:db.execute('INSERT INTO accounts(id,public_id,account_type,display_name,email,password_hash,status,email_verified_at) VALUES(?,?,"traveler",?,?,?,"active",CURRENT_TIMESTAMP)',(i,'fixture-account-'+str(i),'Persona de prueba '+str(i),email,hashed))
for i,name in [(1,'Hotel Existente'),(2,'Hotel Con Responsable')]:
    db.execute('INSERT INTO businesses(id,public_id,destination_id,category_code,slug,record_origin) VALUES(?,?,1,"hospedaje",?,"public_import")',(i,'fixture-business-'+str(i),'business-'+str(i)))
    db.execute('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es",?,"","")',(i,name))
db.execute('UPDATE destinations SET latitude=6.2442,longitude=-75.5812 WHERE id=2')
db.execute('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(2,2,"owner","active")');db.commit()
with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
config=dict(dsn='sqlite:'+str(database),app_key='f'*64,local_test=True,registration_enabled=True,business_accounts_enabled=True,privacy=dict(operator='Fixture',contact='fixture@example.invalid',retention='Prueba',retention_en='Test'))
config_json=json.dumps(config).replace("'","\\'")
(private/'config.php').write_text("<?php return json_decode('"+config_json+"',true);\n")
log=(private/'registration-server.log').open('w')
process=subprocess.Popen(['php','-d','disable_functions=mail,curl_exec,fsockopen,pfsockopen,stream_socket_client','-S',f'127.0.0.1:{port}','-t',str(package/'public'),str(ROOT/'scripts/php-destinations-router.php')],env=dict(os.environ,EL_PREPROD_LOCAL='1'),stdout=log,stderr=log)
base=f'http://127.0.0.1:{port}'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args):return None
client=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(path,fields=None,files=None):
    headers={};data=urllib.parse.urlencode(fields or {}).encode() if fields is not None else None
    if files is not None:
        boundary='fixture-registration';parts=[]
        for key,value in (fields or {}).items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
        for key,name,mime,body in files:parts.extend([f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode(),body,b'\r\n'])
        parts.append(f'--{boundary}--\r\n'.encode());data=b''.join(parts);headers={'Content-Type':'multipart/form-data; boundary='+boundary}
    try:
        with client.open(urllib.request.Request(base+path,data,headers),timeout=10) as response:return response.status,response.read().decode(),response.headers
    except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.headers
def csrf(body):return re.search(r'name="csrf" value="([^"]+)"',body)[1]
try:
    for _ in range(100):
        try:request('/negocios/registro/?tipo=business');break
        except urllib.error.URLError:time.sleep(.05)
    _,body,_=request('/negocios/acceso/');assert request('/negocios/acceso/',dict(csrf=csrf(body),email='registro@example.invalid',password=password))[0]==303
    code,body,_=request('/negocios/registro/?tipo=business');assert code==200
    assert 'Medellín' in body and 'No publicado' not in body
    assert 'name="password"' not in body and 'name="display_name"' not in body and 'name="message"' not in body
    assert 'registro@example.invalid' in body
    assert 'name="description"' not in body and body.count('name="summary"')==1
    assert body.index('</fieldset>\n<fieldset id="registration-product-details"') > body.index('id="registration-new-details"')
    rules=json.loads(html.unescape(re.search(r'data-plan-rules="([^"]+)"',body)[1]))
    assert [rules[plan]['description_limit'] for plan in ['free','featured','page','page_premium']]==[0,300,450,1200]
    assert all(rule['identifier_required'] for rule in rules.values())
    token=csrf(body)
    _,found,_=request('/negocios/registro/?tipo=business&search=1&destination_id=1&category=hospedaje&q=Hotel')
    found=json.loads(found)['results'];assert len(found)==2 and any(row['requires_review'] for row in found)
    # A forged identity never replaces the signed-in account. Use a temporary PDF upload exactly like the wizard.
    pdf=b'%PDF-1.4\n1 0 obj<</Type /Catalog>>endobj\n%%EOF'
    for plan in ['free','featured','page','page_premium']:
        _,body,_=request('/negocios/registro/?tipo=business');token=csrf(body)
        fields=dict(csrf=token,account_type='business',application_type='new_business',business_name='Fixture '+plan,destination_id='2',category_code='hospedaje',relationship_code='owner',responsibility_declared='1',consent='1',plan_code=plan,billing_frequency='annual',business_nit='900123456',summary='' if plan=='free' else 'Presentación de prueba',address_text='' if plan=='free' else 'Calle de prueba 10',latitude='' if plan=='free' else '6.2442',longitude='' if plan=='free' else '-75.5812',location_confirmed='0' if plan=='free' else '1',location_map_url='' if plan=='free' else 'https://www.google.com/maps/search/?api=1&query=6.2442,-75.5812',display_name='Identidad falsa',email='intruso@example.invalid')
        fields.update({'business_phone[type]':'mobile','business_phone[number]':'3001234567','business_phone[area]':'604'})
        limit=rules[plan]['description_limit'];fields['summary']='á'*limit
        for channel in ['email','website','instagram','facebook','linkedin']:
            fields['channels['+channel+']']='fixture@example.invalid' if channel=='email' else 'https://example.invalid/'+channel
        files=[('business_registration','fixture.pdf','application/pdf',pdf)]
        if plan in ['page','page_premium']:
            import struct,zlib
            def chunk(t,b):return struct.pack('>I',len(b))+t+b+struct.pack('>I',zlib.crc32(t+b)&0xffffffff)
            png=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',640,480,8,2,0,0,0))+chunk(b'IDAT',zlib.compress((b'\0'+b'\x40\x70\x90'*640)*480))+chunk(b'IEND',b'')
            code,uploaded,_=request('/business/onboarding-upload.php',dict(csrf=token,action='upload',kind='editorial',slot='cover'),[('file','cover.png','image/png',png)]);assert code==200,uploaded
            fields['temporary_uploads[editorial][cover]']=json.loads(uploaded)['upload']['id']
        before=db.execute('SELECT COUNT(*) FROM business_account_applications').fetchone()[0]
        code,result,_=request('/negocios/registro/?tipo=business',dict(fields,business_nit='',business_rnt=''),files)
        assert code==400 and 'Indica el NIT o el RNT' in result,(plan,code)
        if limit:
            code,result,_=request('/negocios/registro/?tipo=business',dict(fields,summary='á'*(limit+1)),files)
            assert code==400 and 'límite de '+str(limit) in result,(plan,code)
        assert db.execute('SELECT COUNT(*) FROM business_account_applications').fetchone()[0]==before
        code,result,headers=request('/negocios/registro/?tipo=business',fields,files)
        assert code==303,(plan,code,re.findall(r'role="alert">(.*?)</p>',result,re.S))
        row=db.execute('SELECT r.business_id,r.account_id,b.publication_status,s.plan_code FROM business_account_applications r JOIN businesses b ON b.id=r.business_id JOIN business_subscriptions s ON s.business_id=b.id WHERE r.business_name=?',('Fixture '+plan,)).fetchone()
        assert row and row['account_id']==1 and row['plan_code']==plan and row['publication_status']=='draft',dict(row) if row else result
        assert db.execute('SELECT COUNT(*) FROM accounts').fetchone()[0]==2
        details=json.loads(db.execute('SELECT business_details_json FROM business_account_applications WHERE business_id=?',(row['business_id'],)).fetchone()[0])
        assert details['text']['summary']=='á'*limit and not details['text']['description']
        assert set(details['channels']).issubset(set(rules[plan]['channels']))
        assert ('email' in details['channels'])==(plan in ['page','page_premium'])
        assert ('instagram' in details['channels'])==(plan=='page_premium')
        payload=json.loads(db.execute('SELECT payload_json FROM business_editorial_versions WHERE business_id=? ORDER BY id DESC LIMIT 1',(row['business_id'],)).fetchone()[0])
        assert payload['translations']['es']['summary']=='á'*limit,'Description lost at editorial handoff'
    print('OK: verified account, places/claim/dispute, four private plans, shared limits 0/300/450/1200, identifier required in all plans, over-limit rejection without writes, cumulative contacts, cover upload and complete description retained in editorial handoff.',flush=True)
    if args.serve:
        print('UI fixture: '+base+' — registro@example.invalid / '+password,flush=True)
        while True:time.sleep(1)
finally:
    process.terminate();process.wait(timeout=10);db.close();log.close()
