"""The full release must retain the already reviewed coverage integration."""
import hashlib
import importlib.util
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('full_coverage_deploy', ROOT/'scripts/desplegar-sftp.py')
deploy = importlib.util.module_from_spec(spec); spec.loader.exec_module(deploy)


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
                        self.assertEqual(packaged.get(relative), hashlib.sha256(source.read_bytes()).hexdigest(), relative)
            self.assertIn('app/analytics/geoip/country.mmdb', packaged)
            self.assertIn('app/analytics/vendor/maxmind/LICENSE', packaged)
            self.assertFalse(any(p.startswith('private/') or p.endswith('/config.php') for p in packaged))


if __name__ == '__main__': unittest.main()
