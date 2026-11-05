"""Acciones HTTP exclusivamente públicas; SQL solo inspecciona fixtures aislados del runner.
No aprueba solicitudes, pagos ni crea membresías para superar la verificación pendiente.
"""
import html,json,re

def run(env):
 case,check,query=env['case'],env['check'],env['query']
 person,anon,fixtures=env['person'],env['anon'],env['fixtures']
 def current():
  if not fixtures:raise env['Blocked']('Sin fixtures del conjunto NG-00.')
  # A first purchase must not reuse the active paid business used to test public pages.
  return env['account']()['id'],fixtures[3]
 def application_prices():
  current()
  for plan,frequency,expected in [('featured','quarterly',67500),('page','semiannual',204000),('page_premium','annual',576000)]:
   actor=env['ctx']['alternate'] if plan=='page_premium' else None
   target=env['ctx']['alternate_id'] if actor else env['account']()['id']
   code,page,_=env['application']('new_business',plan=plan,frequency=frequency,actor=actor)
   notice=re.search(r'<[^>]+(?:role="alert"|class="notice")[^>]*>(.*?)</[^>]+>',page,re.S)
   detail=html.unescape(re.sub('<[^>]+>',' ',notice.group(1))).strip() if notice else ''
   check(code==200,'No guarda alta pública '+plan+' (HTTP '+str(code)+'): '+detail[:200])
   rows=query('SELECT business_id,business_details_json FROM business_account_applications WHERE account_id=? AND application_type="request_new" ORDER BY id DESC LIMIT 1',[target])
   details=json.loads(rows[0]['business_details_json']);q=details['requested_subscription']
   check(q['plan_code']==plan and q['billing_frequency']==frequency and float(q['quoted_amount'])==expected,'No conserva elección y cotización '+plan)
   if plan=='page_premium':
    channels=details.get('channels',{})
    check(channels.get('facebook')=='https://example.invalid/facebook-registro' and channels.get('linkedin')=='https://example.invalid/linkedin-registro','El formulario no guardó Facebook y LinkedIn en la solicitud Premium')
    saved=query('SELECT channel,contact_value FROM business_contacts WHERE business_id=? AND channel IN ("facebook","linkedin")',[rows[0]['business_id']])
    check({r['channel']:r['contact_value'] for r in saved}=={'facebook':'https://example.invalid/facebook-registro','linkedin':'https://example.invalid/linkedin-registro'},'Facebook y LinkedIn no llegaron a los contactos del negocio')
 case('NG-06','Alta pública: tres suscripciones pagas con sus precios y períodos',application_prices)
 def paid(plan,frequency,amount):
  config=query('SELECT enabled,test_mode FROM payment_instructions WHERE id=1')
  if not config or int(config[0]['enabled'])!=1 or int(config[0]['test_mode'])!=1:raise env['Blocked']('Faltan medios ficticios habilitados en preproducción.')
  actor=env['ctx']['alternate'] if plan=='page_premium' else person
  aid=env['ctx']['alternate_id'] if plan=='page_premium' else env['account']()['id']
  rows=query('SELECT business_id,business_details_json FROM business_account_applications WHERE account_id=? AND application_type="request_new" AND business_id IS NOT NULL',[aid])
  matches=[r for r in rows if json.loads(r['business_details_json']).get('requested_subscription',{}).get('plan_code')==plan]
  check(len(matches)==1,'No existe el negocio ficticio del plan '+plan);bid=int(matches[0]['business_id'])
  code,page,_=actor.get('/business/payment.php');token=re.search(r'name="request_key"\s+value="([^"]+)"',page);check(code==200 and token,'No abre selección de pago')
  fields={'csrf':env['csrf'](page),'action':'prepare_order','request_key':token.group(1),'businesses[0]':str(bid),'plans['+str(bid)+']':plan,'periods['+str(bid)+']':frequency,'quoted_amount':'1'}
  code,body,url=actor.request('/business/payment.php',fields);match=re.search(r'[?&]order=([a-f0-9]{48})',url);check(code==200 and match,'No preparó orden de '+plan)
  order=match.group(1);check('SIMULACIÓN' in body and '/api/payment-qr.php' in body,'Faltan instrucciones ficticias o QR')
  rows=query('SELECT o.id,o.total_cents,o.status,i.quote_json FROM business_payment_orders o JOIN business_payment_order_items i ON i.order_id=o.id WHERE o.public_id=? AND o.account_id=?',[order,aid]);check(len(rows)==1 and int(rows[0]['total_cents'])==amount*100,'Importe o identidad de orden incorrectos')
  quote=json.loads(rows[0]['quote_json']);check(quote['plan_code']==plan and quote['billing_frequency']==frequency,'No conserva plan y período')
  code,body,_=actor.request('/business/payment.php',{'csrf':env['csrf'](body),'action':'submit_evidence','order':order,'payment_reference':env['RUN']+'-'+plan,'transaction_date':env['datetime'].date.today().isoformat(),'paid_amount':str(amount),'payment_method':'transfer','source_bank':'BANCO FICTICIO'},['payment_proof'])
  check(code==200 and 'Comprobante recibido' in body,'No confirma el comprobante '+plan)
  saved=query('SELECT o.status,e.status AS evidence_status FROM business_payment_orders o JOIN business_payment_order_evidence oe ON oe.order_id=o.id JOIN business_subscription_payment_evidence e ON e.id=oe.evidence_id WHERE o.public_id=? AND o.account_id=?',[order,aid]);check(len(saved)==1 and saved[0]['status']=='under_review' and saved[0]['evidence_status']=='pending','Comprobante no quedó pendiente')
  state=query('SELECT publication_status FROM businesses WHERE id=?',[bid]);check(state and state[0]['publication_status']=='draft','El comprobante publicó el negocio')
 for n,(plan,frequency,amount) in enumerate([('featured','quarterly',67500),('page','semiannual',204000),('page_premium','annual',576000)],3):
  case('PG-'+str(n).zfill(2),'Comprobante público '+plan+'/'+frequency+': importe del servidor y pendiente',lambda p=plan,f=frequency,a=amount:paid(p,f,a))
 def free():
  aid=env['account']()['id'];rows=query('SELECT a.business_id,a.business_details_json,b.publication_status FROM business_account_applications a JOIN businesses b ON b.id=a.business_id WHERE a.account_id=? AND a.application_type="request_new"',[aid])
  matches=[r for r in rows if json.loads(r['business_details_json']).get('requested_subscription',{}).get('plan_code')=='free'];check(len(matches)==1,'Falta alta gratuita ficticia')
  bid=matches[0]['business_id'];sub=query('SELECT plan_code,status,quoted_amount FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1',[bid]);check(sub and sub[0]['plan_code']=='free' and sub[0]['status']=='pending_publication' and not float(sub[0]['quoted_amount'] or 0),'Gratis generó cobro o activación')
  check(matches[0]['publication_status']=='draft' and not query('SELECT id FROM business_subscription_payment_evidence WHERE subscription_id IN (SELECT id FROM business_subscriptions WHERE business_id=?)',[bid]),'Gratis creó comprobante o publicó el negocio')
 case('PG-06','Negocio asociado: elegir Gratis sin archivo, cobro ni activación automática',free)
 def ownership():
  current();bid=fixtures[1];code,page,_=person.get('/business/payment.php?business='+str(bid))
  check(code==200 and env['RUN']+' ocupado' not in page and not re.search(r'name="businesses\[\]"\s+value="'+str(bid)+r'"',page),'Expone negocio ajeno en selección de pago')
  token=re.search(r'name="request_key"\s+value="([^"]+)"',page);check(token,'Falta identificador de selección')
  code,body,_=person.request('/business/payment.php',{'csrf':env['csrf'](page),'action':'prepare_order','request_key':token.group(1),'businesses[]':str(bid),'plans['+str(bid)+']':'featured','periods['+str(bid)+']':'monthly'})
  check(code>=400,'Acepta seleccionar negocio ajeno');check(not query('SELECT i.id FROM business_payment_order_items i JOIN business_payment_orders o ON o.id=i.order_id WHERE i.business_id=? AND o.account_id=?',[bid,env['account']()['id']]),'Creó orden para negocio ajeno')
 case('AZ-04','Pago rechaza selección manipulada de negocio ajeno',ownership)
 def grouped_order():
  aid,bid=current();other=fixtures[0]
  code,page,_=person.get('/business/payment.php');check(code==200,'No abre selección de negocios')
  token=re.search(r'name="request_key"\s+value="([^"]+)"',page);check(token,'Falta identificador de orden')
  for business in (bid,other):check(re.search(r'name="businesses\[\]"\s+value="'+str(business)+r'"',page),'Falta negocio propio en selección')
  fields={'csrf':env['csrf'](page),'action':'prepare_order','request_key':token.group(1),'businesses[]':[str(bid),str(other)],'plans['+str(bid)+']':'featured','periods['+str(bid)+']':'monthly','plans['+str(other)+']':'page','periods['+str(other)+']':'quarterly','quoted_amount':'1'}
  # urllib needs doseq for repeated business selections.
  fields['businesses[0]']=fields['businesses[]'][0];fields['businesses[1]']=fields['businesses[]'][1];del fields['businesses[]']
  code,body,url=person.request('/business/payment.php',fields)
  match=re.search(r'[?&]order=([a-f0-9]{48})',url);check(code==200 and match,'No preparó orden agrupada')
  rows=query('SELECT id,total_cents,status FROM business_payment_orders WHERE account_id=? AND public_id=?',[aid,match.group(1)]);check(len(rows)==1,'No persistió exactamente una orden')
  order=rows[0];items=query('SELECT business_id,due_cents,quote_json FROM business_payment_order_items WHERE order_id=? ORDER BY business_id',[order['id']]);check(len(items)==2 and {int(i['business_id']) for i in items}=={bid,other},'Desglose no corresponde a la selección')
  check(int(order['total_cents'])==sum(int(i['due_cents']) for i in items) and int(order['total_cents'])==13300000,'Importe no coincide con tarifas o fue manipulado')
  check(order['status']=='awaiting_evidence','La orden no quedó pendiente de comprobante')
  code,_,_=person.request('/business/payment.php',{'csrf':env['csrf'](body),'action':'cancel_order','order':match.group(1)});check(code==200,'No permite cancelar selección de prueba')
  state=query('SELECT status FROM business_payment_orders WHERE id=?',[order['id']]);check(state and state[0]['status']=='cancelled','Cancelación no persistió')
 case('PG-orden','Crear, verificar y cancelar una orden agrupada',grouped_order)
 def grouped_receipt():
  aid,bid=current();other=fixtures[0]
  config=query('SELECT enabled,test_mode FROM payment_instructions WHERE id=1')
  if not config or int(config[0]['enabled'])!=1 or int(config[0]['test_mode'])!=1:raise env['Blocked']('Faltan medios ficticios habilitados en preproducción.')
  code,page,_=person.get('/business/payment.php');check(code==200,'No abre selección de pago')
  token=re.search(r'name="request_key"\s+value="([^"]+)"',page);check(token,'Falta identificador de orden')
  fields={'csrf':env['csrf'](page),'action':'prepare_order','request_key':token.group(1),'businesses[0]':str(bid),'businesses[1]':str(other),'plans['+str(bid)+']':'featured','periods['+str(bid)+']':'monthly','plans['+str(other)+']':'page','periods['+str(other)+']':'quarterly'}
  code,body,url=person.request('/business/payment.php',fields);match=re.search(r'[?&]order=([a-f0-9]{48})',url);check(code==200 and match,'No preparó orden para comprobante')
  order=match.group(1);check('SIMULACIÓN' in body,'No identifica que son medios ficticios')
  check('/api/payment-qr.php' in body,'La orden no muestra el QR ficticio configurado')
  check(person.get('/api/payment-qr.php')[0]==200,'No carga el QR ficticio para la cuenta autenticada')
  code,body,_=person.request('/business/payment.php',{'csrf':env['csrf'](body),'action':'submit_evidence','order':order,'payment_reference':env['RUN']+'-orden','transaction_date':env['datetime'].date.today().isoformat(),'paid_amount':'133000','payment_method':'transfer','source_bank':'BANCO FICTICIO'},['payment_proof'])
  check(code==200 and 'Comprobante recibido' in body,'No confirma recepción del comprobante')
  rows=query('SELECT o.id,o.status,o.total_cents,e.status AS evidence_status FROM business_payment_orders o JOIN business_payment_order_evidence oe ON oe.order_id=o.id JOIN business_subscription_payment_evidence e ON e.id=oe.evidence_id WHERE o.public_id=? AND o.account_id=?',[order,aid])
  check(len(rows)==1 and rows[0]['status']=='under_review' and rows[0]['evidence_status']=='pending' and int(rows[0]['total_cents'])==13300000,'La orden o el comprobante no quedaron pendientes por el total correcto')
  states=query('SELECT publication_status FROM businesses WHERE id IN (?,?)',[bid,other]);check(len(states)==2 and all(x['publication_status']=='draft' for x in states),'El comprobante publicó un negocio')
 case('PG-comprobante-orden','Enviar un comprobante ficticio para dos negocios',grouped_receipt)
