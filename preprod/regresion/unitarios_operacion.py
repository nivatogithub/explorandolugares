#!/usr/bin/env python3
"""F18: isolated PHP checks, no shared DB/container/HTTP or real email.
Returns 0 only when every explicitly listed test passes. Does not claim UI coverage.
"""
from pathlib import Path
import argparse
import datetime
import json
import subprocess

ROOT = Path(__file__).resolve().parents[2]
TESTS = (
    'business-deletion', 'business-bulk-edit', 'business-categories', 'business-crm',
    'business-subscriptions', 'business-email-campaigns', 'operating-finance',
    'database-change-package', 'account-profile', 'account-authorization',
)
# HTTP/email functions disabled even if an accidental real transport is introduced.
DISABLED = 'mail,curl_exec,fsockopen,pfsockopen,stream_socket_client'


def run():
    results = []
    for name in TESTS:
        try:
            process = subprocess.run(
                ['php', '-d', 'disable_functions='+DISABLED, str(ROOT/'backend/tests'/f'{name}.php')],
                cwd=ROOT, capture_output=True, text=True, timeout=60)
            row = {'test': name, 'status': 'APROBADA' if process.returncode == 0 else 'FALLIDA',
                   'returncode': process.returncode, 'stdout': process.stdout, 'stderr': process.stderr}
        except subprocess.TimeoutExpired:
            row = {'test': name, 'status': 'FALLIDA', 'returncode': None, 'stderr': 'Timeout de 60 segundos'}
        except OSError as error:
            row = {'test': name, 'status': 'FALLIDA', 'returncode': None, 'stderr': str(error)}
        results.append(row)
        print(name+': '+row['status'], flush=True)
    output = ROOT/'.local/preprod/regresion/unitarios-operacion'
    output.mkdir(parents=True, exist_ok=True)
    report = {'created_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
              'scope': 'PHP aislado con SQLite sintético; no UI, HTTP, contenedor ni BD compartida',
              'external_transport_disabled': DISABLED.split(','), 'results': results,
              'ready_for_scope': all(row['status'] == 'APROBADA' for row in results)}
    (output/'resultado.json').write_text(json.dumps(report, ensure_ascii=False, indent=2)+'\n')
    print('Informe: '+str(output/'resultado.json'))
    return 0 if report['ready_for_scope'] else 1


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--listar', action='store_true', help='Mostrar pruebas sin ejecutarlas')
    args = parser.parse_args()
    if args.listar:
        print('\n'.join(TESTS))
    else:
        raise SystemExit(run())
