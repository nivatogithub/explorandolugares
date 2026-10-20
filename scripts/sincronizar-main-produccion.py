#!/usr/bin/env python3
"""Check full production parity, then optionally fast-forward main. Never deploy."""
import argparse
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
from release_guard import require_main_ancestor, require_package_parity, require_validation

ROOT = Path(__file__).resolve().parents[1]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--validation-report', type=Path, required=True)
    parser.add_argument('--apply', action='store_true', help='Fast-forward local de main; nunca force, reset ni push automático.')
    args = parser.parse_args()
    spec = importlib.util.spec_from_file_location('deploy', ROOT/'scripts/desplegar-sftp.py')
    api = importlib.util.module_from_spec(spec); spec.loader.exec_module(api)
    candidate = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
    api.require_approved_preprod(candidate)
    require_main_ancestor(ROOT)
    config = api.load_config(ROOT/'.local/sftp.json')
    with tempfile.TemporaryDirectory(prefix='el-main-parity-') as temporary:
        temp = Path(temporary)
        remote = temp/'manifest.json'
        api.SFTP(config).run(['get '+api.quote(config['remote_base']+'/'+api.MANIFEST_NAME)+' '+api.quote(remote)])
        manifest = api.parse_manifest(remote, config)
        published = subprocess.check_output(['git','rev-parse',manifest['commit']+'^{commit}'],cwd=ROOT,text=True).strip()
        if published != candidate:
            raise ValueError('HEAD de preprod todavía no es la versión publicada. No actualizar main.')
        require_validation(manifest, json.loads(args.validation_report.read_text()))
        require_package_parity(api.build(temp/'build', config), manifest)
        # Recheck remote reference after the build; another worker may have deployed.
        confirmation = temp/'confirmation.json'
        api.SFTP(config).run(['get '+api.quote(config['remote_base']+'/'+api.MANIFEST_NAME)+' '+api.quote(confirmation)])
        if api.parse_manifest(confirmation, config) != manifest:
            raise ValueError('Producción cambió durante la comprobación. Repetir validación.')
        if args.apply:
            worktrees = subprocess.check_output(['git','worktree','list','--porcelain'],cwd=ROOT,text=True)
            main_path = None
            for block in worktrees.strip().split('\n\n'):
                lines = block.splitlines()
                if 'branch refs/heads/main' in lines:
                    main_path = Path(next(line[len('worktree '):] for line in lines if line.startswith('worktree ')))
            if main_path is None:
                raise ValueError('Abrir el worktree de main antes de aplicar; no mover referencias a escondidas.')
            if subprocess.check_output(['git','status','--porcelain'],cwd=main_path,text=True).strip():
                raise ValueError('Main tiene cambios sin guardar; no se modificó.')
            subprocess.run(['git','merge','--ff-only',candidate],cwd=main_path,check=True)
        print(json.dumps({'full_package_matches_production':True,'main_updated':args.apply,'commit':candidate,'push_performed':False}))


if __name__ == '__main__':
    main()
