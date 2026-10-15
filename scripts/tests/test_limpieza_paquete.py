import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

MODULE = Path(__file__).resolve().parents[1] / 'limpiar-paquete-publico.py'
spec = importlib.util.spec_from_file_location('package_cleaner', MODULE)
cleaner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(cleaner)


class PackageAssetCleanupTests(unittest.TestCase):
    def test_keeps_runtime_and_dynamic_seed_assets_and_licenses(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            public = root / 'public'
            (public / 'assets/brand').mkdir(parents=True)
            (public / 'assets/destinos/88').mkdir(parents=True)
            (public / 'assets/brand/logo.webp').write_bytes(b'logo')
            (public / 'assets/destinos/88/draft-attraction.webp').write_bytes(b'draft')
            unused = public / 'assets/brand/old-mark.png'
            unused.write_bytes(b'old')
            old_credits = public / 'assets/destinos/creditos.json'
            old_credits.write_text('{}')
            old_script = public / 'assets/navegacion.js'
            old_script.write_text('// no runtime references')
            license_file = public / 'assets/leaflet-LICENSE.txt'
            license_file.write_text('License terms')
            (public / 'assets/admin.css').write_text(".brand{background:url('brand/logo.webp')}")
            (public / 'index.html').write_text('<link rel="stylesheet" href="assets/admin.css">')
            catalog = root / 'catalog.json'
            catalog.write_text(json.dumps({'media': [{'path': 'assets/destinos/88/draft-attraction.webp'}]}))
            app = root / 'app'
            app.mkdir()
            (app / 'image-reader.php').write_text("<?php $path = 'assets/brand/logo.webp';")

            removed = cleaner.prune_unreferenced_assets(public, catalog, app)

            self.assertEqual(set(removed), {'assets/destinos/creditos.json', 'assets/navegacion.js', 'assets/brand/old-mark.png'})
            self.assertTrue((public / 'assets/brand/logo.webp').is_file())
            self.assertTrue((public / 'assets/destinos/88/draft-attraction.webp').is_file())
            self.assertTrue(license_file.is_file())
            self.assertFalse(unused.exists())
            self.assertFalse(old_credits.exists())
            self.assertFalse(old_script.exists())


if __name__ == '__main__':
    unittest.main()
