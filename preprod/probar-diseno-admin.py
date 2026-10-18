#!/usr/bin/env python3
"""Verifica cobertura del tema servido en todas las plantillas administrativas. Solo lectura local."""
from pathlib import Path
import http.cookiejar,urllib.request,urllib.parse,json,re,datetime
ROOT=Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8088'
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(path,data=None):
 with client.open(BASE+path,None if data is None else urllib.parse.urlencode(data).encode(),timeout=20) as response:return response.status,response.read().decode()
assert json.loads(request('/__preprod-runtime.php')[1])['database']['driver']=='mysql'
profile=json.loads((ROOT/'.local/preprod/test-accounts.json').read_text())['profiles']['administrador']
page=request('/negocios/acceso/')[1]
request('/negocios/acceso/',{'csrf':re.search(r'name="csrf"\s+value="([^"]+)"',page)[1],'email':profile['email'],'password':profile['password']})
assert json.loads(request('/api/account-session.php')[1])['administrator']
routes=['/admin/'+p.name for p in (ROOT/'backend/public/admin').glob('*.php') if '</head>' in p.read_text()]
routes+=['/admin/index.php?tab=sistema','/admin/campanas.php?tab=conexion']
results=[]
for path in sorted(routes):
 code,page=request(path)
 assert code==200 and 'portal-admin' in page and 'admin-theme.css?v=' in page and 'identidad-neutral.css?v=' in page,path
 assert 'assets/logo.svg' not in page,path
 results.append({'path':path,'status':code,'shared_theme':True})
assert '--el-page:#FAFAFA' in request('/assets/identidad-neutral.css')[1]
assert 'floating-select-trigger' in request('/assets/admin-theme.css')[1]
out=ROOT/'.local/preprod/evidencia/admin-diseno';out.mkdir(parents=True,exist_ok=True)
(out/'paginas.json').write_text(json.dumps({'executed_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'scope':'HTML y recursos servidos; no sustituye revisión visual','results':results},ensure_ascii=False,indent=2))
print('OK:',len(results),'vistas administrativas servidas con logotipo y tema actuales; no se modificaron datos del portal.')
