#!/usr/bin/env python3
"""HTTP regression checks for authenticated admin pages using a disposable SQLite DB."""
from pathlib import Path
import re, os
import base64, json, secrets, shutil, socket, sqlite3, subprocess, time
from datetime import date, timedelta
import http.cookiejar
import urllib.error, urllib.parse, urllib.request

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT / '.local' / ('admin-test-' + secrets.token_hex(6))
WORK.mkdir(parents=True)
shutil.copytree(ROOT / 'backend/app', WORK / 'app')
shutil.copytree(ROOT / 'backend/public', WORK / 'public')
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
for number in range(8, 31):
    matches = list((ROOT / 'backend/database/migrations').glob(f'{number:03d}-*.sqlite.sql'))
    assert len(matches) == 1, f'Migración SQLite faltante o ambigua: {number:03d}'
    DB.executescript(matches[0].read_text())

DB.execute("INSERT INTO destinations(slug,publication_status,display_order,latitude,longitude) VALUES('jerico','published',1,5.791,-75.786)")
destination_id = DB.execute('SELECT id FROM destinations').fetchone()[0]
DB.execute("INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(?,?,?,?,?)", (destination_id, 'es', 'Jericó', 'Jericó', 'Destino de prueba'))
DB.execute("INSERT INTO service_categories(code,display_order,is_active) VALUES('hospedaje',1,1)")
DB.execute("INSERT INTO service_category_translations(category_code,locale,label) VALUES('hospedaje','es','Hospedaje')")
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
DB.commit()
expected_prices = {'basic': {'monthly': 19900, 'annual': 199000}, 'featured': {'monthly': 49900, 'annual': 499000}, 'page': {'monthly': 69900, 'annual': 699000}, 'page_premium': {'monthly': 119900, 'annual': 1199000}}
for plan, frequencies in expected_prices.items():
    for frequency, amount in frequencies.items():
        actual = DB.execute('SELECT amount,currency FROM subscription_plan_prices WHERE plan_code=? AND billing_frequency=?', (plan, frequency)).fetchone()
        assert actual and actual['amount'] == amount and actual['currency'] == 'COP', f'Tarifa acordada ausente o alterada: {plan}/{frequency}'
for frequency in ('quarterly', 'semiannual'):
    assert DB.execute('SELECT COUNT(*) FROM subscription_plan_prices WHERE billing_frequency=? AND amount IS NOT NULL', (frequency,)).fetchone()[0] == 0, f'No deben inventarse tarifas {frequency}.'

password = secrets.token_urlsafe(24)
password_hash = subprocess.check_output(['php', '-r', 'echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_DEFAULT);'], input=password.encode()).decode()
config = {'dsn': 'sqlite:' + str(DB_PATH), 'app_key': secrets.token_hex(32), 'admin_user': 'admin-local', 'admin_password_hash': password_hash, 'local_test': True, 'registration_enabled': False, 'privacy': {'operator': 'Operador de prueba', 'contact': 'test@example.invalid', 'retention': 'Prueba', 'retention_en': 'Test'}}
config_file = WORK / 'private/config.php'
config_file.write_text("<?php return json_decode(base64_decode('" + base64.b64encode(json.dumps(config).encode()).decode() + "'), true);\n")

