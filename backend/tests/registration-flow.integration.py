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
def request(path,fields=None,files=None,accept_json=False):
    headers={'Accept':'application/json'} if accept_json else {};data=urllib.parse.urlencode(fields or {}).encode() if fields is not None else None
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
    assert len(re.findall(r'<textarea[^>]*\bdata-text-counter\b',body))==6,'Counter only on business and five product descriptions'
    assert not re.search(r'<input[^>]*\bdata-text-counter\b',body),'Do not attach counters to names, IDs or contact fields'
    assert body.index('</fieldset>\n<fieldset id="registration-product-details"') > body.index('id="registration-new-details"')
    rules=json.loads(html.unescape(re.search(r'data-plan-rules="([^"]+)"',body)[1]))
    assert [rules[plan]['description_limit'] for plan in ['free','featured','page','page_premium']]==[0,100,300,600]
    assert all(rule['identifier_required'] for rule in rules.values())
    token=csrf(body)
    draft_id=re.search(r'name="registration_draft" value="([^"]+)"',body)[1]
    draft_fields=dict(account_type='business',csrf=token,draft_action='save',registration_draft=draft_id,draft_revision='0',wizard_step='3',application_type='new_business',destination_id='2',category_code='hospedaje',business_name='Fixture page',plan_code='page',billing_frequency='annual',summary='Texto parcial que debe recuperarse',password='DO-NOT-STORE')
    before_businesses=db.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]
    code,result,_=request('/negocios/registro/?tipo=business',draft_fields)
    assert code==200 and json.loads(result)['revision']==1,result
    assert db.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]==before_businesses
    stored=db.execute('SELECT payload_json,wizard_step FROM business_registration_drafts WHERE id=?',(draft_id,)).fetchone()
    assert stored['wizard_step']==3 and 'DO-NOT-STORE' not in stored['payload_json']
    code,resumed,_=request('/negocios/registro/?tipo=business&draft='+draft_id)
    assert code==200 and 'Texto parcial que debe recuperarse' in resumed and 'data-resume-step="3"' in resumed
    code,result,_=request('/negocios/registro/?tipo=business',draft_fields)
    assert code==409 and 'otra pestaña' in result
    code,dashboard,_=request('/negocios/portal/?tab=business')
    assert code==200 and 'Borrador' in dashboard and dashboard.count('data-business-row=')==1
    assert 'Continuar registro' in dashboard and 'Solicitudes asociadas a tu cuenta' not in dashboard
    _,found,_=request('/negocios/registro/?tipo=business&search=1&destination_id=1&category=hospedaje&q=Hotel')
    found=json.loads(found)['results'];assert len(found)==2 and any(row['requires_review'] for row in found)
    # A forged identity never replaces the signed-in account. Use a temporary PDF upload exactly like the wizard.
    pdf=b'%PDF-1.4\n1 0 obj<</Type /Catalog>>endobj\n%%EOF'
    for plan in ['free','featured','page','page_premium']:
        _,body,_=request('/negocios/registro/?tipo=business'+('&draft='+draft_id if plan=='page' else ''));token=csrf(body)
        fields=dict(csrf=token,account_type='business',application_type='new_business',business_name='Fixture '+plan,destination_id='2',category_code='hospedaje',relationship_code='owner',responsibility_declared='1',consent='1',plan_code=plan,billing_frequency='annual',business_nit='900123456',summary='' if plan=='free' else 'Presentación de prueba',address_text='' if plan=='free' else 'Calle de prueba 10',latitude='' if plan=='free' else '6.2442',longitude='' if plan=='free' else '-75.5812',location_confirmed='0' if plan=='free' else '1',location_map_url='' if plan=='free' else 'https://www.google.com/maps/search/?api=1&query=6.2442,-75.5812',display_name='Identidad falsa',email='intruso@example.invalid')
        fields.update({'business_phone[type]':'mobile','business_phone[number]':'3001234567','business_phone[area]':'604'})
        fields.update(registration_draft=re.search(r'name="registration_draft" value="([^"]+)"',body)[1],draft_revision=re.search(r'name="draft_revision" value="([^"]+)"',body)[1])
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
            code,saved,_=request('/negocios/registro/?tipo=business',dict(fields,draft_action='save',wizard_step='5'))
            assert code==200,saved
            fields['draft_revision']=str(json.loads(saved)['revision'])
            _,recovered,_=request('/negocios/registro/?tipo=business&draft='+fields['registration_draft'])
            assert 'cover.png' in recovered and 'data-saved-uploads=' in recovered
            expiry=db.execute('SELECT expires_at FROM business_onboarding_uploads WHERE id=?',(fields['temporary_uploads[editorial][cover]'],)).fetchone()[0]
            assert time.mktime(time.strptime(expiry,'%Y-%m-%d %H:%M:%S'))>time.time()+28*86400,'Saved draft files must outlast the temporary upload window'
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
    registration_template=fields.copy()
    code,dashboard,_=request('/negocios/portal/?tab=business')
    assert code==200 and dashboard.count('data-business-row=')==4,dashboard[:300]
    assert all(term not in dashboard for term in ['Solicitudes asociadas a tu cuenta','Solicitar cancelación','Visibilidad de este negocio','Completar contenido y ver mi borrador'])
    assert 'Historial de pagos' in dashboard and 'data-business-preview' in dashboard
    assert 'data-business-qr-url=' not in dashboard,'Unpublished paid drafts must not expose a public QR'
    assert 'id="business-qr-dialog"' in dashboard and 'Descargar PNG' in dashboard
    assert '/assets/vendor/qrcodegen-v1.8.0.js' in dashboard and '/assets/business-qr.js' in dashboard
    grid=re.search(r'<table class="business-workspace-grid">(.*?)</table>',dashboard,re.S)[1]
    assert 'class="pill' not in grid and '<details' not in grid and 'business-row-message' not in grid,'Actions and state notes must not expand the grid rows'
    assert 'aria-label="Ver tarjeta"' in grid and 'aria-hidden="true"' in grid and 'business-icon-action' in grid
    page=db.execute('SELECT b.id FROM businesses b JOIN business_translations t ON t.business_id=b.id WHERE t.display_name="Fixture page"').fetchone()[0]
    db.execute('INSERT INTO payment_instructions(id,enabled,test_mode,holder,bank,account_number,account_type,breb_key,notes,qr_file,updated_at) VALUES(1,1,1,"Fixture Dev2","Banco ficticio","PRUEBA","savings","","Solo simulación","",CURRENT_TIMESTAMP)');db.commit()
    code,checkout,_=request('/business/payment.php?business='+str(page))
    assert code==200 and 'SIMULACIÓN' in checkout and 'Cómo realizar el pago' in checkout
    assert 'name="plan_code"' not in checkout and 'name="billing_frequency"' not in checkout
    quote=re.search(r'name="quote_token" value="([^"]+)"',checkout)[1]
    amount=db.execute('SELECT amount FROM subscription_plan_prices WHERE plan_code="page" AND billing_frequency="annual"').fetchone()[0]
    payment_fields=dict(csrf=csrf(checkout),quote_token=quote,payment_reference='DEV2-PAYMENT',transaction_date=time.strftime('%Y-%m-%d'),paid_amount=str(amount),payment_method='transfer',source_bank='Banco ficticio')
    code,result,_=request('/business/payment.php?business='+str(page),payment_fields,[('payment_proof','payment.pdf','application/pdf',pdf)])
    assert code==303,result
    evidence_count=db.execute('SELECT COUNT(*) FROM business_subscription_payment_evidence').fetchone()[0]
    assert evidence_count==1
    assert request('/business/payment.php?business='+str(page),payment_fields,[('payment_proof','payment.pdf','application/pdf',pdf)])[0]==303
    assert db.execute('SELECT COUNT(*) FROM business_subscription_payment_evidence').fetchone()[0]==evidence_count
    code,dashboard,_=request('/negocios/portal/?tab=business')
    assert 'Pendiente de validación' in dashboard and 'business/payment.php?business='+str(page)+'"' not in dashboard
    assert 'Fecha de aprobación' in dashboard
    assert request('/business/payment.php?business=2')[0]==404,'Foreign business payment must be unavailable'
    free=db.execute('SELECT b.id FROM businesses b JOIN business_translations t ON t.business_id=b.id WHERE t.display_name="Fixture free"').fetchone()[0]
    def prepared_free():
        raw=subprocess.check_output(['php',str(ROOT/'backend/tests/workspace-publication.fixture.php'),str(database),str(free)],env=dict(os.environ,EL_WORKSPACE_TEST='1'))
        return json.loads(raw)
    prepared=prepared_free()
    code,dashboard,_=request('/negocios/portal/?tab=business')
    assert 'Pendiente de publicación' in dashboard and 'Aprobar y publicar' in dashboard
    assert 'data-owner-changes' in dashboard and 'id="business-changes-dialog"' in dashboard
    assert re.search(r'<textarea name="note"[^>]*minlength="8"[^>]*required',dashboard),'Correction comments stay required outside the grid'
    decision=dict(csrf=csrf(dashboard),business_id=str(free),version_id=str(prepared['id']),payload_hash=prepared['hash'],action='owner_changes',note='Ajustar el nombre de la tarjeta de prueba')
    assert request('/negocios/portal/?tab=business',decision)[0]==303
    assert db.execute('SELECT status FROM business_editorial_versions WHERE id=?',(prepared['id'],)).fetchone()[0]=='changes_requested'
    prepared=prepared_free();_,dashboard,_=request('/negocios/portal/?tab=business')
    decision.update(csrf=csrf(dashboard),action='owner_publish',payload_hash=prepared['hash'])
    assert request('/negocios/portal/?tab=business',dict(decision,payload_hash='wrong'))[0]==400
    code,result,_=request('/negocios/portal/?tab=business',decision)
    assert code==303,result[:500]
    assert db.execute('SELECT publication_status FROM businesses WHERE id=?',(free,)).fetchone()[0]=='published'
    _,published_grid,_=request('/negocios/portal/?tab=business')
    assert 'data-business-qr-url=' not in published_grid,'Publishing Free must not enable a page QR'
    assert request('/negocios/portal/?tab=business',decision)[0]==303,'Repeated approval must be idempotent'
    # Expired file uploads return to the saved wizard, not to a POST-only upload endpoint.
    _,body,_=request('/negocios/registro/?tipo=business')
    resumable=re.search(r'name="registration_draft" value="([^"]+)"',body)[1]
    fields=dict(draft_fields,csrf=csrf(body),registration_draft=resumable,draft_revision='0')
    assert request('/negocios/registro/?tipo=business',fields)[0]==200
    resumed_path='/negocios/registro/?tipo=business&draft='+resumable
    client=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    code,result,_=request('/business/onboarding-upload.php',dict(registration_draft=resumable),accept_json=True)
    assert code==401 and json.loads(result)['login_url']=='/negocios/acceso/'
    _,body,_=request('/negocios/acceso/')
    code,_,headers=request('/negocios/acceso/',dict(csrf=csrf(body),email='registro@example.invalid',password=password))
    assert code==303 and headers['Location']==resumed_path,headers
    code,body,_=request(resumed_path)
    assert code==200 and 'Texto parcial que debe recuperarse' in body
    code,result,_=request('/business/onboarding-upload.php',dict(registration_draft=resumable,csrf='expired'),accept_json=True)
    assert code==401 and json.loads(result)['error']=='authentication_required'
    # A second phase in this disposable SQLite fixture, not a live environment.
    db.execute('DELETE FROM rate_limits');db.commit()
    _,login,_=request('/negocios/acceso/')
    assert request('/negocios/acceso/',dict(csrf=csrf(login),email='otro@example.invalid',password=password))[0]==303
    for coupon,percent in [('PAGINA1MES',100),('PAGINAMITAD',50)]:
        db.execute('INSERT INTO business_promotion_codes(code,percentage,plans_json,periods_json,enabled,created_by) VALUES(?,?,?,?,1,"fixture-admin")',(coupon,percent,json.dumps(['page']),json.dumps(['monthly'])))
        db.execute('INSERT INTO business_promotion_place_scopes(code) VALUES(?)',(coupon,));db.execute('INSERT INTO business_promotion_places(code,destination_id) VALUES(?,2)',(coupon,));db.commit()
        _,body,_=request('/negocios/registro/?tipo=business');token=csrf(body)
        coupon_draft=re.search(r'name="registration_draft" value="([^"]+)"',body)[1]
        code,result,_=request('/negocios/registro/?tipo=business&promotion_quote=1&destination_id=2&code='+coupon+'&plan=page_premium&period=monthly',accept_json=True)
        assert code==400 and 'no aplica' in result
        code,result,_=request('/negocios/registro/?tipo=business&promotion_quote=1&destination_id=1&code='+coupon+'&plan=page&period=monthly',accept_json=True)
        assert code==400 and 'no aplica al lugar' in result
        code,result,_=request('/negocios/registro/?tipo=business&promotion_quote=1&destination_id=2&code='+coupon+'&plan=page&period=monthly',accept_json=True)
        assert code==200,result
        offer=json.loads(result)['offer'];assert offer['percentage']==percent
        promo_fields=dict(registration_template,csrf=token,registration_draft=coupon_draft,draft_revision='0',business_name='Promoción '+coupon,plan_code='page',billing_frequency='monthly',summary='Página comercial ficticia con promoción.',promotion_code=coupon,quoted_amount='0',discount_percentage='100')
        code,uploaded,_=request('/business/onboarding-upload.php',dict(csrf=token,action='upload',kind='editorial',slot='cover'),[('file','cover.png','image/png',png)])
        assert code==200,uploaded
        promo_fields['temporary_uploads[editorial][cover]']=json.loads(uploaded)['upload']['id']
        code,result,_=request('/negocios/registro/?tipo=business',dict(promo_fields,promotion_code='NOEXISTE'),files)
        assert code==400 and db.execute('SELECT COUNT(*) FROM business_promotion_redemptions WHERE code=?',(coupon,)).fetchone()[0]==0,(code,result[:500])
        code,result,headers=request('/negocios/registro/?tipo=business',promo_fields,files)
        assert code==303,(code,re.findall(r'role="alert">(.*?)</p>',result,re.S))
        redemption=db.execute('SELECT * FROM business_promotion_redemptions WHERE code=?',(coupon,)).fetchone();assert redemption and redemption['account_id']==2
        promo_business=redemption['business_id'];promo_subscription=redemption['subscription_id']
        assert redemption['due_cents']==offer['due_cents']
        if percent==100:
            assert headers['Location'].startswith('/negocios/portal/') and 'payment.php' not in headers['Location']
            assert request('/business/payment.php?business='+str(promo_business))[0]==303,'100% bypasses even direct payment URL'
            assert db.execute('SELECT COUNT(*) FROM business_subscription_payment_evidence WHERE subscription_id=?',(promo_subscription,)).fetchone()[0]==0
            sub=db.execute('SELECT * FROM business_subscriptions WHERE id=?',(promo_subscription,)).fetchone()
            assert sub['status']=='paid_pending_publication' and sub['starts_at'] is None and sub['ends_at'] is None
        else:
            assert headers['Location']=='/business/payment.php?business='+str(promo_business)
            for _ in range(2):
                code,checkout,_=request('/business/payment.php?business='+str(promo_business))
                assert code==200 and coupon in checkout
            payment=dict(csrf=csrf(checkout),quote_token=re.search(r'name="quote_token" value="([^"]+)"',checkout)[1],payment_reference='PARTIAL-DISCOUNT',transaction_date=time.strftime('%Y-%m-%d'),paid_amount=str(offer['due_cents']/100),payment_method='transfer',source_bank='Banco ficticio')
            assert request('/business/payment.php?business='+str(promo_business),payment,[('payment_proof','payment.pdf','application/pdf',pdf)])[0]==303
            proof=db.execute('SELECT * FROM business_subscription_payment_evidence WHERE subscription_id=?',(promo_subscription,)).fetchone()
            assert proof and round(proof['paid_amount']*100)==offer['due_cents'],'discount persists on actual receipt upload'
            assert db.execute('SELECT COUNT(*) FROM business_promotion_redemptions WHERE code=?',(coupon,)).fetchone()[0]==1
            assert request('/business/payment.php?business='+str(promo_business),payment,[('payment_proof','payment.pdf','application/pdf',pdf)])[0]==303
    print('OK: real HTTP coupons, invalid plan/code rejected, 100% skips payment without receipt, partial discounted checkout survives refresh and receipt upload; forged totals ignored.',flush=True)
    assert request('/admin/cupones.php')[0]==303,'A merchant must not manage coupons'
    # Administrative role exists only in this disposable fixture, never in Dev2.
    db.execute('INSERT INTO account_roles(account_id,role) VALUES(2,"administrator")');db.commit()
    _,login,_=request('/negocios/acceso/')
    assert request('/negocios/acceso/',dict(csrf=csrf(login),email='otro@example.invalid',password=password))[0]==303
    code,admin,_=request('/admin/cupones.php')
    assert code==200 and 'Últimos usos registrados' in admin and 'PAGINA1MES' in admin and 'Promoción PAGINAMITAD' in admin
    assert 'Lugares donde aplica' in admin and 'Inactivo' in admin
    assert request('/admin/cupones.php',{'csrf':csrf(admin),'action':'delete','code':'PAGINA1MES'})[0]==400
    admin_fields={'csrf':csrf(admin),'code':'ADMIN30','percentage':'30','plans[0]':'page','plans[1]':'featured','periods[0]':'monthly','periods[1]':'annual','destinations[0]':'1','destinations[1]':'2','enabled':'1','max_uses':'3','expires_on':'2030-01-01'}
    assert request('/admin/cupones.php',admin_fields)[0]==303
    row=db.execute('SELECT * FROM business_promotion_codes WHERE code="ADMIN30"').fetchone()
    assert row['percentage']==30 and json.loads(row['plans_json'])==['page','featured'] and row['max_uses']==3
    _,admin,_=request('/admin/cupones.php?edit=PAGINAMITAD')
    assert request('/admin/cupones.php',{'csrf':csrf(admin),'code':'PAGINAMITAD','percentage':'75','plans[0]':'page','periods[0]':'monthly','destinations[0]':'1'})[0]==303
    assert db.execute('SELECT enabled FROM business_promotion_codes WHERE code="PAGINAMITAD"').fetchone()[0]==0
    redemption=db.execute('SELECT percentage,due_cents FROM business_promotion_redemptions WHERE code="PAGINAMITAD"').fetchone()
    assert redemption['percentage']==50 and redemption['due_cents']==offer['due_cents'],'admin edits must not rewrite redeemed terms'
    assert db.execute('SELECT destination_id FROM business_promotion_redemption_places WHERE subscription_id=?',(promo_subscription,)).fetchone()[0]==2,'Original redemption place is immutable'
    code,admin,_=request('/admin/paquete-editorial.php?business='+str(promo_business))
    assert code==200 and 'Cupón aplicado' in admin and 'PAGINAMITAD' in admin and '50 %' in admin
    assert db.execute('SELECT COUNT(*) FROM business_promotion_audit').fetchone()[0]==2
    print('OK: administrator HTTP access, coupon creation with plan/period/limit, disabling, immutable redeemed discount, usage history and coupon visible in the business file.',flush=True)
    print('OK: four-plan registration, private resumable step draft, one customer grid, fixed checkout, payment retry without duplication, owner comments/publication from grid, version-hash protection and repeated approval.',flush=True)
    if args.serve:
        prepared_free() # Include the prepublication action icons in the isolated visual fixture.
        print('UI fixture: '+base+' — registro@example.invalid / '+password,flush=True)
        while True:time.sleep(1)
finally:
    process.terminate();process.wait(timeout=10);db.close();log.close()
