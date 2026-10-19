import importlib.util,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('header',ROOT/'scripts/preparar-encabezado.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
class HeaderFirstRender(unittest.TestCase):
 def test_static_links_menu_and_idempotency(self):
  source=(ROOT/'ensayos/v1/en/index.html').read_text();result=module.render(source)
  self.assertIn('header-language-bar',result);self.assertIn('>Log in</a>',result);self.assertIn('>Sign up</a>',result)
  self.assertIn('aria-controls="menu-principal"',result);self.assertIn('id="menu-principal"',result)
  self.assertIn('>We’re growing</a>',result);self.assertEqual(result,module.render(result))
 def test_php_language_urls_survive(self):
  source=(ROOT/'backend/integration-medicion/public/destino.php').read_text();result=module.render(source)
  for url in ['<?=e($langEsUrl)?>','<?=e($langEnUrl)?>','<?=e($langPtUrl)?>']:self.assertIn(url,result)
  self.assertIn('header-language-bar',result);self.assertIn('PT-BR</span></a>',result)
 def test_footer_is_ready_before_javascript_and_idempotent(self):
  source=(ROOT/'ensayos/v1/en/index.html').read_text();result=module.render_footer(source)
  footer=result.split('<footer',1)[1]
  self.assertIn('el-measurement-preferences',footer);self.assertIn('Privacy and statistics',footer)
  self.assertIn('Contact and help',footer);self.assertEqual(result,module.render_footer(result))
if __name__=='__main__':unittest.main()
