#!/usr/bin/env python3
"""Use the already configured SSH identity; never read or transfer private server configuration."""
import argparse,importlib.util,subprocess,shlex
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('deploy',ROOT/'scripts/desplegar-sftp.py');mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)
p=argparse.ArgumentParser();p.add_argument('--install',action='store_true');a=p.parse_args()
c=mod.load_config(ROOT.parent/'explorandolugares/.local/sftp.json')
cmd=['ssh','-F','/dev/null','-p',str(c['port']),'-oBatchMode=yes','-oStrictHostKeyChecking=yes','-oConnectTimeout=20','-oForwardAgent=no','-oClearAllForwardings=yes','-oUserKnownHostsFile='+str(Path(c['known_hosts_file']).expanduser().resolve())]
if c.get('identity_file'):cmd+=['-i',str(Path(c['identity_file']).expanduser().resolve()),'-oIdentitiesOnly=yes']
cmd += [c['user']+'@'+c['host']]
remote='php -v' if not a.install else 'php '+shlex.quote(c['remote_base']+'/app/analytics/install.php')
r=subprocess.run(cmd+[remote],capture_output=True,text=True,timeout=45)
# Installation outputs only a defined nonpersonal status; never dump private diagnostics.
if r.returncode:print('SSH execution unavailable:',r.returncode);raise SystemExit(r.returncode)
print(r.stdout[:500] if not a.install else ('Measurement installed' if 'MEASUREMENT_INSTALLED' in r.stdout else 'Installation not confirmed'))
