#!/usr/bin/env python3
"""Configura por HTTP administrativo y paga por HTTP público con personas/negocios sintéticos. Solo localhost."""
import argparse,base64,html,http.cookiejar,json,re,secrets,subprocess,urllib.error,urllib.parse,urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8088';OUT=ROOT/'.local/preprod/evidencia/pagos';OUT.mkdir(parents=True,exist_ok=True)
p=argparse.ArgumentParser();p.add_argument('--pago',action='store_true');a=p.parse_args()
profiles=json.loads((ROOT/'.local/preprod/test-accounts.json').read_text())['profiles'];qr=(ROOT/'preprod/fixtures/qr-pago-simulado.png').read_bytes()
class Client:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def call(self,path,fields=None,file=None):
  assert path.startswith('/') and not path.startswith('//');headers={};data=None
  if file:
   boundary='----test'+secrets.token_hex(12);parts=[]
   for name,value in fields.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
   parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{file}"; filename="SIMULACION-NO-ES-PAGO.png"\r\nContent-Type: image/png\r\n\r\n'.encode()+qr+b'\r\n');parts.append(f'--{boundary}--\r\n'.encode());data=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
  elif fields is not None:data=urllib.parse.urlencode(fields).encode()
  try:
   with self.opener.open(urllib.request.Request(BASE+path,data,headers),timeout=20) as r:return r.status,r.read().decode(errors='replace')
  except urllib.error.HTTPError as e:return e.code,e.read().decode(errors='replace')
def csrf(page):return re.search(r'name="csrf"\s+value="([^"]+)"',page)[1]
def login(client,profile):
 _,page=client.call('/negocios/acceso/');code,_=client.call('/negocios/acceso/',{'csrf':csrf(page),'email':profile['email'],'password':profile['password']});assert code==200;return json.loads(client.call('/api/account-session.php')[1])
runtime=json.loads(Client().call('/__preprod-runtime.php')[1]);assert runtime['database']['driver']=='mysql'
admin=Client();assert login(admin,profiles['administrador'])['administrator']
code,page=admin.call('/admin/medios-pago.php');assert code==200 and 'Simulación de preproducción' in page
fields={'csrf':csrf(page),'enabled':'1','holder':'EXPLORANDO LUGARES — PRUEBA SIN VALOR','bank':'BANCO FICTICIO — NO TRANSFERIR','account_number':'NO-TRANSFERIR-0000','account_type':'savings','breb_key':'PRUEBA-EXPLORANDO-NO-PAGAR','notes':'SIMULACIÓN. Este QR contiene un texto de prueba, no una orden bancaria. No enviar dinero.'}
code,page=admin.call('/admin/medios-pago.php',fields,'qr_image');assert code==200 and 'Medios de pago guardados' in page,('admin save failed',code)
code,page=admin.call('/admin/medios-pago.php');assert code==200 and 'NO-TRANSFERIR-0000' in page and 'payment-qr.php' in page
source=b'<?php $c=require "/srv/state/private/config.php";if(empty($c["local_test"]))exit(80);$p=new PDO($c["dsn"],$c["db_user"],$c["db_password"]);$r=$p->query("SELECT enabled,test_mode,holder,bank,account_number,account_type,breb_key,qr_file FROM payment_instructions WHERE id=1")->fetch(PDO::FETCH_ASSOC);echo json_encode($r);'
stored=json.loads(subprocess.check_output(['podman','exec','-i','explorando-preprod','php'],input=source))
assert all(str(stored[k])==fields[k] for k in ['holder','bank','account_number','account_type','breb_key']) and stored['enabled']==1 and stored['test_mode']==1 and re.fullmatch(r'[a-f0-9]{48}\.png',stored['qr_file'])

assert Client().call('/admin/medios-pago.php')[0]==403;assert Client().call('/api/payment-qr.php')[0]==403
person=Client();session=login(person,profiles['negocio']);assert session['authenticated'] and not session['administrator'];assert person.call('/admin/medios-pago.php')[0]==403
code,page=person.call('/negocios/portal/?tab=business');assert code==200 and 'SIMULACIÓN — NO ENVÍES DINERO' in page and 'PRUEBA-EXPLORANDO-NO-PAGAR' in page
assert person.call('/api/payment-qr.php')[0]==200
report={'configuration_saved_through_admin_form':True,'configuration_persisted_in_mariadb':True,'qr_uploaded':True,'anonymous_denied':True,'normal_account_cannot_configure':True,'public_payment_instructions_present':True,'test_mode':True,'paid_submission':False}
if a.pago:
 form=re.search(r'<form\b[^>]*class="[^"]*account-payment-form[^\"]*"[^>]*>(.*?)</form>',page,re.S)[1];bid=re.search(r'name="business_id" value="([0-9]+)"',form)[1];reference='PRUEBA-PAGO-'+secrets.token_hex(6)
 prices=json.loads(html.unescape(re.search(r'data-plan-prices="([^"]+)"',page)[1]));expected=float(prices['featured']['quarterly']['amount'])
 fields={'csrf':csrf(form),'business_id':bid,'plan_code':'featured','billing_frequency':'quarterly','payment_reference':reference,'quoted_amount':'1','transaction_date':__import__('datetime').date.today().isoformat(),'paid_amount':str(expected),'payment_method':'breb','source_bank':'BANCO FICTICIO'}
 code,result=person.call('/negocios/portal/?tab=business',fields,'payment_proof');assert code==200 and 'Recibimos el soporte' in result,('public submission failed',code)
 sql='SELECT e.checkout_reference,e.transaction_date,e.paid_amount,e.payment_method,e.source_bank,s.status,s.plan_code,s.quoted_amount,s.billing_frequency,s.starts_at,e.status AS evidence_status,e.payment_reference FROM business_subscriptions s JOIN business_subscription_payment_evidence e ON e.subscription_id=s.id WHERE e.payment_reference=?'
 encoded=base64.b64encode(json.dumps([sql,['[SIMULACIÓN] '+reference]]).encode()).decode();source='<?php $c=require "/srv/state/private/config.php";if(empty($c["local_test"]))exit(80);$p=new PDO($c["dsn"],$c["db_user"],$c["db_password"]);$v=json_decode(base64_decode("'+encoded+'"),true);$q=$p->prepare($v[0]);$q->execute($v[1]);echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));';rows=json.loads(subprocess.check_output(['podman','exec','-i','explorando-preprod','php'],input=source.encode()));assert len(rows)==1;row=rows[0];assert re.fullmatch(r'EL-[A-F0-9]{24}',row['checkout_reference']) and float(row['paid_amount'])==expected and row['source_bank']=='BANCO FICTICIO';assert row['status']=='pending' and row['evidence_status']=='pending' and float(row['quoted_amount'])==expected and row['starts_at'] is None
 report.update(paid_submission=True,stored=row,server_amount_verified=True,payment_not_auto_approved=True)
(OUT/'resultado.json').write_text(json.dumps(report,ensure_ascii=False,indent=2));print(json.dumps(report,ensure_ascii=False))
