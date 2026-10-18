#!/usr/bin/env python3
"""Entorno fijo de revisión: Apache/PHP/MariaDB, aislado de producción."""
import argparse,json,os,secrets,shutil,subprocess,sys,time,urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
STATE=ROOT/'.local/preprod'; NAME='explorando-preprod'; IMAGE='localhost/explorando-preprod:local'
def run(*args,**kwargs):return subprocess.run(args,cwd=ROOT,check=True,**kwargs)
def podman(*args,**kwargs):return run('podman',*args,**kwargs)
def start_machine():
 # On macOS, launch through the user service manager so a terminal/worker exit
 # cannot tear down vfkit and its network helper. No login/startup item is added.
 if sys.platform!='darwin':
  podman('machine','start');return
 import plistlib
 STATE.mkdir(parents=True,exist_ok=True)
 label='com.explorandolugares.preprod.podman';domain=f'gui/{os.getuid()}'
 program=shutil.which('podman')
 if not program:raise SystemExit('Podman no está instalado.')
 plist=STATE/'podman-launch.plist'
 plist.write_bytes(plistlib.dumps({'Label':label,'ProgramArguments':[program,'machine','start'],
  'AbandonProcessGroup':True,'EnvironmentVariables':{'PATH':os.environ.get('PATH','/usr/bin:/bin')},
  'StandardOutPath':str(STATE/'podman-launch.log'),'StandardErrorPath':str(STATE/'podman-launch-error.log')}))
 if subprocess.run(['launchctl','print',domain+'/'+label],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL).returncode!=0:
  run('launchctl','bootstrap',domain,str(plist))
 run('launchctl','kickstart',domain+'/'+label)
 print('Iniciando Podman mediante el servicio de usuario de macOS.',flush=True)
 for attempt in range(90):
  try:
   if subprocess.run(['podman','info','--format','{{.Host.Arch}}'],capture_output=True,timeout=5).returncode==0:return
  except subprocess.TimeoutExpired:pass
  time.sleep(1)
 raise SystemExit('Podman no respondió. Revisa .local/preprod/podman-launch-error.log.')
def branch():return subprocess.check_output(['git','branch','--show-current'],cwd=ROOT,text=True).strip()
def wait_ready():
 for attempt in range(120):
  try:
   with urllib.request.urlopen('http://127.0.0.1:8088/api/cobertura.php',timeout=2) as response:ready=json.load(response).get('publication_source_available') is True
   if ready:return
  except (OSError,ValueError):pass
  time.sleep(1)
 raise SystemExit('El contenedor no terminó de iniciar. Consulta scripts/preprod-local.py logs.')
def configure_test_mail_network():
 # Real-mail testing explicitly opts into outbound networking; no added published ports.
 test_file=STATE/'private/account-mail-test.json'
 enabled=test_file.is_file() and not test_file.is_symlink() and json.loads(test_file.read_text()).get('enabled') is True
 outbound='explorando-preprod-mail-egress'
 attached=json.loads(subprocess.check_output(['podman','inspect','--format','{{json .NetworkSettings.Networks}}',NAME],text=True)) or {}
 if enabled and outbound not in attached:
  if subprocess.run(['podman','network','exists',outbound],cwd=ROOT).returncode!=0:podman('network','create',outbound)
  podman('network','connect',outbound,NAME)
 elif not enabled and outbound in attached:podman('network','disconnect',outbound,NAME)
