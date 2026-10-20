#!/usr/bin/env python3
"""F01: exercise both renderer sources in the actual public/app/private layout.
No EL_DESTINATIONS_DSN fallback: catches configuration-discovery regressions.
Disposable SQLite HTTP test; does not alter the shared preprod container.
"""
from pathlib import Path
import base64
import json
import os
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='el-business-pages-') as temporary:
    work = Path(temporary)
    shutil.copytree(ROOT / 'backend/app', work / 'app')
    (work / 'public').mkdir()
    (work / 'private').mkdir()
    database = work / 'private/test.sqlite'
    db = sqlite3.connect(database)
    for schema in ('sqlite.sql', 'destinations.sqlite.sql'):
        db.executescript((ROOT / 'backend/database' / schema).read_text())
    db.executescript((ROOT / 'backend/database/migrations/006-poi-experience-links.sqlite.sql').read_text())
    for number in range(8, 35):
        migrations = list((ROOT / 'backend/database/migrations').glob(f'{number:03d}-*.sqlite.sql'))
        assert len(migrations) == 1
        db.executescript(migrations[0].read_text())
    db.executescript((ROOT / 'backend/database/migrations/045-business-service-sections.sqlite.sql').read_text())
    db.execute("INSERT INTO destinations(slug,publication_status) VALUES('prueba','published')")
    destination = db.execute('SELECT last_insert_rowid()').fetchone()[0]
    db.execute("INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(?,'es','Prueba','Prueba','Prueba')", (destination,))
    db.execute("INSERT INTO service_categories(code,display_order,is_active) VALUES('hotel_prueba',1,1)")
    db.execute("INSERT INTO service_category_translations(category_code,locale,label) VALUES('hotel_prueba','es','Hotel')")
    ids = {}
    for index, (label, state, tier) in enumerate((('pago', 'published', 'page'), ('borrador', 'draft', 'page'), ('gratuito', 'published', None)), 1):
        public_id = f'00000000-0000-4000-8000-{index:012d}'
        ids[label] = public_id
        db.execute('INSERT INTO businesses(public_id,destination_id,category_code,slug,information_status,operation_status,publication_status) VALUES(?,?,?, ?,?,?,?)', (public_id, destination, 'hotel_prueba', label, 'VERIFICADO', 'ABIERTO', state))
        business = db.execute('SELECT last_insert_rowid()').fetchone()[0]
        db.execute("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,'es',?,'Resumen','Descripción')", (business, label))
        if tier:
            db.execute("INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,starts_at,ends_at) VALUES(?,?,'monthly','active',datetime('now','-1 day'),datetime('now','+30 days'))", (business,tier))
    paid_business=db.execute("SELECT id FROM businesses WHERE slug='pago'").fetchone()[0]
    for locale in ['es','en','pt-BR']:
        db.execute('INSERT INTO business_service_sections VALUES(?,?,?,?)',(paid_business,locale,'restaurant','<script>alert(1)</script> Cocina local'))
    db.commit()
    encoded = base64.b64encode(json.dumps({'dsn': 'sqlite:' + str(database)}).encode()).decode()
    (work / 'private/config.php').write_text("<?php return json_decode(base64_decode('" + encoded + "'),true);")
    (work / 'router.php').write_text("""<?php
    if ($_SERVER['REQUEST_URI']==='/directory') {
      require __DIR__.'/app/DestinationRepository.php';
      $config=require __DIR__.'/private/config.php';
      $pdo=new PDO($config['dsn']);
      header('Content-Type: application/json');
      echo json_encode((new DestinationRepository($pdo))->findPublished('prueba','es')['businesses']);return;
    }
    $_GET['id']=basename(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
    require __DIR__.'/public/negocio.php';
    """)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    environment = {k:v for k,v in os.environ.items() if not k.startswith('EL_DESTINATIONS_')}
    process = subprocess.Popen(['php','-d','opcache.enable=0','-S',f'127.0.0.1:{port}',str(work/'router.php')],env=environment,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    def get(path):
        try:
            with urllib.request.urlopen(f'http://127.0.0.1:{port}'+path,timeout=5) as response:
                return response.status,response.read().decode()
        except urllib.error.HTTPError as error:
            return error.code,error.read().decode()
    try:
        for attempt in range(100):
            try:
                socket.create_connection(('127.0.0.1',port),timeout=.1).close();break
            except OSError:time.sleep(.03)
        for source in ('ensayos/v1/negocio.php','backend/integration-medicion/public/negocio.php'):
            shutil.copy2(ROOT/source,work/'public/negocio.php')
            status,html=get('/negocios/'+ids['pago'])
            assert status==200 and '<h1>pago</h1>' in html,(source,status,html[:100])
            assert 'id="servicio-restaurant"' not in html,'Normal page exposes Premium section'
            if source.startswith('backend/'):
                db.execute("UPDATE business_subscriptions SET plan_code='page_premium' WHERE business_id=?",(paid_business,));db.commit()
                status,premium=get('/negocios/'+ids['pago'])
                assert status==200 and 'id="servicio-restaurant"' in premium,premium[:200]
                assert '&lt;script&gt;alert(1)&lt;/script&gt; Cocina local' in premium and '<script>alert(1)</script>' not in premium
                assert 'id="servicio-lodging"' not in premium and 'id="servicio-other"' not in premium
                db.execute("UPDATE business_subscriptions SET plan_code='page' WHERE business_id=?",(paid_business,));db.commit()
                assert 'id="servicio-restaurant"' not in get('/negocios/'+ids['pago'])[1],'Downgrade leaks sections'
                print('OK: Premium sections render escaped, empty sections hidden, lower tier hides preserved data')
            assert get('/negocios/'+ids['borrador'])[0]==404,source
            assert get('/negocios/'+ids['gratuito'])[0]==404,source
            assert get('/negocios/00000000-0000-4000-8000-999999999999')[0]==404,source
            print('OK:',source,'published paid 200; draft/free/no record 404; canonical private config without DSN environment')
        status,body=get('/directory'); assert status==200,body
        rows=json.loads(body);names={row['display_name'] for row in rows}
        assert names=={'pago','gratuito'},names
        free=next(row for row in rows if row['display_name']=='gratuito')
        assert free['plan_code']=='free' and 'business_page' not in free['features'],free
        print('OK: free published business stays in directory without paid page; draft excluded')
        db.execute("UPDATE destinations SET publication_status='draft' WHERE id=?",(destination,));db.commit()
        assert get('/negocios/'+ids['pago'])[0]==404
        print('OK: draft destination also prevents paid-business publication')
    finally:
        process.terminate();process.wait(timeout=5);db.close()
