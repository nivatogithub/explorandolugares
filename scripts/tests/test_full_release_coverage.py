"""The full release must retain the already reviewed coverage integration."""
import hashlib
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
                subprocess.run([sys.executable,str(ROOT/'scripts/aplicar-identidad-neutral.py'),'--public',str(ref/'public')],check=True)
                subprocess.run(['php',str(ROOT/'scripts/aplicar-nomenclatura-lugares.php'),str(ref)],check=True)
                subprocess.run([sys.executable,str(ROOT/'scripts/versiones-medicion.py'),'--public',str(ref/'public')],check=True)
                for relative in paths:
                    self.assertEqual(packaged.get(relative),hashlib.sha256((ref/relative).read_bytes()).hexdigest(),relative)
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
