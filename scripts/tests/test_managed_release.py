import importlib.util,io,tempfile,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('preprod_control',ROOT/'preprod/control.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class ManagedRelease(unittest.TestCase):
 def test_failure_keeps_current_code_and_persistent_data(self):
  with tempfile.TemporaryDirectory() as directory:
   m.STATE=Path(directory);release=m.STATE/'release';(release/'public').mkdir(parents=True);(release/'app').mkdir();(release/'public/index.html').write_text('old');(release/'app/bootstrap.php').write_text('old');(m.STATE/'private').mkdir();(m.STATE/'private/config.php').write_text('persistent')
   def fail(*args,**kwargs):raise RuntimeError('build failed')
   m.run=fail
   with self.assertRaises(RuntimeError):m.prepare_managed_release(release,io.StringIO())
   self.assertEqual((release/'public/index.html').read_text(),'old');self.assertEqual((m.STATE/'private/config.php').read_text(),'persistent')
 def test_private_data_inside_release_blocks_replacement(self):
  with tempfile.TemporaryDirectory() as directory:
   m.STATE=Path(directory);release=m.STATE/'release'
   for name in ['public','app','private']:(release/name).mkdir(parents=True,exist_ok=True)
   (release/'public/index.html').write_text('old');(release/'app/bootstrap.php').write_text('old');(release/'private/config.php').write_text('do not touch')
   with self.assertRaises(ValueError):m.prepare_managed_release(release,io.StringIO())
   self.assertEqual((release/'private/config.php').read_text(),'do not touch')
if __name__=='__main__':unittest.main()