def save_config():
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
        ('/admin/index.php', 'Solicitudes de participación'),
        ('/admin/index.php?tab=sistema', 'Solicitudes de participación'),
        ('/admin/base-datos.php', 'Estado de la base de datos'),
        ('/admin/destinos.php', 'Administrar destinos'),
        ('/admin/trasladar-negocios.php', 'Trasladar negocios a Cauca Viejo'),
        ('/admin/negocios.php', 'Negocios'),
        ('/admin/negocios.php?tab=crear', 'Negocios'),
        ('/admin/negocios.php?tab=importar', 'Negocios'),
        ('/admin/negocios.php?tab=revisar', 'Revisar negocios importados'),
        ('/admin/importar-negocios.php', 'Importar varios negocios'),
        ('/admin/importar-negocios.php?plantilla=1', 'schema_version'),
        ('/admin/reclamaciones.php', 'Solicitudes anteriores de representación'),
        ('/admin/solicitudes-cuenta.php', 'Solicitudes de cuenta de negocio'),
        ('/admin/usuarios.php', 'Cuentas'),
        ('/admin/suscripciones.php', 'Suscripciones'),
        ('/admin/inversionistas.php', 'Inteligencia de negocio e inversión'),
        ('/admin/contactos-negocios.php', 'Canales y responsable'),
        ('/admin/campanas.php', 'Campañas por correo'),
        ('/admin/campanas.php?tab=conexion', 'Remitente y conexión'),
        ('/admin/campanas.php?tab=correos', 'Correos de negocios'),
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
            check(body.count('class="admin-sidebar"') == 1, f'La página debe mostrar exactamente una navegación lateral en {path}')
            if not path.endswith('plantilla=1'):
                sidebar = re.search(r'<aside class="admin-sidebar".*?</aside>', body, re.S)
                check(sidebar is not None, f'No se pudo leer el menú lateral en {path}')
                active_links = re.findall(r'<a\b[^>]*aria-current="page"[^>]*>(.*?)</a>', sidebar.group(0), re.S)
                check(len(active_links) == 1, f'El menú lateral debe marcar una sola sección activa en {path}; encontró {len(active_links)}')
                active_label = re.sub(r'<[^>]+>', '', active_links[0]).strip()
                route = path.split('?', 1)[0]
                expected_sidebar = {
                    '/admin/index.php': 'Estado del sistema' if 'tab=sistema' in path else 'Solicitudes de participación',
                    '/admin/base-datos.php': 'Base de datos',
                    '/admin/destinos.php': 'Destinos',
                    '/admin/negocios.php': 'Negocios',
                    '/admin/trasladar-negocios.php': 'Negocios',
                    '/admin/importar-negocios.php': 'Negocios',
                    '/admin/reclamaciones.php': 'Reclamos de negocio',
                    '/admin/solicitudes-cuenta.php': 'Solicitudes de cuenta',
                    '/admin/usuarios.php': 'Cuentas',
                    '/admin/suscripciones.php': 'Suscripciones',
                    '/admin/inversionistas.php': 'Inteligencia e inversión',
                    '/admin/contactos-negocios.php': 'Canales y responsable',
                    '/admin/campanas.php': 'Campañas por correo',
                }.get(route)
                if expected_sidebar:
                    check(active_label == expected_sidebar, f'La sección activa debe ser {expected_sidebar} en {path}; mostró {active_label}')
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
                expected_tab = {'tab=crear': 'Crear negocio', 'tab=importar': 'Importación masiva', 'tab=revisar': 'Revisar y publicar'}.get(next((part for part in path.split('?')[1:] if part.startswith('tab=')), ''), 'Todos los negocios')
                check(selected_label == expected_tab, f'La pestaña activa debe ser {expected_tab} en {path}; mostró {selected_label}')
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
    campaign_row = DB.execute('SELECT id,content_hash FROM business_email_campaigns').fetchone()
    status, body, _ = request('/admin/campanas.php', {'csrf': campaign_csrf, 'action': 'approve', 'id': str(campaign_row[0]), 'hash': campaign_row[1], 'reviewed': '1'})
    check(status == 400 and 'Completa y habilita' in body, 'La conexión no configurada debe bloquear aprobación y envío.')

    settings_input = {'csrf': campaign_csrf, 'action': 'settings', 'sender_name': 'Test Sender', 'sender_email': 'sender@example.invalid', 'reply_to': 'reply@example.invalid', 'sender_address': 'Test address', 'api_key': 'fictitious-http-api-key', 'enabled': '1'}
    status, body, _ = request('/admin/campanas.php', settings_input)
    check(status == 200 and 'Conexión guardada' in body and 'fictitious-http-api-key' not in body, 'La llave no debe volver en HTML.')
    private_settings = (WORK / 'private/brevo/settings.json').read_text()
    check('fictitious-http-api-key' not in private_settings, 'La llave debe quedar cifrada fuera de public/.')
    status, _, _ = request('/admin/campanas.php', {'csrf': campaign_csrf, 'action': 'approve', 'id': str(campaign_row[0]), 'hash': campaign_row[1], 'reviewed': '1'})
    check(status == 400, 'Cambiar el remitente requiere un nuevo borrador revisado.')
    check(DB.execute('SELECT status FROM business_email_campaigns WHERE id=?', (campaign_row[0],)).fetchone()[0] == 'draft', 'Las pruebas HTTP no deben preparar ni enviar campañas al proveedor.')

    _, review_html, _ = request('/admin/negocios.php?tab=revisar')
    check('Seleccionar todos los' in review_html and 'Aprobar y publicar seleccionados' in review_html, 'La revisión de negocios debe admitir selección múltiple y publicación por lote.')
    check('Posibles duplicados' in review_html and 'Hospedaje' in review_html, 'La revisión por lote debe permitir filtrar posibles duplicados y categorías antes de publicar.')
    status, body, _ = request('/admin/inversionistas.php?periodo=30')
    check(status == 200 and 'Últimos 30 días' in body and 'No hay analítica de visitas' in body, 'La inteligencia debe aceptar períodos comparables y aclarar las métricas de visitantes aún no instrumentadas.')
    check('Valor asociado a pagos revisados' in body and '19.900 COP' in body and 'Negocios con suscripción paga vigente' in body, 'Debe presentar el valor cotizado y el negocio pago desde registros persistidos.')
    check('100,0%' in body and '1 de 1 negocios con vencimiento renovaron a tiempo' in body, 'La retención debe contar negocios con vencimiento y renovaciones enlazadas dentro del plazo.')
    check('100,0%' in body and '1 de 1 negocios con vencimiento renovaron a tiempo' in body, 'La retención debe contar negocios con vencimiento y renovaciones enlazadas dentro del plazo.')
    check('Actividad nueva; período anterior sin registros' in body or 'Sin actividad en ambos períodos' in body, 'Debe explicar comparaciones sin una base previa sin inventar porcentajes.')
    review_query = '/admin/negocios.php?tab=revisar&destino=' + str(destination_id) + '&categoria=hospedaje&q=Hotel%20de%20lote&estado=draft&verificacion=PUBLICO&duplicados=no'
    status, review_page_one, _ = request(review_query)
    check(status == 200 and '27 resultados' in review_page_one and 'Página 1 de 2' in review_page_one, 'La revisión de lotes debe paginar y conservar el conteo completo del filtro.')
    check(len(re.findall(r'data-business-id(?:\s|>)', review_page_one)) == 25, 'La primera página de revisión debe mostrar 25 negocios, no el lote completo.')
    check('name="filter_query" value="Hotel de lote"' in review_page_one, 'La acción de aprobación debe conservar la búsqueda aplicada.')
    status, review_page_two, _ = request(review_query + '&pagina=2')
    check(status == 200 and '27 resultados' in review_page_two and 'Página 2 de 2' in review_page_two, 'La segunda página debe conservar los filtros del lote.')
    review_csrf = re.search(r'name="csrf" value="([^"]+)"', review_page_two).group(1)
    bulk_fields = {'csrf': review_csrf, 'action': 'publish_imported_bulk', 'select_all_filtered': '1', 'filter_destination': str(destination_id), 'filter_category': 'hospedaje', 'filter_query': 'Hotel de lote', 'filter_status': 'draft', 'filter_verification': 'PUBLICO', 'filter_duplicates': 'no'}
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
    check(status == 400 and 'se crean desde el registro de negocio' in body, 'El panel aceptó crear una cuenta empresarial por fuera del registro enlazado.')
    account_form.update(account_type='traveler', display_name='Viajera de prueba', email='viajera@example.invalid')
    status, body, _ = request('/admin/usuarios.php', account_form)
    check(status == 400 and 'se crean desde el registro de negocio' in body, 'El panel permitió crear cuentas pendientes desconectadas del registro público.')
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
    status, body, _ = request('/admin/negocios.php?tab=revisar', {'csrf': csrf, 'action': 'publish_imported_bulk', 'select_all_filtered': '1', 'filter_destination': str(destination_id), 'filter_category': 'hospedaje', 'filter_status': 'draft', 'filter_verification': 'PUBLICO', 'filter_duplicates': 'no'})
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

    # Create a business through the real admin HTTP handler. It must remain a private draft.
    failed_create = {'csrf': csrf, 'action': 'save', 'id': '', 'destination_id': str(destination_id), 'category_code': 'categoria_invalida', 'translations[es][display_name]': 'Prueba que debe conservarse', 'plan_code': 'free', 'billing_frequency': 'monthly', 'address_text': 'Parque principal, Jericó', 'map_workflow': '1', 'location_confirmed': '1', 'image_assistance_requested': '0'}
    failed_status, failed_body, _ = request('/admin/negocios.php', failed_create)
    check(failed_status == 400 and 'Crear negocio' in failed_body and 'No se guardó el negocio' in failed_body and 'Prueba que debe conservarse' in failed_body, 'Al fallar el alta, el formulario debe permanecer abierto, explicar el error y conservar los campos escritos.')
    missing_location = {'csrf': csrf, 'action': 'save', 'id': '', 'destination_id': str(destination_id), 'category_code': 'hospedaje', 'translations[es][display_name]': 'Prueba sin punto de mapa', 'plan_code': 'basic', 'billing_frequency': 'monthly', 'address_text': 'Parque principal, Jericó', 'map_workflow': '1', 'location_confirmed': '0', 'image_assistance_requested': '0'}
    location_status, location_body, _ = request('/admin/negocios.php', missing_location)
    check(location_status == 400 and 'Selecciona el punto exacto en el mapa' in location_body and 'Prueba sin punto de mapa' in location_body, 'El alta sin punto confirmado debe explicar que faltó el mapa y conservar lo escrito.')
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
    check(status == 200 and 'Hostal creado por prueba' in body and 'Abrir o editar este negocio' in body and 'Ver altas administrativas' in body, f'No se guardó el negocio ni se mostró el acceso directo al registro creado: {status} {body[:300]}')
    created = DB.execute("SELECT id,publication_status,information_status,claim_status,record_origin,location_confirmed,latitude,longitude FROM businesses WHERE slug='hostal-creado-por-prueba'").fetchone()
    check(created is not None and tuple(created)[1:] == ('draft', 'PENDIENTE_REVISION', 'NO_RECLAMADO', 'admin_created', 1, 5.791, -75.786), 'El negocio no quedó en borrador privado con ubicación y estados esperados.')
    check(DB.execute('SELECT COUNT(*) FROM business_subscriptions WHERE business_id=? AND plan_code="free"', (created['id'],)).fetchone()[0] == 1, 'La creación no registró la suscripción Gratis pendiente.')
    status, body, _ = request('/admin/negocios.php?tab=lista&origen=admin_created&categoria=hospedaje&q=Hostal+creado+por+prueba')
    check(status == 200 and 'Hostal creado por prueba' in body and 'No publicado' in body, 'El negocio recién creado no aparece filtrando el listado por categoría y alta administrativa.')
    check('name="categoria"' in body and 'name="representacion"' not in body, 'La lista administrativa no ofrece categoría como filtro principal.')

    status, subscription_list, _ = request('/admin/suscripciones.php?estado=free')
    check(status == 200 and 'Negocio sin suscripción' in subscription_list and 'Gratis' in subscription_list and 'Elegir suscripción' in subscription_list, 'Un negocio sin fila explícita de suscripción debe aparecer en el nivel Gratis, con opción de configurar su suscripción.')

    future_date = (date.today() + timedelta(days=7)).isoformat()
    status, body, _ = request('/admin/suscripciones.php', {'csrf': csrf, 'action': 'save', 'business_id': created['id'], 'plan_code': 'free', 'billing_frequency': 'monthly', 'publish_not_before': future_date})
    check(status == 200 and 'Elección guardada' in body, f'No se guardó la elección de suscripción: {status} {body[-400:]}')
    subscription = DB.execute('SELECT plan_code,status,publish_not_before FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1', (created['id'],)).fetchone()
    check(tuple(subscription) == ('free','pending_publication',future_date), 'La suscripción no quedó pendiente con fecha solicitada.')

    subscription_count_before_invalid_tariff = DB.execute('SELECT COUNT(*) FROM business_subscriptions WHERE business_id=?', (created['id'],)).fetchone()[0]
    status, body, _ = request('/admin/suscripciones.php', {'csrf': csrf, 'action': 'save', 'business_id': created['id'], 'plan_code': 'page', 'billing_frequency': 'quarterly'})
    check(status == 400 and 'tarifa' in body.lower(), f'El servidor aceptó una frecuencia sin precio configurado: {status} {body[-500:]}')
    check(DB.execute('SELECT COUNT(*) FROM business_subscriptions WHERE business_id=?', (created['id'],)).fetchone()[0] == subscription_count_before_invalid_tariff, 'Una frecuencia sin precio creó una suscripción.')

    # The admin cannot publish an unverified draft just by posting to the subscription action.
    status, body, _ = request('/admin/suscripciones.php', {'csrf': csrf, 'action': 'publish', 'business_id': created['id']})
    check(status == 400 and 'Verifica la información' in body, f'La publicación permitió un negocio todavía no verificado: {status} {body[-800:]}')
    check(DB.execute('SELECT publication_status FROM businesses WHERE id=?', (created['id'],)).fetchone()[0] == 'draft', 'Un intento de publicación no autorizado cambió la ficha.')

    # Full HTTP flow for a new business account, including private unlock, login,
    # approval, membership creation and confirmation that the business stays draft.
    test_key = secrets.token_hex(32)
    config['business_accounts_enabled'] = False
    config['business_accounts_test_key'] = test_key
    save_config()
    business_cookies = http.cookiejar.CookieJar()
    business_user = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(business_cookies))
    status, body, _ = request('/business/register.php?prueba=' + test_key, opener=business_user)
    check(status == 200 and 'Vincula tu negocio a una cuenta' in body and 'name="csrf"' in body, f'El registro empresarial privado no abrió: {status} {body[:300]}')
    registration_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    class NoBusinessRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, req, fp, code, msg, headers, newurl):
            return None
    business_flow = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(business_cookies), NoBusinessRedirect())
    registration_fields = {
        'csrf': registration_csrf, 'application_type': 'new_business', 'business_id': '',
        'business_name': 'Cafetería de cuenta HTTP', 'destination_id': destination_id,
        'category_code': 'hospedaje', 'display_name': 'Responsable HTTP',
        'email': 'responsable-http@example.invalid', 'phone': '3230000000',
        'relationship_code': 'owner', 'message': 'Adjuntaré matrícula y RUT para validar el negocio.',
        'password': 'ClaveFuerteDePrueba123', 'password_confirm': 'ClaveFuerteDePrueba123', 'consent': '1',
    }
    registration_png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII=')
    incomplete_fields = dict(registration_fields, email='sin-documentos-http@example.invalid')
    status, body, _ = request('/business/register.php', incomplete_fields, business_flow)
    check(status == 400 and 'Adjunta los documentos obligatorios' in body and DB.execute("SELECT COUNT(*) FROM accounts WHERE email='sin-documentos-http@example.invalid'").fetchone()[0] == 0, 'El registro aceptó una cuenta sin documentos de identidad y del negocio.')
    status, _, headers = multipart('/business/register.php', registration_fields, [('identity_document', 'identidad.png', 'image/png', registration_png), ('business_registration', 'registro.png', 'image/png', registration_png)], business_flow)
    check(status == 303, f'El registro web no creó la cuenta y redirigió al tablero: {status}')
    status, body, _ = request('/business/dashboard.php?solicitud=recibida', opener=business_user)
    check(status == 200 and 'Cuenta creada y solicitud recibida' in body and 'Cafetería de cuenta HTTP' in body, f'El registro web no terminó en el estado de solicitud esperado: {status} {body[:500]}')
    account_row = DB.execute("SELECT id,account_type,status,password_hash FROM accounts WHERE email='responsable-http@example.invalid'").fetchone()
    application_row = DB.execute("SELECT id,application_type,status,business_id FROM business_account_applications WHERE account_id=?", (account_row['id'],)).fetchone()
    check(account_row and account_row['account_type']=='business' and account_row['status']=='active' and account_row['password_hash'], 'El registro no creó una cuenta empresarial con contraseña protegida.')
    check(application_row and tuple(application_row)[1:] == ('request_new','pending',None), 'El alta inicial creó una ficha demasiado pronto o no dejó la solicitud pendiente.')
    representation_rows = DB.execute('SELECT id,evidence_type,storage_name FROM business_account_application_evidence WHERE application_id=? ORDER BY evidence_type', (application_row['id'],)).fetchall()
    check([row['evidence_type'] for row in representation_rows] == ['business_registration','identity_document'], 'La solicitud debe adjuntar evidencia del negocio y de identidad.')
    check(all(row['storage_name'] not in [item.name for item in (WORK / 'public').rglob('*') if item.is_file()] for row in representation_rows), 'Los documentos de representación deben quedar fuera del directorio público.')
    check(DB.execute("SELECT COUNT(*) FROM businesses WHERE slug='cafeteria-de-cuenta-http'").fetchone()[0] == 0, 'La solicitud nueva se insertó antes de la aprobación administrativa.')
    anonymous = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    check(request('/business/register.php', opener=anonymous)[0] == 404, 'La ruta de registro quedó visible para un visitante sin llave de prueba.')
    dashboard_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    status, _, _ = request('/business/logout.php', {'csrf': dashboard_csrf}, business_flow)
    check(status == 303, 'El cierre de sesión empresarial no completó la redirección.')
    status, body, _ = request('/business/login.php', opener=business_user)
    login_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    status, body, _ = request('/business/login.php', {'csrf': login_csrf, 'email': 'responsable-http@example.invalid', 'password': 'incorrecta'}, business_flow)
    check(status == 400 and 'Correo o contraseña incorrectos' in body, 'El acceso empresarial aceptó una contraseña incorrecta.')
    status, _, _ = request('/business/login.php', {'csrf': login_csrf, 'email': 'responsable-http@example.invalid', 'password': 'ClaveFuerteDePrueba123'}, business_flow)
    check(status == 303, 'El acceso empresarial válido no redirigió al tablero.')
    status, body, _ = request('/business/dashboard.php', opener=business_user)
    check(status == 200 and 'Solicitudes asociadas a tu cuenta' in body and 'Pendiente de revisión' in body, 'La persona no pudo volver a entrar y consultar el estado de su solicitud.')
    csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', request('/admin/index.php')[1]).group(1)
    status, body, _ = request('/admin/solicitudes-cuenta.php', {'csrf': csrf, 'action': 'review', 'application_id': application_row['id'], 'status': 'approved', 'admin_note': 'RUT y matrícula mercantil revisados para la prueba'}, ADMIN)
    check(status == 400 and 'Marca como revisados' in body, 'La administración no debe aprobar una representación sin marcar como revisados sus documentos.')
    check(request('/admin/representacion-evidencia.php?id=' + str(representation_rows[0]['id']), opener=GUEST)[0] == 403, 'Un visitante pudo abrir documentos privados de representación.')
    with ADMIN.open(BASE + '/admin/representacion-evidencia.php?id=' + str(representation_rows[0]['id']), timeout=10) as evidence_response:
        check(evidence_response.status == 200 and evidence_response.read() == registration_png, 'El administrador no pudo abrir un documento de representación privado.')
    status, body, _ = request('/admin/solicitudes-cuenta.php', opener=ADMIN)
    doc_ids = [row['id'] for row in representation_rows]
    review_fields = {'csrf': csrf, 'action': 'review', 'application_id': application_row['id'], 'status': 'approved', 'admin_note': 'Identidad y registro del negocio verificados manualmente.' , 'verified_evidence[]': doc_ids}
    status, body, _ = request('/admin/solicitudes-cuenta.php', review_fields, ADMIN)
    check(status == 200 and 'Se actualizó el estado de la solicitud' in body, 'La administración no pudo aprobar la solicitud web real.')
    approved = DB.execute('SELECT r.status,r.business_id,b.publication_status,b.claim_status,m.account_id FROM business_account_applications r JOIN businesses b ON b.id=r.business_id JOIN business_account_memberships m ON m.business_id=b.id WHERE r.id=?', (application_row['id'],)).fetchone()
    check(approved and approved['status']=='approved' and approved['publication_status']=='draft' and approved['claim_status']=='RECLAMADO' and approved['account_id']==account_row['id'], 'La aprobación no creó el borrador privado y la membresía esperados.')
    status, body, _ = request('/business/dashboard.php', opener=business_user)
    check(status == 200 and 'Cafetería de cuenta HTTP' in body and 'draft' in body, 'El tablero no reflejó el negocio asociado después de la aprobación.')
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
    status, body, _ = request('/business/dashboard.php', opener=business_flow)
    check(status == 200 and 'Elegir suscripción y enviar soporte de pago' in body and 'Cafetería de cuenta HTTP' in body and '69900' in body, 'El panel de negocio no mostró la tarifa acordada para la suscripción Página.')
    account_payment_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    business_id = DB.execute("SELECT b.id FROM businesses b JOIN business_translations t ON t.business_id=b.id WHERE t.locale='es' AND t.display_name='Cafetería de cuenta HTTP'").fetchone()[0]
    sample_png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII=')
    status, body, _ = multipart('/business/dashboard.php', {'csrf': account_payment_csrf, 'business_id': business_id, 'plan_code': 'page', 'billing_frequency': 'monthly', 'payment_reference': 'TRX-LOCAL-2026'}, [('payment_proof', 'soporte.png', 'image/png', sample_png)], business_flow)
    check(status == 303, f'El representante no pudo enviar el comprobante: HTTP {status} {body[:300]}')
    payment_row = DB.execute('SELECT e.id,e.storage_name,e.account_id,e.status,s.status AS subscription_status,s.quoted_amount FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE e.account_id=? ORDER BY e.id DESC LIMIT 1', (account_row['id'],)).fetchone()
    check(payment_row and payment_row['status']=='pending' and payment_row['subscription_status']=='pending' and payment_row['quoted_amount']==69900 and payment_row['storage_name'] not in [item.name for item in (WORK / 'public').rglob('*') if item.is_file()], 'El comprobante no quedó privado o alteró antes de revisión el pago/la publicación.')
    status, subscriptions_with_evidence, _ = request('/admin/suscripciones.php?q=Cafeter%C3%ADa')
    check(status == 200 and 'Ver comprobante' in subscriptions_with_evidence and 'Pendiente de revisión' in subscriptions_with_evidence, 'La fila de suscripción debe enlazar el comprobante pendiente de ese negocio.')
    check(request('/admin/comprobante-pago.php?id=' + str(payment_row['id']), opener=GUEST)[0] == 403, 'Un visitante pudo abrir un soporte privado de pago.')
    with ADMIN.open(BASE + '/admin/comprobante-pago.php?id=' + str(payment_row['id']), timeout=10) as evidence_response:
        check(evidence_response.status == 200 and evidence_response.read() == sample_png, 'El administrador no pudo abrir el comprobante privado.')
    status, body, _ = request('/admin/suscripciones.php', {'csrf': csrf, 'action': 'review_payment', 'evidence_id': payment_row['id'], 'decision': 'approved', 'admin_note': ''}, ADMIN)
    reviewed_payment = DB.execute('SELECT e.status,s.status AS subscription_status FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE e.id=?', (payment_row['id'],)).fetchone()
    check(status == 200 and reviewed_payment['status']=='approved' and reviewed_payment['subscription_status']=='paid_pending_publication', 'Aprobar el comprobante no dejó el pago esperando publicación.')
    check(DB.execute('SELECT publication_status FROM businesses WHERE id=?', (business_id,)).fetchone()[0]=='draft', 'Aprobar el pago publicó el negocio automáticamente.')
    status, body, _ = request('/business/dashboard.php', opener=business_flow)
    check(status == 200 and 'Pago confirmado' in body and 'todavía requiere aprobación de publicación' in body, 'La cuenta no muestra que el pago se aprobó mientras el negocio sigue en borrador.')
    account_payment_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    status, _, _ = multipart('/business/dashboard.php', {'csrf': account_payment_csrf, 'business_id': business_id, 'plan_code': 'page', 'billing_frequency': 'monthly', 'payment_reference': 'TRX-RECHAZO-LOCAL'}, [('payment_proof', 'soporte-segundo.png', 'image/png', sample_png)], business_flow)
    check(status == 303, 'La cuenta no pudo enviar un soporte de reemplazo para probar el flujo de rechazo.')
    rejected_evidence = DB.execute('SELECT id FROM business_subscription_payment_evidence WHERE account_id=? ORDER BY id DESC LIMIT 1', (account_row['id'],)).fetchone()[0]
    rejection_message = 'El valor transferido no coincide con el importe esperado. Verifica el soporte y vuelve a enviarlo.'
    internal_note = 'El soporte no coincide con la tarifa cotizada.'
    status, _, _ = request('/admin/suscripciones.php', {'csrf': csrf, 'action': 'review_payment', 'evidence_id': rejected_evidence, 'decision': 'rejected', 'admin_note': internal_note, 'customer_message': rejection_message}, ADMIN)
    status, body, _ = request('/business/dashboard.php', opener=business_flow)
    check(status == 200 and 'Comprobante rechazado' in body and rejection_message in body and 'envía un nuevo soporte válido' in body and internal_note not in body, 'El portal no mostró el mensaje para el negocio o expuso la nota interna.')
    status, body, _ = request('/admin/usuarios.php', {'csrf': csrf, 'action': 'set_status', 'account_id': account_row['id'], 'status': 'disabled'}, ADMIN)
    check(status == 200 and 'La cuenta quedó deshabilitada' in body, f'La administración no pudo deshabilitar el acceso de una cuenta: HTTP {status}, {body[:300]}')
    status, _, headers = request('/business/dashboard.php', opener=business_flow)
    check(status == 303 and headers.get('Location', '').endswith('/negocios/acceso/'), f'Una cuenta deshabilitada conservó el acceso con una sesión abierta: HTTP {status}, destino {headers.get("Location", "")}')
    status, body, _ = request('/business/login.php', opener=business_user)
    disabled_login_csrf = __import__('re').search(r'name="csrf" value="([^"]+)"', body).group(1)
    status, body, _ = request('/business/login.php', {'csrf': disabled_login_csrf, 'email': 'responsable-http@example.invalid', 'password': 'NuevaClaveHTTP123'}, business_flow)
    check(status == 400 and 'Correo o contraseña incorrectos' in body, 'Una cuenta deshabilitada pudo iniciar sesión nuevamente.')
    status, body, _ = request('/admin/usuarios.php', {'csrf': csrf, 'action': 'set_status', 'account_id': account_row['id'], 'status': 'active'}, ADMIN)
    check(status == 200 and 'La cuenta quedó habilitada nuevamente' in body, 'La administración no pudo rehabilitar una cuenta deshabilitada.')
    status, _, _ = request('/business/login.php', {'csrf': disabled_login_csrf, 'email': 'responsable-http@example.invalid', 'password': 'NuevaClaveHTTP123'}, business_flow)
    check(status == 303, 'La cuenta rehabilitada no pudo iniciar sesión nuevamente.')

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

    print('OK: páginas administrativas autenticadas y bloqueadas para visitantes; registro/inicio/cierre de sesión empresarial, consulta y aprobación, membresía y borrador privado; enlace administrativo de contraseña con almacenamiento hash, expiración y uso único; controles privados; importación transaccional; alta/suscripción/publicación; errores legibles ante migraciones faltantes.')
finally:
    PROCESS.terminate(); PROCESS.wait(timeout=5); DB.close(); shutil.rmtree(WORK)
