"""The release must have exactly one destination implementation."""
from pathlib import Path
import importlib.util
import unittest


ROOT=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('public_inputs',ROOT/'scripts/public_inputs.py')
public_inputs=importlib.util.module_from_spec(spec)
spec.loader.exec_module(public_inputs)


class DynamicDestinationBoundaryTests(unittest.TestCase):
    def test_static_destination_detail_pages_are_not_public_inputs(self):
        self.assertTrue(public_inputs.is_retired_static_destination_page('destinos/jerico/index.html'))
        self.assertTrue(public_inputs.is_retired_static_destination_page('en/destinos/jerico/index.html'))
        self.assertTrue(public_inputs.is_retired_static_destination_page('pt-br/destinos/jerico/alojamiento/index.html'))
        self.assertTrue(public_inputs.is_retired_static_destination_page('destinos/index.html'))
        self.assertFalse(public_inputs.is_retired_static_destination_page('business/register.php'))

    def test_destination_directory_is_dynamic_too(self):
        source=(ROOT/'scripts/_construir-hosting.py').read_text()
        self.assertIn('destinos.php',source)
        self.assertIn("rglob('*.html')",source)

    def test_release_builder_does_not_run_the_retired_generator(self):
        source=(ROOT/'scripts/_construir-hosting.py').read_text()
        self.assertNotIn("scripts/generar-destinos.py')],check=True)",source)
        self.assertNotIn("scripts/generar-fichas.py')],check=True)",source)
        self.assertIn('assert_dynamic_destination_boundary',source)

    def test_conflicting_public_assets_are_rejected(self):
        source=(ROOT/'scripts/public_inputs.py').read_text()
        self.assertIn('La ruta pública tiene más de un productor',source)
        self.assertNotIn('DIVERGENT_AUTHORITIES',source)


if __name__=='__main__':
    unittest.main()
