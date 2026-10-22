#!/usr/bin/env python3
"""HTTP regression checks for authenticated admin pages using a disposable SQLite DB."""
from pathlib import Path
import re, os
from html import unescape
import base64, json, secrets, shutil, socket, sqlite3, subprocess, time
from datetime import date, timedelta
import http.cookiejar
import urllib.error, urllib.parse, urllib.request

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT / '.local' / ('admin-test-' + secrets.token_hex(6))
WORK.mkdir(parents=True)
shutil.copytree(ROOT / 'backend/app', WORK / 'app')
shutil.copytree(ROOT / 'backend/public', WORK / 'public')
for renderer in ('destino.php', 'negocio.php'):
    shutil.copy2(ROOT / 'backend/integration-medicion/public' / renderer, WORK / 'public' / renderer)
shutil.copytree(ROOT / 'ensayos/v1/assets', WORK / 'public/assets', dirs_exist_ok=True)
(WORK / 'app/content').mkdir(parents=True, exist_ok=True)
shutil.copy2(ROOT / 'content/plantilla-importar-negocios.json', WORK / 'app/content/plantilla-importar-negocios.json')
(WORK / 'private').mkdir()
DB_PATH = WORK / 'private/test.sqlite'
DB = sqlite3.connect(DB_PATH)
DB.row_factory = sqlite3.Row
DB.execute('PRAGMA foreign_keys=ON')
DB.executescript((ROOT / 'backend/database/sqlite.sql').read_text())
DB.executescript((ROOT / 'backend/database/destinations.sqlite.sql').read_text())
DB.executescript((ROOT / 'backend/database/migrations/006-poi-experience-links.sqlite.sql').read_text())
for number in range(8, 50):
    matches = list((ROOT / 'backend/database/migrations').glob(f'{number:03d}-*.sqlite.sql'))
    assert len(matches) == 1, f'Migración SQLite faltante o ambigua: {number:03d}'
    DB.executescript(matches[0].read_text())

DB.execute("INSERT INTO destinations(slug,publication_status,display_order,latitude,longitude) VALUES('jerico','published',1,5.791,-75.786)")
destination_id = DB.execute('SELECT id FROM destinations').fetchone()[0]
DB.execute("INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(?,?,?,?,?)", (destination_id, 'es', 'Jericó', 'Jericó', 'Destino de prueba'))
DB.execute("INSERT INTO service_categories(code,display_order,is_active) VALUES('hospedaje',1,1)")
DB.execute("INSERT INTO service_category_translations(category_code,locale,label) VALUES('hospedaje','es','Hospedaje')")
DB.execute("INSERT INTO service_categories(code,display_order,is_active) VALUES('experiencias',3,1)")
DB.execute("INSERT INTO service_categories(code,display_order,is_active) VALUES('alimentacion',2,1)")
DB.execute("INSERT INTO service_category_translations(category_code,locale,label) VALUES('alimentacion','es','Restaurante')")
DB.execute("INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status) VALUES(?,?,?,?,?,?,?,?,?)", ('00000000-0000-4000-8000-000000000001', destination_id, 'hospedaje', 'public_import', 'hotel-prueba', 'VERIFICADO', 'NO_RECLAMADO', 'ABIERTO', 'published'))
business_id = DB.execute('SELECT id FROM businesses').fetchone()[0]
DB.execute("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)", (business_id, 'es', 'Hotel de prueba', '', ''))
DB.execute("INSERT INTO business_sources(business_id,source_url,note) VALUES(?,?,?)", (business_id, 'https://example.invalid/hotel', 'Fuente ficticia'))
DB.execute("INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,starts_at,ends_at,reviewed_at,quoted_amount,currency) VALUES(?,?,?,?,datetime('now'),datetime('now','+1 year'),datetime('now'),?,'COP')", (business_id, 'basic', 'monthly', 'active', 19900))
previous_subscription_id = DB.execute('SELECT last_insert_rowid()').fetchone()[0]
DB.execute("UPDATE business_subscriptions SET status='expired',starts_at=datetime('now','-1 year'),ends_at=datetime('now','-20 days') WHERE id=?", (previous_subscription_id,))
DB.execute("INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,starts_at,ends_at,quoted_amount,currency,renewal_of_subscription_id) VALUES(?,?,?,'active',datetime('now','-25 days'),datetime('now','+5 days'),?, 'COP', ?)", (business_id, 'page', 'monthly', 69900, previous_subscription_id))
previous_subscription_id = DB.execute('SELECT last_insert_rowid()').fetchone()[0]
DB.execute("UPDATE business_subscriptions SET status='expired',starts_at=datetime('now','-1 year'),ends_at=datetime('now','-20 days') WHERE id=?", (previous_subscription_id,))
DB.execute("INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,starts_at,ends_at,quoted_amount,currency,renewal_of_subscription_id) VALUES(?,?,?,'active',datetime('now','-25 days'),datetime('now','+5 days'),?, 'COP', ?)", (business_id, 'page', 'monthly', 69900, previous_subscription_id))
DB.execute("INSERT INTO business_claim_requests(business_id,claimant_name,email,relationship_code,message,consent_version,status) VALUES(?,?,?,?,?,?,?)", (business_id, 'Persona de prueba', 'persona@example.invalid', 'owner', 'Solicitud ficticia', 'test-v1', 'pending'))
DB.execute("INSERT INTO accounts(public_id,account_type,display_name,email,status) VALUES(?,?,?,?,?)", ('00000000-0000-4000-8000-000000000002', 'business', 'Cuenta de prueba', 'cuenta@example.invalid', 'pending_activation'))
account_id = DB.execute('SELECT id FROM accounts').fetchone()[0]
DB.execute("INSERT INTO business_account_applications(account_id,application_type,destination_id,category_code,business_name,relationship_code,contact_phone,message,consent_version,status) VALUES(?,?,?,?,?,?,?,?,?,?)", (account_id, 'request_new', destination_id, 'hospedaje', 'Nuevo negocio de prueba', 'owner', '', 'Solicitud ficticia', 'test-v1', 'pending'))
# More than one review page: these fixtures verify that selecting all filtered rows
# reaches the whole result set even though only 25 records render on each page.
for index in range(1, 28):
    name = f'Hotel de lote {index:03d}'
    DB.execute("INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status) VALUES(?,?,?,'public_import',?,'PUBLICO','NO_RECLAMADO','DESCONOCIDO','draft')", (f'00000000-0000-4000-8000-{index + 100:012d}', destination_id, 'hospedaje', f'hotel-lote-prueba-{index:02d}'))
    review_business_id = DB.execute('SELECT id FROM businesses WHERE slug=?', (f'hotel-lote-prueba-{index:02d}',)).fetchone()[0]
    for locale in ('es', 'en', 'pt-BR'):
        DB.execute('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)', (review_business_id, locale, name, '', ''))
    DB.execute("INSERT INTO business_subscriptions(business_id,plan_code,status) VALUES(?,'free','pending_publication')", (review_business_id,))
DB.execute("INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status) VALUES(?,?,?,?,?,?,?,?,?)", ('00000000-0000-4000-8000-000000000099', destination_id, 'hospedaje', 'admin_created', 'negocio-sin-suscripcion', 'PENDIENTE_REVISION', 'NO_RECLAMADO', 'DESCONOCIDO', 'draft'))
no_subscription_business_id = DB.execute('SELECT last_insert_rowid()').fetchone()[0]
DB.execute("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,'es','Negocio sin suscripción','', '')", (no_subscription_business_id,))
DB.execute("INSERT INTO leads(request_key,name,business,municipality,category,has_brand,description,interest,consent_version,created_at) VALUES('admin-repair','Persona ficticia','Consulta <script>alert(1)</script>','Jericó','hospedaje','no','Mensaje sintético','negocio','test','2026-10-20 00:00:00')")
DB.execute("UPDATE leads SET email='consulta@example.invalid',wants_contact=1 WHERE request_key='admin-repair'")
DB.commit()
expected_prices = {'basic': {'monthly': 19900, 'annual': 199000}, 'featured': {'monthly': 49900, 'annual': 499000}, 'page': {'monthly': 69900, 'annual': 699000}, 'page_premium': {'monthly': 119900, 'annual': 1199000}}
for plan, frequencies in expected_prices.items():
    for frequency, amount in frequencies.items():
        actual = DB.execute('SELECT amount,currency FROM subscription_plan_prices WHERE plan_code=? AND billing_frequency=?', (plan, frequency)).fetchone()
        assert actual and actual['amount'] == amount and actual['currency'] == 'COP', f'Tarifa acordada ausente o alterada: {plan}/{frequency}'
for frequency, multiplier in [('quarterly', 2.5), ('semiannual', 5.2), ('annual', 10)]:
    for plan in ('featured','page','page_premium'):
        monthly=expected_prices[plan]['monthly']
        amount=DB.execute('SELECT amount,currency FROM subscription_plan_prices WHERE plan_code=? AND billing_frequency=?',(plan,frequency)).fetchone()
        assert amount and amount['amount']==round(monthly*multiplier,2) and amount['currency']=='COP', f'Tarifa aprobada incorrecta: {plan}/{frequency}'
        policy=DB.execute('SELECT billable_months FROM subscription_pricing_policy WHERE period_code=?',(frequency,)).fetchone()
        assert policy and float(policy[0])==multiplier, f'Política aprobada incorrecta: {frequency}'

password = secrets.token_urlsafe(24)
password_hash = subprocess.check_output(['php', '-r', 'echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_DEFAULT);'], input=password.encode()).decode()
config = {'dsn': 'sqlite:' + str(DB_PATH), 'app_key': secrets.token_hex(32), 'admin_user': 'admin-local', 'admin_password_hash': password_hash, 'local_test': True, 'registration_enabled': False, 'privacy': {'operator': 'Operador de prueba', 'contact': 'test@example.invalid', 'retention': 'Prueba', 'retention_en': 'Test'}}
config_file = WORK / 'private/config.php'
config_file.write_text("<?php return json_decode(base64_decode('" + base64.b64encode(json.dumps(config).encode()).decode() + "'), true);\n")

def save_config():
    config_file.write_text("<?php return json_decode(base64_decode('" + base64.b64encode(json.dumps(config).encode()).decode() + "'), true);\n")

subprocess.run(['php', str(WORK / 'app/analytics/install-basic.php')], check=True, capture_output=True)

with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    PORT = sock.getsockname()[1]
TEST_ROUTER=WORK/'router.php'
TEST_ROUTER.write_text("<?php $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if($path==='/cuenta/verificar-correo/'||$path==='/cuenta/verificar-correo'){require __DIR__.'/public/business/verify-email.php';return true;}return require "+repr(str(ROOT/'scripts/php-destinations-router.php'))+";")
(WORK / 'public/api/fault-probe.php').write_text("<?php require dirname(__DIR__,2).'/app/bootstrap.php'; throw new RuntimeException('secret@example.invalid password=DO_NOT_EXPOSE');")
PROCESS = subprocess.Popen(['php', '-d', 'opcache.enable=0', '-d', 'disable_functions=mail,curl_exec,fsockopen,pfsockopen,stream_socket_client', '-S', f'127.0.0.1:{PORT}', '-t', str(WORK / 'public'), str(TEST_ROUTER)], env=os.environ | {'EL_DESTINATIONS_DSN': 'sqlite:' + str(DB_PATH), 'EL_PREPROD_LOCAL':'1'}, stdout=subprocess.DEVNULL, stderr=(WORK/'server.log').open('w'))
BASE = f'http://127.0.0.1:{PORT}'
ADMIN = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
GUEST = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(path, fields=None, opener=None):
    opener = opener or ADMIN
    data = urllib.parse.urlencode(fields, doseq=True).encode() if fields is not None else None
    try:
        with opener.open(BASE + path, data, timeout=10) as response:
            return response.status, response.read().decode(), response.headers
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode(), error.headers

