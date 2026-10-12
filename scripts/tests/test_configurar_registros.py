import importlib.util
import tempfile
import unittest
from pathlib import Path

spec=importlib.util.spec_from_file_location('configurar',Path(__file__).resolve().parents[1]/'configurar-registros.py')
module=importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

class ConfigurationTests(unittest.TestCase):
 def test_private_file_is_exclusive_and_restricted(self):
  with tempfile.TemporaryDirectory() as folder:
   target=Path(folder)/'private/config.php'
   module.write_config(target, {'db_password':"'secret\n$test"})
   self.assertEqual(target.stat().st_mode & 0o777,0o600)
   self.assertNotIn('secret',target.read_text())
   before=target.read_bytes()
   with self.assertRaises(FileExistsError): module.write_config(target,{})
   self.assertEqual(target.read_bytes(),before)
 def test_reject_public_paths(self):
  with tempfile.TemporaryDirectory() as folder:
   for name in ['public_html','public','ensayos','sources']:
    with self.assertRaises(ValueError): module.write_config(Path(folder)/name/'private/config.php',{})
