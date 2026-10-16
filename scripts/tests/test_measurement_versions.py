import hashlib,importlib.util,tempfile,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('measurement_versions',ROOT/'scripts/versiones-medicion.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class MeasurementVersionTests(unittest.TestCase):
 def test_new_measurement_invalidates_menu_and_every_page_loader(self):
  with tempfile.TemporaryDirectory() as temporary:
   public=Path(temporary);assets=public/'assets';(assets/'cobertura').mkdir(parents=True)
   script=assets/'cobertura/medicion.js';script.write_text('first')
   menu=assets/'menu-principal.js';menu.write_text("s.src='/assets/cobertura/medicion.js?v=1';document.querySelector('script[src*=\"menu-principal.js\"]');")
   page=public/'index.html';page.write_text('<header>Unchanged header/footer</header><script src="assets/menu-principal.js"></script><script src="/assets/cobertura/medicion.js?v=1"></script>')
   php=public/'destino.php';php.write_text("<script src=\"<?=u('/assets/menu-principal.js?v=old')?>\"></script>")
   m.stamp_public(public);old=page.read_text();m.stamp_public(public);self.assertEqual(old,page.read_text(),'idempotent')
   script.write_text('second');m.stamp_public(public);new=page.read_text();self.assertNotEqual(old,new)
   self.assertIn('<header>Unchanged header/footer</header>',new)
   self.assertIn('menu-principal.js?v='+m.digest(menu),new);self.assertIn('medicion.js?v='+m.digest(script),new)
   self.assertIn('menu-principal.js?v='+m.digest(menu),php.read_text())
   self.assertIn('script[src*="menu-principal.js"]',menu.read_text(),'selectors are not resource URLs')
if __name__=='__main__':unittest.main()
