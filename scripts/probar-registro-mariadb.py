#!/usr/bin/env python3
"""Run registration-specific MySQL tests in disposable databases, never the active DB."""
from pathlib import Path
import io
import secrets
import subprocess
import tarfile

ROOT = Path(__file__).resolve().parents[1]
tests = ['business-drafts', 'business-text-capacity', 'business-promotions', 'database-clock']
temporary = '/tmp/el-registration-tests-' + secrets.token_hex(12)
archive = io.BytesIO()
with tarfile.open(fileobj=archive, mode='w') as stream:
    for name in ['app', 'database']:
        stream.add(ROOT / 'backend' / name, arcname=name)
    for name in tests:
        stream.add(ROOT / 'backend/tests' / (name + '.mysql.php'), arcname=name + '.mysql.php')
subprocess.run(['podman', 'exec', '-i', 'explorando-preprod', 'sh', '-c',
                'mkdir ' + temporary + ' && tar -xf - -C ' + temporary],
               input=archive.getvalue(), check=True)
try:
    for name in tests:
        print('MariaDB: ' + name, flush=True)
        subprocess.run(['podman', 'exec', '-e', 'EL_PREPROD_LOCAL=1',
                        'explorando-preprod', 'php', temporary + '/' + name + '.mysql.php',
                        temporary], check=True)
finally:
    # Exact randomly-created test directory; no volumes or application data.
    subprocess.run(['podman', 'exec', 'explorando-preprod', 'rm', '-r', temporary], check=True)
