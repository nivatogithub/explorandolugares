#!/usr/bin/env python3
"""Build in an isolated source snapshot; never let legacy generators edit the checkout."""
from pathlib import Path
import argparse
import hashlib
import json
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
INPUTS = ('scripts', 'backend', 'content', 'design', 'ensayos', 'preprod')


def build(output: Path) -> None:
    output = output.resolve()
    if output == ROOT or ROOT in output.parents and '.local' not in output.relative_to(ROOT).parts:
        raise ValueError('La salida debe estar fuera de las fuentes o dentro de .local/.')
    if output.exists() and any(output.iterdir()):
        raise ValueError('La construcción exige una carpeta de salida nueva/vacía; no se mezclan entregas.')
    output.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='el-build-') as temporary:
        snapshot = Path(temporary) / 'source'
        snapshot.mkdir()
        for name in INPUTS:
            shutil.copytree(ROOT / name, snapshot / name,
                            ignore=shutil.ignore_patterns('.local', '__pycache__', 'node_modules', '.DS_Store'))
        subprocess.run([sys.executable, str(snapshot / 'scripts/_construir-hosting.py'),
                        '--output', str(output)], check=True)
    files = {}
    for path in sorted(output.rglob('*')):
        if path.is_file():
            relative = path.relative_to(output).as_posix()
            files[relative] = {'sha256': hashlib.sha256(path.read_bytes()).hexdigest(), 'bytes': path.stat().st_size}
    (output / 'build-manifest.json').write_text(json.dumps({'schema_version': 1, 'files': files},
                                                         ensure_ascii=False, indent=2) + '\n')
    print(f'Construcción aislada: {len(files)} archivos; fuentes intactas; manifiesto en {output}/build-manifest.json')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ROOT / '.local/hosting')
    args = parser.parse_args()
    try:
        build(args.output)
    except (ValueError, subprocess.CalledProcessError) as error:
        parser.exit(1, f'{error}\n')
