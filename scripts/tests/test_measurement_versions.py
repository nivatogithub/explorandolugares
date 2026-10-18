import hashlib,importlib.util,tempfile,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('measurement_versions',ROOT/'scripts/versiones-medicion.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class MeasurementVersionTests(unittest.TestCase):
 def test_account_dependencies_invalidate_loader_and_hidden_selector_fix(self):
  with tempfile.TemporaryDirectory() as temporary:
   public=Path(temporary);assets=public/'assets';(assets/'cobertura').mkdir(parents=True)
   (assets/'cobertura/medicion.css').write_text('body{}');(assets/'cobertura/medicion.js').write_text('measurement')
   css=assets/'account-page.css';css.write_text('dialog{padding:16px}')
   residence=assets/'account-residence.js';residence.write_text('residence')
   selector=assets/'select-flotante.js';selector.write_text('hidden controls stay hidden')
   entry=assets/'account-entry.js';entry.write_text("style.href='/assets/account-page.css';script.src='/assets/account-residence.js';")
   menu=assets/'menu-principal.js';menu.write_text("s.src='/assets/account-entry.js?v=old';")
   page=public/'index.html';page.write_text('<script src="assets/menu-principal.js"></script><script src="assets/select-flotante.js"></script>')
   m.stamp_public(public);old=page.read_text();old_menu=m.digest(menu)
   self.assertIn('select-flotante.js?v='+m.digest(selector),old)
   self.assertIn('account-entry.js?v='+m.digest(entry),menu.read_text())
   self.assertIn('account-residence.js?v='+m.digest(residence),entry.read_text())
   m.stamp_public(public);self.assertEqual(old,page.read_text(),'component stamping is idempotent')
   css.write_text('dialog{padding:12px}');m.stamp_public(public)
   self.assertNotEqual(old_menu,m.digest(menu),'CSS changes invalidate the account loader and menu chain')
   self.assertIn('account-page.css?v='+m.digest(css),entry.read_text())
   self.assertIn('menu-principal.js?v='+m.digest(menu),page.read_text())
 def test_new_measurement_invalidates_menu_and_every_page_loader(self):
  with tempfile.TemporaryDirectory() as temporary:
   public=Path(temporary);assets=public/'assets';(assets/'cobertura').mkdir(parents=True)
   (assets/'cobertura/medicion.css').write_text('body{}')
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
