#!/usr/bin/env python3
"""HTTP regression checks for authenticated admin pages using a disposable SQLite DB."""
from pathlib import Path
import base64, json, secrets, shutil, socket, sqlite3, subprocess, time
from datetime import date, timedelta
import http.cookiejar
import urllib.error, urllib.parse, urllib.request

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT / '.local' / ('admin-test-' + secrets.token_hex(6))
WORK.mkdir(parents=True)
shutil.copytree(ROOT / 'backend/app', WORK / 'app')
shutil.copytree(ROOT / 'backend/public', WORK / 'public')
(WORK / 'app/content').mkdir(parents=True, exist_ok=True)
shutil.copy2(ROOT / 'content/plantilla-importar-negocios.json', WORK / 'app/content/plantilla-importar-negocios.json')
(WORK / 'private').mkdir()
DB_PATH = WORK / 'private/test.sqlite'
DB = sqlite3.connect(DB_PATH)
DB.row_factory = sqlite3.Row
DB.execute('PRAGMA foreign_keys=ON')
DB.executescript((ROOT / 'backend/database/sqlite.sql').read_text())
DB.executescript((ROOT / 'backend/database/destinations.sqlite.sql').read_text())
for number in range(8, 20):
    matches = list((ROOT / 'backend/database/migrations').glob(f'{number:03d}-*.sqlite.sql'))
    assert len(matches) == 1, f'Migración SQLite faltante o ambigua: {number:03d}'
    DB.executescript(matches[0].read_text())

DB.execute("INSERT INTO destinations(slug,publication_status,display_order,latitude,longitude) VALUES('jerico','published',1,5.791,-75.786)")
destination_id = DB.execute('SELECT id FROM destinations').fetchone()[0]
DB.execute("INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(?,?,?,?,?)", (destination_id, 'es', 'Jericó', 'Jericó', 'Destino de prueba'))
DB.execute("INSERT INTO service_categories(code,display_order,is_active) VALUES('hospedaje',1,1)")
DB.execute("INSERT INTO service_category_translations(category_code,locale,label) VALUES('hospedaje','es','Hospedaje')")
DB.execute("INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status) VALUES(?,?,?,?,?,?,?,?,?)", ('00000000-0000-4000-8000-000000000001', destination_id, 'hospedaje', 'public_import', 'hotel-prueba', 'VERIFICADO', 'NO_RECLAMADO', 'ABIERTO', 'published'))
business_id = DB.execute('SELECT id FROM businesses').fetchone()[0]
DB.execute("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)", (business_id, 'es', 'Hotel de prueba', '', ''))
DB.execute("INSERT INTO business_sources(business_id,source_url,note) VALUES(?,?,?)", (business_id, 'https://example.invalid/hotel', 'Fuente ficticia'))
DB.execute("INSERT INTO business_claim_requests(business_id,claimant_name,email,relationship_code,message,consent_version,status) VALUES(?,?,?,?,?,?,?)", (business_id, 'Persona de prueba', 'persona@example.invalid', 'owner', 'Solicitud ficticia', 'test-v1', 'pending'))
DB.execute("INSERT INTO accounts(public_id,account_type,display_name,email,status) VALUES(?,?,?,?,?)", ('00000000-0000-4000-8000-000000000002', 'business', 'Cuenta de prueba', 'cuenta@example.invalid', 'pending_activation'))
account_id = DB.execute('SELECT id FROM accounts').fetchone()[0]
DB.execute("INSERT INTO business_account_applications(account_id,application_type,destination_id,category_code,business_name,relationship_code,contact_phone,message,consent_version,status) VALUES(?,?,?,?,?,?,?,?,?,?)", (account_id, 'request_new', destination_id, 'hospedaje', 'Nuevo negocio de prueba', 'owner', '', 'Solicitud ficticia', 'test-v1', 'pending'))
DB.commit()

