#!/usr/bin/env python3
"""Trust HTTPS metadata only for one explicit Cloudflare review host in a dev container."""
import argparse,re,subprocess
p=argparse.ArgumentParser();p.add_argument('--host',required=True);p.add_argument('--container',choices=['explorando-dev1','explorando-dev2'],required=True);a=p.parse_args()
if not re.fullmatch(r'[a-z0-9-]+\.trycloudflare\.com',a.host):p.error('Expected the exact temporary trycloudflare.com hostname')
# A server boundary, never a production application bypass. Local dev only.
conf=('SetEnvIfExpr "req(\'Host\') == \''+a.host+'\' && req(\'X-Forwarded-Proto\') == \'https\'" HTTPS=on\n').encode()
check=subprocess.run(['podman','exec',a.container,'php','-r',"$c=require '/srv/portal/private/config.php';exit(empty($c['local_test'])?1:0);"])
if check.returncode:raise SystemExit('Refusing a non-local runtime')
subprocess.run(['podman','exec','-i',a.container,'tee','/etc/apache2/conf-enabled/el-review-tunnel.conf'],input=conf,check=True,stdout=subprocess.DEVNULL)
subprocess.run(['podman','exec',a.container,'apache2ctl','configtest'],check=True)
subprocess.run(['podman','exec',a.container,'apache2ctl','graceful'],check=True)
print('HTTPS review host enabled only in '+a.container+': '+a.host)
