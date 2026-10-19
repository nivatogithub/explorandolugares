#!/usr/bin/env python3
"""Restore a verified snapshot only into an isolated disposable local MariaDB database."""
from pathlib import Path
import argparse,hashlib,json,secrets,subprocess
ROOT=Path(__file__).resolve().parents[1]
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--backup',type=Path,required=True);a=p.parse_args();file=a.backup.resolve();meta=json.loads(file.with_suffix('.json').read_text())
 if hashlib.sha256(file.read_bytes()).hexdigest()!=meta['sha256']:raise SystemExit('Respaldo alterado; no se restaura.')
 target='/tmp/el-restore-'+secrets.token_hex(8)+'.sql.gz'
 subprocess.run(['podman','cp',str(file),'explorando-preprod:'+target],check=True,stdout=subprocess.DEVNULL)
 options={'file':target,'tables':meta['tables']}
 source=(ROOT/'scripts/php/verify-snapshot-local.php').read_text().replace('<?php','<?php\n$options=json_decode('+repr(json.dumps(options))+',true);',1).replace('declare(strict_types=1);','')
 result=subprocess.run(['podman','exec','-e','EL_PREPROD_LOCAL=1','-i','explorando-preprod','php'],input=source.encode(),capture_output=True)
 if result.returncode:raise SystemExit('La restauración aislada no completó; no se tocó producción ni la base activa de preproducción.')
 report=json.loads(result.stdout);meta['restore_verification']=report;file.with_suffix('.json').write_text(json.dumps(meta,indent=2)+'\n')
 print(json.dumps(report))
if __name__=='__main__':main()