def main():
 operation_started=time.monotonic()
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('action',choices=['build','start','up','down','status','logs']);args=parser.parse_args()
 if args.action in ('up','start','build') and branch()!='preprod':raise SystemExit('Solo se prepara este entorno desde la rama preprod.')
 if args.action in ('build','start','up'):
  if subprocess.run(['podman','info','--format','{{.Host.Arch}}'],cwd=ROOT,capture_output=True).returncode!=0:start_machine()
 elif subprocess.run(['podman','info','--format','{{.Host.Arch}}'],cwd=ROOT,capture_output=True).returncode!=0:
  print('Podman está apagado; preproducción también.');return
 STATE.mkdir(parents=True,exist_ok=True)
 if args.action=='build':podman('build','--platform','linux/amd64','-t',IMAGE,'-f','preprod/infra/Containerfile','.');return
 if args.action=='down':
  if subprocess.run(['podman','container','exists',NAME],cwd=ROOT).returncode==0:podman('stop',NAME)
  print('Preproducción apagada. Imagen y datos conservados.');return
 if args.action=='logs':podman('logs','--tail','80',NAME);return
 if args.action=='status':podman('ps','-a','--filter','name=^'+NAME+'$','--format','{{.Names}} {{.Status}} {{.Ports}}');return
 if args.action=='start':
  if subprocess.run(['podman','container','exists',NAME],cwd=ROOT).returncode==0:
   old_image=subprocess.check_output(['podman','inspect','--format','{{.Image}}',NAME],text=True).strip()
   new_image=subprocess.check_output(['podman','image','inspect','--format','{{.Id}}',IMAGE],text=True).strip()
   if old_image==new_image:
    running=subprocess.check_output(['podman','inspect','--format','{{.State.Running}}',NAME],text=True).strip()
    if running!='true':podman('start',NAME)
    configure_test_mail_network();wait_ready();run(sys.executable,'preprod/cuentas-prueba.py');print('Preproducción lista: http://127.0.0.1:8088/ (sin reconstrucción ni reconfiguración)');print(f'Arranque comprobado en {time.monotonic()-operation_started:.1f} segundos.');return
  run(sys.executable,'preprod/control.py','up');return
 if subprocess.run(['podman','image','exists',IMAGE],cwd=ROOT).returncode!=0:
  run(sys.executable,'preprod/control.py','build')
 network='explorando-preprod-net'
 if subprocess.run(['podman','network','exists',network],cwd=ROOT).returncode!=0:podman('network','create','--internal',network)
 # Build the same managed package used by production, without connecting to hosting.
 release=STATE/'release'
 print('Preparando el código local; no se recompila PHP ni MariaDB.',flush=True)
 with (STATE/'prepare.log').open('w') as log:
  run(sys.executable,'scripts/preparar-hosting.py','--output',str(release),stdout=log,stderr=subprocess.STDOUT)
 shutil.copyfile(ROOT/'preprod/infra/runtime.php',release/'public/__preprod-runtime.php')
 shutil.copyfile(ROOT/'preprod/infra/account-status.php',release/'public/__preprod-account.php')
 shutil.copyfile(ROOT/'preprod/infra/mailbox.php',release/'public/admin/buzon-local.php')
 shutil.copyfile(ROOT/'preprod/infra/correo-ejemplo.php',release/'public/correo-ejemplo.php')
 # Version local assets after adding the isolated test instruments.
 run(sys.executable,'scripts/versiones-medicion.py','--public',str(release/'public'))
 # This access bypass exists only in the local build, never in hosting sources.
 bootstrap=release/'app/bootstrap.php';source=bootstrap.read_text()
 anchor="    session_start();\n    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));"
 if source.count(anchor)!=1:raise SystemExit('No se reconoce el punto seguro de sesión local; no se modificó el acceso.')
 bootstrap.write_text(source.replace(anchor,anchor+"\n    require '/usr/local/lib/preprod-admin-access.php';"))
 (STATE/'private').mkdir(exist_ok=True);(STATE/'media').mkdir(exist_ok=True)
 media=STATE/'media/destinos';media.mkdir(exist_ok=True)
 for source in (release/'public/assets/destinos').rglob('*'):
  if source.is_file():
   target=media/source.relative_to(release/'public/assets/destinos');target.parent.mkdir(parents=True,exist_ok=True)
   if not target.exists():shutil.copyfile(source,target)
 credential=STATE/'admin.json'
 if not credential.exists():
  credential.write_text(json.dumps({'username':'localadmin','password':secrets.token_urlsafe(20)},indent=2)+'\n');credential.chmod(0o600)
 settings_file=STATE/'settings.json'
 if not settings_file.exists():settings_file.write_text(json.dumps({'admin_without_password':False},indent=2)+'\n');settings_file.chmod(0o600)
 admin_open=json.loads(settings_file.read_text()).get('admin_without_password') is True
 info=json.loads(credential.read_text());envfile=STATE/'container.env';envfile.write_text('PREPROD_ADMIN_PASSWORD='+info['password']+'\nEL_PREPROD_ADMIN_OPEN='+('1' if admin_open else '0')+'\n');envfile.chmod(0o600)
 existing=subprocess.run(['podman','container','exists',NAME],cwd=ROOT).returncode==0
 if existing:
  old_image=subprocess.check_output(['podman','inspect','--format','{{.Image}}',NAME],text=True).strip()
  new_image=subprocess.check_output(['podman','image','inspect','--format','{{.Id}}',IMAGE],text=True).strip()
  layout=subprocess.check_output(['podman','inspect','--format','{{index .Config.Labels "el-preprod-layout"}}',NAME],text=True).strip()
  environment=json.loads(subprocess.check_output(['podman','inspect',NAME],text=True))[0]['Config']['Env']
  configured_open='EL_PREPROD_ADMIN_OPEN='+('1' if admin_open else '0') in environment
  if old_image!=new_image or layout!='3' or not configured_open:
   podman('stop',NAME);podman('rm',NAME);existing=False
 if existing:
  # Container stays defined so stop/start does not erase its MariaDB volume.
  podman('restart',NAME)
 else:
  podman('run','--platform','linux/amd64','-d','--name',NAME,'--label','el-preprod-layout=3','--network',network,'--env-file',str(envfile),'-p','127.0.0.1:8088:80','--mount','type=volume,source=explorando-preprod-mariadb-10-11,target=/var/lib/mysql','-v',str(release)+':/srv/portal:ro','-v',str(STATE)+':/srv/state:rw','-v',str(STATE/'private')+':/srv/portal/private:rw','-v',str(media)+':/srv/portal/public/assets/destinos:rw',IMAGE)
 configure_test_mail_network()
 wait_ready()
 run(sys.executable,'preprod/cuentas-prueba.py')
 print('Preproducción: http://127.0.0.1:8088/\nAdministrador: http://127.0.0.1:8088/admin/\nAcceso local privado: '+str(credential))
if __name__=='__main__':main()