password = secrets.token_urlsafe(24)
password_hash = subprocess.check_output(['php', '-r', 'echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_DEFAULT);'], input=password.encode()).decode()
config = {'dsn': 'sqlite:' + str(DB_PATH), 'app_key': secrets.token_hex(32), 'admin_user': 'admin-local', 'admin_password_hash': password_hash, 'local_test': True, 'registration_enabled': False, 'privacy': {'operator': 'Operador de prueba', 'contact': 'test@example.invalid', 'retention': 'Prueba', 'retention_en': 'Test'}}
config_file = WORK / 'private/config.php'
config_file.write_text("<?php return json_decode(base64_decode('" + base64.b64encode(json.dumps(config).encode()).decode() + "'), true);\n")

with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    PORT = sock.getsockname()[1]
PROCESS = subprocess.Popen(['php', '-d', 'opcache.enable=0', '-S', f'127.0.0.1:{PORT}', '-t', str(WORK / 'public')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
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

try:
    for _ in range(100):
        try:
            request('/admin/index.php', opener=GUEST)
            break
        except urllib.error.URLError:
            time.sleep(.05)

    paths = [
        ('/admin/index.php', 'Contactos de negocios'),
        ('/admin/index.php?tab=sistema', 'Contactos de negocios'),
        ('/admin/base-datos.php', 'Estado de la base de datos'),
        ('/admin/destinos.php', 'Administrar destinos'),
        ('/admin/negocios.php', 'Negocios'),
        ('/admin/importar-negocios.php', 'Importar negocios'),
        ('/admin/importar-negocios.php?plantilla=1', 'schema_version'),
        ('/admin/reclamaciones.php', 'Solicitudes anteriores de representación'),
        ('/admin/solicitudes-cuenta.php', 'Solicitudes de cuenta empresarial'),
        ('/admin/usuarios.php', 'Cuentas de usuario'),
        ('/admin/suscripciones.php', 'Suscripciones'),
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

    for path, expected in paths:
        status, body, _ = request(path)
        if path.endswith('plantilla=1'):
            check(status == 200 and 'business-import-v1' in body, f'La plantilla de importación falló: {status} {path}')
        else:
            check(status == 200 and expected in body, f'La página administrativa falló: {status} {path}')
            check('admin-sidebar' in body, f'Falta navegación lateral en {path}')
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

    # Account creation must not make a business account without a verified business.
    account_form = {'csrf': csrf, 'action': 'save', 'account_type': 'business', 'display_name': 'Cuenta sin negocio', 'email': 'sin-negocio@example.invalid'}
    status, body, _ = request('/admin/usuarios.php', account_form)
    check(status == 400 and 'debe quedar vinculada' in body, 'Se creó o aceptó una cuenta empresarial sin negocio aprobado.')
    account_form.update(account_type='traveler', display_name='Viajera de prueba', email='viajera@example.invalid')
    status, body, _ = request('/admin/usuarios.php', account_form)
    check(status == 200 and 'Cuenta creada sin contraseña' in body, 'No se creó la cuenta viajera pendiente.')
    traveler = DB.execute("SELECT account_type,status,password_hash FROM accounts WHERE email='viajera@example.invalid'").fetchone()
    check(tuple(traveler) == ('traveler', 'pending_activation', None), 'La cuenta viajera no quedó pendiente o recibió contraseña.')

    # Import page rejects missing confirmation and malformed JSON without partial rows.
    bad_package = json.dumps({'schema_version': 'business-import-v1', 'destination_slug': 'jerico', 'businesses': []}).encode()
    status, body, _ = multipart('/admin/importar-negocios.php', {'csrf': csrf, 'action': 'import'}, [('package', 'invalid.json', 'application/json', bad_package)])
    check(status == 400 and 'Confirma' in body, 'La importación no exigió confirmación explícita.')
    status, body, _ = multipart('/admin/importar-negocios.php', {'csrf': csrf, 'action': 'import', 'confirm_import': '1'}, [('package', 'invalid.json', 'application/json', b'{no-json')])
    check(status == 400 and 'JSON válido' in body, 'La importación no rechazó JSON malformado con error legible.')
    check(DB.execute('SELECT COUNT(*) FROM businesses').fetchone()[0] == 1, 'La importación inválida dejó registros parciales.')
    valid_package = json.dumps({'schema_version':'business-import-v1','destination_slug':'jerico','businesses':[{'name':'Comercio importado de prueba','summary':'','description':'','category_code':'hospedaje','information_status':'PUBLICO','sources':[{'url':'https://example.invalid/fuente','note':'Prueba automatizada'}]}]}).encode()
    status, body, _ = multipart('/admin/importar-negocios.php', {'csrf': csrf, 'action': 'import', 'confirm_import': '1'}, [('package', 'valid.json', 'application/json', valid_package)])
    check(status == 200 and 'Se importaron 1 negocios' in body, f'La importación válida no terminó: {status} {body[-400:]}')
    imported = DB.execute("SELECT b.publication_status,b.claim_status,b.information_status,b.record_origin FROM businesses b JOIN business_translations t ON t.business_id=b.id WHERE t.display_name='Comercio importado de prueba'").fetchone()
    check(tuple(imported) == ('draft','NO_RECLAMADO','PUBLICO','public_import'), 'La ficha importada no quedó como borrador sin reclamar.')

    # Create a business through the real admin HTTP handler. It must remain a private draft.
    fields = [
        ('csrf', csrf), ('action', 'save'), ('id', ''), ('map_workflow', '1'),
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
    check(status == 200 and 'Ficha guardada como borrador' in body, f'No se guardó una ficha válida: {status} {body[:300]}')
    created = DB.execute("SELECT id,publication_status,information_status,claim_status,record_origin,location_confirmed,latitude,longitude FROM businesses WHERE slug='hostal-creado-por-prueba'").fetchone()
    check(created is not None and tuple(created)[1:] == ('draft', 'PENDIENTE_REVISION', 'NO_RECLAMADO', 'admin_created', 1, 5.791, -75.786), 'La ficha no quedó en borrador privado con ubicación y estados esperados.')
    check(DB.execute('SELECT COUNT(*) FROM business_subscriptions WHERE business_id=? AND plan_code="free"', (created['id'],)).fetchone()[0] == 1, 'La creación no registró la suscripción Gratis pendiente.')

    future_date = (date.today() + timedelta(days=7)).isoformat()
    status, body, _ = request('/admin/suscripciones.php', {'csrf': csrf, 'action': 'save', 'business_id': created['id'], 'plan_code': 'free', 'billing_frequency': 'monthly', 'publish_not_before': future_date})
    check(status == 200 and 'Elección guardada' in body, f'No se guardó la elección de suscripción: {status} {body[-400:]}')
    subscription = DB.execute('SELECT plan_code,status,publish_not_before FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1', (created['id'],)).fetchone()
    check(tuple(subscription) == ('free','pending_publication',future_date), 'La suscripción no quedó pendiente con fecha solicitada.')

    # The admin cannot publish an unverified draft just by posting to the subscription action.
    status, body, _ = request('/admin/suscripciones.php', {'csrf': csrf, 'action': 'publish', 'business_id': created['id']})
    check(status == 400 and 'Verifica la información' in body, f'La publicación permitió un negocio todavía no verificado: {status} {body[-800:]}')
    check(DB.execute('SELECT publication_status FROM businesses WHERE id=?', (created['id'],)).fetchone()[0] == 'draft', 'Un intento de publicación no autorizado cambió la ficha.')

    # Test graceful behavior against missing database migrations on a separate DB.
    broken_path = WORK / 'private/missing-schema.sqlite'
    broken_db = sqlite3.connect(broken_path)
    broken_db.executescript((ROOT / 'backend/database/sqlite.sql').read_text())
    broken_db.close()
    broken_config = dict(config, dsn='sqlite:' + str(broken_path))
    broken_file = WORK / 'private/missing-config.php'
    broken_file.write_text("<?php return json_decode(base64_decode('" + base64.b64encode(json.dumps(broken_config).encode()).decode() + "'), true);\n")
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

    print('OK: páginas administrativas autenticadas y bloqueadas para visitantes; navegación lateral; acciones de revisión de solicitudes y cuentas; controles de cuentas empresariales; importación confirmada y transaccional; alta de negocio con ubicación derivada del mapa y borrador obligatorio; publicación bloqueada hasta validación; errores explícitos cuando faltan migraciones.')
finally:
    PROCESS.terminate(); PROCESS.wait(timeout=5); DB.close(); shutil.rmtree(WORK)
