#!/usr/bin/env python3
"""Build a disposable package, run a command, and remove only that test's files."""
from pathlib import Path
import argparse
import importlib.util
import os
import signal
import subprocess
import sys

from build_storage import temporary_package, run_owned_command

ROOT = Path(__file__).resolve().parents[1]


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', nargs=argparse.REMAINDER,
                        help='Después de --: comando de prueba; {package} se sustituye por su carpeta.')
    args = parser.parse_args(argv)
    command = args.command[1:] if args.command[:1] == ['--'] else args.command
    if not command:
        parser.error('Indica el comando de prueba después de --.')
    builder = next((ROOT / 'scripts' / name for name in
                    ('prepare-hosting.py', 'preparar-hosting.py')
                    if (ROOT / 'scripts' / name).is_file()), None)
    if builder is None:
        parser.error('No se encontró el constructor del portal.')
    spec = importlib.util.spec_from_file_location('test_package_builder', builder)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    previous = signal.getsignal(signal.SIGTERM)
    def terminate(signum, frame):
        raise KeyboardInterrupt('Prueba interrumpida')
    signal.signal(signal.SIGTERM, terminate)
    try:
        with temporary_package(ROOT) as package:
            module.build(package, retained=False)
            command = [item.replace('{package}', str(package)) for item in command]
            return run_owned_command(command, cwd=ROOT,
                                     env={**os.environ, 'EL_TEST_PACKAGE': str(package)})
    finally:
        signal.signal(signal.SIGTERM, previous)


if __name__ == '__main__':
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        sys.exit(130)
    except (OSError, ValueError, subprocess.CalledProcessError) as error:
        print(f'Prueba detenida: {error}', file=sys.stderr)
        sys.exit(1)
