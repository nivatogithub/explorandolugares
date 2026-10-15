import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

MODULE = Path(__file__).resolve().parents[1] / 'limpiar-paquete-publico.py'
spec = importlib.util.spec_from_file_location('package_cleaner', MODULE)
cleaner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(cleaner)


class PackageImageCleanupTests(unittest.TestCase):
    def test_keeps_runtime_and_dynamic_seed_images_and_removes_unreferenced_images(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            public = root / 'public'
            (public / 'assets/brand').mkdir(parents=True)
            (public / 'assets/destinos/88').mkdir(parents=True)
            (public / 'assets/brand/logo.webp').write_bytes(b'logo')
            (public / 'assets/destinos/88/draft-attraction.webp').write_bytes(b'draft')
            unused = public / 'assets/brand/old-mark.png'
            unused.write_bytes(b'old')
            (public / 'assets/admin.css').write_text(".brand{background:url('brand/logo.webp')}")
            catalog = root / 'catalog.json'
            catalog.write_text(json.dumps({'media': [{'path': 'assets/destinos/88/draft-attraction.webp'}]}))

            removed = cleaner.prune_unreferenced_image_assets(public, catalog)

            self.assertEqual(removed, ['assets/brand/old-mark.png'])
            self.assertTrue((public / 'assets/brand/logo.webp').is_file())
            self.assertTrue((public / 'assets/destinos/88/draft-attraction.webp').is_file())
            self.assertFalse(unused.exists())


if __name__ == '__main__':
    unittest.main()
