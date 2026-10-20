"""The full release must retain the already reviewed coverage integration."""
import hashlib
import json
import subprocess
import sys
import shutil
import importlib.util
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('full_coverage_deploy', ROOT/'scripts/desplegar-sftp.py')
deploy = importlib.util.module_from_spec(spec); spec.loader.exec_module(deploy)
version_spec=importlib.util.spec_from_file_location('measurement_versions',ROOT/'scripts/versiones-medicion.py')
versions=importlib.util.module_from_spec(version_spec);version_spec.loader.exec_module(versions)


class FullReleaseCoverageTests(unittest.TestCase):
    def test_full_build_preserves_integration_and_dynamic_assets(self):
        with tempfile.TemporaryDirectory() as folder:
            files = deploy.build(Path(folder))
            packaged = {f['relative']: f['sha256'] for f in files}
            # Compare the complete reference overlay after the same approved
            # presentation/wording transformations as the shipping package.
            with tempfile.TemporaryDirectory() as reference:
                ref=Path(reference);(ref/'app').mkdir();(ref/'public').mkdir()
                sources=[(ROOT/'backend/integration-medicion/public','public'),
                         (ROOT/'backend/public/cobertura','public/cobertura'),
                         (ROOT/'backend/public/assets/cobertura','public/assets/cobertura')]
                paths=set()
                for source,prefix in sources:
                    shutil.copytree(source,ref/prefix,dirs_exist_ok=True)
                    paths.update(prefix+'/'+p.relative_to(source).as_posix() for p in source.rglob('*') if p.is_file())
                # The shared menu now hashes account/select dependencies too.
                # Prepare those original sources so reference and release use
                # the same dependency graph, while comparing the entire overlay.
                for name in [*versions.ACCOUNT_COMPONENTS,'account-entry.js','header-controls.css','header-controls.js','language-navigation.js','portada-atracciones.js','portada-atracciones.css']:
                    candidates=[ROOT/'backend/public/assets'/name,ROOT/'ensayos/v1/assets'/name]
                    source=next((p for p in candidates if p.is_file()),None)
                    if source:shutil.copyfile(source,ref/'public/assets'/name)
                subprocess.run([sys.executable,str(ROOT/'scripts/aplicar-identidad-neutral.py'),'--public',str(ref/'public')],check=True)
                shell=json.loads((ROOT/'design/portal/tokens.json').read_text())['colors']
                controls=ref/'public/assets/header-controls.css'
                controls.write_text(controls.read_text()+'\nhtml:root{--el-shell:'+shell['shell']+';--el-shell-border:'+shell['shell-border']+'}\n')
                subprocess.run([sys.executable,str(ROOT/'scripts/preparar-encabezado.py'),'--public',str(ref/'public')],check=True)
                subprocess.run(['php',str(ROOT/'scripts/aplicar-nomenclatura-lugares.php'),str(ref)],check=True)
                subprocess.run([sys.executable,str(ROOT/'scripts/ajustar-auditoria-publica.py'),'--public',str(ref/'public')],check=True)
                subprocess.run([sys.executable,str(ROOT/'scripts/versiones-medicion.py'),'--public',str(ref/'public')],check=True)
                for relative in paths:
                    expected=(ref/relative).read_bytes()
                    # Only the approved subscription URL changes in the coverage CTA;
                    # continue comparing every other byte of the integration overlay.
                    if (ref/relative).suffix in {'.html','.php','.js','.json','.xml'}:
                        expected=expected.replace(b'negocios-locales/planes/',b'negocios-locales/suscripciones/')
                    self.assertEqual(packaged.get(relative),hashlib.sha256(expected).hexdigest(),relative)
            self.assertNotIn('public/__preprod-account.php', packaged)
            self.assertIn('public/business/recover.php', packaged)
            self.assertNotIn('public/admin/buzon-local.php', packaged)
            self.assertFalse(any('preprod' in name.lower() for name in packaged), 'Local preprod tools/access must never enter production')
            self.assertFalse(any('cuentas-prueba' in name or 'test-accounts' in name or 'CUENTAS_PRUEBA' in name for name in packaged), 'Persistent fixture tooling and credentials must stay local')
            for file in Path(folder).rglob('*'):
                if file.is_file() and file.suffix in {'.php','.json','.sql'}:
                    self.assertNotIn('administrador@pruebas.invalid',file.read_text(errors='replace'),str(file))
            for file in Path(folder).rglob('*.php'):
                self.assertNotIn('/usr/local/lib/preprod-admin-access.php', file.read_text(), str(file))
                self.assertNotIn("$_SESSION['preprod_admin_open']", file.read_text(), str(file))
            self.assertIn('app/analytics/geoip/country.mmdb', packaged)
            self.assertIn('app/analytics/vendor/maxmind/LICENSE', packaged)
            self.assertFalse(any(p.startswith('private/') or p.endswith('/config.php') for p in packaged))


if __name__ == '__main__': unittest.main()
