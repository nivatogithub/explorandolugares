#!/usr/bin/env python3
"""Probe/apply only the five owner-approved 2026-10-24 destination snapshots.
Uses the established expiring token/private PHP migration mechanism. Never SQL from callers.
"""
import argparse,importlib.util,json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
SCOPE=['barranquilla','manizales','pereira','san-bernardo-del-viento','villavieja']
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--config',type=Path,default=ROOT/'.local/sftp.json');p.add_argument('--apply',action='store_true');p.add_argument('--approved-preprod');a=p.parse_args()
 spec=importlib.util.spec_from_file_location('fixed_migration',ROOT/'scripts/respaldo-base-datos.py');tool=importlib.util.module_from_spec(spec);spec.loader.exec_module(tool)
 if a.apply:tool.api.require_approved_preprod(a.approved_preprod)
 c=tool.api.load_config(a.config);result=tool.private_php(c,{'config':c['remote_base']+'/private/config.php','slugs':SCOPE,'apply':a.apply},'prepare-destination-migration.php')
 print(json.dumps(result,ensure_ascii=False,indent=2))
 if result.get('error'):raise SystemExit('La migración no completó; revisar el registro privado, sin restaurar ni borrar datos.')
if __name__=='__main__':main()
