#!/usr/bin/env python3
"""Prepara copia local desechable: nunca conecta producción ni envía correo."""
from pathlib import Path
import sqlite3,shutil,json,subprocess,secrets,base64,os,argparse
root=Path(__file__).resolve().parents[2]
parser=argparse.ArgumentParser();parser.add_argument('--package',required=True);args=parser.parse_args()
source=Path(args.package).resolve()
if not source.is_relative_to(root/'.local') or not (source/'build-manifest.json').is_file():raise ValueError('Usa un paquete local completo y construido.')
base=root/'.local/reparacion-admin'
run=root/'.local/playwright-video'/('escenario-'+secrets.token_hex(4));run.mkdir(parents=True)
for folder in ('app','public'):
    shutil.copytree(source/folder,run/folder,ignore=shutil.ignore_patterns('assets') if folder=='public' else None)
shutil.copytree(source/'public/assets',run/'public/assets',copy_function=os.link)
(run/'private').mkdir()
src=sqlite3.connect(base/'editorial-preview/test.sqlite');db=sqlite3.connect(run/'private/demo.sqlite');src.backup(db);src.close();db.close()
password='DemoLocal2026!Segura'
hash_value=subprocess.check_output(['php','-r','echo password_hash(trim(stream_get_contents(STDIN)),PASSWORD_DEFAULT);'],input=password.encode()).decode()
config={'dsn':'sqlite:'+str(run/'private/demo.sqlite'),'app_key':secrets.token_hex(32),'local_test':True,'registration_enabled':True,'business_accounts_enabled':True,'admin_user':'admin-demo','admin_password_hash':hash_value,'privacy':{'operator':'Demostración local','contact':'demo@example.invalid','retention':'Prueba local'}}
(run/'private/config.php').write_text("<?php return json_decode(base64_decode('"+base64.b64encode(json.dumps(config).encode()).decode()+"'),true);")
(run/'router.php').write_text("<?php $path=rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');$aliases=['/cuenta/verificar-correo'=>'business/verify-email.php','/cuenta/espacio'=>'business/workspace.php','/suscripciones/continuar'=>'business/checkout.php'];if(isset($aliases[$path])){require __DIR__.'/public/'.$aliases[$path];return true;}return require "+repr(str(root/'scripts/php-destinations-router.php'))+";")
(run/'fixtures').mkdir()
# Documento sintético, no una identificación real.
(run/'fixtures/documento.svg').write_text('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500"><rect width="800" height="500" fill="white"/><text x="60" y="100" font-size="32">DOCUMENTO DE PRUEBA · SIN VALIDEZ</text></svg>')
# Copia local de una referencia ya disponible; no se genera ni cambia ningún medio del portal.
photos=list((source/'public/assets').rglob('*.webp'))
assert photos,'Falta imagen local'
shutil.copyfile(photos[0],run/'fixtures/foto.webp')
# PDF sintético mínimo válido para probar cargas, con marca explícita.
pdf=b'%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 600 400]/Contents 4 0 R>>endobj\n4 0 obj<</Length 0>>stream\n\nendstream endobj\ntrailer<</Root 1 0 R>>\n%%EOF'
(run/'fixtures/documento.pdf').write_bytes(pdf)
db=sqlite3.connect(run/'private/demo.sqlite')
for migration in sorted((root/'backend/database/migrations').glob('*.sqlite.sql')):
 if int(migration.name.split('-')[0])>=50:db.executescript(migration.read_text())
db.execute("INSERT OR REPLACE INTO payment_instructions(id,enabled,test_mode,holder,bank,account_number,account_type,breb_key,notes,qr_file,updated_at,updated_by) VALUES(1,1,1,'Demostración local — SIN TRANSFERENCIA','Banco de prueba','0000000000','savings','DEMO-NO-PAGAR','Datos sintéticos. No transferir dinero.','',datetime('now'),0)")
for asset_id,asset_path in db.execute('SELECT id,asset_path FROM media_assets').fetchall():
 if not (run/'public'/asset_path).is_file():raise ValueError('Falta un recurso de la base demo: '+asset_path)
db.commit();db.close()

info={'run':str(run),'admin':'admin-demo','password':password,'source':str(source),'source_commit':subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip(),'production':False}
(run/'entorno.json').write_text(json.dumps(info,indent=2));(run/'entorno.json').chmod(0o600)
print(run)
