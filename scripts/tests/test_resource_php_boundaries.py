import importlib.util,tempfile,unittest
from pathlib import Path
spec=importlib.util.spec_from_file_location('resource_versions',Path(__file__).resolve().parents[1]/'versionar-recursos.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
class PHPResourceBoundaries(unittest.TestCase):
 def test_dynamic_version_and_filesystem_are_unchanged(self):
  with tempfile.TemporaryDirectory() as directory:
   root=Path(directory);(root/'assets').mkdir();(root/'assets/x.css').write_text('body{}')
   source='''<link href="/assets/x.css"><link href="/assets/x.css?v=<?=substr(hash_file('sha256',__DIR__.'/assets/x.css'),0,12)?>">'''
   (root/'page.php').write_text(source);module.apply(root);out=(root/'page.php').read_text()
   self.assertIn("__DIR__.'/assets/x.css'",out);self.assertIn('/assets/x.css?v=<?=substr',out);self.assertEqual(out.count('?v='),2)
if __name__=='__main__':unittest.main()
