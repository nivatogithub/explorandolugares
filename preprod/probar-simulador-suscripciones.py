#!/usr/bin/env python3
"""Local-only regression. Reads synthetic accounts; no business/purchase/payment creation."""
from pathlib import Path
import urllib.request,urllib.parse,urllib.error,http.cookiejar,json,re
ROOT=Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8088'
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,path,data=None):
 try:
  r=c.open(urllib.request.Request(BASE+path,data=urllib.parse.urlencode(data).encode() if data else None));return r.status,r.read().decode(),r.url
 except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.url
anon=client();status,raw,_=call(anon,'/api/subscription-prices.php');assert status==200
catalog=json.loads(raw);plans={p['code']:p for p in catalog['plans']};assert set(plans)=={'free','featured','page','page_premium'}
for code,monthly in [('featured',49900),('page',69900),('page_premium',119900)]:
 for period,factor in [('monthly',1),('quarterly',2.5),('semiannual',5.2),('annual',10)]:assert float(plans[code]['prices'][period]['quoted_amount'])==round(monthly*factor,2)
for period,amount in [('monthly',29900),('quarterly',74750),('semiannual',155480),('annual',299000)]:assert float(catalog['operator'][period]['amount'])==amount
status,body,url=call(anon,'/suscripciones/continuar/?plan=page&period=quarterly');assert status==200 and '/negocios/acceso/' in url
csrf=re.search(r'name="csrf" value="([^"]+)"',body)[1]
profile=json.loads((ROOT/'.local/preprod/test-accounts.json').read_text())['profiles']['negocio']
status,body,url=call(anon,'/negocios/acceso/',{'csrf':csrf,'email':profile['email'],'password':profile['password']});assert status==200 and '¿Para cuál negocio?' in body and 'Trimestral' in body
business=re.search(r'href="\?business=(\d+)"',body)[1]
status,body,url=call(anon,'/suscripciones/continuar/?business='+business);assert status==200 and '#pagos' in url
assert re.search(r'<option value="page"\s+selected',body) and re.search(r'<option value="quarterly"\s+selected',body)
status,body,_=call(anon,'/negocios/registro/?tipo=business&desde=simulador');assert status==200 and re.search(r'name="plan_code" value="page"\s+checked',body) and re.search(r'<option value="quarterly"\s+selected',body)
status,body,_=call(anon,'/suscripciones/continuar/?business=2147483647');assert status==400 and 'Selecciona un negocio asociado' in body
for query in ['plan=basic&period=monthly','plan=page&period=invalid','plan=free&period=monthly&operator=1']:
 assert call(anon,'/suscripciones/continuar/?'+query)[0]==400
status,body,_=call(anon,'/suscripciones/continuar/?plan=featured&period=annual&operator=1');assert status==200
status,body,_=call(anon,'/suscripciones/continuar/?business='+business);assert status==400 and 'categoría Experiencias' in body
traveler=client();status,body,_=call(traveler,'/suscripciones/continuar/?plan=page_premium&period=semiannual');csrf=re.search(r'name="csrf" value="([^"]+)"',body)[1]
p=json.loads((ROOT/'.local/preprod/test-accounts.json').read_text())['profiles']['viajero']
status,body,_=call(traveler,'/negocios/acceso/',{'csrf':csrf,'email':p['email'],'password':p['password']});assert status==200 and '¿Para cuál negocio?' in body and 'Crear o reclamar un negocio' in body
status,body,_=call(traveler,'/negocios/registro/?tipo=business&desde=simulador');assert status==200 and re.search(r'name="plan_code" value="page_premium"\s+checked',body) and re.search(r'<option value="semiannual"\s+selected',body)
print('OK: 12 tarifas, 4 complementos, anónimo→login→negocio→pago; selección conservada; persona→nuevo negocio; restricciones de negocio ajeno, categoría, plan y período. No se crearon pagos.')
