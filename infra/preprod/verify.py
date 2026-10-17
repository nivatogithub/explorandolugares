#!/usr/bin/env python3
"""Read-only application smoke checks; authenticates only to this local sandbox."""
import http.cookiejar,json,re,urllib.request,urllib.parse,urllib.error
from pathlib import Path
BASE='http://127.0.0.1:8088'
ROOT=Path(__file__).resolve().parents[2]
class LocalRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,newurl):
  if not newurl.startswith(BASE+'/'):raise RuntimeError('External redirect rejected')
  return super().redirect_request(req,fp,code,msg,headers,newurl)
client=urllib.request.build_opener(LocalRedirect(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(path):
 with client.open(BASE+path,timeout=20) as response:
  body=response.read().decode();assert response.status==200
  assert not re.search(r'Fatal error|Uncaught |Warning:|Deprecated:',body),path
  return body
coverage=json.loads(get('/api/cobertura.php'))
assert 'audience' in coverage and 'basic' in coverage['audience']
assert coverage['country']['coverage_percent'] is not None
assert coverage['country']['municipality_total']==1122
assert coverage['country']['counts']['in_progress']==0
assert all(p['status']=='published' for d in coverage['departments'] for p in d['places'])
page=get('/cobertura/colombia/')
assert all('data-page-tab="'+tab+'"' in page for tab in ['cobertura','audiencia','participar'])
assert json.loads(get('/api/medicion.php'))['enabled']
assert json.loads(get('/api/actividad.php'))['enabled']
for path in ['/api/cobertura.php?days%5B%5D=30']:
 try:get(path);raise AssertionError('Malformed query accepted')
 except urllib.error.HTTPError as error:assert error.code==400
request=urllib.request.Request(BASE+'/api/medicion.php',headers={'Host':'external.example.invalid'})
try:client.open(request);raise AssertionError('Local HTTP accepted an external host')
except urllib.error.HTTPError as error:assert error.code==403
get('/destinos/jerico/')
login=get('/admin/index.php')
token=re.search(r'name="csrf" value="([^"]+)"',login).group(1)
credentials=json.loads((ROOT/'.local/preprod/admin.json').read_text())
data=urllib.parse.urlencode({'csrf':token,'action':'login',**credentials}).encode()
with client.open(BASE+'/admin/index.php',data=data,timeout=20) as response:
 assert response.status==200
 assert 'Sistema' in response.read().decode()
for path in ['/admin/index.php?tab=sistema','/admin/base-datos.php','/admin/negocios.php','/admin/contactos-negocios.php','/admin/inversionistas.php','/admin/medicion.php','/admin/campanas.php']:
 get(path)
report={'origin':BASE,'checks':'public tabs, aggregate audience, geographic denominator, private drafts, Jerico, consent availability, local admin and seven admin pages','success':True,'production_accessed':False}
(ROOT/'.local/preprod/verification.json').write_text(json.dumps(report,indent=2)+'\n')
print('OK: public and authenticated local smoke checks. No production access or external sends.')
