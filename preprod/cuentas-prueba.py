#!/usr/bin/env python3
"""Create persistent local fixtures; passwords stay in ignored files, never in Git or output."""
import argparse,fcntl,json,os,secrets,subprocess,tempfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
STATE=ROOT/'.local/preprod'
PROFILES=('viajero','negocio','administrador')
def private_write(path,text):
    fd,name=tempfile.mkstemp(prefix='.test-accounts-',dir=STATE)
    try:
        os.fchmod(fd,0o600)
        with os.fdopen(fd,'w') as stream:stream.write(text)
        os.replace(name,path)
    finally:
        if os.path.exists(name):os.unlink(name)
def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--restablecer',action='store_true',help='Restablece acceso, nombre, tipo y permisos solo de las tres cuentas ficticias; conserva el negocio y sus pruebas.')
    args=parser.parse_args()
    if subprocess.check_output(['git','branch','--show-current'],cwd=ROOT,text=True).strip()!='preprod':raise SystemExit('Usar únicamente desde la rama preprod.')
    STATE.mkdir(parents=True,exist_ok=True)
    with (STATE/'test-accounts.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX)
        path=STATE/'test-accounts.json'
        data=json.loads(path.read_text()) if path.exists() else {'version':1,'profiles':{}}
        if data.get('version')!=1:raise SystemExit('El archivo local de cuentas tiene una versión desconocida.')
        for key in PROFILES:
            row=data['profiles'].setdefault(key,{})
            if not row.get('password'):row['password']='Preprod-'+secrets.token_urlsafe(20)+'-9a'
        # Persist the generated passwords before provisioning, so a restart cannot lose them.
        private_write(path,json.dumps(data,indent=2,ensure_ascii=False)+'\n')
        payload=dict(data,reset=args.restablecer)
        php_source=(ROOT/'preprod/cuentas-prueba.php').read_text()
        # Script and data enter separate streams; no credential appears in command arguments.
        encoded=json.dumps(payload,ensure_ascii=False).encode().hex()
        source=php_source.replace("stream_get_contents(STDIN)","hex2bin('"+encoded+"')",1)
        result=subprocess.run(['podman','exec','-e','EL_PREPROD_LOCAL=1','-i','explorando-preprod','php'],input=source.encode(),capture_output=True)
        if result.returncode:
            raise SystemExit('No se pudieron preparar las cuentas de prueba. No se mostraron credenciales. '+result.stderr.decode(errors='replace')[-1800:])
        provisioned=json.loads(result.stdout)
        for key in PROFILES:data['profiles'][key].update(provisioned['profiles'][key])
        private_write(path,json.dumps(data,indent=2,ensure_ascii=False)+'\n')
        lines=['# Accesos de prueba de este equipo — solo preproducción local','','Abrir http://127.0.0.1:8088/ y pulsar Iniciar sesión.','','Estas credenciales son ficticias, locales y no funcionan en producción. No compartirlas con clientes.','','| Perfil | Correo actual | Contraseña local |','|---|---|---|']
        for key in PROFILES:
            row=data['profiles'][key];lines.append(f"| {key.capitalize()} | {row['email']} | {row['password']} |")
        lines+=['','## Negocios asociados a la cuenta de prueba','','| Negocio ficticio | Suscripción actual | Estado de suscripción | Publicación |','|---|---|---|---|']
        for business in data['profiles']['negocio'].get('businesses',[]):
            lines.append(f"| {business['display_name']} | {business['plan_code']} | {business['subscription_status']} | {business['publication_status']} |")
        lines+=['','Los cuatro negocios son ficticios y se crean en borrador. Las cuatro suscripciones pagas se configuran mensualmente sin registrar pagos ni cobros. El script local activar-directorio-pruebas.py permite publicarlos y activar suscripciones sintéticas exclusivamente para pruebas visuales; conserva un respaldo privado.','','Si cambias una contraseña desde el portal, este archivo conserva la clave inicial hasta que ejecutes `./preprod/cuentas-prueba.sh --restablecer`. Ese comando restablece las claves documentadas y los permisos de estas tres cuentas; conserva los datos del negocio y las demás cuentas.','','Guía portable: `preprod/CUENTAS_PRUEBA.md`.']
        private_write(STATE/'CUENTAS_PRUEBA.md','\n'.join(lines)+'\n')
    print('Cuentas de prueba persistentes listas. Accesos privados: '+str(STATE/'CUENTAS_PRUEBA.md'))
if __name__=='__main__':main()
