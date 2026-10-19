#!/usr/bin/env python3
"""Apply the agreed retention only to verified tool-owned database snapshot files."""
from pathlib import Path
import argparse,importlib.util,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('backup_tool',ROOT/'scripts/respaldo-base-datos.py');tool=importlib.util.module_from_spec(spec);spec.loader.exec_module(tool)
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--config',type=Path,default=ROOT/'.local/sftp.json');p.add_argument('--apply',action='store_true');p.add_argument('--local-only',action='store_true');a=p.parse_args();config=tool.api.load_config(a.config)
 remote=config['remote_base']+'/private/backups';result={}
 if not a.local_only:result['hosting']=tool.private_php(config,{'config':config['remote_base']+'/private/config.php','apply':a.apply},'backup-retention.php')
 options={'directory':str(ROOT/'.local/database-backups'),'remote_directory':remote,'apply':a.apply,'retention_class':str(ROOT/'scripts/php/BackupRetention.php')}
 source=(ROOT/'scripts/php/backup-retention.php').read_text().replace('<?php','<?php\n$options=json_decode('+repr(json.dumps(options))+',true);',1).replace('declare(strict_types=1);','')
 response=subprocess.run(['php'],input=source,text=True,capture_output=True)
 if response.returncode:raise SystemExit('Conservación local detenida para proteger los respaldos.')
 result['local']=json.loads(response.stdout);print(json.dumps(result,ensure_ascii=False))
if __name__=='__main__':main()
