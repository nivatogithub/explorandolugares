#!/usr/bin/env python3
"""Compare the complete deployable package with a verified production inventory.
Read-only: never connects to the hosting, uploads files or touches its database.
"""
import argparse
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import subprocess
import tarfile
import tempfile

ROOT = Path(__file__).resolve().parents[1]


def deploy_module(root, name):
    spec = importlib.util.spec_from_file_location(name, root / 'scripts/desplegar-sftp.py')
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def differences(expected, actual):
    return {'missing': sorted(set(expected) - set(actual)),
            'extra': sorted(set(actual) - set(expected)),
            'changed': sorted(p for p in expected.keys() & actual.keys() if expected[p] != actual[p])}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', type=Path, required=True)
    parser.add_argument('--manifest', type=Path, required=True)
    parser.add_argument('--compare-ref')
    parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args()
    deploy = deploy_module(ROOT, 'parity_current')
    config = deploy.load_config(args.config)
    production = deploy.parse_manifest(args.manifest, config)
    if not production:
        raise ValueError('Se necesita el inventario de producción confirmado.')
    expected = {f['relative']: f['sha256'] for f in production['files']}
    with tempfile.TemporaryDirectory(prefix='el-parity-') as temporary:
        temp = Path(temporary)
        current = {f['relative']: f['sha256'] for f in deploy.build(temp / 'current', config)}
        report = {'production_comparison': differences(expected, current),
                  'file_count': len(current),
                  'inventory_sha256': hashlib.sha256(json.dumps(current, sort_keys=True).encode()).hexdigest()}
        if args.compare_ref:
            snapshot = temp / 'ref'; snapshot.mkdir()
            archive = subprocess.check_output(['git', 'archive', args.compare_ref], cwd=ROOT)
            with tarfile.open(fileobj=io.BytesIO(archive)) as tar:
                tar.extractall(snapshot, filter='data')
            other_deploy = deploy_module(snapshot, 'parity_reference')
            other = {f['relative']: f['sha256'] for f in other_deploy.build(temp / 'reference', config)}
            report['reference'] = args.compare_ref
            report['reference_comparison'] = differences(current, other)
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(report, indent=2) + '\n')
        print(json.dumps(report, indent=2))
        checks = [report['production_comparison'], report.get('reference_comparison', {})]
        return 1 if any(paths for check in checks for paths in check.values()) else 0


if __name__ == '__main__':
    raise SystemExit(main())
