#!/usr/bin/env python3
"""Regresión HTTP local: un negocio por vista y selección limitada a membresías propias."""
import http.cookiejar,json,re,urllib.request,urllib.parse,urllib.error
from pathlib import Path
BASE='http://127.0.0.1:8088'
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(path,data=None):
 try:
  with client.open(BASE+path,None if data is None else urllib.parse.urlencode(data).encode(),timeout=20) as response:return response.status,response.read().decode()
 except urllib.error.HTTPError as error:return error.code,error.read().decode()
assert json.loads(get('/__preprod-runtime.php')[1])['database']['driver']=='mysql'
profile=json.loads((Path(__file__).resolve().parents[1]/'.local/preprod/test-accounts.json').read_text())['profiles']['negocio']
page=get('/negocios/acceso/')[1]
assert get('/negocios/acceso/',{'csrf':re.search(r'name="csrf"\s+value="([^"]+)"',page)[1],'email':profile['email'],'password':profile['password']})[0]==200
page=get('/negocios/portal/?tab=business')[1]
nav=re.search(r'<nav class="business-tabs".*?</nav>',page,re.S)[0]
ids=re.findall(r'business=([0-9]+)',nav)
assert len(ids)>=4
for bid in ids:
 code,page=get('/negocios/portal/?tab=business&business='+bid)
 assert code==200
 forms=re.findall(r'<form[^>]*class="[^"]*account-payment-form[^\"]*"[^>]*>(.*?)</form>',page,re.S)
 assert len(forms)==1 and re.search(r'name="business_id" value="'+bid+'"',forms[0])
 assert '/assets/logo.svg' not in page and 'explorando-lugares-logo-v2-transparent.png' in page
 assert page.count('aria-current="page"')==2
assert get('/negocios/portal/?tab=business&business=999999999')[0]==404
print('OK: 4 pestañas, un formulario por negocio, identidad vigente y selección ajena bloqueada.')
