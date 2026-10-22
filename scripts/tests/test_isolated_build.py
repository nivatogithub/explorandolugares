import importlib.util
from pathlib import Path
import tempfile
import unittest
ROOT=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('isolated_build',ROOT/'scripts/preparar-hosting.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
class BuildBoundary(unittest.TestCase):
 def test_nonempty_output_rejected_without_removing_content(self):
  with tempfile.TemporaryDirectory() as directory:
   p=Path(directory);(p/'keep.txt').write_text('keep')
   with self.assertRaises(ValueError):module.build(p)
   self.assertEqual((p/'keep.txt').read_text(),'keep')
 def test_source_directory_rejected(self):
  with self.assertRaises(ValueError):module.build(ROOT/'backend')
if __name__=='__main__':unittest.main()
