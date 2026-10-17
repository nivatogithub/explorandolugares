#!/usr/bin/env python3
"""Entorno fijo de revisión: Apache/PHP/MySQL, aislado de producción."""
import argparse,json,os,secrets,shutil,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
STATE=ROOT/'.local/preprod'; NAME='explorando-preprod'; IMAGE='localhost/explorando-preprod:local'
def run(*args,**kwargs):return subprocess.run(args,cwd=ROOT,check=True,**kwargs)
def podman(*args,**kwargs):return run('podman',*args,**kwargs)
def branch():return subprocess.check_output(['git','branch','--show-current'],cwd=ROOT,text=True).strip()
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('action',choices=['build','up','down','status','logs']);args=parser.parse_args()
 if args.action in ('up','build') and branch()!='preprod':raise SystemExit('Solo se prepara este entorno desde la rama preprod.')
 STATE.mkdir(parents=True,exist_ok=True)
 if args.action=='build':podman('build','-t',IMAGE,'-f','infra/preprod/Containerfile','.');return
 if args.action=='down':podman('stop',NAME);return
 if args.action=='logs':podman('logs','--tail','80',NAME);return
 if args.action=='status':podman('ps','-a','--filter','name=^'+NAME+'$','--format','{{.Names}} {{.Status}} {{.Ports}}');return
 network='explorando-preprod-net'
 if subprocess.run(['podman','network','exists',network],cwd=ROOT).returncode!=0:podman('network','create','--internal',network)
 # Build the same managed package used by production, without connecting to hosting.
 release=STATE/'release';run(sys.executable,'scripts/preparar-hosting.py','--output',str(release))
 (STATE/'private').mkdir(exist_ok=True);(STATE/'media').mkdir(exist_ok=True)
 media=STATE/'media/destinos';media.mkdir(exist_ok=True)
 for source in (release/'public/assets/destinos').rglob('*'):
  if source.is_file():
   target=media/source.relative_to(release/'public/assets/destinos');target.parent.mkdir(parents=True,exist_ok=True)
   if not target.exists():shutil.copyfile(source,target)
 credential=STATE/'admin.json'
 if not credential.exists():
  credential.write_text(json.dumps({'username':'localadmin','password':secrets.token_urlsafe(20)},indent=2)+'\n');credential.chmod(0o600)
 info=json.loads(credential.read_text());envfile=STATE/'container.env';envfile.write_text('PREPROD_ADMIN_PASSWORD='+info['password']+'\n');envfile.chmod(0o600)
 existing=subprocess.run(['podman','container','exists',NAME],cwd=ROOT).returncode==0
 if existing:
  old_image=subprocess.check_output(['podman','inspect','--format','{{.Image}}',NAME],text=True).strip()
  new_image=subprocess.check_output(['podman','image','inspect','--format','{{.Id}}',IMAGE],text=True).strip()
  layout=subprocess.check_output(['podman','inspect','--format','{{index .Config.Labels "el-preprod-layout"}}',NAME],text=True).strip()
  if old_image!=new_image or layout!='2':
   podman('stop',NAME);podman('rm',NAME);existing=False
 if existing:
  # Container stays defined so stop/start does not erase its MySQL volume.
  podman('restart',NAME)
 else:
  podman('run','-d','--name',NAME,'--label','el-preprod-layout=2','--network',network,'--env-file',str(envfile),'-p','127.0.0.1:8088:80','--mount','type=volume,source=explorando-preprod-mysql,target=/var/lib/mysql','-v',str(release)+':/srv/portal:ro','-v',str(STATE)+':/srv/state:rw','-v',str(STATE/'private')+':/srv/portal/private:rw','-v',str(media)+':/srv/portal/public/assets/destinos:rw',IMAGE)
 print('Preproducción: http://127.0.0.1:8088/\nAdministrador: http://127.0.0.1:8088/admin/\nAcceso local privado: '+str(credential))
if __name__=='__main__':main()
