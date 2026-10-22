#!/usr/bin/env python3
"""Local release gate: syntax, PHP/Python tests, deployment tests and HTTP flows."""
from pathlib import Path
import ast
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]


def run(label, command):
    print(f'\n== {label} ==', flush=True)
    subprocess.run(command, cwd=ROOT, check=True)


def main():
    php_files = sorted((ROOT / 'backend').rglob('*.php'))
    if not php_files:
        raise RuntimeError('No se encontraron archivos PHP para validar.')
    print(f'Validando sintaxis PHP en {len(php_files)} archivos...', flush=True)
    for path in php_files:
        subprocess.run(['php', '-l', str(path)], cwd=ROOT, check=True, stdout=subprocess.DEVNULL)

    js_files = sorted((ROOT / 'backend/public/assets').glob('*.js'))
    js_files += sorted((ROOT / 'ensayos/v1/assets').glob('*.js'))
    if js_files and shutil_which('node'):
        for path in js_files:
            subprocess.run(['node', '--check', str(path)], cwd=ROOT, check=True, stdout=subprocess.DEVNULL)
    else:
        print('Node.js no está instalado; se omite la comprobación de sintaxis JavaScript.', flush=True)

    python_files = [*ROOT.joinpath('backend').rglob('*.py'), *ROOT.joinpath('scripts').rglob('*.py')]
    for path in python_files:
        ast.parse(path.read_text(), filename=str(path))
    print(f'Sintaxis Python: {len(python_files)} archivos correctos.', flush=True)

    run('Tokens y versiones de cobertura', [sys.executable, 'scripts/sincronizar-estilos-cobertura.py', '--check'])
    run('Diseño del portal', [sys.executable, 'backend/tests/test_portal_design.py'])
    run('Coherencia de oferta comercial y condiciones', [sys.executable, 'backend/tests/test_business_offer_copy.py'])
    run('Paquete incremental de cobertura', [sys.executable, 'backend/tests/test_coverage_package.py'])
    with tempfile.TemporaryDirectory(prefix='el-release-gate-') as folder:
        package = Path(folder)/'package'
        run('Paquete completo de pruebas', [sys.executable, 'scripts/preparar-hosting.py', '--output', str(package)])
        for path in sorted((ROOT / 'backend/tests').glob('*.php')):
            if path.name.endswith('.mariadb.php'):
                continue  # Executed below in the disposable MariaDB container.
            arguments = [str(package)] if path.name == 'presentation-package.php' else []
            run(f'PHP {path.name}', ['php', str(path), *arguments])
    run('MariaDB aislada, concurrencia y recuperación', [sys.executable, 'scripts/probar-reparacion-negocios.py', '--mariadb'])
    run('Navegación, enlaces y despliegue', [sys.executable, '-m', 'unittest', 'discover', '-s', 'scripts/tests', '-p', 'test*.py'])
    if shutil_which('node'):
        run('Visibilidad y clics de tarjetas', ['node', 'backend/tests/basic-cards.cjs'])
        run('Exclusión de estadísticas por navegador', ['node', 'backend/tests/measurement-exclusion.dom.js'])
    run('Integración HTTP de conteo básico sin cookies', [sys.executable, 'backend/tests/basic-audience.integration.py'])
    run('Integración HTTP de participación', [sys.executable, 'backend/tests/integration.py'])
    run('Integración HTTP del panel administrativo', [sys.executable, 'backend/tests/admin-pages.integration.py'])
    print('\nPRE-DESPLIEGUE APROBADO: todas las pruebas locales incluidas terminaron correctamente.', flush=True)
    print('Esto no valida MySQL/GoDaddy, credenciales del hosting ni la respuesta del sitio publicado.', flush=True)
    return 0


def shutil_which(command):
    import shutil
    return shutil.which(command)


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (OSError, subprocess.CalledProcessError, RuntimeError, SyntaxError) as error:
        print(f'PRE-DESPLIEGUE BLOQUEADO: {error}', file=sys.stderr)
        sys.exit(1)
