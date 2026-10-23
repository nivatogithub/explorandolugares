#!/usr/bin/env python3
"""Levanta únicamente los dos contenedores dev de puertos fijos."""
import argparse,subprocess,secrets
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('--state',type=Path,required=True);p.add_argument('--engine',choices=['podman','docker'],default='podman');a=p.parse_args();root=a.state.resolve()
def run(*cmd):return subprocess.run([a.engine,*cmd],check=True)
network='explorando-dev-net'
if subprocess.run([a.engine,'network','inspect',network],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL).returncode:run('network','create','--internal',network)
for i in [1,2]:
 name='explorando-dev'+str(i);state=root/('dev'+str(i));release=state/'release'
 if not (release/'public/index.html').exists() and not (release/'public/index.php').exists():raise SystemExit('Falta paquete preparado: '+str(release))
 if subprocess.run([a.engine,'container','inspect',name],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL).returncode==0:
  run('start',name);continue
 state.mkdir(parents=True,exist_ok=True);(state/'private').mkdir(exist_ok=True)
 env=state/'container.env'
 if not env.exists():env.write_text('PREPROD_ADMIN_PASSWORD='+secrets.token_urlsafe(24)+'\nEL_PREPROD_ADMIN_OPEN=0\n');env.chmod(0o600)
 run('run','--platform','linux/amd64','-d','--name',name,'--label','el-project=explorandolugares','--label','el-environment=dev'+str(i),'--network',network,'--env-file',str(env),'-p','127.0.0.1:'+str(8085+i)+':80','--mount','type=volume,source=explorando-dev'+str(i)+'-mariadb,target=/var/lib/mysql','-v',str(Path(__file__).resolve().parent/'init-dev.php')+':/usr/local/lib/preprod-init.php:ro','-v',str(release)+':/srv/portal:ro','-v',str(state)+':/srv/state:rw','-v',str(state/'private')+':/srv/portal/private:rw','localhost/explorando-preprod:local')
print('dev1 http://127.0.0.1:8086/; dev2 http://127.0.0.1:8087/; preprod intacto')
