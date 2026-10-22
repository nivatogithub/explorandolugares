import hashlib
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('retained_legacy', ROOT/'scripts/retained_legacy.py')
legacy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(legacy)


class RetainedLegacyTests(unittest.TestCase):
    def test_only_identical_unreferenced_bytes_are_retained(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            public = root/'public'
            (public/'assets').mkdir(parents=True)
            source = public/'assets/old.js'
            source.write_bytes(b'old bytes')
            metadata = root/'backend/app/presentation/retained-legacy.json'
            metadata.parent.mkdir(parents=True)
            metadata.write_text(json.dumps({'files': [{'relative': 'public/assets/old.js', 'sha256': hashlib.sha256(source.read_bytes()).hexdigest()}]}))
            data = legacy.capture(root, public)
            source.unlink()
            page = public/'index.html'
            page.write_text('<script src="/assets/old.js"></script>')
            with self.assertRaises(ValueError):
                legacy.restore(public, data)
            page.write_text('<main>new implementation</main>')
            legacy.restore(public, data)
            self.assertEqual(source.read_bytes(), b'old bytes')
            source.write_bytes(b'unreviewed change')
            with self.assertRaises(ValueError):
                legacy.capture(root, public)
