#!/usr/bin/env python3
"""Read-only application smoke checks; authenticates only to this local sandbox."""
import argparse,http.cookiejar,json,re,urllib.request,urllib.parse,urllib.error
from pathlib import Path
BASE='http://127.0.0.1:8088'
ROOT=Path(__file__).resolve().parents[2]
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--exclude-coverage',action='store_true',help='No certifica aceptación de cobertura; solo para una entrega cuyo alcance la excluye expresamente.')
args=parser.parse_args()
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
if not args.exclude_coverage:
 assert coverage['country']['municipality_total']==1122
assert coverage['country']['counts']['in_progress']==0
assert all(p['status'] in {'published','in_progress','pending','unknown'} for d in coverage['departments'] for p in d['places'])
assert all(p.get('url') is None for d in coverage['departments'] for p in d['places'] if p['status']!='published')
page=get('/cobertura/colombia/')
if not args.exclude_coverage:
 assert all('data-page-tab="'+tab+'"' in page for tab in ['cobertura','audiencia','participar'])
legacy_measurement=json.loads(get('/api/medicion.php'))
assert legacy_measurement['enabled'] is False and legacy_measurement['mode']=='aggregate_only'
assert json.loads(get('/api/actividad.php'))['enabled']
for path in ['/api/cobertura.php?days%5B%5D=30']:
 try:get(path);raise AssertionError('Malformed query accepted')
 except urllib.error.HTTPError as error:assert error.code==400
request=urllib.request.Request(BASE+'/api/medicion.php',headers={'Host':'external.example.invalid'})
try:client.open(request);raise AssertionError('Local HTTP accepted an external host')
except urllib.error.HTTPError as error:assert error.code==403
get('/destinos/jerico/')
admin=get('/admin/index.php')
settings=json.loads((ROOT/'.local/preprod/settings.json').read_text())
if settings.get('admin_without_password') is True:
 assert 'Sistema' in admin and 'name="password"' not in admin, 'Local administrator must open without credentials'
else:
 assert 'name="password"' in admin, 'Fresh local installations must require credentials'
 login=get('/negocios/acceso/')
 token=re.search(r'name="csrf" value="([^"]+)"',login).group(1)
 credentials=json.loads((ROOT/'.local/preprod/test-accounts.json').read_text())['profiles']['administrador']
 data=urllib.parse.urlencode({'csrf':token,'email':credentials['email'],'password':credentials['password']}).encode()
 with client.open(BASE+'/negocios/acceso/',data=data,timeout=20) as response:
  assert response.status==200
 assert 'admin-sidebar' in get('/admin/index.php'), 'The local administrator must use the unified account login'
for path in ['/admin/index.php?tab=sistema','/admin/base-datos.php','/admin/negocios.php','/admin/contactos-negocios.php','/admin/inversionistas.php','/admin/medicion.php','/admin/campanas.php']:
 get(path)
assert 'name="password"' in get('/negocios/acceso/'), 'Business account login must remain protected'
report={'origin':BASE,'checks':'aggregate audience, private drafts, Jerico, measurement availability, local admin and seven admin pages','success':True,'production_accessed':False}
report['coverage_denominator_verified']=not args.exclude_coverage
report['excluded_checks']=['coverage_acceptance'] if args.exclude_coverage else []
(ROOT/'.local/preprod/verification.json').write_text(json.dumps(report,indent=2)+'\n')
print('OK: public and authenticated local smoke checks. No production access or external sends.')
