#!/usr/bin/env python3
"""Local preprod multipart package workflow. Uses an already applied migration only."""
import http.cookiejar,io,json,re,urllib.request,urllib.error,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
URL='http://127.0.0.1:8088/admin/base-datos.php'
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def send(data=None,headers=None):
    try:
        with client.open(urllib.request.Request(URL,data=data,headers=headers or {}),timeout=30) as r:return r.status,r.read().decode()
    except urllib.error.HTTPError as e:return e.code,e.read().decode()
status,html=send();assert status==200 and '030-business-categories</code> · <strong>Completo' in html,'Requires existing local admin session and complete migration 030'
csrf=re.search(r'name="csrf" value="([^"]+)"',html)[1]
name='030-business-categories.mysql.sql';sql=(ROOT/'backend/database/migrations'/name).read_bytes()
import hashlib
manifest=dict(format='explorandolugares-db-v1',engine='mysql-mariadb',migrations=[dict(id='030-business-categories',file=name,sha256=hashlib.sha256(sql).hexdigest())])
def upload(tamper=False,token=csrf):
    out=io.BytesIO()
    with zipfile.ZipFile(out,'w',zipfile.ZIP_DEFLATED) as z:z.writestr('manifest.json',json.dumps(manifest));z.writestr(name,sql+(b'\n-- altered' if tamper else b''))
    boundary='el-package-http-boundary';body=b''
    for field,value in [('csrf',token),('action','validate')]:body+=f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"\r\n\r\n{value}\r\n'.encode()
    body+=f'--{boundary}\r\nContent-Disposition: form-data; name="package"; filename="test.zip"\r\nContent-Type: application/zip\r\n\r\n'.encode()+out.getvalue()+f'\r\n--{boundary}--\r\n'.encode()
    return send(body,{'Content-Type':'multipart/form-data; boundary='+boundary})
assert upload(token='incorrect')[0]==403
status,html=upload(tamper=True);assert status==400 and 'modificado' in html
status,html=upload();assert status==200 and 'Paquete validado' in html and 'Revisión antes de ejecutar' in html
status,html=send(urllib.parse.urlencode(dict(csrf=csrf,action='apply',confirmation='APLICAR PAQUETE')).encode(),{'Content-Type':'application/x-www-form-urlencoded'})
assert status==200 and 'Ya estaba aplicada' in html
status,html=send(urllib.parse.urlencode(dict(csrf=csrf,action='apply',confirmation='APLICAR PAQUETE')).encode(),{'Content-Type':'application/x-www-form-urlencoded'})
assert status==400 and 'vencida' in html
print('OK: multipart upload, CSRF rejection, tampered script rejection, preview, already-applied skip, single-use confirmation. Local only; no DDL executed.')
