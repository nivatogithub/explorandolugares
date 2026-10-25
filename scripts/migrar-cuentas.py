#!/usr/bin/env python3
"""Inspect or apply explicitly selected account/registration migrations; never imports data."""
import argparse,importlib.util,json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('snapshot_tool',ROOT/'scripts/respaldo-base-datos.py');tool=importlib.util.module_from_spec(spec);spec.loader.exec_module(tool)
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--config',type=Path,default=ROOT/'.local/sftp.json');p.add_argument('--apply',action='store_true');p.add_argument('--approved-preprod');p.add_argument('--include-subscription-changes',action='store_true',help='Incluye la migración aditiva 044 del saldo de suscripciones. Sin --apply solo consulta.');p.add_argument('--business-registration',action='store_true',help='Selecciona exclusivamente las migraciones 052–057 de registro, límites, borradores y cupones. Sin --apply solo consulta.');a=p.parse_args()
 if a.apply:tool.api.require_approved_preprod(a.approved_preprod)
 migrations=['043-account-lifecycle']+(['044-subscription-changes'] if a.include_subscription_changes else [])
 if a.business_registration:
  if a.include_subscription_changes:p.error('No combines el alcance de registro con el de cuentas.')
  migrations=['052-business-onboarding-uploads','053-current-business-text-rules','054-business-description-budgets','055-business-registration-drafts','056-business-promotions','057-business-promotion-places']
 c=tool.api.load_config(a.config);result=tool.private_php(c,{'config':c['remote_base']+'/private/config.php','operation':'apply' if a.apply else 'probe','migrations':migrations},'prepare-account-migration.php')
 if result.get('error'):raise SystemExit('La preparación no completó; no publicar código dependiente de esa migración.')
 print(json.dumps(result,ensure_ascii=False))
if __name__=='__main__':main()
