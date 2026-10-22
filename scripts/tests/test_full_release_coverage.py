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
            # Coverage data and its request controllers remain unchanged. Shell
            # markup/CSS are intentionally shared now, and asset query hashes
            # change with content; neither is a cartography/data mutation.
            import re
            def without_versions(value):
                return re.sub(rb"\?v=[A-Za-z0-9_-]+", b"", value)
            for source in (ROOT/'backend/public/assets/cobertura').rglob('*'):
                if not source.is_file(): continue
                relative='public/assets/cobertura/'+source.relative_to(ROOT/'backend/public/assets/cobertura').as_posix()
                actual=Path(folder)/relative
                self.assertTrue(actual.is_file(), relative)
                if source.suffix in {'.geojson','.json'}:
                    self.assertEqual(actual.read_bytes(),source.read_bytes(),relative)
                elif source.suffix=='.js':
                    self.assertEqual(without_versions(actual.read_bytes()),without_versions(source.read_bytes()),relative)
            for source in (ROOT/'backend/integration-medicion/public/api').rglob('*.php'):
                relative='public/api/'+source.relative_to(ROOT/'backend/integration-medicion/public/api').as_posix()
                self.assertEqual((Path(folder)/relative).read_bytes(),source.read_bytes(),relative)
            coverage=(Path(folder)/'public/cobertura/colombia/index.html').read_text()
            self.assertEqual(coverage.count('<header'),1)
            self.assertIn('cobertura-meta.js',coverage)
            self.assertIn('id="panel-cobertura"',coverage)
            self.assertIn('id="panel-visitantes"',coverage)
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
