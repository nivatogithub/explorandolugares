"""Acciones HTTP exclusivamente públicas; SQL solo inspecciona fixtures aislados del runner.
No aprueba solicitudes, pagos ni crea membresías para superar la verificación pendiente.
"""
import json,re

def run(env):
 case,check,query=env['case'],env['check'],env['query']
 person,anon,fixtures=env['person'],env['anon'],env['fixtures']
 def current():
  if not fixtures:raise env['Blocked']('Sin fixtures del conjunto NG-00.')
  # A first purchase must not reuse the active paid business used to test public pages.
  return env['account']()['id'],fixtures[3]
 def application_prices():
  current()
  for plan,frequency,expected in [('featured','quarterly',124750),('page','semiannual',363480),('page_premium','annual',1199000)]:
   code,page,_=env['application']('new_business',plan=plan,frequency=frequency)
   check(code==200,'No guarda alta pública '+plan)
   rows=query('SELECT business_details_json FROM business_account_applications WHERE account_id=? AND application_type="request_new" ORDER BY id DESC LIMIT 1',[env['account']()['id']])
   q=json.loads(rows[0]['business_details_json'])['requested_subscription']
   check(q['plan_code']==plan and q['billing_frequency']==frequency and float(q['quoted_amount'])==expected,'No conserva elección y cotización '+plan)
 case('NG-06','Alta pública: tres suscripciones pagas con sus precios y períodos',application_prices)
 def instructions():
  _,bid=current();code,page,_=person.get('/negocios/portal/?tab=business&business='+str(bid))
  check(code==200,'No abre pago público')
  if 'SIMULACIÓN — NO ENVÍES DINERO' not in page:raise env['Blocked']('Configurar primero los medios ficticios de preproducción; no se modifican desde esta suite pública.')
  check(all(x in page for x in ['PRUEBA-EXPLORANDO-NO-PAGAR','NO-TRANSFERIR-0000','Ahorros','/api/payment-qr.php']),'Faltan medios de simulación configurados')
  check(person.get('/api/payment-qr.php')[0]==200 and anon.get('/api/payment-qr.php')[0]==403,'QR no respeta sesión')
 case('PG-01','Pago público: QR, Bre-B, banco, número y tipo de cuenta de simulación',instructions)
 def paid(plan,frequency,amount):
  aid,bid=current();code,page,_=person.get('/negocios/portal/?tab=business&business='+str(bid))
  before=query('SELECT publication_status FROM businesses WHERE id=?',[bid])
  reference=env['RUN']+'-'+plan
  fields={'csrf':env['csrf'](page),'business_id':bid,'plan_code':plan,'billing_frequency':frequency,'quoted_amount':'1','payment_reference':reference,'transaction_date':env['datetime'].date.today().isoformat(),'paid_amount':str(amount),'payment_method':'breb','source_bank':'BANCO FICTICIO'}
  code,page,url=person.request('/negocios/portal/?tab=business&business='+str(bid),fields,['payment_proof'])
  check(code==200 and 'Recibimos el soporte' in page,'No recibe comprobante público '+plan)
  check('business='+str(bid) in url,'Perdió pestaña del negocio al guardar')
  rows=query('SELECT e.checkout_reference,e.paid_amount,e.transaction_date,e.source_bank,e.status AS evidence_status,s.status,s.plan_code,s.billing_frequency,s.quoted_amount,s.starts_at FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE e.account_id=? AND e.payment_reference LIKE ?',[aid,'%'+reference])
  check(len(rows)==1,'No persistió exactamente un comprobante')
  row=rows[0]
  check(float(row['paid_amount'])==amount and row['source_bank']=='BANCO FICTICIO' and re.fullmatch(r'EL-[A-F0-9]{24}',row['checkout_reference']),'No persistió detalle de transacción');check(row['status']=='pending' and row['evidence_status']=='pending' and row['starts_at'] is None,'El pago se activó sin revisión')
  check(row['plan_code']==plan and row['billing_frequency']==frequency and float(row['quoted_amount'])==amount,'Aceptó importe manipulado o perdió la elección')
  check('Pendiente de revisión' in page and 'Estado de tus pagos' in page,'No muestra estado en la cuenta pública')
  check(query('SELECT publication_status FROM businesses WHERE id=?',[bid])==before,'El envío alteró publicación')
 for n,(plan,frequency,amount) in enumerate([('featured','monthly',49900),('page','quarterly',174750),('page_premium','annual',1199000)],3):
  case('PG-'+str(n).zfill(2),'Comprobante público '+plan+'/'+frequency+': importe del servidor y pendiente',lambda p=plan,f=frequency,a=amount:paid(p,f,a))
 def free():
  aid,bid=current();_,page,_=person.get('/negocios/portal/?tab=business&business='+str(bid))
  count=query('SELECT COUNT(*) AS n FROM business_subscription_payment_evidence WHERE account_id=?',[aid])[0]['n']
  code,page,_=person.request('/negocios/portal/?tab=business&business='+str(bid),{'csrf':env['csrf'](page),'business_id':bid,'plan_code':'free','billing_frequency':'annual'})
  check(code==200,'Gratis exige soporte')
  row=query('SELECT plan_code,status,billing_frequency,quoted_amount FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1',[bid])[0]
  check(row['plan_code']=='free' and row['status']=='pending_publication' and row['billing_frequency'] is None and row['quoted_amount'] is None,'Gratis genera un cobro o se activa automáticamente')
  check(query('SELECT COUNT(*) AS n FROM business_subscription_payment_evidence WHERE account_id=?',[aid])[0]['n']==count,'Gratis creó comprobante')
 case('PG-06','Negocio asociado: elegir Gratis sin archivo, cobro ni activación automática',free)
 def ownership():
  current();code,page,_=person.get('/negocios/portal/?tab=business&business='+str(fixtures[1]))
  check(code==404 and env['RUN']+' ocupado' not in page,'Permite seleccionar negocio ajeno')
 case('AZ-04','Pestaña de negocio ajeno devuelve 404 y no revela información',ownership)
