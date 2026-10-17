"""The full release must retain the already reviewed coverage integration."""
import hashlib
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
            for root, prefix in [(ROOT/'backend/integration-medicion/public', 'public/'),
                                 (ROOT/'backend/public/cobertura', 'public/cobertura/'),
                                 (ROOT/'backend/public/assets/cobertura', 'public/assets/cobertura/')]:
                for source in root.rglob('*'):
                    if source.is_file():
                        relative = prefix + source.relative_to(root).as_posix()
                        expected=source.read_bytes()
                        # Release URLs are intentionally stamped after the integration overlay.
                        if source.suffix in {'.php','.html'} or source.name in {'menu-principal.js','medicion.js'}:
                            text=expected.decode()
                            for asset in ['medicion.css','medicion.js','menu-principal.js']:
                                location=Path(folder)/'public/assets'/('cobertura' if asset!='menu-principal.js' else '')/asset
                                text=versions.stamp(text,asset,versions.digest(location))
                            expected=text.encode()
                        self.assertEqual(packaged.get(relative), hashlib.sha256(expected).hexdigest(), relative)
            self.assertFalse(any('preprod' in name.lower() for name in packaged), 'Local preprod tools/access must never enter production')
            for file in Path(folder).rglob('*.php'):
                self.assertNotIn('/usr/local/lib/preprod-admin-access.php', file.read_text(), str(file))
                self.assertNotIn("$_SESSION['preprod_admin_open']", file.read_text(), str(file))
            self.assertIn('app/analytics/geoip/country.mmdb', packaged)
            self.assertIn('app/analytics/vendor/maxmind/LICENSE', packaged)
            self.assertFalse(any(p.startswith('private/') or p.endswith('/config.php') for p in packaged))


if __name__ == '__main__': unittest.main()
