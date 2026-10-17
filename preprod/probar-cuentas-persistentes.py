#!/usr/bin/env python3
"""Verify persistent synthetic fixtures without deleting them or exposing their passwords."""
import http.cookiejar,json,re,subprocess,urllib.request,urllib.parse,urllib.error
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8088'
def prepare():
    subprocess.run(['python3',str(ROOT/'preprod/cuentas-prueba.py')],cwd=ROOT,check=True,capture_output=True)
    return json.loads((ROOT/'.local/preprod/test-accounts.json').read_text())
def call(client,path,data=None):
    request=urllib.request.Request(BASE+path,None if data is None else urllib.parse.urlencode(data).encode())
    try:
        with client.open(request,timeout=30) as response:return response.status,response.read().decode()
    except urllib.error.HTTPError as error:return error.code,error.read().decode()
initial=prepare()
for key,row in initial['profiles'].items():
    client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    status,page=call(client,'/negocios/acceso/');assert status==200
    csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',page)[1]
    status,page=call(client,'/negocios/acceso/',{'csrf':csrf,'email':row['email'],'password':row['password']});assert status==200,key+': no se pudo iniciar sesión'
    state=json.loads(call(client,'/__preprod-account.php')[1]);assert state['authenticated'] is True and state['administrator']==(key=='administrador'),key+': permisos incorrectos'
    if key=='viajero':assert 'Ya tienes una cuenta de viajero o turista' in page
    if key=='negocio':
        assert 'Solicitudes asociadas a tu cuenta' in page
        for business in row['businesses']:
            assert business['display_name'] in page,'Falta un negocio asociado en el portal comercial'
    assert call(client,'/admin/usuarios.php')[0]==(200 if key=='administrador' else 403),key+': acceso administrativo incorrecto'
    assert call(client,'/negocios/salir/',{'csrf':state['csrf']})[0]==200
    assert json.loads(call(client,'/__preprod-account.php')[1])['authenticated'] is False
    assert call(client,'/admin/usuarios.php')[0]==403
again=prepare()
for key,row in initial['profiles'].items():
    assert again['profiles'][key]['id']==row['id'] and again['profiles'][key]['password']==row['password'],'Preparar cambió un acceso existente'
assert again['profiles']['negocio']['business_id']==initial['profiles']['negocio']['business_id'],'Se duplicó el negocio ficticio'
assert [b['id'] for b in again['profiles']['negocio']['businesses']]==[b['id'] for b in initial['profiles']['negocio']['businesses']],'Se duplicó alguno de los cuatro negocios'
assert len({b['id'] for b in again['profiles']['negocio']['businesses']})==4,'Deben existir cuatro negocios distintos'
source='<?php $c=require "/srv/state/private/config.php";$p=new PDO($c["dsn"],$c["db_user"],$c["db_password"]);$q=$p->prepare("SELECT COUNT(*) FROM business_account_memberships m JOIN businesses b ON b.id=m.business_id WHERE m.account_id=? AND b.public_id IN (\'f00d0000-0000-4000-8000-000000000010\',\'f00d0000-0000-4000-8000-000000000011\',\'f00d0000-0000-4000-8000-000000000012\',\'f00d0000-0000-4000-8000-000000000013\') AND m.status=\'active\'");$q->execute(['+str(initial['profiles']['negocio']['id'])+']);echo $q->fetchColumn();'
assert subprocess.check_output(['podman','exec','-i','explorando-preprod','php'],input=source.encode()).decode()=='4','Faltan negocios asociados al perfil comercial'
print('OK: tres perfiles persistentes; permisos aislados; inicio/cierre de sesión; cuatro negocios asociados; preparación repetible sin duplicados ni cambios de clave. Las cuentas se conservaron.')
