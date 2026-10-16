"""Design contract: one generated palette, no dark/cold surface regressions, usable contrast."""
import hashlib,json,re,subprocess,sys,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
class PortalDesignTests(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  cls.tokens=json.loads((ROOT/'design/portal/tokens.json').read_text());cls.colors=cls.tokens['colors'];cls.css=(ROOT/'backend/public/assets/cobertura/cobertura.css').read_text();cls.page=(ROOT/'backend/public/cobertura/colombia/index.html').read_text()
 def test_generated_tokens_and_versions(self):
  subprocess.run([sys.executable,str(ROOT/'scripts/sincronizar-estilos-cobertura.py'),'--check'],check=True,capture_output=True)
 def test_warm_card_family(self):
  self.assertEqual(self.colors['card'],'#fff8ed');self.assertEqual(self.colors['panel'],'#fff0dc');self.assertNotEqual(self.colors['card'],self.colors['panel'])
  for selector in ['.country-stats article','.audience-metrics article','.audience-empty','.invite-options article']:
   pattern=re.escape('#contenido '+selector)+r'[^{}]*\{([^{}]+)\}'
   rules=re.findall(pattern,self.css)
   self.assertTrue(any('background: var(--el-card)' in rule for rule in rules),selector)
 def test_no_dark_or_cold_content_surfaces(self):
  for forbidden in ['#fafbfe','#e6edf9','#edf0f6','#8fc6ba','#e6eee3','#eef2ea','background: var(--el-text)','background:var(--ink)']:
   self.assertNotIn(forbidden,self.css)
  self.assertIn('#contenido .join {',self.css)
  self.assertIn('background: var(--el-panel); color: var(--el-text);',self.css)
 def test_token_loading_order(self):
  self.assertLess(self.page.index('assets/style.css?'),self.page.index('assets/cobertura/portal-tokens.css?'))
  self.assertLess(self.page.index('assets/cobertura/portal-tokens.css?'),self.page.index('assets/cobertura/cobertura.css?'))
 def test_shared_shell_not_redefined(self):
  self.assertNotRegex(self.css,r'(?:^|})\s*(?:body|header|footer|\.brand)\s*\{')
  self.assertIn('class="coverage-intro"',self.page);self.assertNotIn('class="intro"',self.page)
 def test_contrast_of_text_and_action_pairs(self):
  def lum(h):
   values=[int(h[i:i+2],16)/255 for i in [1,3,5]];linear=[v/12.92 if v<=.04045 else ((v+.055)/1.055)**2.4 for v in values]
   return sum(v*w for v,w in zip(linear,[.2126,.7152,.0722]))
  for a,b in [('action','on-action'),('text','card'),('text','panel'),('muted','card'),('muted','panel'),('action-hover','field')]:
   x,y=sorted([lum(self.colors[a]),lum(self.colors[b])]);self.assertGreaterEqual((y+.05)/(x+.05),4.5,(a,b))
 def test_instructions_link_to_guide(self):
  self.assertIn('docs/GUIA_ESTILO_PORTAL.md',(ROOT/'AGENTS.md').read_text())
if __name__=='__main__':unittest.main()
