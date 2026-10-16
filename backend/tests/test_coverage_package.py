import importlib.util,unittest
from pathlib import Path
root=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('coverage_package',root/'scripts/preparar-cobertura.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class PackageTests(unittest.TestCase):
 def setUp(self):self.config={'site':'explorandolugares','host':'example.test','remote_base':'/home/test/explorandolugares','public_directory':'public','public_root':'/home/test/public_html/explorandolugares.com'}
 def test_private_files_stay_private(self):self.assertEqual(m.target(self.config,'app/CoverageService.php'),'/home/test/explorandolugares/app/CoverageService.php')
 def test_public_files_use_domain_root(self):self.assertEqual(m.target(self.config,'public/api/cobertura.php'),'/home/test/public_html/explorandolugares.com/api/cobertura.php')
 def test_escape_rejected(self):
  for path in ['../config.php','private/config.php','public/../private/config.php','/public/a']:
   with self.assertRaises(ValueError):m.target(self.config,path)
 def test_scope_and_activation_order(self):
  pairs=m.sources();self.assertGreaterEqual(len(pairs),64);self.assertEqual(pairs[-1][1],'public/cobertura/colombia/index.html')
  self.assertFalse(any('config.php' in s for s,d in pairs));self.assertEqual([d for s,d in pairs if d.startswith('public/admin/')],['public/admin/medicion.php'])
  self.assertIn('public/assets/cobertura/medicion.js',[d for s,d in pairs])
  for prefix in ['', 'en/','pt-br/']:
   relative='public/'+prefix+'destinos/index.html';self.assertIn(relative,[d for s,d in pairs])
   source=next(s for s,d in pairs if d==relative);html=(root/source).read_text()
   self.assertEqual(html.count('href="/cobertura/colombia/"'),1);self.assertEqual(html.count('menu-principal.js'),1)
  self.assertFalse(any(d in ['public/index.html','public/.htaccess','app/bootstrap.php'] for s,d in pairs))
 def test_wrong_manifest_rejected(self):
  with self.assertRaises(ValueError):m.manifest({'site':'other'},self.config)
if __name__=='__main__':unittest.main()
