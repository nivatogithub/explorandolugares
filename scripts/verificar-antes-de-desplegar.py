#!/usr/bin/env python3
"""Local release gate: syntax, PHP/Python tests, deployment tests and HTTP flows."""
from pathlib import Path
import ast
import os
import shutil
import subprocess
import sys
import tempfile
import argparse

ROOT = Path(__file__).resolve().parents[1]


def run(label, command, **options):
    print(f'\n== {label} ==', flush=True)
    subprocess.run(command, cwd=ROOT, check=True, **options)


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--managed-preprod',action='store_true',help='Use the existing isolated Apache/MariaDB acceptance runner instead of legacy temporary HTTP servers.')
    args=parser.parse_args()
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
    (ROOT/'.local').mkdir(exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='el-release-gate-', dir=ROOT/'.local') as folder:
        package = Path(folder)/'package'
        run('Paquete completo de pruebas', [sys.executable, 'scripts/prepare-hosting.py', '--output', str(package)])
        seo_package=Path(folder)/'seo-package'
        shutil.copytree(package,seo_package)
        run('Fixture editorial desechable', [sys.executable, 'scripts/prepare-presentation-fixture.py', '--package', str(seo_package)])
        for path in sorted((ROOT / 'backend/tests').glob('*.php')):
            if path.name.endswith(('.mariadb.php', '.mysql.php', '.fixture.php')):
                continue  # Database-specific tests and HTTP routers have dedicated runners below.
            arguments = [str(package)] if path.name == 'presentation-package.php' else []
            if path.name in {'place-reader-contract.php','place-selectors.php'}:
                arguments=[str(seo_package/'private/presentation.sqlite')]
            run(f'PHP {path.name}', ['php', str(path), *arguments])
        # Separate private fixtures; never replace registration's configuration.
        run('SEO sobre respuestas PHP finales', [sys.executable, 'scripts/check-package-seo.py', '--package', str(seo_package), '--output', str(seo_package/'seo-results.json')])
        if args.managed_preprod:
            run('Regresión completa en Apache/MariaDB local existente', [sys.executable,'preprod/regresion/ejecutar.py','--paquete',str(package)])
            for pattern in ['test_release_guard.py','test_desplegar_sftp.py','test_deployment_recovery.py','test_managed_release.py','test_full_release_coverage.py']:
                run('Controles de entrega '+pattern,[sys.executable,'-m','unittest','discover','-s','scripts/tests','-p',pattern])
            print('PRE-DESPLIEGUE APROBADO: PHP, SEO, regresión MariaDB administrada y recuperación. No se ejecutaron servidores HTTP heredados ni se verificó correo productivo.')
            return 0
        run('Registro personal y activación con el buzón de preprod', [sys.executable,'preprod/regresion/ejecutar.py','--solo-registro','--paquete',str(package)])
        run('Registro HTTP: cuatro planes, borradores, pago y cupones', [sys.executable, 'backend/tests/registration-flow.integration.py', '--package', str(package)])
        run('ZIP editorial y publicación Premium', [sys.executable, 'backend/tests/editorial-roundtrip.integration.py'])
        run('ZIP editorial con cupón del 100 %', [sys.executable, 'backend/tests/editorial-roundtrip.integration.py'], env={**os.environ, 'EL_EDITORIAL_PROMOTION':'1'})
    run('MariaDB aislada, concurrencia y recuperación', [sys.executable, 'scripts/probar-reparacion-negocios.py', '--mariadb'])
    run('Registro, cupones y reloj en MariaDB', [sys.executable, 'scripts/probar-registro-mariadb.py'])
    run('Navegación, enlaces y despliegue', [sys.executable, '-m', 'unittest', 'discover', '-s', 'scripts/tests', '-p', 'test*.py'])
    if shutil_which('node'):
        run('Visibilidad y clics de tarjetas', ['node', 'backend/tests/basic-cards.cjs'])
        run('QR de negocios: lectura y descarga PNG', ['node', 'backend/tests/business-qr.cjs'])
        run('Contadores editoriales por plan', ['node', 'backend/tests/business-text-counters.dom.js'])
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
