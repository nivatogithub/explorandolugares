#!/usr/bin/env python3
"""HTTP checks against disposable SQLite only; no production traffic or cookies."""
from pathlib import Path
import base64, json, os, shutil, socket, sqlite3, subprocess, tempfile, time
import urllib.request, urllib.error

ROOT=Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='el-basic-http-') as temporary:
    work=Path(temporary)
    shutil.copytree(ROOT/'backend/app',work/'app')
    shutil.copytree(ROOT/'backend/public',work/'public')
    (work/'public/index.html').write_text('<h1>Test only</h1>')
    (work/'private').mkdir()
    database=work/'private/test.sqlite'
    config=base64.b64encode(json.dumps({'dsn':'sqlite:'+str(database),'app_key':'x'*40}).encode()).decode()
    (work/'private/config.php').write_text("<?php return json_decode(base64_decode('"+config+"'), true);")
    subprocess.run(['php',str(work/'app/analytics/install-basic.php')],check=True,capture_output=True)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    base=f'http://127.0.0.1:{port}'
    with (work/'server.log').open('w') as log:
        server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(work/'public')],stdout=log,stderr=log)
        try:
            def call(body=None,extra=None):
                headers={'Origin':base,'Content-Type':'application/json','User-Agent':'Local verification'}
                headers.update(extra or {})
                request=urllib.request.Request(base+'/api/actividad.php',data=json.dumps(body).encode() if body is not None else None,headers=headers)
                try:
                    with urllib.request.urlopen(request,timeout=5) as r:return r.status,json.loads(r.read()),r.headers
                except urllib.error.HTTPError as r:return r.code,json.loads(r.read()),r.headers
            for _ in range(100):
                try: status,body,headers=call();break
                except OSError: time.sleep(.05)
            assert status==200 and body['enabled'] and body['cookies'] is False and body['visitor_identifiers'] is False
            assert 'Set-Cookie' not in headers
            for _ in range(2):
                status,body,headers=call({'path':'/'})
                assert status==200 and body['accepted'] and 'Set-Cookie' not in headers
            status,body,headers=call({'path':'/'},{'Cookie':'el_session=ignored; el_stats_visitor=ignored'})
            assert body['accepted'] and 'Set-Cookie' not in headers
            for signal in [{'DNT':'1'},{'Sec-GPC':'1'},{'User-Agent':'Googlebot'}]:
                assert call({'path':'/'},signal)[1]['accepted'] is False
            assert call({'path':'/'},{'Origin':'https://example.invalid'})[0]==403
            assert call({'path':'/','visitor_id':'forbidden'})[0]==400
            assert call({'path':'/admin/index.html'})[1]['accepted'] is False
            assert call({'path':'/?email=person@example.invalid'})[1]['accepted'] is False
            assert call({'path':'/not-real/'})[1]['accepted'] is False
            db=sqlite3.connect(database)
            assert db.execute('SELECT SUM(page_views) FROM el_basic_page_daily').fetchone()[0]==3
            assert [row[1] for row in db.execute('PRAGMA table_info(el_basic_page_daily)')]==['page_day','scope_code','page_views']
            assert db.execute("SELECT COUNT(*) FROM sqlite_master WHERE name IN ('el_measurement_events','el_measurement_sessions')").fetchone()[0]==0
            assert db.execute('SELECT SUM(page_views) FROM el_basic_country_daily').fetchone()[0]==3
            assert [row[1] for row in db.execute('PRAGMA table_info(el_basic_country_daily)')]==['page_day','scope_code','destination_slug','country_code','page_views']
            assert db.execute('SELECT DISTINCT country_code FROM el_basic_country_daily').fetchall()==[('ZZ',)]
            for payload in [None, {'event':'page_view','consent':True,'policy_version':'measurement-v1','path':'/'}]:
                request=urllib.request.Request(base+'/api/medicion.php',data=None if payload is None else json.dumps(payload).encode(),headers={'Origin':base,'Content-Type':'application/json'})
                with urllib.request.urlopen(request) as r:
                    legacy=json.load(r);assert 'Set-Cookie' not in r.headers
                    assert legacy.get('enabled') is False if payload is None else legacy['accepted'] is False
            with urllib.request.urlopen(base+'/api/cobertura.php?days=all') as r:
                result=json.load(r);assert result['audience']['basic']['page_views']==3
                assert result['audience']['basic']['period_mode']=='all'
                assert result['audience']['basic']['comparison_available'] is False
            db.close()
            print('Basic HTTP: OK — no Set-Cookie, no session or event table, consent-independent counting, DNT/GPC, bots, same-origin, private paths and payload allowlist.')
        finally:
            server.terminate();server.wait(timeout=5)
