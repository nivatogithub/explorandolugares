#!/usr/bin/env python3
"""Recreate machine-local settings; never imports hosting secrets or data."""
import argparse,json,shutil,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser(description=__doc__)
mode=parser.add_mutually_exclusive_group();mode.add_argument('--admin-sin-clave',action='store_true');mode.add_argument('--admin-con-clave',action='store_true')
args=parser.parse_args()
for program in ['git','podman']:
 if not shutil.which(program):raise SystemExit('Falta instalar '+program+'. Consulta preprod/README.md.')
if subprocess.check_output(['git','branch','--show-current'],cwd=ROOT,text=True).strip()!='preprod':raise SystemExit('Configura este entorno desde la rama preprod.')
state=ROOT/'.local/preprod';state.mkdir(parents=True,exist_ok=True)
file=state/'settings.json'
settings=json.loads(file.read_text()) if file.exists() else json.loads((ROOT/'preprod/settings.example.json').read_text())
if args.admin_sin_clave:settings['admin_without_password']=True
elif args.admin_con_clave:settings['admin_without_password']=False
file.write_text(json.dumps(settings,indent=2)+'\n');file.chmod(0o600)
# Podman needs a VM on macOS; do not change an existing machine or its resources.
if sys.platform=='darwin':
 machines=json.loads(subprocess.check_output(['podman','machine','list','--format','json'],cwd=ROOT,text=True))
 if not machines:subprocess.run(['podman','machine','init','--cpus','4','--memory','4096'],cwd=ROOT,check=True)
print('Configuración privada preparada en .local/preprod/settings.json (fuera de Git).')
print('Admin local: '+('sin contraseña por elección explícita.' if settings.get('admin_without_password') is True else 'con contraseña; se generará un acceso local al arrancar.'))
print('Primera instalación: ./preprod/subir.sh\nSi el entorno ya existe y cambiaste esta opción: ./preprod/actualizar.sh')
