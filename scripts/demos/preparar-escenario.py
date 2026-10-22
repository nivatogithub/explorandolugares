#!/usr/bin/env python3
"""Prepara copia local desechable: nunca conecta producción ni envía correo."""
from pathlib import Path
import sqlite3,shutil,json,subprocess,secrets,base64,os
root=Path(__file__).resolve().parents[2]
base=root/'.local/reparacion-admin'
source=base/'paquete'
run=root/'.local/playwright-video'/('escenario-'+secrets.token_hex(4))
run.mkdir(parents=True)
for folder in ('app','public'):
    shutil.copytree(source/folder,run/folder,ignore=shutil.ignore_patterns('assets') if folder=='public' else None)
shutil.copytree(source/'public/assets',run/'public/assets',copy_function=os.link)
(run/'private').mkdir()
src=sqlite3.connect(base/'editorial-preview/test.sqlite');db=sqlite3.connect(run/'private/demo.sqlite');src.backup(db);src.close();db.close()
password='DemoLocal2026!Segura'
hash_value=subprocess.check_output(['php','-r','echo password_hash(trim(stream_get_contents(STDIN)),PASSWORD_DEFAULT);'],input=password.encode()).decode()
config={'dsn':'sqlite:'+str(run/'private/demo.sqlite'),'app_key':secrets.token_hex(32),'local_test':True,'registration_enabled':True,'business_accounts_enabled':True,'admin_user':'admin-demo','admin_password_hash':hash_value,'privacy':{'operator':'Demostración local','contact':'demo@example.invalid','retention':'Prueba local'}}
(run/'private/config.php').write_text("<?php return json_decode(base64_decode('"+base64.b64encode(json.dumps(config).encode()).decode()+"'),true);")
(run/'router.php').write_text("<?php if(rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/')==='/cuenta/verificar-correo'){require __DIR__.'/public/business/verify-email.php';return true;}return require "+repr(str(root/'scripts/php-destinations-router.php'))+";")
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
info={'run':str(run),'admin':'admin-demo','password':password,'source':'copia exclusivamente local','production':False}
(run/'entorno.json').write_text(json.dumps(info,indent=2));(run/'entorno.json').chmod(0o600)
print(run)
