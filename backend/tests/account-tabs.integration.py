#!/usr/bin/env python3
"""Cuenta personal/negocios HTTP, exclusivamente con base desechable y sin correos."""
from pathlib import Path
import base64, http.cookiejar, json, re, secrets, shutil, socket, sqlite3, subprocess, tempfile, time, urllib.request, urllib.parse, urllib.error
ROOT=Path(__file__).resolve().parents[2]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None
with tempfile.TemporaryDirectory(prefix='el-account-tabs-') as temporary:
    work=Path(temporary)
    shutil.copytree(ROOT/'backend/app',work/'app')
    shutil.copytree(ROOT/'backend/public',work/'public')
    (work/'private').mkdir()
    database=sqlite3.connect(work/'private/test.sqlite')
    for source in [ROOT/'backend/database/sqlite.sql',ROOT/'backend/database/destinations.sqlite.sql',ROOT/'backend/database/migrations/006-poi-experience-links.sqlite.sql']:
        database.executescript(source.read_text())
    for number in range(8,39):
        database.executescript(next((ROOT/'backend/database/migrations').glob(f'{number:03d}-*.sqlite.sql')).read_text())
    password='Fixture-only-'+secrets.token_hex(12)
    hashed=subprocess.check_output(['php','-r','echo password_hash(stream_get_contents(STDIN),PASSWORD_DEFAULT);'],input=password.encode()).decode()
    database.execute("INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status,email_verified_at) VALUES(?,?,?,?,?,'active',CURRENT_TIMESTAMP)",('f00d0000-0000-4000-8000-000000000001','traveler','Persona <Prueba>','persona@example.invalid',hashed))
    database.execute("INSERT INTO destinations(slug,publication_status) VALUES('lugar-prueba','draft')")
    database.execute("INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,'es','Lugar prueba','Lugar','Prueba')")
    database.execute("INSERT INTO service_categories(code,display_order,is_active) VALUES('test',1,1)")
    database.execute("INSERT INTO service_category_translations(category_code,locale,label) VALUES('test','es','Prueba')")
    database.execute("INSERT INTO business_account_applications(account_id,application_type,destination_id,category_code,business_name,relationship_code,contact_phone,message,consent_version,status,business_details_json) VALUES(1,'claim_existing',1,'test',?,'owner','','','test','pending',?)",('<Negocio reclamado>',json.dumps({'claim_review_kind':'disputed_existing'})))
    database.commit()
    settings={'dsn':'sqlite:'+str(work/'private/test.sqlite'),'app_key':secrets.token_hex(32),'local_test':True,'business_accounts_enabled':True,'privacy':{'contact':'test@example.invalid'}}
    encoded=base64.b64encode(json.dumps(settings).encode()).decode()
    (work/'private/config.php').write_text("<?php return json_decode(base64_decode('"+encoded+"'),true);")
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(work/'public')],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    opener=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(path,fields=None):
        try:
            response=opener.open(f'http://127.0.0.1:{port}'+path,None if fields is None else urllib.parse.urlencode(fields).encode(),timeout=10)
        except urllib.error.HTTPError as error: response=error
        return response.status,response.read().decode(),response.headers
    try:
        time.sleep(.3)
        code,html,headers=request('/business/dashboard.php'); assert code==303 and headers['Location']=='/negocios/acceso/'
        code,html,_=request('/business/login.php'); csrf=re.search('name="csrf" value="([^"]+)"',html)[1]
        code,html,headers=request('/business/login.php',{'csrf':csrf,'email':'persona@example.invalid','password':password})
        assert code==303 and headers['Location']=='/negocios/portal/'
        code,html,_=request('/business/dashboard.php'); assert code==200 and 'Datos personales' in html and 'Persona &lt;Prueba&gt;' in html
        assert 'aria-current="page">Personal' in html and '¿Qué quieres hacer ahora' not in html
        csrf=re.search('name="csrf" value="([^"]+)"',html)[1]
        residence={'csrf':csrf,'action':'save_residence','residence_country_code':'CO','residence_region_code':'05','residence_city_code':'05368'}
        code,_,_=request('/business/dashboard.php?tab=personal',residence);assert code==303
        assert database.execute('SELECT country_code,region_code,city_code FROM account_residences WHERE account_id=1').fetchone()==('CO','05','05368')
        bad=dict(residence,residence_region_code='25');code,_,_=request('/business/dashboard.php?tab=personal',bad);assert code==400
        assert database.execute('SELECT region_code FROM account_residences WHERE account_id=1').fetchone()[0]=='05'
        code,html,_=request('/business/dashboard.php',{'csrf':csrf,'business_id':'999999','plan_code':'free'})
        assert code==400 and 'No tienes permiso' in html
        code,html,_=request('/business/dashboard.php?tab=business'); assert code==200 and 'Negocios que administras' in html and 'Registrar o reclamar un negocio' in html
        assert 'esta solicitud no te concede acceso para administrarlo' in html and '&lt;Negocio reclamado&gt;' in html
        assert 'Estado de tus pagos' in html and 'Visibilidad de tus negocios' in html and 'aria-current="page">Mis negocios' in html
        code,html,_=request('/business/dashboard.php?tab=administrator'); assert code==400
        code,html,_=request('/business/dashboard.php?espacio=explore'); assert code==200 and 'Datos personales' in html
        code,html,headers=request('/business/workspace.php'); assert code==303 and headers['Location']=='/negocios/portal/?tab=personal'
        assert database.execute('SELECT COUNT(*) FROM business_account_memberships').fetchone()[0]==0
        assert database.execute('SELECT COUNT(*) FROM account_roles').fetchone()[0]==0
        print('PASS: login sin selector; Personal/Mis negocios; identidad escapada; negocio vacío; funciones conservadas; pestaña inválida; compatibilidad; sin privilegios ni membresías nuevas.')
    finally:
        server.terminate();server.wait(timeout=5);database.close()
