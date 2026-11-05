"""Fingerprint persistent LOCAL identities/data; allow legitimate counters to grow."""
import hashlib,json

def digest(rows):
 return hashlib.sha256(json.dumps(sorted(json.dumps(r,sort_keys=True,ensure_ascii=False) for r in rows),ensure_ascii=False).encode()).hexdigest()

def capture(query):
 businesses=query('SELECT id FROM businesses ORDER BY id');ids=[int(x['id']) for x in businesses]
 accounts=query('SELECT id FROM accounts ORDER BY id');aids=[int(x['id']) for x in accounts]
 b=','.join(map(str,ids)) or '0';a=','.join(map(str,aids)) or '0'
 statements={
  'businesses':'SELECT * FROM businesses WHERE id IN ('+b+')',
  'business_translations':'SELECT * FROM business_translations WHERE business_id IN ('+b+')',
  'business_memberships':'SELECT * FROM business_account_memberships WHERE business_id IN ('+b+')',
  'business_subscriptions':'SELECT * FROM business_subscriptions WHERE business_id IN ('+b+')',
  'business_media':'SELECT * FROM business_media WHERE business_id IN ('+b+')',
  'business_contacts':'SELECT * FROM business_contacts WHERE business_id IN ('+b+')',
  'accounts':'SELECT id,public_id,account_type,display_name,email,password_hash,status,email_verified_at FROM accounts WHERE id IN ('+a+')',
  'account_roles':'SELECT * FROM account_roles WHERE account_id IN ('+a+')',
  'measurement_settings':'SELECT * FROM el_basic_settings',
 }
 counters={}
 for table,metric in [('el_basic_page_daily','page_views'),('el_basic_entity_daily','metric_count'),('el_basic_country_daily','page_views')]:
  counters[table]=(metric,{json.dumps({k:v for k,v in row.items() if k!=metric},sort_keys=True):int(row[metric]) for row in query('SELECT * FROM '+table)})
 return {'statements':statements,'hashes':{k:digest(query(sql)) for k,sql in statements.items()},'counters':counters,'businesses':len(ids),'accounts':len(aids)}

def verify(snapshot,query):
 changed=[name for name,sql in snapshot['statements'].items() if digest(query(sql))!=snapshot['hashes'][name]]
 if changed:raise AssertionError('Datos persistentes locales modificados: '+', '.join(changed))
 for table,(metric,before) in snapshot['counters'].items():
  after={json.dumps({k:v for k,v in r.items() if k!=metric},sort_keys=True):int(r[metric]) for r in query('SELECT * FROM '+table)}
  if any(after.get(key,-1)<value for key,value in before.items()):raise AssertionError('Contadores perdidos o reducidos: '+table)
 return {'passed':True,'production_accessed':False,'persistent_businesses':snapshot['businesses'],'persistent_accounts':snapshot['accounts'],'checked_groups':list(snapshot['hashes']),'visit_counters_preserved':True}
