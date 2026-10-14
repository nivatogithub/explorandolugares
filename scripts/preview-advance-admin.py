#!/usr/bin/env python3
"""Persistent, loopback-only Advance Admin demo with isolated fictional data."""
from pathlib import Path
import argparse, base64, json, secrets, shutil, sqlite3, subprocess, os

root=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--port',type=int,default=8037)
parser.add_argument('--prepare-only',action='store_true',help='Actualizar la copia local sin iniciar otro servidor')
args=parser.parse_args()
if not 1024<=args.port<=65535:parser.error('Usa un puerto entre 1024 y 65535.')
work=root/'.local/advance-preview'
work.mkdir(parents=True,exist_ok=True)
os.chmod(work,0o700)
# Copy only app/public sources; never serve the checkout, configuration or database.
shutil.copytree(root/'backend/app',work/'app',dirs_exist_ok=True)
shutil.copytree(root/'backend/public',work/'public',dirs_exist_ok=True)
assets=work/'public/assets';assets.mkdir(exist_ok=True)
for name in ['style.css','logo.svg','select-flotante.js']:
    shutil.copyfile(root/'ensayos/v1/assets'/name,assets/name)
for name in ['admin/index.php','admin/advance/index.php','admin/reports.php']:
    path=work/'public'/name
    source=path.read_text().replace('<body>','<body><p role="status">Entorno local · datos ficticios · envíos reales deshabilitados</p>',1)
    path.write_text(source)
brand_assets=assets/'brand';brand_assets.mkdir(exist_ok=True)
shutil.copyfile(root/'ensayos/v1/assets/brand/explorando-lugares-logo-v2-transparent.png',brand_assets/'explorando-lugares-logo-v2-transparent.png')
private=work/'private';private.mkdir(exist_ok=True);os.chmod(private,0o700)
sessions=private/'sessions';sessions.mkdir(exist_ok=True);os.chmod(sessions,0o700)
bootstrap=work/'app/bootstrap.php'
bootstrap.write_text(bootstrap.read_text().replace("session_name('el_session');", "session_name('el_advance_preview_"+str(args.port)+"');"))
database=private/'preview.sqlite'
if not database.exists():
    db=sqlite3.connect(database)
    try:
        for filename in ['database/sqlite.sql','database/destinations.sqlite.sql','database/migrations/007-advance-admin.sqlite.sql','database/migrations/008-advance-shared-catalog.sqlite.sql','database/migrations/009-advance-english-values.sqlite.sql','database/migrations/010-advance-unsubscribe.sqlite.sql','database/migrations/011-admin-reports.sqlite.sql','database/migrations/012-business-follow-up.sqlite.sql']:
            db.executescript((root/'backend'/filename).read_text())
        db.executescript((root/'backend/tests/advance/shared-fixture.sql').read_text())
        now='2026-10-14 00:00:00'
        db.execute('INSERT INTO advance_contacts(name,email,phone,municipality,destination_id,relationship,notes,source,marketing_consent,consent_evidence,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',('Persona ficticia de demostración','demo@example.invalid','', '',1,'prospect','Datos de demostración; no corresponden a una persona real.','Demostración local',1,'Consentimiento ficticio para pruebas sin envíos',now,now))
        db.execute('INSERT INTO advance_business_crm(business_id,contact_id) VALUES(1,1)')
        db.execute('INSERT INTO advance_templates(name,subject,html,created_at,updated_at) VALUES(?,?,?,?,?)',('Demostración local','Hola {{name}}','<!doctype html><html lang="es"><body><h1>Explorando Lugares</h1><p>Hola {{name}}, esta es una demostración local para {{municipality}}.</p><p>No se ha enviado ningún correo.</p></body></html>',now,now))
        db.commit()
    finally:db.close()
    os.chmod(database,0o600)
else:
    with sqlite3.connect(database) as db:
        # Do not reset or silently modify data entered during previous review sessions.
        db.execute('SELECT unsubscribe_url FROM advance_deliveries LIMIT 0')
        db.execute('SELECT email FROM advance_email_suppressions LIMIT 0')
        count=db.execute("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('business_subscriptions','subscription_payments','business_capabilities')").fetchone()[0]
        if count==0:db.executescript((root/'backend/database/migrations/011-admin-reports.sqlite.sql').read_text())
        elif count!=3:raise RuntimeError('Esquema parcial de reportes. Revisa la copia local antes de continuar.')
        has_follow_up=db.execute("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='business_follow_up'").fetchone()[0]
        has_plan='plan_name' in [row[1] for row in db.execute('PRAGMA table_info(business_subscriptions)')]
        if not has_follow_up and not has_plan:db.executescript((root/'backend/database/migrations/012-business-follow-up.sqlite.sql').read_text())
        elif bool(has_follow_up)!=has_plan:raise RuntimeError('Esquema parcial de seguimiento. Revisa la copia local.')
access=private/'local-access.json'
if access.exists():
    credentials=json.loads(access.read_text())
else:
    credentials={'username':'advance-local','password':secrets.token_urlsafe(18),'app_key':secrets.token_hex(32)}
    access.write_text(json.dumps(credentials));os.chmod(access,0o600)
hashed=subprocess.check_output(['php','-r','echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);'],input=credentials['password'].encode()).decode()
config={'dsn':'sqlite:'+str(database),'db_user':None,'db_password':None,'app_key':credentials['app_key'],'admin_user':credentials['username'],'admin_password_hash':hashed,'local_test':True,'registration_enabled':False,'public_base_url':'https://example.invalid','resend':{'enabled':False,'api_key':'','from':''},'privacy':{'operator':'Demostración local','contact':'','retention':'Solo datos ficticios','retention_en':''}}
encoded=base64.b64encode(json.dumps(config).encode()).decode()
config_path=private/'config.php';config_path.write_text("<?php return json_decode(base64_decode('"+encoded+"'),true);");os.chmod(config_path,0o600)
# The production URL check is intentionally unchanged. Convert example.invalid links
# to loopback URLs only in this disposable local copy; live sends remain impossible.
p=work/'app/advance/Unsubscribe.php'
s=p.read_text().replace("return rtrim($this->config['public_base_url'],'/')", "return 'http://127.0.0.1:"+str(args.port)+"'")
p.write_text(s)
print(f'Panel: http://127.0.0.1:{args.port}/admin/index.php',flush=True)
print(f'Advance Admin: http://127.0.0.1:{args.port}/admin/advance/index.php',flush=True)
print('Usuario: '+credentials['username'],flush=True)
print('Contraseña local: '+credentials['password'],flush=True)
print('Solo este Mac. SQLite aislada. Datos ficticios persistentes. Resend deshabilitado.',flush=True)
if args.prepare_only:raise SystemExit(0)
os.execvp('php',['php','-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{args.port}','-t',str(work/'public')])