def multipart(path, fields, files, opener=None):
    opener = opener or ADMIN
    boundary = '----eltest' + secrets.token_hex(12)
    chunks = []
    for name, value in fields.items():
        values = value if isinstance(value, list) else [value]
        for item in values:
            chunks += [f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{item}\r\n'.encode()]
    for name, filename, mime, body in files:
        chunks += [f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode(), body, b'\r\n']
    chunks += [f'--{boundary}--\r\n'.encode()]
    req = urllib.request.Request(BASE + path, data=b''.join(chunks), headers={'Content-Type': 'multipart/form-data; boundary=' + boundary})
    try:
        with opener.open(req, timeout=10) as response:
            return response.status, response.read().decode(), response.headers
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode(), error.headers

def check(ok, message):
    if not ok:
        raise AssertionError(message)

def verify_account_mail(email, client):
    row=DB.execute('SELECT id,status,email_verified_at FROM accounts WHERE email=?',(email,)).fetchone()
    check(row and row['status']=='pending_activation' and not row['email_verified_at'],'Cuenta se activó antes de verificar correo.')
    messages=json.loads((WORK/'private/mailbox.json').read_text())
    message=next((m for m in reversed(messages) if m['to']==email and m.get('kind')=='verification'),None)
    check(message,'No llegó activación al buzón local.')
    link=urllib.parse.urlsplit(message['url']);check(link.netloc==f'127.0.0.1:{PORT}','Enlace sale del servidor aislado.')
    status,body,_=request('/business/verify-email.php?'+link.query,opener=client)
    check(status==200 and 'Correo verificado' in body,'No activó por enlace de correo.')
    check(request('/business/verify-email.php?'+link.query,opener=client)[0]==400,'Token reutilizable.')

if os.environ.get('EL_ADMIN_REPAIR_PREVIEW')=='1':
    config['registration_enabled']=True;config['business_accounts_enabled']=True;save_config()
    DB.execute('UPDATE accounts SET status="active",email_verified_at=CURRENT_TIMESTAMP,password_hash=? WHERE id=?',(password_hash,account_id));DB.commit()
    preview=ROOT/'.local/admin-repair-preview.json'
    preview.write_text(json.dumps({'url':BASE,'username':'admin-local','password':password,'business_email':'cuenta@example.invalid','fixture':str(WORK),'pid':PROCESS.pid}))
    preview.chmod(0o600)
    print('Vista aislada: '+BASE,flush=True)
    try:
        while preview.exists():time.sleep(1)
    finally:
        PROCESS.terminate();PROCESS.wait(timeout=5);DB.close();shutil.rmtree(WORK)
    raise SystemExit(0)

try:
    for _ in range(100):
        try:
            request('/admin/index.php', opener=GUEST)
            break
        except urllib.error.URLError:
            time.sleep(.05)

    status, body, _ = request('/destinos/jerico/', opener=GUEST)
    check(status == 200 and 'https://explorandolugares.com/destinos/jerico/' in body and '"/v1/' not in body, 'Guía local debe usar rutas de producción sin prefijos.')
    status, body, _ = request('/negocios/00000000-0000-4000-8000-000000000001/', opener=GUEST)
    check(status == 200 and '"/v1/' not in body, 'Página propia debe usar rutas raíz.')
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, req, fp, code, msg, headers, newurl):
            return None
    direct = urllib.request.build_opener(NoRedirect())
    for source, target in [('/destino.php?destino=jerico&idioma=es', '/destinos/jerico/'), ('/negocio.php?id=00000000-0000-4000-8000-000000000001', '/negocios/00000000-0000-4000-8000-000000000001/')]:
        status, _, headers = request(source, opener=direct)
        check(status == 301 and headers.get('Location') == target, 'Plantilla debe redirigir a ruta limpia idéntica en local y producción.')
    check(request('/v1/destinos/jerico/', opener=GUEST)[0] == 404, 'El prefijo retirado no debe seguir como alias de pruebas.')

    status, body, _ = request('/api/cobertura.php', opener=GUEST)
    check(status == 200 and 'audience' in json.loads(body), 'API pública debe ofrecer el resumen agregado de audiencia aprobado.')

    # Sitemap is public read-only: no bootstrap session or private robots header.
    status, body, headers = request('/sitemap-live.php', opener=GUEST)
    check(status == 200 and '/destinos/jerico/' in body, 'Sitemap debe leer publicaciones de la base.')
    check('/negocios/00000000-0000-4000-8000-000000000001/' in body, 'Sitemap debe incluir página propia vigente.')
    check('Set-Cookie' not in headers and 'noindex' not in headers.get('X-Robots-Tag', ''), 'Sitemap no debe crear sesión ni bloquear indexación.')
    DB.execute("UPDATE destinations SET publication_status='draft' WHERE id=?", (destination_id,)); DB.commit()
    status, body, _ = request('/sitemap-live.php', opener=GUEST)
    check(status == 200 and '/destinos/jerico/' not in body and '/negocios/' not in body, 'Despublicar debe retirar URL sin reconstrucción.')
    DB.execute("UPDATE destinations SET publication_status='published' WHERE id=?", (destination_id,)); DB.commit()

    fault_status,fault_body,_=request('/api/fault-probe.php',opener=GUEST)
    check(fault_status==503 and 'EL-' in fault_body,'Fallo no controlado debe dar referencia y 503.')
    check('secret@' not in fault_body and 'DO_NOT_EXPOSE' not in fault_body,'Respuesta de fallo filtró datos.')
    incident_files=list((WORK/'private/incidents').glob('*.jsonl'))
    check(incident_files,'Falta registro central del fallo no controlado.')
    check(all('DO_NOT_EXPOSE' not in p.read_text() and 'secret@' not in p.read_text() for p in incident_files),'Incidencias filtraron mensajes originales.')
    paths = [
        ('/admin/index.php', 'Solicitudes de participación'),
        ('/admin/index.php?tab=sistema', 'Solicitudes de participación'),
        ('/admin/base-datos.php', 'Base de datos'),
        ('/admin/destinos.php', 'Administrar destinos'),
        ('/admin/trasladar-negocios.php', 'Trasladar negocios a Cauca Viejo'),
        ('/admin/negocios.php', 'Negocios'),
        ('/admin/negocios.php?tab=crear', 'Negocios'),
        ('/admin/negocios.php?tab=importar', 'Negocios'),
        ('/admin/negocios.php?tab=revisar', 'Publicar importaciones Gratis sin representante'),
        ('/admin/importar-negocios.php', 'Importar varios negocios'),
        ('/admin/importar-negocios.php?plantilla=1', 'schema_version'),
        ('/admin/reclamaciones.php', 'Solicitudes anteriores de representación'),
        ('/admin/solicitudes-cuenta.php', 'Revisión de negocios'),
        ('/admin/usuarios.php', 'Cuentas'),
        ('/admin/atencion-comercios.php', 'Atención a comercios'),
        ('/admin/atencion-comercios.php?view=review', 'Revisión y publicación'),
        ('/admin/incidencias.php', 'Errores de ejecución'),
        ('/admin/reportes.php', 'Reportes'),
        ('/admin/solicitudes-publicas.php', 'Solicitudes de usuarios'),
        ('/admin/suscripciones.php', 'Suscripciones'),
        ('/admin/inversionistas.php', 'Inteligencia de negocio e inversión'),
        ('/admin/contactos-negocios.php', 'Gestión comercial'),
        ('/admin/campanas.php', 'Campañas por correo'),
        ('/admin/correos.php', 'Plantillas y correos'),
        ('/admin/campanas.php?tab=conexion', 'Remitente y conexión'),
        ('/admin/campanas.php?tab=correos', 'Negocios destinatarios'),
    ]
    # The login is public; all operational pages require an authenticated session.
    for path, _ in paths:
        status, body, _ = request(path, opener=GUEST)
        if path.startswith('/admin/index.php'):
            check(status == 200 and 'name="password"' in body, f'El visitante debe ver el login en {path}.')
        else:
            check(status == 403, f'El visitante anónimo obtuvo {status} en {path}, esperaba 403.')

    login_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', request('/admin/index.php', opener=GUEST)[1]).group(1)
    status, body, _ = request('/admin/index.php', {'action': 'login', 'csrf': login_csrf, 'username': 'admin-local', 'password': password}, opener=GUEST)
    check(status == 200 and 'Cerrar sesión' in body, f'El login válido no abrió el panel: {status}')
    ADMIN = GUEST
    GUEST = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    # Private detail and informational link must not confer permissions or publish.
    detail='/admin/solicitud-detalle.php?type=lead&id=1'
    check(request(detail,opener=GUEST)[0]==403,'El detalle debe ser privado.')
    check(request('/admin/solicitud-detalle.php?type=lead&id=99999')[0]==404,'ID inexistente debe devolver 404.')
    detail_html=request(detail)[1]
    check('&lt;script&gt;' in detail_html and '<script>alert(1)</script>' not in detail_html,'Escape de solicitud.')
    check('Enviado por la persona' in request('/admin/solicitud-detalle.php?type=account&id=1')[1],'Detalle de cuenta con evidencia enviada.')
    link_csrf=re.search(r'name="csrf" value="([^"]+)"',request(detail+'&search=Hotel')[1]).group(1)
    check(request(detail,{'csrf':'invalid','action':'link','business_id':business_id,'confirm':'1'})[0]==403,'Vínculo exige CSRF.')
    before_memberships=DB.execute('SELECT COUNT(*) FROM business_account_memberships').fetchone()[0]
    before_publications=DB.execute('SELECT COUNT(*) FROM businesses WHERE publication_status="published"').fetchone()[0]
    for attempt in range(2):
        check(request(detail,{'csrf':link_csrf,'action':'link','business_id':business_id,'confirm':'1'})[0]==200,'Guardar vínculo informativo.')
    check(DB.execute('SELECT COUNT(*) FROM lead_business_links').fetchone()[0]==1,'Vínculo repetido no duplica.')
    check(DB.execute('SELECT COUNT(*) FROM business_account_memberships').fetchone()[0]==before_memberships,'Vincular no otorga permisos.')
    check(DB.execute('SELECT COUNT(*) FROM businesses WHERE publication_status="published"').fetchone()[0]==before_publications,'Vincular no publica.')
    status_csrf=re.search(r'name="csrf" value="([^"]+)"',request('/admin/index.php')[1]).group(1)
    body=request('/admin/index.php',{'csrf':status_csrf,'action':'status','id':'1','status':'archivado','return_status':'nuevo','return_q':'Consulta','return_page':'99'})[1]
    check('dejó de aparecer' in body and 'Página 1' in body,'Estado conserva contexto y ajusta página.')
    body=request('/admin/index.php',{'csrf':status_csrf,'action':'status','id':'9999','status':'nuevo'})[1]
    check('ya no existe' in body,'ID ausente no anuncia éxito.')

    # Individual reply preview is private and local delivery is always simulated.
    reply_url='/admin/responder-solicitud.php?lead=1'
    check(request(reply_url,opener=GUEST)[0]==403,'Respuesta individual requiere administrador.')
    reply_page=request(reply_url)[1]
    reply_csrf=re.search(r'name="csrf" value="([^"]+)"',reply_page).group(1)
    before_state=DB.execute('SELECT status FROM leads WHERE id=1').fetchone()[0]
    prepared=request(reply_url,{'csrf':reply_csrf,'action':'prepare','subject':'Respuesta sintética','message':'Mensaje <script>bad()</script>','recipient':'consulta@example.invalid'})
    check(prepared[0]==200 and 'Vista previa' in prepared[1],'Previsualización de respuesta.')
    reply=DB.execute('SELECT * FROM lead_reply_operations WHERE lead_id=1').fetchone()
    check(reply and reply['status']=='draft' and DB.execute('SELECT COUNT(*) FROM lead_reply_attempts').fetchone()[0]==0,'Borrador no contacta proveedor.')
    preview_url='/admin/respuesta-vista-previa.php?lead=1&operation='+reply['id']
    check(request(preview_url,opener=GUEST)[0]==403,'La vista previa del correo debe ser privada.')
    preview_status,preview_html,preview_headers=request(preview_url)
    check(preview_status==200 and '&lt;script&gt;' in preview_html and 'sandbox' in preview_headers.get('Content-Security-Policy',''),'Vista previa escapada y aislada.')
    fields={'csrf':reply_csrf,'action':'send','operation':reply['id'],'content_hash':reply['content_hash'],'confirm':'1'}
    check(request(reply_url,dict(fields,content_hash='bad'))[0]==400,'Hash de confirmación inválido rechazado.')
    for attempt in range(2):check(request(reply_url,fields)[0]==200,'Simulación confirmada idempotente.')
    check(DB.execute('SELECT COUNT(*) FROM lead_reply_attempts').fetchone()[0]==1,'Doble POST genera un solo intento.')
    check(DB.execute('SELECT status FROM leads WHERE id=1').fetchone()[0]==before_state,'Respuesta no cambia estado manual.')

    for path, expected in paths:
        status, body, _ = request(path)
        if path.endswith('plantilla=1'):
            check(status == 200 and 'business-import-v1' in body, f'La plantilla de importación falló: {status} {path}')
        else:
            check(status == 200 and expected in body, f'La página administrativa falló: {status} {path}')
            check(len(re.findall(r'<aside\b[^>]*class="[^"]*\badmin-sidebar\b[^"]*"', body)) == 1, f'La página debe mostrar exactamente una navegación lateral en {path}')
            if not path.endswith('plantilla=1'):
                sidebar = re.search(r'<aside\b[^>]*class="[^"]*\badmin-sidebar\b[^"]*".*?</aside>', body, re.S)
                check(sidebar is not None, f'No se pudo leer el menú lateral en {path}')
                active_links = re.findall(r'<a\b[^>]*aria-current="page"[^>]*>(.*?)</a>', sidebar.group(0), re.S)
                check(len(active_links) == 1, f'El menú lateral debe marcar una sola sección activa en {path}; encontró {len(active_links)}')
                active_label = re.sub(r'<[^>]+>', '', active_links[0]).strip()
                route = path.split('?', 1)[0]
                expected_sidebar = {
                    '/admin/index.php': 'Estado del sistema' if 'tab=sistema' in path else 'Solicitudes de participación',
                    '/admin/base-datos.php': 'Base de datos',
                    '/admin/destinos.php': 'Lugares',
                    '/admin/negocios.php': 'Catálogo y cobertura',
                    '/admin/trasladar-negocios.php': 'Catálogo y cobertura',
                    '/admin/importar-negocios.php': 'Catálogo y cobertura',
                    '/admin/reclamaciones.php': 'Revisión de negocios',
                    '/admin/solicitudes-cuenta.php': 'Revisión de negocios',
                    '/admin/usuarios.php': 'Cuentas',
                    '/admin/suscripciones.php': 'Suscripciones',
                    '/admin/inversionistas.php': 'Inteligencia e inversión',
                    '/admin/contactos-negocios.php': 'Canales y responsable',
                    '/admin/correos.php': 'Plantillas y correos',
                    '/admin/campanas.php': 'Conexión con Brevo' if 'tab=conexion' in path else 'Campañas por correo',
                }.get(route)
                if expected_sidebar:
                    check(active_label == expected_sidebar, f'La sección activa debe ser {expected_sidebar} en {path}; mostró {active_label}')
            if path in ('/admin/reportes.php','/admin/solicitudes-publicas.php'):
                check(re.search(r'<main\b[^>]*class="[^"]*admin-page[^\"]*"[^>]*>.*?<aside\b',body,re.S) is not None,'Reports must contain the sidebar inside the page layout to reserve its width.')
            if path == '/admin/contactos-negocios.php':
                check('<meta name="robots" content="noindex,nofollow">' in body and 'Solo administración' in body, 'La pantalla de contactos privados debe quedar autenticada y fuera de indexación.')
            if path == '/admin/negocios.php?tab=importar':
                check('contactos-negocios.php' in body and 'negocios.php?tab=revisar' in body, 'La pestaña de importación debe enlazar la carga de contactos privados y la revisión/publicación masiva.')
            if path.startswith('/admin/negocios.php'):
                workflow = re.search(r'<nav class="admin-workflow-tabs".*?</nav>', body, re.S)
                check(workflow is not None, f'Faltan las pestañas de Negocios en {path}')
                selected_tabs = re.findall(r'<a\b[^>]*aria-current="page"[^>]*>(.*?)</a>', workflow.group(0), re.S)
                check(len(selected_tabs) == 1, f'Negocios debe marcar una sola pestaña activa en {path}; encontró {len(selected_tabs)}')
                selected_label = re.sub(r'<[^>]+>', '', selected_tabs[0]).strip()
                expected_tab = {'tab=crear': 'Listado de negocios', 'tab=importar': 'Importación masiva', 'tab=revisar': 'Publicar importaciones Gratis sin representante'}.get(next((part for part in path.split('?')[1:] if part.startswith('tab=')), ''), 'Listado de negocios')
                check(selected_label == expected_tab, f'La pestaña activa debe ser {expected_tab} en {path}; mostró {selected_label}')
    _,upcoming_subs,_=request('/admin/suscripciones.php?renovacion=7&q=Hotel+de+prueba')
    check('Preparar recordatorio' in upcoming_subs and 'Vence en ' in upcoming_subs,'Paid renewal due within 7 days was not shown with a reminder action.')
    _,expired_subs,_=request('/admin/suscripciones.php?renovacion=expired&q=Hotel+de+prueba')
    check('Preparar recordatorio' not in expired_subs,'Active subscription with future expiry leaked into expired filter.')
    health_csrf = re.search(r'name="csrf" value="([^"]+)"', request('/admin/campanas.php?tab=conexion')[1]).group(1)
    check(request('/admin/campanas.php?tab=conexion', {'action': 'check_connection', 'csrf': 'invalid'})[0] == 403, 'Diagnóstico sin CSRF')
    status, health_body, _ = request('/admin/campanas.php?tab=conexion', {'action': 'check_connection', 'csrf': health_csrf, 'return_to': 'system'})
    check(status == 200 and 'Salud del correo · Brevo' in health_body and 'Revisar conexión o configuración' in health_body, 'No informa conexión deshabilitada en Salud')
    check((WORK / 'private/brevo/health.json').is_file(), 'Diagnóstico no persistido en privado')
    check(health_body.index('Salud del correo · Brevo') < health_body.index('Capacidad y disponibilidad'), 'Brevo debe aparecer primero en Estado del sistema')
    check('Conexiones y presión de base de datos' not in health_body and 'Intentos de conexión desde el arranque' not in health_body, 'El detalle de base de datos no debe aparecer en Sistema')
    db_health_body = request('/admin/base-datos.php')[1]
    check('Conexiones y rendimiento' in db_health_body and 'no permite consultar estos indicadores' in db_health_body, 'No se deben fabricar indicadores cuando el controlador no los ofrece')
    check(request('/admin/base-datos.php', {'action': 'start_health_period', 'csrf': 'invalid'})[0] == 403, 'Período sin CSRF')
    db_health_csrf = re.search(r'name="csrf" value="([^"]+)"', db_health_body).group(1)
    check(request('/admin/base-datos.php', {'action': 'start_health_period', 'csrf': db_health_csrf})[0] == 400, 'No debe iniciar período con contadores no disponibles en SQLite')
    check(not (WORK / 'private/database-health/period.json').exists(), 'No debe fabricar mediciones ausentes')
    check(request('/admin/campanas.php?tab=conexion', {'action': 'check_connection', 'csrf': health_csrf}, opener=GUEST)[0] == 403, 'Visitante pudo ejecutar el diagnóstico')
    contact_csrf = re.search(r'name="csrf" value="([^"]+)"', request('/admin/contactos-negocios.php?tab=cargar')[1]).group(1)
    research_before = DB.execute('SELECT COUNT(*) FROM business_research_contacts').fetchone()[0]
    business_before_research = DB.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]
    research_package = json.dumps({
        'schema_version': 'business-research-contacts-v1',
        'destination_slug': 'jerico',
        'businesses': [{'name': 'Hotel de prueba', 'contacts': [{
            'channel': 'email', 'value': 'hotel@example.invalid',
            'source_url': 'https://example.invalid/hotel/contacto',
            'source_type': 'Sitio web oficial', 'source_field': 'Contacto',
            'source_period': '2026', 'consulted_at': date.today().isoformat(),
            'evidence_status': 'PUBLICADO_PARA_CLIENTES',
            'source_note': 'Dato ficticio de prueba',
            'authorization_status': 'NO_OBTENIDA',
            'legal_basis_status': 'Pendiente de revisión',
        }]}],
    }).encode()
    status, body, _ = multipart('/admin/contactos-negocios.php',
        {'csrf': contact_csrf, 'action': 'import_private_contacts'},
        [('package', 'contactos.json', 'application/json', research_package)])
    check(status == 200 and '1 registros de canal y fuente añadidos' in body and 'Se conservaron las fichas existentes' in body,
          f'La importación de contactos privados falló o no informó que no crea negocios: {status} {body[-500:]}')
    research_row = DB.execute('SELECT authorization_status,marketing_consent,campaign_allowed,suppressed FROM business_research_contacts').fetchone()
    check(tuple(research_row) == ('NO_OBTENIDA', 0, 0, 0), 'La importación no debe inventar consentimiento ni autorización de campañas.')
    check(DB.execute('SELECT COUNT(*) FROM businesses').fetchone()[0] == business_before_research,
          'Importar contactos privados creó un negocio.')
    status, body, _ = request('/admin/contactos-negocios.php?business_id=' + str(business_id))
    check(status == 200 and 'hotel@example.invalid' in body and 'Ver fuente' in body,
          'El administrador no puede consultar el contacto privado con su fuente.')
    repeated_status, repeated_body, _ = multipart('/admin/contactos-negocios.php',
        {'csrf': contact_csrf, 'action': 'import_private_contacts'},
        [('package', 'contactos.json', 'application/json', research_package)])
    check(repeated_status == 200 and '0 registros de canal y fuente añadidos' in repeated_body and '1 ya estaban guardados' in repeated_body,
          'Repetir la importación duplicó un contacto privado.')
    check(DB.execute('SELECT COUNT(*) FROM business_research_contacts').fetchone()[0] == research_before + 1,
          'La importación idempotente creó contactos duplicados.')
    _, with_channels, _ = request('/admin/contactos-negocios.php?contact=with&subscription=all')
    _, without_channels, _ = request('/admin/contactos-negocios.php?contact=without&subscription=all')
    def channel_summary(html):
        summary = re.search(r'<div class="crm-metrics".*?</div>', html, re.S).group(0)
        return [int(value) for value in re.findall(r'<strong>([0-9]+)</strong>', summary)]
    summary_with = channel_summary(with_channels)
    check(summary_with == channel_summary(without_channels) and summary_with[0] == sum(summary_with[1:]) and summary_with[1] > 0,
          'Con/Sin canales debe conservar los mismos totales y no ocultar los canales existentes en el resumen.')
    check('data-open-channels' in with_channels and 'admin-business-channels.js' in with_channels,
          'Los canales deben abrirse en un diálogo desde la fila del negocio.')
    _, channel_detail, _ = request('/admin/contactos-negocios.php?business_id=' + str(business_id))
    check('crm-channel-card-value' in channel_detail and 'Fuentes y opciones' in channel_detail,
          'El detalle debe mostrar canales en tarjetas y conservar sus fuentes.')


    status, campaign_html, _ = request('/admin/campanas.php?tab=correos')
    check(status == 200 and 'hotel@example.invalid' in campaign_html and 'Pendiente de revisión' in campaign_html, 'Los correos importados deben verse pendientes; no habilitar campañas automáticamente.')
    campaign_csrf = re.search(r'name="csrf" value="([^"]+)"', campaign_html).group(1)
    channel_id = DB.execute('SELECT id FROM business_research_contacts WHERE contact_value=?', ('hotel@example.invalid',)).fetchone()[0]
    bad_status, _, _ = request('/admin/campanas.php', {'csrf': 'invalid', 'action': 'permission', 'channel_id': str(channel_id), 'decision': 'allowed', 'confirmed': '1', 'evidence': 'Fictitious permission for local test'})
    check(bad_status == 403 and DB.execute('SELECT COUNT(*) FROM business_email_permissions').fetchone()[0] == 0, 'La revisión debe exigir CSRF.')
    status, _, _ = request('/admin/campanas.php', {'csrf': campaign_csrf, 'action': 'create', 'name': 'Prueba bloqueada', 'subject': 'Asunto', 'body': 'Mensaje', 'channels[]': str(channel_id)})
    check(status == 400 and DB.execute('SELECT COUNT(*) FROM business_email_campaigns').fetchone()[0] == 0, 'No crear campaña con audiencia pendiente.')
    status, _, _ = request('/admin/campanas.php', {'csrf': campaign_csrf, 'action': 'permission', 'channel_id': str(channel_id), 'decision': 'allowed', 'confirmed': '1', 'evidence': 'Fictitious permission for isolated HTTP test'})
    check(status == 200, 'La revisión explícita del correo no se guardó.')
    status, body, _ = request('/admin/campanas.php', {'csrf': campaign_csrf, 'action': 'create', 'name': 'Prueba local', 'subject': 'Asunto local', 'body': '<script>alert(1)</script>', 'channels[]': str(channel_id)})
    check(status == 200 and '&lt;script&gt;' in body and 'Aprobar esta versión' in body, 'El borrador debe abrir para revisión con contenido escapado.')
    email_status,email_template_form,_=request('/admin/correos.php?tab=templates')
    email_csrf=re.search(r'name="csrf" value="([^"]+)"',email_template_form).group(1)
    check(request('/admin/correos.php',{'csrf':'invalid','action':'save_template'})[0]==403,'Email templates must require CSRF.')
    email_status,email_saved,_=request('/admin/correos.php?tab=templates',{'csrf':email_csrf,'action':'save_template','name':'Respuesta HTTP','subject':'Respuesta para {{business_name}}','html':'<p>Hola {{business_name}}</p><script>evil()</script>'})
    check(email_status==200 and 'Plantilla guardada' in email_saved,'HTML template was not saved.')
    template_id=re.search(r'name="template_id" value="([a-f0-9]{32})"',email_saved).group(1)
    _,email_composer,_=request('/admin/correos.php?template='+template_id+'&business_id='+str(business_id))
    recipient_key=re.search(r'<option value="([a-f0-9]{64})"',email_composer).group(1)
    email_status,email_preview,_=request('/admin/correos.php',{'csrf':email_csrf,'action':'preview','template_id':template_id,'recipient':recipient_key,'requested_reply':'1'})
    check(email_status==200 and 'Vista previa del correo' in email_preview and 'Hotel de prueba' in email_preview and 'sandbox=""' in email_preview and 'evil()' not in email_preview,'Individual email did not render safe actual business data.')
    email_status,email_history,_=request('/admin/correos.php?tab=history&business_id='+str(business_id))
    check(email_status==200 and 'Respuesta para Hotel de prueba' in email_history and 'Borrador revisable' in email_history,'Individual mail draft missing from business history.')
    preview_id=re.search(r'message=([a-f0-9]{32})',email_history).group(1)
    check(request('/admin/correos.php',{'csrf':email_csrf,'action':'send','message_id':preview_id,'confirm_send':'1'})[0]==400,'Local individual email must never send live mail.')
    campaign_row = DB.execute('SELECT id,content_hash FROM business_email_campaigns').fetchone()
    _,reuse_html,_=request('/admin/campanas.php?reuse='+str(campaign_row[0]))
    check('value="Asunto local"' in reuse_html and '&lt;script&gt;' in reuse_html and 'name="channels[]"' in reuse_html and 'name="channels[]" checked' not in reuse_html,'Reusing a message must escape content and require a fresh recipient selection.')
    _,filtered_html,_=request('/admin/campanas.php?tab=correos&q=nonexistent-business')
    check('hotel@example.invalid' not in filtered_html,'Recipient search did not filter businesses.')
    _,templates_html,_=request('/admin/campanas.php?tab=plantillas')
    check('Usar como base' in templates_html and 'Prueba local' in templates_html,'Saved messages are not available to reuse.')
    campaign_tabs=re.search(r'<nav class="admin-workflow-tabs".*?</nav>',templates_html,re.S).group(0)
    check('tab=conexion' not in campaign_tabs,'Connection settings must stay out of campaign tabs.')

    status, body, _ = request('/admin/campanas.php', {'csrf': campaign_csrf, 'action': 'approve', 'id': str(campaign_row[0]), 'hash': campaign_row[1], 'reviewed': '1'})
    check(status == 400 and 'Completa y habilita' in body, 'La conexión no configurada debe bloquear aprobación y envío.')

    settings_input = {'csrf': campaign_csrf, 'action': 'settings', 'sender_name': 'Test Sender', 'sender_email': 'sender@example.invalid', 'reply_to': 'reply@example.invalid', 'sender_address': 'Test address', 'api_key': 'fictitious-http-api-key', 'enabled': '1'}
    status, body, _ = request('/admin/campanas.php', settings_input)
    check(status == 200 and 'Conexión guardada' in body and 'fictitious-http-api-key' not in body, 'La llave no debe volver en HTML.')
    private_settings = (WORK / 'private/brevo/settings.json').read_text()
    check('fictitious-http-api-key' not in private_settings, 'La llave debe quedar cifrada fuera de public/.')
    status,reloaded,_=request('/admin/campanas.php?tab=conexion')
    check('•••••••••••• · Clave guardada' in reloaded and 'name="api_key" autocomplete="new-password" maxlength="1000" placeholder=' in reloaded,'Falta máscara informativa sin devolver la clave como valor.')
    check(status==200 and 'Test Sender' in reloaded and 'Llave guardada de forma privada' in reloaded and 'fictitious-http-api-key' not in reloaded,'La conexión no persiste al recargar.')
    invalid_settings=dict(settings_input,sender_name='Nombre conservado',sender_email='correo-invalido')
    status,invalid_body,_=request('/admin/campanas.php?tab=conexion',invalid_settings)
    check(status==400 and 'Nombre conservado' in invalid_body and 'correo-invalido' in invalid_body and 'fictitious-http-api-key' not in invalid_body,'Un error borra los datos del formulario o expone la API.')
    check((WORK/'private/brevo/settings.json').read_text()==private_settings,'Un formulario inválido alteró la conexión guardada.')
    preserved_settings=dict(settings_input,api_key='',sender_address='Test address\nFloor 15')
    status,body,_=request('/admin/campanas.php?tab=conexion',preserved_settings)
    check(status==200 and 'Conexión guardada' in body and 'Test address Floor 15' in body,'No admite dirección pegada o clave vacía para conservar la existente.')
    status, _, _ = request('/admin/campanas.php', {'csrf': campaign_csrf, 'action': 'approve', 'id': str(campaign_row[0]), 'hash': campaign_row[1], 'reviewed': '1'})
    check(status == 400, 'Cambiar el remitente requiere un nuevo borrador revisado.')
    check(DB.execute('SELECT status FROM business_email_campaigns WHERE id=?', (campaign_row[0],)).fetchone()[0] == 'draft', 'Las pruebas HTTP no deben preparar ni enviar campañas al proveedor.')

    _, review_html, _ = request('/admin/negocios.php?tab=revisar')
    check('Seleccionar todos los' in review_html and 'Aprobar y publicar seleccionados' in review_html, 'La revisión de negocios debe admitir selección múltiple y publicación por lote.')
    check('Posibles duplicados' in review_html and 'Hospedaje' in review_html, 'La revisión por lote debe permitir filtrar posibles duplicados y categorías antes de publicar.')
    status, body, _ = request('/admin/inversionistas.php?periodo=30')
    check(status == 200 and 'Últimos 30 días' in body and 'Visitantes y comportamiento medido' in body and 'No hay analítica de visitas' not in body, 'La inteligencia debe reutilizar la medición existente y aclarar su alcance.')
    check('Valor asociado a pagos revisados' in body and '19.900 COP' in body and 'Negocios con suscripción paga vigente' in body, 'Debe presentar el valor cotizado y el negocio pago desde registros persistidos.')
    check('100,0%' in body and '1 de 1 negocios con vencimiento renovaron a tiempo' in body, 'La retención debe contar negocios con vencimiento y renovaciones enlazadas dentro del plazo.')
    check('100,0%' in body and '1 de 1 negocios con vencimiento renovaron a tiempo' in body, 'La retención debe contar negocios con vencimiento y renovaciones enlazadas dentro del plazo.')
    check('Actividad nueva; período anterior sin registros' in body or 'Sin actividad en ambos períodos' in body, 'Debe explicar comparaciones sin una base previa sin inventar porcentajes.')
    status, costs_html, _ = request('/admin/costos.php')
    check(status==200 and 'Registrar un gasto realizado' in costs_html,'El registro de costos debe estar disponible para administración.')
    costs_csrf=__import__('re').search(r'name="csrf" value="([^\"]+)"',costs_html).group(1)
    cost_fields={'csrf':costs_csrf,'action':'add','cost_date':__import__('datetime').datetime.now(__import__('datetime').timezone.utc).strftime('%Y-%m-%d'),'category':'travel','amount_cop':'2400.50','description':'Gasto HTTP de prueba','destination_id':str(destination_id)}
    check(request('/admin/costos.php',dict(cost_fields,csrf='incorrecto'))[0]==403,'El registro de costos exige CSRF.')
    check(request('/admin/costos.php',cost_fields)[0]==200,'El gasto válido no se guardó.')
    check(DB.execute('SELECT description FROM operating_costs').fetchone()[0]=='Gasto HTTP de prueba','El gasto debe persistirse en la base.')
    check(request('/admin/costos.php',opener=GUEST)[0]==403,'Los costos deben permanecer privados.')
    receipt_png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII=')
    before_costs=DB.execute('SELECT COUNT(*) FROM operating_costs').fetchone()[0]
    invalid_file=[('evidence','factura.pdf','application/pdf',b'<html>not a receipt</html>')]
    check(multipart('/admin/costos.php',cost_fields,invalid_file)[0]==400,'Se aceptó un soporte con tipo falso.')
    check(DB.execute('SELECT COUNT(*) FROM operating_costs').fetchone()[0]==before_costs,'Un soporte inválido dejó un gasto guardado parcialmente.')
    check(multipart('/admin/costos.php',cost_fields,[('evidence','pantallazo.png','image/png',receipt_png)])[0]==200,'No se guardó el gasto con pantallazo.')
    receipt=DB.execute('SELECT * FROM operating_cost_evidence ORDER BY id DESC LIMIT 1').fetchone()
    check(receipt is not None,'No se conservó el soporte asociado al gasto.')
    with ADMIN.open(BASE+'/admin/soporte-gasto.php?id='+str(receipt['id'])) as response:
        check(response.read()==receipt_png and 'attachment' in response.headers.get('Content-Disposition',''),'El soporte privado no se descarga correctamente.')
    check(request('/admin/soporte-gasto.php?id='+str(receipt['id']),opener=GUEST)[0]==403,'Un visitante pudo obtener una factura privada.')
    check(not list((WORK/'public').rglob(receipt['storage_name'])),'El soporte quedó publicado dentro de la web.')
    attach_fields={'csrf':costs_csrf,'action':'attach','cost_id':str(receipt['cost_id'])}
    check(multipart('/admin/costos.php',attach_fields,[('evidence','recibo.png','image/png',receipt_png)])[0]==200,'No se pudo adjuntar un segundo documento a un gasto existente.')
    check(DB.execute('SELECT COUNT(*) FROM operating_cost_evidence WHERE cost_id=?',(receipt['cost_id'],)).fetchone()[0]==2,'Se reemplazó un soporte anterior en lugar de conservar ambos.')

    review_query = '/admin/negocios.php?tab=revisar&destino=' + str(destination_id) + '&categoria=hospedaje&q=Hotel%20de%20lote&estado=draft&verificacion=PUBLICO&duplicados=no'
    status, review_page_one, _ = request(review_query)
    check(status == 200 and '27 resultados' in review_page_one and 'Página 1 de 2' in review_page_one, 'La revisión de lotes debe paginar y conservar el conteo completo del filtro.')
    check(len(re.findall(r'data-business-id(?:\s|>)', review_page_one)) == 25, 'La primera página de revisión debe mostrar 25 negocios, no el lote completo.')
    check('name="filter_query" value="Hotel de lote"' in review_page_one, 'La acción de aprobación debe conservar la búsqueda aplicada.')
    status, review_page_two, _ = request(review_query + '&pagina=2')
    check(status == 200 and '27 resultados' in review_page_two and 'Página 2 de 2' in review_page_two, 'La segunda página debe conservar los filtros del lote.')
    review_csrf = re.search(r'name="csrf" value="([^"]+)"', review_page_two).group(1)
    bulk_fields = {'review_token': re.search(r'name="review_token" value="([^"]+)"',review_page_two).group(1), 'csrf': review_csrf, 'action': 'publish_imported_bulk', 'select_all_filtered': '1', 'filter_destination': str(destination_id), 'filter_category': 'hospedaje', 'filter_query': 'Hotel de lote', 'filter_status': 'draft', 'filter_verification': 'PUBLICO', 'filter_duplicates': 'no'}
    status, bulk_result, _ = request('/admin/negocios.php?tab=revisar', bulk_fields)
    check(status == 200 and 'Lote revisado: 27 publicados' in bulk_result, 'Seleccionar todos los resultados filtrados debe incluir registros de ambas páginas.')
    _, html, _ = request('/admin/index.php')
    csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', html).group(1)
    # Legacy representation request status transition and SQL persistence.
    status, body, _ = request('/admin/reclamaciones.php', {'csrf': csrf, 'action': 'update', 'request_id': 1, 'status': 'in_review', 'admin_note': 'Revisar documento'})
    check(status == 200 and 'Se actualizó la solicitud' in body, 'No confirmó la actualización de la solicitud anterior.')
    row = DB.execute('SELECT status,admin_note FROM business_claim_requests WHERE id=1').fetchone()
    check(tuple(row) == ('in_review', 'Revisar documento'), 'El cambio de estado anterior no persistió.')
    status, body, _ = request('/admin/reclamaciones.php', {'csrf': csrf, 'action': 'update', 'request_id': 1, 'status': 'closed', 'admin_note': 'Estado inválido'})
    check(status == 400 and 'no válido' in body, 'Se aceptó un estado no permitido para una solicitud anterior.')

    # Business account application review: both status and note must reach the database.
    status, body, _ = request('/admin/solicitudes-cuenta.php', {'csrf': csrf, 'action': 'review', 'application_id': 1, 'status': 'in_review', 'admin_note': 'Validar existencia'})
    check(status == 200 and 'Se actualizó el estado de la solicitud' in body, 'No confirmó la revisión de una solicitud de cuenta.')
    row = DB.execute('SELECT status,admin_note FROM business_account_applications WHERE id=1').fetchone()
    check(tuple(row) == ('in_review', 'Validar existencia'), 'La revisión de cuenta no persistió.')
    # Requests without submitted evidence must remain unauthorized.
    status, body, _ = request('/admin/solicitudes-cuenta.php', {'csrf': csrf, 'action': 'review', 'application_id': 1, 'status': 'approved', 'admin_note': 'RUT revisado; matrícula mercantil confirmada'})
    check(status == 400 and 'No se puede aprobar' in body, 'La administración no debe aprobar una solicitud sin documentos de identidad y del negocio.')
    pending_application = DB.execute('SELECT status,business_id FROM business_account_applications WHERE id=1').fetchone()
    check(tuple(pending_application) == ('in_review', None), 'Una solicitud sin documentos creó un negocio o concedió acceso.')

    # The Accounts page does not create orphaned accounts; accounts enter through registration.
    account_form = {'csrf': csrf, 'action': 'save', 'account_type': 'business', 'display_name': 'Cuenta sin negocio', 'email': 'sin-negocio@example.invalid'}
    status, body, _ = request('/admin/usuarios.php', account_form)
    check(status == 400 and 'Las cuentas se crean desde el registro empresarial' in body, 'El panel aceptó crear una cuenta empresarial por fuera del registro enlazado.')
    account_form.update(account_type='traveler', display_name='Viajera de prueba', email='viajera@example.invalid')
    status, body, _ = request('/admin/usuarios.php', account_form)
    check(status == 400 and 'Las cuentas se crean desde el registro empresarial' in body, 'El panel permitió crear cuentas pendientes desconectadas del registro público.')
    check(DB.execute("SELECT COUNT(*) FROM accounts WHERE email IN ('sin-negocio@example.invalid','viajera@example.invalid')").fetchone()[0] == 0, 'Una acción deshabilitada creó cuentas huérfanas.')

    # Import page returns validation errors in the integrated Negocios > Importación tab.
    business_count_before_import = DB.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]
    bad_package = json.dumps({'schema_version': 'business-import-v1', 'destination_slug': 'jerico', 'businesses': []}).encode()
    status, body, _ = multipart('/admin/importar-negocios.php', {'csrf': csrf, 'action': 'import'}, [])
    check(status == 200 and 'Selecciona un archivo JSON o ZIP válido' in body and 'Importar varios negocios' in body, 'La importación integrada no indicó que falta seleccionar el archivo.')
    status, body, _ = multipart('/admin/importar-negocios.php', {'csrf': csrf, 'action': 'import'}, [('package', 'invalid.json', 'application/json', b'{no-json')])
    check(status == 200 and 'JSON válido' in body, 'La importación integrada no rechazó JSON malformado con error legible.')
    check(DB.execute('SELECT COUNT(*) FROM businesses').fetchone()[0] == business_count_before_import, 'La importación inválida dejó registros parciales.')
    valid_package = json.dumps({'schema_version':'business-import-v1','destination_slug':'jerico','businesses':[{'name':'Comercio importado de prueba','summary':'','description':'','category_code':'hospedaje','information_status':'PUBLICO','sources':[{'url':'https://example.invalid/fuente','note':'Prueba automatizada'}]}]}).encode()
    status, body, _ = multipart('/admin/importar-negocios.php', {'csrf': csrf, 'action': 'import'}, [('package', 'valid.json', 'application/json', valid_package)])
    check(status == 200 and 'Se importaron 1 negocios' in body and 'Importar varios negocios' in body and 'Revisar el directorio privado del destino' in body, f'La importación válida no terminó, no ofreció previsualizar el directorio o no regresó a su pestaña: {status} {body[-500:]}')
    imported = DB.execute("SELECT b.publication_status,b.claim_status,b.information_status,b.record_origin,s.plan_code,s.status FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale='es' JOIN business_subscriptions s ON s.business_id=b.id WHERE t.display_name='Comercio importado de prueba'").fetchone()
    check(tuple(imported) == ('draft','NO_RECLAMADO','PUBLICO','public_import','free','pending_publication'), 'La ficha importada no quedó como Gratis, privada y sin reclamar.')
    imported_locales = DB.execute("SELECT locale,display_name FROM business_translations WHERE business_id=(SELECT business_id FROM business_translations WHERE display_name='Comercio importado de prueba' AND locale='es')").fetchall()
    check(len(imported_locales) == 3 and len({row['display_name'] for row in imported_locales}) == 1, 'La importación masiva no conservó el nombre propio en los tres idiomas del portal.')
    fresh_review=request('/admin/negocios.php?tab=revisar')[1]
    status, body, _ = request('/admin/negocios.php?tab=revisar', {'review_token':re.search(r'name="review_token" value="([^"]+)"',fresh_review).group(1),'csrf': csrf, 'action': 'publish_imported_bulk', 'select_all_filtered': '1', 'filter_destination': str(destination_id), 'filter_category': 'hospedaje', 'filter_status': 'draft', 'filter_verification': 'PUBLICO', 'filter_duplicates': 'no'})
    check(status == 200 and 'Lote revisado: 1 publicados' in body, 'La aprobación masiva filtrada no publicó el negocio importado seleccionado.')
    bulk_row = DB.execute("SELECT b.publication_status,s.status FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale='es' JOIN business_subscriptions s ON s.business_id=b.id WHERE t.display_name='Comercio importado de prueba'").fetchone()
    check(tuple(bulk_row) == ('published','active'), 'La aprobación masiva no activó el registro Gratis tras publicarlo.')

    imported_id = DB.execute("SELECT business_id FROM business_translations WHERE display_name='Comercio importado de prueba' AND locale='es'").fetchone()[0]
    before_free = DB.execute('SELECT public_id,record_origin FROM businesses WHERE id=?', (imported_id,)).fetchone()
    count_before_free = DB.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]
    minimal_free = {'csrf': csrf, 'action': 'save', 'id': str(imported_id), 'destination_id': str(destination_id), 'category_code': 'hospedaje', 'plan_code': 'free', 'map_workflow': '1', 'location_confirmed': '0', 'translations[es][display_name]': 'Comercio importado de prueba'}
    free_status, free_body, _ = request('/admin/negocios.php', minimal_free)
    check(free_status == 200 and 'Abrir o editar este negocio' in free_body, 'Editar Gratis debe guardar sin dirección, mapa, teléfono, descripción ni frecuencia de pago.')
    check(tuple(DB.execute('SELECT public_id,record_origin FROM businesses WHERE id=?', (imported_id,)).fetchone()) == tuple(before_free) and DB.execute('SELECT COUNT(*) FROM businesses').fetchone()[0] == count_before_free, 'Editar Gratis debe conservar ID, origen y el mismo registro.')
    _, free_form, _ = request('/admin/negocios.php?id=' + str(imported_id))
    check('data-paid-only hidden disabled' in free_form and not re.search(r'<input[^>]*name="address_text"[^>]*\brequired\b', free_form), 'El HTML gratuito no debe exigir dirección ni permitir validar campos pagos ocultos.')

    if os.environ.get('EL_FREE_EDITOR_BROWSER') == '1':
        subprocess.run(['/Users/nivato/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node', str(ROOT / 'backend/tests/free-business-editor.browser.cjs')], input=json.dumps({'base': BASE, 'password': password, 'businessId': imported_id}), text=True, check=True)
        check(DB.execute('SELECT COUNT(*) FROM business_categories WHERE business_id=? AND category_code=?', (imported_id, 'alimentacion')).fetchone()[0] == 1, 'El editor no guardó la categoría adicional en el mismo negocio.')

    before_manual_link=[tuple(row) for row in DB.execute('SELECT * FROM business_account_memberships ORDER BY id')]
    status,account_list,_=request('/admin/usuarios.php')
    check(status==200 and 'name="action" value="link"' not in account_list and 'Asignar un negocio' not in account_list,'Accounts must not offer manual business assignment.')
    status,blocked_link,_=request('/admin/usuarios.php',{'csrf':csrf,'action':'link','account_id':'1','business_id':str(imported_id),'membership_role':'owner'})
    check(status==403 and 'asociación manual' in blocked_link,'Direct manual assignment must be blocked server-side.')
    check([tuple(row) for row in DB.execute('SELECT * FROM business_account_memberships ORDER BY id')]==before_manual_link,'Blocked manual assignment changed memberships.')
    # Manual creation is blocked even through a direct authenticated POST.
    before_manual_count=DB.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]
    failed_status,failed_body,_=request('/admin/negocios.php',{'csrf':csrf,'action':'save','id':'','translations[es][display_name]':'Forbidden manual business'})
    check(failed_status==403 and 'creación manual está deshabilitada' in failed_body,'Manual creation must be blocked server-side.')
    check(DB.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]==before_manual_count,'Blocked creation inserted a business.')
    check('id="business-form"' not in request('/admin/negocios.php?tab=crear')[1],'Direct create link exposed a creation form.')
    # Seed a historical business and verify editing remains available.
    DB.execute("INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status) VALUES('historical-edit-fixture',?,'hospedaje','admin_created','hostal-creado-por-prueba','PENDIENTE_REVISION','NO_RECLAMADO','DESCONOCIDO','draft')",(destination_id,))
    edit_fixture_id=DB.execute('SELECT last_insert_rowid()').fetchone()[0]
    DB.execute("INSERT INTO business_translations(business_id,locale,display_name,description) VALUES(?,'es','Hostal creado por prueba','')",(edit_fixture_id,))
    DB.commit()
    missing_location = {'csrf': csrf, 'action': 'save', 'id': str(edit_fixture_id), 'destination_id': str(destination_id), 'category_code': 'hospedaje', 'translations[es][display_name]': 'Prueba sin punto de mapa', 'plan_code': 'featured', 'billing_frequency': 'monthly', 'address_text': 'Parque principal, Jericó', 'map_workflow': '1', 'location_confirmed': '0', 'image_assistance_requested': '0'}
    location_status, location_body, _ = request('/admin/negocios.php', missing_location)
    check(location_status == 400 and 'Selecciona el punto exacto en el mapa' in location_body and 'Prueba sin punto de mapa' in location_body, 'El alta sin punto confirmado debe explicar que faltó el mapa y conservar lo escrito.')
    fields = [
        ('csrf', csrf), ('action', 'save'), ('id', str(edit_fixture_id)), ('map_workflow', '1'),
        ('location_confirmed', '0'), ('location_confirmed', '1'),
        ('destination_id', str(destination_id)), ('category_code', 'hospedaje'),
        ('translations[es][display_name]', 'Hostal creado por prueba'), ('plan_code', 'free'),
        ('billing_frequency', 'monthly'), ('address_text', 'Parque principal, Jericó'),
        ('location_map_url', 'https://www.google.com/maps/@5.791,-75.786,18z'),
        ('latitude', ''), ('longitude', ''), ('location_reference', ''),
        ('image_assistance_requested', '0'),
    ]
    data = urllib.parse.urlencode(fields).encode()
    req = urllib.request.Request(BASE + '/admin/negocios.php', data=data)
    try:
        with ADMIN.open(req, timeout=10) as response:
            status, body, headers = response.status, response.read().decode(), response.headers
    except urllib.error.HTTPError as error:
        status, body, headers = error.code, error.read().decode(), error.headers
    check(status == 200 and 'Hostal creado por prueba' in body and 'Abrir o editar este negocio' in body and 'Ver altas administrativas' in body, f'No se guardó el negocio ni se mostró el acceso directo al registro creado: {status} {body[:300]}')
    created = DB.execute("SELECT id,publication_status,information_status,claim_status,record_origin,location_confirmed,latitude,longitude FROM businesses WHERE slug='hostal-creado-por-prueba'").fetchone()
    check(created is not None and tuple(created)[1:] == ('draft', 'PENDIENTE_REVISION', 'NO_RECLAMADO', 'admin_created', 1, 5.791, -75.786), 'El negocio no quedó en borrador privado con ubicación y estados esperados.')
    check(DB.execute('SELECT COUNT(*) FROM business_subscriptions WHERE business_id=? AND plan_code="free"', (created['id'],)).fetchone()[0] == 1, 'La creación no registró la suscripción Gratis pendiente.')
    status, body, _ = request('/admin/negocios.php?tab=lista&origen=admin_created&categoria=hospedaje&q=Hostal+creado+por+prueba')
    check(status == 200 and 'Hostal creado por prueba' in body and 'Pendiente de publicación' in body, 'El negocio recién creado no aparece filtrando el listado por categoría y alta administrativa.')
    check('name="categoria"' in body and 'name="representacion"' not in body, 'La lista administrativa no ofrece categoría como filtro principal.')

    _,registered_segment,_=request('/admin/negocios.php?tab=lista')
    _,historical_segment,_=request('/admin/negocios.php?tab=lista&segment=historical')
    check('Hostal creado por prueba' not in registered_segment and 'Hostal creado por prueba' in historical_segment,'Historical administrative businesses leaked into person registrations.')
    preview_path='/admin/negocio-vista-previa.php?id='+str(created['id'])
    check(request(preview_path,opener=GUEST)[0]==403,'Business card preview must remain private.')
    status,card_preview,_=request(preview_path)
    check(status==200 and 'Hostal creado por prueba' in card_preview and 'directory-card-tier-free' in card_preview and 'Pendiente de publicación' in card_preview,'Free business preview must render its actual data with the shared public card.')
    check(request('/admin/negocio-vista-previa.php?id=999999')[0]==404,'Unknown business preview must return 404.')
    status, subscription_list, _ = request('/admin/suscripciones.php')
    check(status == 200 and 'Negocio sin suscripción</' not in subscription_list and 'Suscripciones de pago y renovaciones' in subscription_list, 'La bandeja de suscripciones debe centrarse en planes de pago.')

    future_date = (date.today() + timedelta(days=7)).isoformat()
    status, body, _ = request('/admin/suscripciones.php', {'csrf': csrf, 'action': 'save', 'business_id': created['id'], 'plan_code': 'free', 'billing_frequency': 'monthly', 'publish_not_before': future_date})
    check(status == 200 and 'Elección guardada' in body, f'No se guardó la elección de suscripción: {status} {body[-400:]}')
    subscription = DB.execute('SELECT plan_code,status,publish_not_before FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1', (created['id'],)).fetchone()
    check(tuple(subscription) == ('free','pending_publication',future_date), 'La suscripción no quedó pendiente con fecha solicitada.')

    # Simulate a missing configured tariff in this disposable DB; restore afterwards.
    tariff_before=DB.execute('SELECT amount FROM subscription_plan_prices WHERE plan_code="page" AND billing_frequency="quarterly"').fetchone()[0]
    DB.execute('UPDATE subscription_plan_prices SET amount=NULL WHERE plan_code="page" AND billing_frequency="quarterly"');DB.commit()
    subscription_count_before_invalid_tariff = DB.execute('SELECT COUNT(*) FROM business_subscriptions WHERE business_id=?', (created['id'],)).fetchone()[0]
    status, body, _ = request('/admin/suscripciones.php', {'csrf': csrf, 'action': 'save', 'business_id': created['id'], 'plan_code': 'page', 'billing_frequency': 'quarterly'})
    check(status == 400 and 'tarifa' in body.lower(), f'El servidor aceptó una frecuencia sin precio configurado: {status} {body[-500:]}')
    check(DB.execute('SELECT COUNT(*) FROM business_subscriptions WHERE business_id=?', (created['id'],)).fetchone()[0] == subscription_count_before_invalid_tariff, 'Una frecuencia sin precio creó una suscripción.')
    DB.execute('UPDATE subscription_plan_prices SET amount=? WHERE plan_code="page" AND billing_frequency="quarterly"',(tariff_before,));DB.commit()
    check(DB.execute('SELECT amount FROM subscription_plan_prices WHERE plan_code="page" AND billing_frequency="quarterly"').fetchone()[0]==tariff_before,'No se restauró la tarifa aprobada.')

    # The admin cannot publish an unverified draft just by posting to the subscription action.
    status, body, _ = request('/admin/suscripciones.php', {'csrf': csrf, 'action': 'publish', 'business_id': created['id']})
    check(status == 400 and 'Verifica la información' in body, f'La publicación permitió un negocio todavía no verificado: {status} {body[-800:]}')
    check(DB.execute('SELECT publication_status FROM businesses WHERE id=?', (created['id'],)).fetchone()[0] == 'draft', 'Un intento de publicación no autorizado cambió la ficha.')

    # Full HTTP flow for a new business account, including explicit enablement, login,
    # approval, membership creation and confirmation that the business stays draft.
    config['business_accounts_enabled'] = True
    save_config()
    business_cookies = http.cookiejar.CookieJar()
    business_user = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(business_cookies))
    status, body, _ = request('/business/register.php?tipo=business', opener=business_user)
    check(status == 200 and 'Registra o reclama un negocio' in body and 'name="csrf"' in body, f'El registro empresarial privado no abrió: {status} {body[:300]}')
    registration_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    class NoBusinessRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, req, fp, code, msg, headers, newurl):
            return None
    business_flow = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(business_cookies), NoBusinessRedirect())
    registration_fields = {
        'holder_kind':'natural_document','holder_name':'Responsable HTTP','holder_document_type':'CC','holder_number':'123456789',
        'residence_country_code':'CO','residence_region_code':'05','residence_city_code':'05368',
        'plan_code':'featured','billing_frequency':'monthly','csrf': registration_csrf, 'application_type': 'new_business', 'business_id': '',
        'business_name': 'Cafetería de cuenta HTTP', 'destination_id': destination_id,
        'address_text':'Dirección de prueba privada','latitude':'5.79','longitude':'-75.78','summary':'Presentación enviada por el negocio','description':'Descripción privada de prueba','channels[email]':'negocio@example.invalid', 'category_codes[]':'hospedaje',
        'business_phone[type]':'mobile','business_phone[number]':'3001234567','business_phone[same_whatsapp]':'1',
        'category_code': 'hospedaje', 'display_name': 'Responsable HTTP',
        'email': 'responsable-http@example.invalid', 'phone': '3230000000',
        'relationship_code': 'owner', 'message': 'Adjuntaré matrícula y RUT para validar el negocio.',
        'responsibility_declared':'1','password': 'ClaveFuerteDePrueba123', 'password_confirm': 'ClaveFuerteDePrueba123', 'consent': '1',
    }
    import struct,zlib
    def png_chunk(tag,payload):return struct.pack('!I',len(payload))+tag+payload+struct.pack('!I',zlib.crc32(tag+payload)&0xffffffff)
    reference_png=b'\x89PNG\r\n\x1a\n'+png_chunk(b'IHDR',struct.pack('!2I5B',240,160,8,2,0,0,0))+png_chunk(b'IDAT',zlib.compress((b'\x00'+b'\xa0\x80\x60'*240)*160))+png_chunk(b'IEND',b'')
    registration_png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII=')
    missing_holder = dict(registration_fields, holder_kind='')
    status, body, _ = request('/business/register.php', missing_holder, business_flow)
    check(status == 400 and 'Selecciona cómo se identifica' in body, 'El registro debe exigir identificación privada del titular.')
    incomplete_fields = dict(registration_fields, email='sin-documentos-http@example.invalid')
    status, body, _ = request('/business/register.php', incomplete_fields, business_flow)
    check(status == 400 and 'Adjunta el documento privado' in body and DB.execute("SELECT COUNT(*) FROM accounts WHERE email='sin-documentos-http@example.invalid'").fetchone()[0] == 0, 'El registro aceptó una cuenta sin documentos de identidad y del negocio.')
    status, _, headers = multipart('/business/register.php', registration_fields, [('identity_document', 'identidad.png', 'image/png', registration_png), ('business_registration', 'registro.png', 'image/png', registration_png),('reference_images[]','foto.png','image/png',reference_png)], business_flow)
    check(status == 303, f'El registro web no creó la cuenta y redirigió al tablero: {status}')
    verify_account_mail('responsable-http@example.invalid', business_user)
    _,login_page,_=request('/business/login.php',opener=business_user)
    login_token=re.search(r'name="csrf" value="([^"]+)"',login_page)[1]
    check(request('/business/login.php',{'csrf':login_token,'email':'responsable-http@example.invalid','password':'ClaveFuerteDePrueba123'},business_flow)[0]==303,'No entra después de verificar correo.')
    status, body, _ = request('/business/dashboard.php?tab=business&solicitud=recibida', opener=business_user)
    check(status == 200 and 'Solicitudes asociadas a tu cuenta' in body and 'Cafetería de cuenta HTTP' in body, f'El registro web no terminó en el estado de solicitud esperado: {status} {body[:500]}')
    account_row = DB.execute("SELECT id,account_type,status,password_hash FROM accounts WHERE email='responsable-http@example.invalid'").fetchone()
    application_row = DB.execute("SELECT id,application_type,status,business_id FROM business_account_applications WHERE account_id=?", (account_row['id'],)).fetchone()
    check(account_row and account_row['account_type']=='traveler' and account_row['status']=='active' and account_row['password_hash'], 'El registro no creó una cuenta personal activada con contraseña protegida.')
    check(application_row and application_row['application_type']=='request_new' and application_row['status']=='approved' and application_row['business_id'] is not None, 'El alta inicial creó una ficha demasiado pronto o no dejó la solicitud pendiente.')
    holder = json.loads(DB.execute('SELECT business_details_json FROM business_account_applications WHERE id=?',(application_row['id'],)).fetchone()[0])['holder_identity']
    check(holder['document_type']=='CC' and holder['number']=='123456789' and not holder['officially_verified'] and not holder['ownership_verified'], 'Debe conservar el documento privado sin afirmar verificación oficial.')
    check('123456789' not in body, 'Mi cuenta no debe exponer el documento del titular en el listado de solicitudes.')
    representation_rows = DB.execute('SELECT id,evidence_type,storage_name FROM business_account_application_evidence WHERE application_id=? ORDER BY evidence_type', (application_row['id'],)).fetchall()
    check([row['evidence_type'] for row in representation_rows] == ['business_registration'], 'La solicitud debe adjuntar evidencia del negocio y de identidad.')
    check(all(row['storage_name'] not in [item.name for item in (WORK / 'public').rglob('*') if item.is_file()] for row in representation_rows), 'Los documentos de representación deben quedar fuera del directorio público.')
    check(DB.execute('SELECT publication_status FROM businesses WHERE id=?',(application_row['business_id'],)).fetchone()[0]=='draft','La asociación automática no debe publicar.')
    anonymous = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    check(request('/business/register.php', opener=anonymous)[0] == 200, 'El registro habilitado debe admitir visitantes sin claves de prueba.')
    dashboard_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    status, _, _ = request('/business/logout.php', {'csrf': dashboard_csrf}, business_flow)
    check(status == 303, 'El cierre de sesión empresarial no completó la redirección.')
    status, body, _ = request('/business/login.php', opener=business_user)
    login_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    status, body, _ = request('/business/login.php', {'csrf': login_csrf, 'email': 'responsable-http@example.invalid', 'password': 'incorrecta'}, business_flow)
    check(status == 400 and 'Correo o contraseña incorrectos' in body, 'El acceso empresarial aceptó una contraseña incorrecta.')
    status, _, _ = request('/business/login.php', {'csrf': login_csrf, 'email': 'responsable-http@example.invalid', 'password': 'ClaveFuerteDePrueba123'}, business_flow)
    check(status == 303, 'El acceso empresarial válido no redirigió al tablero.')
    status, body, _ = request('/business/dashboard.php?tab=business', opener=business_user)
    check(status == 200 and 'Solicitudes asociadas a tu cuenta' in body and 'Negocio asociado' in body, 'La persona no pudo volver a entrar y consultar el estado de su solicitud.')
    csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', request('/admin/index.php')[1]).group(1)
    check(request('/admin/representacion-evidencia.php?id=' + str(representation_rows[0]['id']), opener=GUEST)[0] == 403, 'Un visitante pudo abrir documentos privados de representación.')
    with ADMIN.open(BASE + '/admin/representacion-evidencia.php?id=' + str(representation_rows[0]['id']), timeout=10) as evidence_response:
        check(evidence_response.status == 200 and evidence_response.read() == registration_png, 'El administrador no pudo abrir un documento de representación privado.')
    # Ordinary first association is now automatic; documents are not marked as ownership verified.
    approved = DB.execute('SELECT r.status,r.business_id,b.publication_status,b.claim_status,m.account_id FROM business_account_applications r JOIN businesses b ON b.id=r.business_id JOIN business_account_memberships m ON m.business_id=b.id WHERE r.id=?', (application_row['id'],)).fetchone()
    check(DB.execute('SELECT COUNT(*) FROM business_image_references WHERE business_id=?',(approved['business_id'],)).fetchone()[0]==1,'Las fotos privadas de la solicitud no se conservaron al aprobar.')
    status,all_pending,_=request('/admin/negocios.php?'+urllib.parse.urlencode({'tab':'lista','estado':'draft','q':'Cafetería de cuenta HTTP'}))
    check(status==200 and 'Cafetería de cuenta HTTP' in all_pending and 'Editar datos' in all_pending,'Filtered business list must include owner_submitted drafts.')
    status,legacy_pending,_=request('/admin/negocios.php?tab=pendientes&q=Cafeter%C3%ADa')
    check(status==200 and 'Listado de negocios' in legacy_pending and 'value="draft" selected' in legacy_pending and '?tab=pendientes"' not in legacy_pending,'Legacy pending links must use the unified list with the publication filter.')
    _,imported_segment,_=request('/admin/negocios.php?tab=lista&segment=imported&q=Cafeter%C3%ADa')
    check('Cafetería de cuenta HTTP' not in imported_segment,'Person registration leaked into imported businesses.')
    crm_account_marker='data-crm-business-id="'+str(approved['business_id'])+'"'
    _,crm_prospects,_=request('/admin/contactos-negocios.php?subscription=all&account=without')
    _,crm_customers,_=request('/admin/contactos-negocios.php?subscription=all&account=with')
    check(crm_account_marker not in crm_prospects and crm_account_marker in crm_customers,'Business with active account was not isolated from unassociated CRM prospects.')
    check(approved and approved['status']=='approved' and approved['publication_status']=='draft' and approved['claim_status']=='RECLAMADO' and approved['account_id']==account_row['id'], 'La aprobación no creó el borrador privado y la membresía esperados.')
    normalized_contacts=dict(DB.execute("SELECT channel,contact_value FROM business_contacts WHERE business_id=? AND channel IN ('phone','whatsapp')",(approved['business_id'],)).fetchall())
    check(normalized_contacts=={'phone':'+573001234567','whatsapp':'+573001234567'},'El registro no guardó teléfono y WhatsApp compartido en formato internacional.')
    check(DB.execute('SELECT address_text FROM businesses WHERE id=?',(approved['business_id'],)).fetchone()[0]=='Dirección de prueba privada','Se perdieron los datos de ubicación al aprobar.')
    check(DB.execute('SELECT description FROM business_translations WHERE business_id=? AND locale="es"',(approved['business_id'],)).fetchone()[0]=='Descripción privada de prueba','Se perdió la descripción enviada.')
    check(tuple(DB.execute('SELECT contact_value,is_public FROM business_contacts WHERE business_id=? AND channel="email"',(approved['business_id'],)).fetchone())==('negocio@example.invalid',0),'El canal debe conservarse privado.')
    status, body, _ = request('/business/dashboard.php?tab=business', opener=business_user)
    check(status == 200 and 'Cafetería de cuenta HTTP' in body and 'Borrador' in body, 'El tablero no reflejó el negocio asociado después de la aprobación.')
    check('Retomar la suscripción que elegí' in body and 'plan=featured' in body,'La elección paga debe conservar un acceso para continuar con el pago.')
    free_form=request('/business/register.php?tipo=business',opener=business_user)[1]
    free_csrf=re.search(r'name="csrf" value="([^"]+)"',free_form).group(1)
    free_fields={'csrf':free_csrf,'application_type':'new_business','business_name':'Fonda Sintética 401','destination_id':destination_id,'category_code':'hospedaje','plan_code':'free','billing_frequency':'monthly','relationship_code':'owner','responsibility_declared':'1','consent':'1'}
    free_response=multipart('/business/register.php',free_fields,[('business_registration','negocio.png','image/png',registration_png)],business_flow)
    check(free_response[0]==303,'Gratis debe completarse sin NIT, dirección, texto ni identidad adicionales: '+str(free_response[0]))
    free_app=DB.execute('SELECT r.id,r.business_id,r.status,b.publication_status FROM business_account_applications r JOIN businesses b ON b.id=r.business_id WHERE r.business_name=?',('Fonda Sintética 401',)).fetchone()
    check(free_app and free_app['status']=='approved' and free_app['publication_status']=='draft','Gratis creado y asociado, privado hasta publicación separada.')
    check(DB.execute('SELECT plan_code FROM business_subscriptions WHERE business_id=?',(free_app['business_id'],)).fetchone()[0]=='free','Gratis asignado sin intervención administrativa.')
    before_free=DB.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]
    free_fields['csrf']=re.search(r'name="csrf" value="([^"]+)"',request('/business/register.php?tipo=business',opener=business_user)[1]).group(1)
    repeated=multipart('/business/register.php',free_fields,[('business_registration','negocio.png','image/png',registration_png)],business_flow)
    check(repeated[0]==303 and DB.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]==before_free,'Repetir alta no duplica fichas: '+str(repeated[0])+' '+(' · '.join(re.findall(r'<p class="notice" role="alert">(.*?)</p>',repeated[1],re.S))))

    own_public_id = DB.execute('SELECT public_id FROM businesses WHERE id=?', (approved['business_id'],)).fetchone()[0]
    status, audience_json, _ = request('/api/audiencia-entidad.php?type=business&key='+own_public_id, opener=business_user)
    check(status == 200 and audience_json.startswith('{'), 'Respuesta de estadísticas inesperada: '+str(status)+' '+audience_json[:240])
    check(json.loads(audience_json)['status']=='enabled', 'El titular no puede consultar sus estadísticas agregadas.')
    status, _, _ = request('/api/audiencia-entidad.php?type=destination&key=jerico', opener=business_user)
    check(status == 403, 'Una cuenta comercial no debe consultar estadísticas privadas de destinos.')
    other_public_id = DB.execute('SELECT public_id FROM businesses WHERE id<>? LIMIT 1', (approved['business_id'],)).fetchone()[0]
    status, _, _ = request('/api/audiencia-entidad.php?type=business&key='+other_public_id, opener=business_user)
    check(status == 403, 'Una cuenta comercial no debe consultar otro negocio por cambiar su ID.')
    status, _, _ = request('/api/audiencia-entidad.php?type=business&key='+own_public_id, opener=GUEST)
    check(status == 403, 'Las estadísticas comerciales no deben exponerse a visitantes.')
    status, malformed_json, _ = request('/api/audiencia-entidad.php?type[]=business&key[]=test&days[]=30', opener=business_user)
    check(status == 400 and json.loads(malformed_json)['error']=='Entidad inválida', 'Los parámetros malformados deben producir JSON sin warnings ni rutas privadas.')
    status, malformed_json, _ = request('/api/audiencia-entidad.php?type[]=business&key[]=test', opener=GUEST)
    check(status == 403 and json.loads(malformed_json)['error']=='Acceso privado requerido', 'Los parámetros malformados no deben filtrar datos a visitantes.')
    account_dashboard_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    request('/business/logout.php', {'csrf': account_dashboard_csrf}, business_flow)
    status, body, _ = request('/admin/usuarios.php', {'csrf': csrf, 'action': 'reset_password', 'account_id': account_row['id']}, ADMIN)
    reset_match = __import__('re').search(r'value="(/negocios/cambiar-clave/\?token=([a-f0-9]{64}))"', body)
    check(status == 200 and 'Enlace de contraseña' in body and reset_match, 'El panel no generó un enlace temporal de contraseña de un solo uso.')
    reset_token = reset_match.group(2)
    check(DB.execute('SELECT token_hash FROM account_password_resets WHERE account_id=? ORDER BY id DESC LIMIT 1', (account_row['id'],)).fetchone()[0] == __import__('hashlib').sha256(reset_token.encode()).hexdigest(), 'El enlace temporal quedó almacenado en texto legible.')
    reset_client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    status, body, _ = request('/business/password.php?token=' + reset_token, opener=reset_client)
    check(status == 200 and 'Crea o cambia tu contraseña' in body, 'La página de contraseña rechazó un enlace recién emitido.')
    reset_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    status, body, _ = request('/business/password.php', {'csrf': reset_csrf, 'token': reset_token, 'password': 'NuevaClaveHTTP123', 'password_confirm': 'NuevaClaveHTTP123'}, reset_client)
    check(status == 200 and 'Contraseña actualizada' in body, 'El enlace no permitió guardar la nueva contraseña.')
    check(DB.execute('SELECT email_verified_at FROM accounts WHERE id=?', (account_row['id'],)).fetchone()[0] is not None, 'El uso del enlace emitido al correo no confirmó el correo de la cuenta.')
    status, body, _ = request('/business/password.php?token=' + reset_token, opener=reset_client)
    check(status == 200 and 'Enlace vencido o no válido' in body, 'El enlace temporal se pudo volver a usar.')
    status, body, _ = request('/business/login.php', opener=business_user)
    login_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    status, body, _ = request('/business/login.php', {'csrf': login_csrf, 'email': 'responsable-http@example.invalid', 'password': 'ClaveFuerteDePrueba123'}, business_flow)
    check(status == 400 and 'Correo o contraseña incorrectos' in body, 'La contraseña anterior siguió siendo válida tras el restablecimiento.')
    status, _, _ = request('/business/login.php', {'csrf': login_csrf, 'email': 'responsable-http@example.invalid', 'password': 'NuevaClaveHTTP123'}, business_flow)
    check(status == 303, 'La nueva contraseña no permitió iniciar sesión.')
    status, body, _ = request('/business/dashboard.php?tab=business', opener=business_flow)
    check(status == 200 and 'Elegir suscripción y enviar soporte de pago' in body and 'Cafetería de cuenta HTTP' in body and '69900' in body, 'El panel de negocio no mostró la tarifa acordada para la suscripción Página.')
    account_payment_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    business_id = DB.execute("SELECT b.id FROM businesses b JOIN business_translations t ON t.business_id=b.id WHERE t.locale='es' AND t.display_name='Cafetería de cuenta HTTP'").fetchone()[0]
    check('name="operator_experience_id"' not in body, 'Un negocio sin categoría de experiencias ve el complemento.')
    DB.execute("INSERT INTO business_categories(business_id,category_code) VALUES(?,'experiencias')",(business_id,)); DB.commit()
    status, body, _ = request('/business/dashboard.php?tab=business', opener=business_flow)
    check('name="operator_experience_id"' in body, 'La categoría adicional no habilita el complemento.')
    sample_png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII=')
    addon_destination = DB.execute('SELECT destination_id FROM businesses WHERE id=?',(business_id,)).fetchone()[0]
    addon_experience = DB.execute("INSERT INTO points_of_interest(destination_id,slug,poi_type,publication_status) VALUES(?,'experiencia-operador-prueba','experience','published')",(addon_destination,)).lastrowid
    DB.execute("INSERT INTO point_of_interest_translations(point_of_interest_id,locale,name,description) VALUES(?,'es','Experiencia de prueba operador','Sintética')",(addon_experience,)); DB.commit()
    status, body, _ = multipart('/business/dashboard.php?tab=business', {'csrf': account_payment_csrf, 'business_id': business_id, 'plan_code': 'page', 'billing_frequency': 'monthly', 'payment_reference': 'TRX-LOCAL-2026', 'operator_experience_id':addon_experience, 'transaction_date':date.today().isoformat(), 'paid_amount':'99800', 'payment_method':'breb', 'source_bank':'BANCO FICTICIO'}, [('payment_proof', 'soporte.png', 'image/png', sample_png)], business_flow)
    check(status == 303, f'El representante no pudo enviar el comprobante: HTTP {status} {body[:300]}')
    payment_row = DB.execute('SELECT e.checkout_reference,e.transaction_date,e.paid_amount,e.payment_method,e.source_bank,e.id,e.storage_name,e.account_id,e.status,s.status AS subscription_status,s.quoted_amount FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE e.account_id=? ORDER BY e.id DESC LIMIT 1', (account_row['id'],)).fetchone()
    check(payment_row and payment_row['status']=='pending' and payment_row['subscription_status']=='pending' and payment_row['quoted_amount']==99800 and payment_row['storage_name'] not in [item.name for item in (WORK / 'public').rglob('*') if item.is_file()], 'El comprobante no quedó privado o alteró antes de revisión el pago/la publicación.')
    check(payment_row['paid_amount']==99800 and payment_row['source_bank']=='BANCO FICTICIO' and re.fullmatch(r'EL-[A-F0-9]{24}',payment_row['checkout_reference']), 'Se perdieron los datos de transacción.')
    status, subscriptions_with_evidence, _ = request('/admin/suscripciones.php?q=Cafeter%C3%ADa')
    check('Experiencia de prueba operador' in subscriptions_with_evidence, 'No muestra el complemento al revisar el pago.')
    check('BANCO FICTICIO' in subscriptions_with_evidence and payment_row['checkout_reference'] in subscriptions_with_evidence and 'TRX-LOCAL-2026' in subscriptions_with_evidence, 'El revisor no ve los datos de la transacción.')
    check(status == 200 and 'Ver comprobante' in subscriptions_with_evidence and 'Pendiente de revisión' in subscriptions_with_evidence, 'La fila de suscripción debe enlazar el comprobante pendiente de ese negocio.')
    check(request('/admin/comprobante-pago.php?id=' + str(payment_row['id']), opener=GUEST)[0] == 403, 'Un visitante pudo abrir un soporte privado de pago.')
    with ADMIN.open(BASE + '/admin/comprobante-pago.php?id=' + str(payment_row['id']), timeout=10) as evidence_response:
        check(evidence_response.status == 200 and evidence_response.read() == sample_png, 'El administrador no pudo abrir el comprobante privado.')
    status, body, _ = request('/admin/suscripciones.php', {'csrf': csrf, 'action': 'review_payment', 'evidence_id': payment_row['id'], 'decision': 'approved', 'admin_note': ''}, ADMIN)
    reviewed_payment = DB.execute('SELECT e.status,s.status AS subscription_status FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE e.id=?', (payment_row['id'],)).fetchone()
    check(status == 200 and reviewed_payment['status']=='approved' and reviewed_payment['subscription_status']=='paid_pending_publication', 'Aprobar el comprobante no dejó el pago esperando publicación.')
    check(DB.execute('SELECT publication_status FROM businesses WHERE id=?', (business_id,)).fetchone()[0]=='draft', 'Aprobar el pago publicó el negocio automáticamente.')
    # Isolated publication fixture verifies public operator rendering, without deploying/publishing real businesses.
    before_subscription=DB.execute('SELECT status,starts_at,ends_at FROM business_subscriptions WHERE id=(SELECT subscription_id FROM business_subscription_payment_evidence WHERE id=?)',(payment_row['id'],)).fetchone()
    DB.execute("UPDATE businesses SET publication_status='published' WHERE id=?",(business_id,))
    DB.execute("UPDATE business_subscriptions SET status='active',starts_at=datetime('now','-1 day'),ends_at=datetime('now','+29 days') WHERE id=(SELECT subscription_id FROM business_subscription_payment_evidence WHERE id=?)",(payment_row['id'],));DB.commit()
    try:
        rendered_status,rendered,_=request('/destino.php?destino=jerico',opener=GUEST)
        (WORK/'operator-render.html').write_text(rendered)
        operators=re.findall(r'<ul class="experience-paid-operators".*?</ul>',rendered,re.S)
        check(rendered_status==200 and any('Cafetería de cuenta HTTP' in unescape(item) for item in operators),'El operador vigente no aparece dentro de la experiencia pública: '+str(rendered_status)+' '+rendered[-600:])
        quote_url='/business/dashboard.php?tab=business&business='+str(business_id)+'&subscription_quote=1&plan_code=page_premium&billing_frequency=monthly&operator_experience_id='+str(addon_experience)
        quote_status, quote_body, quote_headers=request(quote_url,opener=business_flow)
        quote_data=json.loads(quote_body)
        check(quote_status==200 and quote_data['quote']['kind']=='upgrade' and quote_data['quote']['credit_cents']>0 and quote_data['quote']['due_cents']<5000000, 'La cotización pública no aplicó el saldo al upgrade: '+str(quote_status)+' '+quote_body)
        check(quote_headers.get('Cache-Control')=='no-store' and len(quote_data['token'])==48, 'La cotización no quedó privada y vinculada a la sesión.')
        check(DB.execute('SELECT status FROM business_subscriptions WHERE id=(SELECT subscription_id FROM business_subscription_payment_evidence WHERE id=?)',(payment_row['id'],)).fetchone()[0]=='active','Cotizar canceló la suscripción vigente.')
        free_status, free_body, _=request('/business/dashboard.php?tab=business&business='+str(business_id)+'&subscription_quote=1&plan_code=free',opener=business_flow)
        free_quote=json.loads(free_body)['quote']
        check(free_status==200 and free_quote['kind']=='renewal' and free_quote['effective_at'] and free_quote['due_cents']==0,'Gratis no quedó reservado para el vencimiento.')
        upgrade_due=f"{quote_data['quote']['due_cents']/100:.2f}"
        status, _, _=multipart('/business/dashboard.php?tab=business', {'csrf':account_payment_csrf,'business_id':business_id,'plan_code':'page_premium','billing_frequency':'monthly','operator_experience_id':addon_experience,'subscription_quote_token':quote_data['token'],'payment_reference':'TX-UPGRADE-HTTP','transaction_date':date.today().isoformat(),'paid_amount':upgrade_due,'payment_method':'qr','source_bank':'BANCO FICTICIO'}, [('payment_proof','upgrade.png','image/png',sample_png)], business_flow)
        check(status==303,'No guardó el comprobante público del upgrade con su diferencia cotizada.')
        upgrade_row=DB.execute('SELECT s.id,s.quoted_amount,c.credit_cents,c.due_cents,c.original_ends_at,e.id AS evidence_id FROM business_subscriptions s JOIN subscription_changes c ON c.subscription_id=s.id JOIN business_subscription_payment_evidence e ON e.subscription_id=s.id WHERE s.business_id=? ORDER BY s.id DESC LIMIT 1',(business_id,)).fetchone()
        check(upgrade_row and upgrade_row['due_cents']==quote_data['quote']['due_cents'] and upgrade_row['credit_cents']==quote_data['quote']['credit_cents'],'Perdió el saldo o la diferencia al guardar el comprobante real HTTP.')
        check(DB.execute('SELECT status FROM business_subscriptions WHERE id=(SELECT subscription_id FROM business_subscription_payment_evidence WHERE id=?)',(payment_row['id'],)).fetchone()[0]=='active','Enviar el comprobante del upgrade desactivó la suscripción anterior.')
        status,_,_=request('/admin/suscripciones.php',{'csrf':csrf,'action':'review_payment','evidence_id':upgrade_row['evidence_id'],'decision':'rejected','admin_note':'Rechazo sintético controlado de upgrade','customer_message':'Soporte de prueba rechazado para verificar que conservas la suscripción anterior.'},ADMIN)
        check(status==200,'No pudo rechazar el soporte sintético del upgrade.')
        DB.execute("UPDATE points_of_interest SET publication_status='draft' WHERE id=?",(addon_experience,));DB.commit()
        rendered=request('/destino.php?destino=jerico',opener=GUEST)[1]
        check(not any('Cafetería de cuenta HTTP' in item for item in re.findall(r'<ul class="experience-paid-operators".*?</ul>',rendered,re.S)),'La experiencia oculta sigue anunciando el operador.')
    finally:
        DB.execute("UPDATE points_of_interest SET publication_status='published' WHERE id=?",(addon_experience,))
        DB.execute("UPDATE businesses SET publication_status='draft' WHERE id=?",(business_id,))
        DB.execute('UPDATE business_subscriptions SET status=?,starts_at=?,ends_at=? WHERE id=(SELECT subscription_id FROM business_subscription_payment_evidence WHERE id=?)',(*before_subscription,payment_row['id']));DB.commit()

    status, body, _ = request('/business/dashboard.php?tab=business', opener=business_flow)
    check(status == 200 and 'Pago confirmado' in body and 'todavía requiere aprobación de publicación' in body, 'La cuenta no muestra que el pago se aprobó mientras el negocio sigue en borrador.')
    account_payment_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    # An approved but unpublished payment must not disappear when selecting again.
    status, blocked_body, _ = multipart('/business/dashboard.php?tab=business', {'csrf': account_payment_csrf, 'business_id': business_id, 'plan_code': 'free'}, [], business_flow)
    check(status == 400 and 'pago confirmado pendiente' in blocked_body, 'Permitió reemplazar una solicitud con dinero ya confirmado.')
    # This isolated fixture now represents an elapsed, previously published term.
    DB.execute("UPDATE business_subscriptions SET status='expired',starts_at=datetime('now','-31 days'),ends_at=datetime('now','-1 day'),published_at=datetime('now','-31 days') WHERE id=(SELECT subscription_id FROM business_subscription_payment_evidence WHERE id=?)",(payment_row['id'],)); DB.commit()
    status, _, _ = multipart('/business/dashboard.php?tab=business', {'csrf': account_payment_csrf, 'business_id': business_id, 'plan_code': 'page', 'billing_frequency': 'monthly', 'payment_reference': 'TRX-RECHAZO-LOCAL', 'transaction_date':date.today().isoformat(), 'paid_amount':'69901', 'payment_method':'qr', 'source_bank':'BANCO FICTICIO'}, [('payment_proof', 'soporte-segundo.png', 'image/png', sample_png)], business_flow)
    check(status == 303, 'La cuenta no pudo enviar un soporte de reemplazo para probar el flujo de rechazo.')
    check('Difiere del importe esperado' in request('/admin/suscripciones.php',opener=ADMIN)[1], 'No advirtió la diferencia entre importe declarado y cotizado.')
    rejected_evidence = DB.execute('SELECT id FROM business_subscription_payment_evidence WHERE account_id=? ORDER BY id DESC LIMIT 1', (account_row['id'],)).fetchone()[0]
    rejection_message = 'El valor transferido no coincide con el importe esperado. Verifica el soporte y vuelve a enviarlo.'
    internal_note = 'El soporte no coincide con la tarifa cotizada.'
    status, _, _ = request('/admin/suscripciones.php', {'csrf': csrf, 'action': 'review_payment', 'evidence_id': rejected_evidence, 'decision': 'rejected', 'admin_note': internal_note, 'customer_message': rejection_message}, ADMIN)
    status, body, _ = request('/business/dashboard.php?tab=business', opener=business_flow)
    check(status == 200 and 'Comprobante rechazado' in body and rejection_message in body and 'envía un nuevo soporte válido' in body and internal_note not in body, 'El portal no mostró el mensaje para el negocio o expuso la nota interna.')
    status, body, _ = request('/admin/usuarios.php', {'csrf': csrf, 'action': 'set_status', 'account_id': account_row['id'], 'status': 'disabled', 'reason': 'Suspensión sintética de prueba'}, ADMIN)
    check(status == 200 and 'La cuenta quedó suspendida' in body, f'La administración no pudo deshabilitar el acceso de una cuenta: HTTP {status}, {body[:300]}')
    status, _, headers = request('/business/dashboard.php?tab=business', opener=business_flow)
    check(status == 303 and headers.get('Location', '').endswith('/negocios/acceso/'), f'Una cuenta deshabilitada conservó el acceso con una sesión abierta: HTTP {status}, destino {headers.get("Location", "")}')
    status, body, _ = request('/business/login.php', opener=business_flow)
    disabled_login_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    status, body, _ = request('/business/login.php', {'csrf': disabled_login_csrf, 'email': 'responsable-http@example.invalid', 'password': 'NuevaClaveHTTP123'}, business_flow)
    check(status == 400 and 'Correo o contraseña incorrectos' in body, 'Una cuenta deshabilitada pudo iniciar sesión nuevamente.')
    status, body, _ = request('/admin/usuarios.php', {'csrf': csrf, 'action': 'set_status', 'account_id': account_row['id'], 'status': 'active', 'reason': 'Fin de prueba'}, ADMIN)
    check(status == 200 and 'La cuenta quedó habilitada nuevamente' in body, 'La administración no pudo rehabilitar una cuenta deshabilitada.')
    status, _, _ = request('/business/login.php', {'csrf': disabled_login_csrf, 'email': 'responsable-http@example.invalid', 'password': 'NuevaClaveHTTP123'}, business_flow)
    check(status == 303, 'La cuenta rehabilitada no pudo iniciar sesión nuevamente.')

    # Public lifecycle: close -> register again -> local email -> confirm -> same account.
    lifecycle_id = account_row['id']
    account_total = DB.execute('SELECT COUNT(*) FROM accounts').fetchone()[0]
    membership_total = DB.execute('SELECT COUNT(*) FROM business_account_memberships').fetchone()[0]
    status, body, _ = request('/business/dashboard.php?tab=personal', opener=business_flow)
    lifecycle_csrf = re.search(r'name="csrf" value="([^"]+)"', body).group(1)
    status, _, headers = request('/business/dashboard.php?tab=personal', {'csrf': lifecycle_csrf, 'action': 'close_account', 'current_password': 'NuevaClaveHTTP123', 'confirm_closure': '1'}, business_flow)
    check(status == 303 and 'cuenta=cerrada' in headers.get('Location',''), 'El cierre público no terminó correctamente.')
    check(DB.execute('SELECT status FROM accounts WHERE id=?',(lifecycle_id,)).fetchone()[0]=='disabled', 'El cierre no desactivó la cuenta.')
    check(DB.execute('SELECT event_type FROM account_access_events WHERE account_id=? ORDER BY id DESC LIMIT 1',(lifecycle_id,)).fetchone()[0]=='closed', 'El cierre se confundió con una suspensión.')
    status, body, _ = request('/business/register.php', opener=business_flow)
    lifecycle_csrf = re.search(r'name="csrf" value="([^"]+)"', body).group(1)
    status, _, headers = request('/business/register.php', {'csrf': lifecycle_csrf, 'account_type':'traveler', 'email':'responsable-http@example.invalid'}, business_flow)
    check(status == 303 and 'solicitado=1' in headers.get('Location',''), 'Volver a registrarse no ofreció continuar por correo.')
    messages = json.loads((WORK/'private/mailbox.json').read_text())
    message = [m for m in messages if m.get('kind')=='reactivation'][-1]
    link = urllib.parse.urlparse(message['url'])
    status, body, _ = request('/business/password.php?'+link.query, opener=business_flow)
    check(status == 200 and 'Quiero reactivar mi cuenta' in body, 'El enlace no pide confirmar la reactivación.')
    lifecycle_csrf = re.search(r'name="csrf" value="([^"]+)"', body).group(1)
    token = urllib.parse.parse_qs(link.query)['token'][0]
    status, body, _ = request('/business/password.php', {'csrf':lifecycle_csrf, 'reactivacion':'1','token':token,'confirm_reactivation':'1','password':'RegresoSeguroHTTP789','password_confirm':'RegresoSeguroHTTP789'}, business_flow)
    check(status==200 and 'Cuenta reactivada' in body, 'No se reactivó la cuenta desde el enlace.')
    check('Enlace vencido o no válido' in request('/business/password.php?'+link.query,opener=business_flow)[1], 'El enlace de reactivación se pudo reutilizar.')
    check(DB.execute('SELECT COUNT(*) FROM accounts').fetchone()[0]==account_total and DB.execute('SELECT COUNT(*) FROM business_account_memberships').fetchone()[0]==membership_total, 'Reactivar duplicó la cuenta o modificó sus asociaciones.')
    status, body, _ = request('/business/login.php',opener=business_flow)
    lifecycle_csrf = re.search(r'name="csrf" value="([^"]+)"', body).group(1)
    check(request('/business/login.php',{'csrf':lifecycle_csrf,'email':'responsable-http@example.invalid','password':'RegresoSeguroHTTP789'},business_flow)[0]==303,'La cuenta reactivada no permite iniciar sesión.')

    # Public owner edits Premium without entering administration; all external services remain mocked.
    premium_business=approved['business_id']
    _,checkout_body,_=request('/business/dashboard.php?tab=business&business='+str(premium_business),opener=business_flow)
    checkout_csrf=re.search(r'name="csrf" value="([^"]+)"',checkout_body)[1]
    checkout_fields={'csrf':checkout_csrf,'business_id':premium_business,'plan_code':'page_premium','billing_frequency':'monthly','payment_reference':'TX-EDITORIAL-PREMIUM','transaction_date':date.today().isoformat(),'paid_amount':'119900','payment_method':'qr','source_bank':'BANCO FICTICIO'}
    check(multipart('/business/dashboard.php?tab=business',checkout_fields,[('payment_proof','premium.png','image/png',sample_png)],business_flow)[0]==303,'No pudo enviar pago Premium previo al contenido')
    premium_evidence=DB.execute('SELECT id FROM business_subscription_payment_evidence WHERE account_id=? ORDER BY id DESC LIMIT 1',(account_row['id'],)).fetchone()[0]
    check(request('/admin/suscripciones.php',{'csrf':csrf,'action':'review_payment','evidence_id':premium_evidence,'decision':'approved','admin_note':''},ADMIN)[0]==200,'No confirmó pago Premium')
    status,profile_page,_=request('/business/dashboard.php?tab=business&business='+str(premium_business),opener=business_flow)
    check(status==200 and 'Contenido y publicación de mi negocio' in profile_page,'Premium owner editor missing')
    profile_fields={'csrf':re.search(r'name="csrf" value="([^"]+)"',profile_page)[1],'action':'premium_profile','business_id':premium_business,'page_alias':'hotel-publico-prueba','service_sections[es][restaurant]':'Cocina regional','service_sections[en][restaurant]':'Regional cuisine','service_sections[pt-BR][restaurant]':'Cozinha regional'}
    status,_,_=request('/business/dashboard.php?tab=business',profile_fields,business_flow)
    check(status==303 and DB.execute('SELECT alias FROM business_page_aliases WHERE business_id=? AND is_current=1',(premium_business,)).fetchone() is None,'El acceso antiguo debe redirigir al circuito editorial sin cambiar la página pública')
    editorial_path='/business/editorial.php?business='+str(premium_business)
    status,premium_editor,_=request(editorial_path,opener=business_flow)
    check(status==200 and 'URL más personalizada' in premium_editor and '</html>' in premium_editor,'Editor Premium se interrumpió durante el render.')
    premium_csrf=re.search(r'name="csrf" value="([^"]+)"',premium_editor)[1]
    premium_version=DB.execute('SELECT id FROM business_editorial_versions WHERE business_id=? ORDER BY id DESC',(premium_business,)).fetchone()
    status,_,_=request(editorial_path,{'csrf':premium_csrf,'action':'save','version_id':str(premium_version[0] if premium_version else 0),'translations[es][display_name]':'Fixture Premium Privado','category_code':'hospedaje'},business_flow)
    check(status==303,'No guardó la versión privada Premium.')
    premium_version=DB.execute('SELECT id FROM business_editorial_versions WHERE business_id=? ORDER BY id DESC',(premium_business,)).fetchone()[0]
    # Upload independent private source images to their exact slots (same filename deliberately).
    status,premium_editor,_=request(editorial_path,opener=business_flow)
    source_fields={'csrf':premium_csrf,'action':'save','version_id':str(premium_version),'translations[es][display_name]':'Fixture Premium Privado','showcase[products][2][translations][es][name]':'Producto Estrella Tres'}
    status,upload_body,_=multipart(editorial_path,source_fields,[('reference_images[cover]','misma.png','image/png',reference_png),('reference_images[product_2]','misma.png','image/png',reference_png)],business_flow)
    check(status==400,'Subida sin consentimiento aceptada')
    source_fields['image_consent']='1'
    status,upload_body,_=multipart(editorial_path,source_fields,[('reference_images[cover]','misma.png','image/png',reference_png),('reference_images[product_2]','misma.png','image/png',reference_png)],business_flow)
    check(status==303,'Fotos por posición no guardadas: '+str(status)+' '+upload_body[-1800:])
    photo_version=DB.execute('SELECT * FROM business_editorial_versions WHERE business_id=? ORDER BY id DESC',(premium_business,)).fetchone()
    photo_payload=json.loads(photo_version['payload_json']);premium_version=photo_version['id']
    check(photo_payload['source_images']['cover']!=photo_payload['source_images']['product_2'],'Fotos con igual nombre se asociaron a la misma posición')
    refs=DB.execute('SELECT id FROM business_editorial_images WHERE version_id=?',(premium_version,)).fetchall()
    for ref in refs:
        with business_flow.open(BASE+'/business/editorial-image.php?id='+str(ref['id'])) as image_response:
            check(image_response.status==200 and image_response.read()==reference_png,'Foto original no visible a su responsable')
        check(request('/business/editorial-image.php?id='+str(ref['id']),opener=urllib.request.build_opener())[0]==404,'Foto original accesible anónimamente')
    status,source_preview,_=request(editorial_path+'&version='+str(premium_version)+'&preview=page',opener=business_flow)
    check(status==200 and source_preview.count('business/editorial-image.php?id=')>=2 and 'Producto Estrella Tres' in source_preview,'Borrador no presenta originales de portada/producto')
    # Replace cover without accumulating sources in the latest version; historic version remains intact.
    source_fields['version_id']=str(premium_version)
    check(multipart(editorial_path,source_fields,[('reference_images[cover]','nueva.png','image/png',reference_png)],business_flow)[0]==303,'No reemplazó portada')
    replaced_version=DB.execute('SELECT id FROM business_editorial_versions WHERE business_id=? ORDER BY id DESC',(premium_business,)).fetchone()[0]
    check(DB.execute('SELECT COUNT(*) FROM business_editorial_images WHERE version_id=?',(replaced_version,)).fetchone()[0]==len(refs),'Reemplazo acumuló fuentes innecesarias')
    check(DB.execute('SELECT COUNT(*) FROM business_editorial_images WHERE version_id=?',(premium_version,)).fetchone()[0]==len(refs),'Reemplazo destruyó historial')
    premium_version=replaced_version
    for demo_view in ['free','featured','page','page_premium']:
        code,example,headers=request('/business/demo.php?view='+demo_view,opener=urllib.request.build_opener())
        check(code==200 and 'noindex' in headers.get('X-Robots-Tag','') and 'data-el-measurement' not in example,'Ejemplo público falla o mide visitas: '+demo_view)
        check((demo_view=='page_premium')==('Nube De Cacao' in example),'Diferencia de productos no corresponde a modalidad')
        check((demo_view!='free')==('aria-disabled="true"' in example),'Iconos de simulación no corresponden a modalidad')
        for icon_tag in re.findall(r'<a\b[^>]*class="[^"]*(?:directory-contact-link|business-channel|footer-contact-link)[^"]*"[^>]*>',example):
            check('href=' not in icon_tag and 'target=' not in icon_tag and 'aria-disabled="true"' in icon_tag,'Un canal del ejemplo conserva navegación')
        code,embedded,embedded_headers=request('/business/demo.php?view='+demo_view+'&embedded=1',opener=urllib.request.build_opener())
        check(code==200 and embedded_headers.get('X-Frame-Options')=='SAMEORIGIN' and "frame-ancestors 'self'" in embedded_headers.get('Content-Security-Policy',''),'El ejemplo no admite el panel del mismo origen')
        check('Volver a suscripciones' not in embedded and 'Horario de atención' not in embedded and 'business-profile-back' not in embedded,'El panel conserva navegación de salida u horarios')
        if demo_view in ['free','featured']:check('data-directory-layout="editorial-v2"' in embedded,'La tarjeta no está protegida de transformaciones antiguas')
        if demo_view in ['page','page_premium']:
            code,card_example,_=request('/business/demo.php?view='+demo_view+'&embedded=1&presentation=card',opener=urllib.request.build_opener())
            check(code==200 and 'directory-card-tier-'+demo_view in card_example and 'directory-business-page-link' in card_example,'Falta tarjeta enlazada de '+demo_view)
            check('embedded=1&amp;presentation=page' in card_example and 'target="_blank"' not in re.search(r'<a class="text-link directory-business-page-link".*?</a>',card_example,re.S)[0],'La tarjeta no abre su página dentro del ejemplo')
            check((demo_view=='page_premium')==('directory-premium-star' in card_example),'Distintivo Premium incorrecto')


    for language in ['es','en','pt-BR']:
        status,page_preview,preview_headers=request(editorial_path+'&version='+str(premium_version)+'&preview=page&lang='+language,opener=business_flow)
        check(status==200 and '</html>' in page_preview and 'Fatal error' not in page_preview and 'Vista previa privada' in page_preview,'Página Premium privada incompleta: '+str(status)+' '+page_preview[-1000:])
        check('noindex' in preview_headers.get('X-Robots-Tag','') and 'header-language-options' not in page_preview and 'lang=en' not in page_preview and 'lang=pt-BR' not in page_preview,'La prepublicación perdió privacidad o enlaces de idioma privados.')
    # Complete the private photo -> editorial replacement -> correction -> exact approval cycle.
    version=DB.execute('SELECT * FROM business_editorial_versions WHERE id=?',(premium_version,)).fetchone()
    check(request(editorial_path,{'csrf':premium_csrf,'action':'submit','version_id':str(premium_version),'payload_hash':version['payload_hash']},business_flow)[0]==303,'No envió contenido a preparación')
    admin_editorial=editorial_path+'&admin=1'
    _,admin_editor_form,_=request(admin_editorial)
    editorial_admin_csrf=re.search(r'name="csrf" value="([^"]+)"',admin_editor_form)[1]
    (WORK/'public/assets/editorial-final.png').write_bytes(reference_png)
    DB.execute("INSERT INTO media_assets(asset_path,origin,creator,source_url,license_name,license_url) VALUES('assets/editorial-final.png','own','Fixture','https://example.invalid','Prueba','https://example.invalid')");DB.commit()
    final_asset=DB.execute('SELECT id FROM media_assets ORDER BY id LIMIT 1').fetchone()[0]
    editorial_fields={'csrf':editorial_admin_csrf,'action':'save','version_id':str(premium_version),'media[cover]':str(final_asset),'location_confirmed':'1','location_map_url':'https://www.google.com/maps/search/?api=1&query=Medellin','contacts[phone]':'+573001234567','showcase[products][2][asset_id]':str(final_asset)}
    for locale in ['es','en','pt-BR']:
        editorial_fields['translations['+locale+'][display_name]']='Fixture Premium Preparado'
        editorial_fields['translations['+locale+'][summary]']='Resumen preparado '+locale
        editorial_fields['translations['+locale+'][description]']='Presentación preparada '+locale
        editorial_fields['showcase[products][2][translations]['+locale+'][name]']='Producto Estrella Tres'
    check(request(admin_editorial,editorial_fields)[0]==200,'No guardó reemplazos y traducciones finales')
    prepared=DB.execute('SELECT * FROM business_editorial_versions WHERE business_id=? ORDER BY id DESC',(premium_business,)).fetchone()
    quality={'csrf':editorial_admin_csrf,'action':'prepare','version_id':str(prepared['id']),'payload_hash':prepared['payload_hash'],'quality[translations]':'1','quality[images]':'1','quality[layout]':'1'}
    code,prepared_body,_=request(admin_editorial,quality)
    check(code==200 and DB.execute('SELECT status FROM business_editorial_versions WHERE id=?',(prepared['id'],)).fetchone()[0]=='awaiting_owner','No preparó prepublicación: '+prepared_body[-1000:])
    check(DB.execute("SELECT COUNT(*) FROM business_editorial_events WHERE version_id=? AND action='mail_simulated'",(prepared['id'],)).fetchone()[0]==1,'Preparar no envió aviso automático simulado')
    editorial_mail=next(m for m in reversed(json.loads((WORK/'private/mailbox.json').read_text())) if m.get('kind')=='editorial' and m.get('version_id')==prepared['id'])
    check(urllib.parse.urlsplit(editorial_mail['url']).netloc==f'127.0.0.1:{PORT}' and 'version='+str(prepared['id']) in editorial_mail['url'],'El aviso editorial no apunta al ambiente y versión correctos.')
    _,final_preview,_=request(editorial_path+'&version='+str(prepared['id'])+'&preview=page',opener=business_flow)
    check('business/editorial-image.php?id=' not in final_preview,'La versión final sigue usando fotos originales')
    owner_action={'csrf':premium_csrf,'action':'request_changes','version_id':str(prepared['id']),'payload_hash':prepared['payload_hash'],'note':'Corregir la presentación del producto'}
    check(request(editorial_path,owner_action,business_flow)[0]==303,'No pudo solicitar correcciones')
    editorial_fields['version_id']=str(prepared['id'])
    check(request(admin_editorial,editorial_fields)[0]==200,'No guardó corrección editorial')
    corrected=DB.execute('SELECT * FROM business_editorial_versions WHERE business_id=? ORDER BY id DESC',(premium_business,)).fetchone()
    quality.update(version_id=str(corrected['id']),payload_hash=corrected['payload_hash'])
    check(request(admin_editorial,quality)[0]==200,'No preparó versión corregida')
    owner_action.update(action='approve',version_id=str(corrected['id']),payload_hash=corrected['payload_hash'])
    check(request(editorial_path,owner_action,business_flow)[0]==303,'No aprobó versión corregida')
    code,published_body,_=request(admin_editorial,{'csrf':editorial_admin_csrf,'action':'publish','version_id':str(corrected['id']),'payload_hash':corrected['payload_hash']})
    check(code==200 and DB.execute('SELECT status FROM business_editorial_versions WHERE id=?',(corrected['id'],)).fetchone()[0]=='published','No publicó versión corregida: '+published_body[-1500:])
    before_sections=DB.execute('SELECT * FROM business_service_sections WHERE business_id=?',(premium_business,)).fetchall()
    status,_,_=request('/business/dashboard.php?tab=business',dict(profile_fields,csrf='invalid'),business_flow)
    check(status>=400 and DB.execute('SELECT * FROM business_service_sections WHERE business_id=?',(premium_business,)).fetchall()==before_sections,'Profile update bypasses CSRF')
    status,_,_=request('/business/dashboard.php?tab=business',dict(profile_fields,business_id=no_subscription_business_id),business_flow)
    check(status==404,'Profile update accepts another business ID')

    # Test graceful behavior against missing database migrations on a separate DB.
    broken_path = WORK / 'private/missing-schema.sqlite'
    broken_db = sqlite3.connect(broken_path)
    broken_db.executescript((ROOT / 'backend/database/sqlite.sql').read_text())
    broken_db.close()
    broken_config = dict(config, dsn='sqlite:' + str(broken_path))
    broken_file = WORK / 'private/missing-config.php'
    broken_file.write_text("<?php return json_decode(base64_decode('" + base64.b64encode(json.dumps(broken_config).encode()).decode() + "'), true);\n")
    DB.execute("INSERT INTO businesses(public_id,destination_id,category_code,slug) SELECT 'delete-http-fixture',destination_id,category_code,'delete-http-fixture' FROM businesses WHERE id=?", (imported_id,))
    delete_id = DB.execute("SELECT id FROM businesses WHERE public_id='delete-http-fixture'").fetchone()[0]
    DB.execute("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,'es','Negocio para borrar','','')", (delete_id,))
    DB.commit()
    status, body, _ = request('/admin/negocios.php?id='+str(delete_id), opener=ADMIN)
    check(status == 200 and 'Eliminar definitivamente' in body, 'Falta la confirmación visible de borrado.')
    delete_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    unauthenticated = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    for opener, token, confirmation in [(unauthenticated, delete_csrf, '1'), (ADMIN, 'invalid', '1'), (ADMIN, delete_csrf, '')]:
        status, _, _ = request('/admin/negocios.php', {'csrf': token, 'action':'delete', 'id':str(delete_id), 'confirm_delete':confirmation}, opener)
        check(status in (400,403) and DB.execute('SELECT COUNT(*) FROM businesses WHERE id=?',(delete_id,)).fetchone()[0] == 1, 'Se borró sin sesión, CSRF válido o confirmación.')
    status, body, _ = request('/admin/negocios.php', {'csrf':delete_csrf,'action':'delete','id':str(delete_id),'confirm_delete':'1'}, ADMIN)
    check(status == 200 and DB.execute('SELECT COUNT(*) FROM businesses WHERE id=?',(delete_id,)).fetchone()[0] == 0 and 'Negocio eliminado.' in body, f'El borrado confirmado falló: HTTP {status}, {body[:300]}')

    status, body, _ = request('/admin/destinos.php', opener=ADMIN)
    check(status == 200 and 'Agregar Cauca Viejo en borrador' in body, 'Falta el alta preparada de Cauca Viejo.')
    starter_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    status, _, _ = request('/admin/destinos.php', {'csrf':'invalid','action':'create_starter','starter_slug':'cauca-viejo'}, ADMIN)
    check(status == 403 and DB.execute("SELECT COUNT(*) FROM destinations WHERE slug='cauca-viejo'").fetchone()[0] == 0, 'Alta preparada sin CSRF válido.')
    for _ in range(2):
        status, body, _ = request('/admin/destinos.php', {'csrf':starter_csrf,'action':'create_starter','starter_slug':'cauca-viejo'}, ADMIN)
        starter_alerts = __import__('re').findall(r'role="alert">(.*?)<', body)
        check(status == 200 and 'Destino disponible para asociar negocios' in body, f'No se creó o reutilizó el destino preparado: HTTP {status}, {starter_alerts}')
    starter = DB.execute("SELECT id,publication_status,destination_type FROM destinations WHERE slug='cauca-viejo'").fetchone()
    check(starter['publication_status'] == 'draft' and starter['destination_type'] == 'other' and DB.execute("SELECT COUNT(*) FROM destinations WHERE slug='cauca-viejo'").fetchone()[0] == 1, 'El destino preparado se publicó o duplicó.')
    status, body, _ = request('/admin/negocios.php?id='+str(imported_id), opener=ADMIN)
    check(status == 200 and 'Cauca Viejo' in body, 'El destino en borrador no está disponible para asociar negocios.')

    DB.execute("INSERT INTO businesses(public_id,destination_id,category_code,slug) SELECT 'transfer-http-fixture',destination_id,category_code,'transfer-http-fixture' FROM businesses WHERE id=?", (imported_id,))
    transfer_id = DB.execute("SELECT id FROM businesses WHERE public_id='transfer-http-fixture'").fetchone()[0]
    DB.execute("INSERT INTO business_sources(business_id,source_url,note) VALUES(?,'https://example.invalid/rnt','MinCIT RNT 221062')", (transfer_id,))
    DB.execute("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,'es','Casa Mama Rosita','','')", (transfer_id,))
    DB.commit()
    status, body, _ = request('/admin/trasladar-negocios.php', opener=ADMIN)
    check(status == 200 and 'Casa Mama Rosita' in body and 'Posada Dulcinea' in body, 'La vista previa no muestra candidatos y exclusiones.')
    transfer_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    transfer_hash = __import__('re').search(r'name="preview_hash" value="([^"]+)"', body).group(1)
    data = {'csrf':transfer_csrf,'action':'transfer','preview_hash':transfer_hash,'business_ids[]':str(transfer_id),'confirm_transfer':'1'}
    bad = dict(data,csrf='invalid');status, _, _ = request('/admin/trasladar-negocios.php', bad, ADMIN)
    check(status == 403, 'El traslado aceptó una petición sin CSRF válido.')
    status, body, _ = request('/admin/trasladar-negocios.php', data, ADMIN)
    check(status == 200 and '1 negocios trasladados' in body and DB.execute('SELECT destination_id FROM businesses WHERE id=?',(transfer_id,)).fetchone()[0] == starter['id'], 'El traslado desde el panel no conservó y reasoció el mismo ID.')
    check(DB.execute("SELECT COUNT(*) FROM businesses WHERE public_id='transfer-http-fixture'").fetchone()[0] == 1, 'Se duplicó la ficha durante el traslado.')

    broken_env = dict(__import__('os').environ, EL_LOCAL_CONFIG_PATH=str(broken_file))
    # PHP's server reads the env only at startup, so make a second ephemeral listener.
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0)); BROKEN_PORT = sock.getsockname()[1]
    broken_proc = subprocess.Popen(['php', '-d', 'opcache.enable=0', '-S', f'127.0.0.1:{BROKEN_PORT}', '-t', str(WORK / 'public')], env=broken_env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    broken_base = f'http://127.0.0.1:{BROKEN_PORT}'
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, req, fp, code, msg, headers, newurl):
            return None
    broken_client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
    try:
        for _ in range(100):
            try:
                with broken_client.open(broken_base + '/admin/index.php', timeout=2): pass
                break
            except urllib.error.URLError:
                time.sleep(.05)
        # Authenticated session on the broken DB should receive intentional diagnostics, not a PHP fatal/blank page.
        with broken_client.open(broken_base + '/admin/index.php', timeout=3) as response:
            login_html = response.read().decode()
        broken_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', login_html).group(1)
        broken_login = urllib.request.Request(broken_base + '/admin/index.php', data=urllib.parse.urlencode({'action':'login','csrf':broken_csrf,'username':'admin-local','password':password}).encode())
        # Stop the redirect after login so the session cookie is retained before testing pages.
        try:
            with broken_client.open(broken_login, timeout=3) as response:
                check(response.status == 303, 'El login del entorno incompleto no redirigió.')
        except urllib.error.HTTPError as error:
            check(error.code == 303, 'El login del entorno incompleto no redirigió.')
        for path, phrase in [
            ('/admin/negocios.php','migraciones 003–016'),
            ('/admin/usuarios.php','migraciones 003, 008 y 013'),
            ('/admin/solicitudes-cuenta.php','migraciones 013 y 019'),
            ('/admin/reclamaciones.php','migraciones 003 y 017–018'),
            ('/admin/suscripciones.php','migraciones 003, 008–016'),
        ]:
            try:
                with broken_client.open(broken_base + path, timeout=3) as response:
                    code, body = response.status, response.read().decode()
            except urllib.error.HTTPError as error:
                code, body = error.code, error.read().decode()
            check(code in (200,503) and phrase in body and 'Fatal error' not in body, f'{path} no explicó tablas faltantes de forma segura: {code} {body[:200]}')
    finally:
        broken_proc.terminate(); broken_proc.wait(timeout=5)

    traveler_user=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    status,choice,_=request('/business/register.php',opener=traveler_user)
    check(status==200 and 'Crea tu cuenta personal' in choice,'Falta registro de cuenta personal unificada.')
    status,traveler_html,_=request('/business/register.php?tipo=traveler',opener=traveler_user)
    traveler_csrf=re.search(r'name="csrf" value="([^"]+)"',traveler_html)[1]
    traveler_fields={'residence_country_code':'CO','residence_region_code':'05','residence_city_code':'05368','csrf':traveler_csrf,'account_type':'traveler','display_name':'Viajera de prueba','email':'viajera-http@example.invalid','password':'ViajeraTestPassword123','password_confirm':'ViajeraTestPassword123','consent':'1','role':'administrator'}
    status,_,_=request('/business/register.php',traveler_fields,traveler_user)
    check(status==200,'No se creó la cuenta viajera.');verify_account_mail('viajera-http@example.invalid',traveler_user)
    _,login_page,_=request('/business/login.php',opener=traveler_user)
    request('/business/login.php',{'csrf':re.search(r'name="csrf" value="([^"]+)"',login_page)[1],'email':'viajera-http@example.invalid','password':'ViajeraTestPassword123'},traveler_user)
    traveler_row=DB.execute("SELECT id,account_type FROM accounts WHERE email='viajera-http@example.invalid'").fetchone()
    check(traveler_row and traveler_row['account_type']=='traveler','El registro no conservó el tipo viajero.')
    check(DB.execute('SELECT COUNT(*) FROM account_roles WHERE account_id=?',(traveler_row['id'],)).fetchone()[0]==0,'El registro público permitió elevar permisos.')
    check(DB.execute('SELECT COUNT(*) FROM business_account_applications WHERE account_id=?',(traveler_row['id'],)).fetchone()[0]==0,'Un viajero no debe generar solicitudes de negocio.')
    check(DB.execute('SELECT COUNT(*) FROM account_consents WHERE account_id=?',(traveler_row['id'],)).fetchone()[0]==1,'Falta el consentimiento de la cuenta viajera.')
    check(request('/admin/usuarios.php',opener=traveler_user)[0]==403,'La cuenta viajera obtuvo acceso al panel.')
    _,traveler_profile,_=request('/business/dashboard.php?tab=personal',opener=traveler_user)
    traveler_tabs=re.search(r'<nav class="account-tabs".*?</nav>',traveler_profile,re.S).group(0)
    check('>Viajero</a>' in traveler_tabs and 'Mis negocios' not in traveler_tabs and 'Registrar o reclamar un negocio' in traveler_profile,'Traveler without businesses must only see Viajero and an optional registration link.')
    nav_business_id=DB.execute("INSERT INTO businesses(public_id,destination_id,category_code,slug) VALUES('traveler-nav-fixture',?,'hospedaje','traveler-nav-fixture')",(destination_id,)).lastrowid
    DB.execute("INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,'editor','active')",(traveler_row['id'],nav_business_id));DB.commit()
    _,owner_profile,_=request('/business/dashboard.php?tab=personal',opener=traveler_user)
    check('Mis negocios</a>' in re.search(r'<nav class="account-tabs".*?</nav>',owner_profile,re.S).group(0),'Mis negocios must appear once the account has a business, regardless of account type.')
    DB.execute("UPDATE business_account_memberships SET status='revoked' WHERE account_id=?",(traveler_row['id'],));DB.commit()
    _,former_profile,_=request('/business/dashboard.php?tab=personal',opener=traveler_user)
    check('Mis negocios' not in re.search(r'<nav class="account-tabs".*?</nav>',former_profile,re.S).group(0),'Revoked memberships must not keep the business menu visible.')
    # Private editorial workflow through the public account UI, not through an administrator acting as owner.
    DB.execute("INSERT INTO businesses(public_id,destination_id,category_code,slug,information_status,record_origin,publication_status) VALUES('f00d0000-0000-4000-8000-000000000099',?,'hospedaje','editorial-ficticio','VERIFICADO','owner_submitted','published')",(DB.execute('SELECT id FROM destinations ORDER BY id LIMIT 1').fetchone()[0],))
    editorial_business=DB.execute("SELECT id FROM businesses WHERE slug='editorial-ficticio'").fetchone()[0]
    for language in ['es','en','pt-BR']:
        DB.execute("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,'','')",(editorial_business,language,'Editorial Ficticio'))
    DB.execute("INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,'owner','active')",(traveler_row['id'],editorial_business))
    DB.execute("INSERT INTO business_subscriptions(business_id,plan_code,status,starts_at) VALUES(?,'free','active',CURRENT_TIMESTAMP)",(editorial_business,));DB.commit()
    path='/business/editorial.php?business='+str(editorial_business)
    code,editorial_html,_=request(path,opener=traveler_user)
    check(code==200 and 'Contenido de tu suscripción' in editorial_html,'Editor público privado no cargó: '+str(code)+' '+editorial_html[-2500:])
    check(request(path,opener=BUSINESS_OTHER)[0] in (403,503),'Otra cuenta abrió la revisión.') if 'BUSINESS_OTHER' in globals() else None
    owner_csrf=re.search(r'name="csrf" value="([^"]+)"',editorial_html)[1]
    post={'csrf':owner_csrf,'action':'save','version_id':'0','translations[es][display_name]':'Editorial Ficticio Revisado','category_code':'hospedaje'}
    check(request(path,post,traveler_user)[0]==200,'No guardó borrador privado desde cuenta.')
    version=DB.execute('SELECT * FROM business_editorial_versions WHERE business_id=? ORDER BY id DESC',(editorial_business,)).fetchone()
    check(version and version['status']=='draft','No creó versión privada.')
    check(DB.execute('SELECT display_name FROM business_translations WHERE business_id=? AND locale="es"',(editorial_business,)).fetchone()[0]=='Editorial Ficticio','Guardar cambió el sitio publicado.')
    preview_path=path+'&version='+str(version['id'])+'&preview=card'
    code,card,headers=request(preview_path,opener=traveler_user)
    check(code==200 and 'directory-card-tier-free' in card and 'Editorial Ficticio Revisado' in card and 'noindex' in headers.get('X-Robots-Tag',''),'Vista previa no usó tarjeta real privada: '+str(code)+' '+card[-2000:])
    _,_,_=request(path,{'csrf':owner_csrf,'action':'submit','version_id':str(version['id']),'payload_hash':version['payload_hash']},traveler_user)
    admin_path=path+'&admin=1';_,admin_form,_=request(admin_path)
    admin_csrf=re.search(r'name="csrf" value="([^"]+)"',admin_form)[1]
    check(request(admin_path,{'csrf':admin_csrf,'action':'prepare','quality[translations]':'1','quality[images]':'1','quality[layout]':'1','version_id':str(version['id']),'payload_hash':version['payload_hash']})[0]==200,'No dejó preparada la prepublicación.')
    code,_,_=request(path,{'csrf':owner_csrf,'action':'approve','version_id':str(version['id']),'payload_hash':version['payload_hash']},traveler_user)
    check(code==200 and DB.execute('SELECT status FROM business_editorial_versions WHERE id=?',(version['id'],)).fetchone()[0]=='owner_approved','No registró aprobación exacta del comerciante.')
    code,body,_=request(admin_path,{'csrf':admin_csrf,'action':'publish','version_id':str(version['id']),'payload_hash':version['payload_hash']})
    check(code==200 and DB.execute('SELECT status FROM business_editorial_versions WHERE id=?',(version['id'],)).fetchone()[0]=='published','Publicación final falló: '+body[:100])
    check(DB.execute('SELECT display_name FROM business_translations WHERE business_id=? AND locale="es"',(editorial_business,)).fetchone()[0]=='Editorial Ficticio Revisado','No aplicó la versión aprobada.')
    print('OK: flujo editorial HTTP desde cuenta pública, tarjeta real, versión privada, preparación, aprobación exacta y publicación final; sin mensajes externos.')
    print('OK: páginas administrativas autenticadas y bloqueadas para visitantes; registro/inicio/cierre de sesión empresarial, consulta y aprobación, membresía y borrador privado; enlace administrativo de contraseña con almacenamiento hash, expiración y uso único; controles privados; importación transaccional; alta/suscripción/publicación; errores legibles ante migraciones faltantes.')
finally:
    PROCESS.terminate(); PROCESS.wait(timeout=5); DB.close()
    if os.environ.get('EL_KEEP_TEST_FIXTURE')!='1': shutil.rmtree(WORK)
    else: print('Fixture local conservada:',WORK)
