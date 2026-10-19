#!/usr/bin/env python3
"""Prepare or apply only additive account migration 043; never imports preproduction data."""
import argparse,importlib.util,json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('snapshot_tool',ROOT/'scripts/respaldo-base-datos.py');tool=importlib.util.module_from_spec(spec);spec.loader.exec_module(tool)
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--config',type=Path,default=ROOT/'.local/sftp.json');p.add_argument('--apply',action='store_true');p.add_argument('--approved-preprod');a=p.parse_args()
 if a.apply:tool.api.require_approved_preprod(a.approved_preprod)
 c=tool.api.load_config(a.config);result=tool.private_php(c,{'config':c['remote_base']+'/private/config.php','operation':'apply' if a.apply else 'probe'},'prepare-account-migration.php')
 if result.get('error'):raise SystemExit('La preparación no completó; no publicar código dependiente de esa migración.')
 print(json.dumps(result,ensure_ascii=False))
if __name__=='__main__':main()
