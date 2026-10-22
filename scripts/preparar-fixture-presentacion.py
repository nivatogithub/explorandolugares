#!/usr/bin/env python3
"""Seed a NEW isolated SQLite fixture from versioned editorial data, never from live databases."""
from pathlib import Path
import argparse,base64,json,secrets,sqlite3,subprocess,uuid
ROOT=Path(__file__).resolve().parents[1]
def prepare(package):
 package=package.resolve()
 if not package.is_relative_to(ROOT/'.local'):raise ValueError('Solo paquetes aislados bajo .local/.')
 private=package/'private';private.mkdir(exist_ok=True)
 path=private/'presentation.sqlite'
 if path.exists() or (private/'config.php').exists():raise ValueError('Fixture/config existentes; no se sustituyen datos.')
 db=sqlite3.connect(path);db.executescript((ROOT/'backend/database/sqlite.sql').read_text());db.executescript((ROOT/'backend/database/destinations.sqlite.sql').read_text());db.executescript((ROOT/'backend/database/migrations/006-poi-experience-links.sqlite.sql').read_text())
 for number in range(8,47):
  scripts=list((ROOT/'backend/database/migrations').glob(f'{number:03d}-*.sqlite.sql'))
  if len(scripts)!=1:raise ValueError(f'Migración ambigua {number}')
  db.executescript(scripts[0].read_text())
 def insert(table,row):
  schema={r[1]:r for r in db.execute(f'PRAGMA table_info({table})')};values={k:v for k,v in row.items() if k in schema and not isinstance(v,(dict,list)) and not (v is None and schema[k][3] and schema[k][4] is not None)}
  names=list(values)
  try:return db.execute(f'INSERT OR IGNORE INTO {table} ('+','.join(names)+') VALUES ('+','.join('?' for _ in names)+')',list(values.values())).lastrowid
  except sqlite3.IntegrityError as error:raise ValueError(f'{table} id={row.get("id")}: {error}') from error
 media_ids={}
 release=json.loads((ROOT/'content/release-places-businesses.json').read_text())
 for d in release['editorial'].values():
  insert('destinations',d)
  for locale,tr in d['translations'].items():insert('destination_translations',dict(tr,destination_id=d['id'],locale=locale))
  for key,parent,translation,fk in [('history','destination_story_blocks','destination_story_translations','story_block_id'),('reasons','destination_reasons','destination_reason_translations','reason_id')]:
   for i in range(max(map(len,d[key].values()),default=0)):
    item_id=insert(parent,{'destination_id':d['id'],'display_order':i})
    for locale,items in d[key].items():
     if i<len(items):insert(translation,{fk:item_id,'locale':locale,'body':items[i]})
  for locale,items in d['sources'].items():
   for i,item in enumerate(items):insert('destination_sources',dict(item,destination_id=d['id'],locale=locale,display_order=i))
 for table in ['service_categories','service_category_translations','media_assets','media_asset_translations','businesses','business_translations','business_categories','business_sources','business_contacts','business_media']:
  for row in release['tables'][table]:
   row=dict(row)
   if 'media_asset_id' in row:row['media_asset_id']=media_ids.get(row['media_asset_id'],row['media_asset_id'])
   insert(table,row)
   if table=='media_assets':media_ids[row['id']]=db.execute('SELECT id FROM media_assets WHERE asset_path=?',(row['asset_path'],)).fetchone()[0]
 for d in release['editorial'].values():
  for role,item in d['destination_media'].items():insert('destination_media',dict(item,destination_id=d['id'],role=role,media_asset_id=media_ids.get(item['media_asset_id'],item['media_asset_id'])))
  for i,p in enumerate(d['places']):
   insert('points_of_interest',dict(p,destination_id=d['id'],poi_type=p['type'],display_order=i))
   for locale,tr in p['translations'].items():insert('point_of_interest_translations',dict(tr,point_of_interest_id=p['id'],locale=locale))
   if p.get('media_asset_id'):insert('point_of_interest_media',{'point_of_interest_id':p['id'],'media_asset_id':media_ids.get(p['media_asset_id'],p['media_asset_id']),'role':'gallery','display_order':0})
 password=secrets.token_urlsafe(18)
 password_hash=subprocess.check_output(['php','-r','echo password_hash(trim(stream_get_contents(STDIN)),PASSWORD_DEFAULT);'],input=password.encode()).decode()
 for name,email,admin in [('Revisión Viajera','traveler@example.invalid',False),('Revisión Administración','admin@example.invalid',True)]:
  account_id=insert('accounts',{'public_id':str(uuid.uuid4()),'account_type':'traveler','display_name':name,'email':email,'password_hash':password_hash,'status':'active','email_verified_at':'2026-10-21 00:00:00'})
  if admin:insert('account_roles',{'account_id':account_id,'role':'administrator'})
 db.commit();db.close()
 (private/'review-credentials.json').write_text(json.dumps({'traveler':'traveler@example.invalid','administrator':'admin@example.invalid','password':password}))
 config={'dsn':'sqlite:'+str(path),'app_key':secrets.token_hex(32),'local_test':True,'registration_enabled':True,'business_accounts_enabled':True,'unified_admin_only':True,'account_mail':{'enabled':False},'privacy':{'operator':'Revisión local aislada','contact':'review@example.invalid','retention':'Solo fixture'}}
 encoded=base64.b64encode(json.dumps(config).encode()).decode();(private/'config.php').write_text("<?php return json_decode(base64_decode('"+encoded+"'),true);\n")
 for installer in ['install.php','install-basic.php']:
  subprocess.run(['php',str(package/'app/analytics'/installer)],check=True,capture_output=True)
 print(f'Fixture nueva aislada con {len(release["editorial"])} lugares. Contiene dos cuentas sintéticas, ninguna cuenta ni configuración real. Correos deshabilitados.')
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--package',type=Path,required=True);prepare(p.parse_args().package)
