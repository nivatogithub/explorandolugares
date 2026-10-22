import unittest,sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from css_components import split
class CSSOwnership(unittest.TestCase):
 def test_mixed_selectors_keep_other_components(self):
  keep,owned=split('header,.card{color:red}main{color:blue}')
  self.assertIn('.card{color:red}',keep);self.assertIn('main{color:blue}',keep);self.assertIn('header{color:red}',owned)
 def test_strings_comments_and_nested_media(self):
  keep,owned=split('/* { } */ @media(max-width:600px){header .brand{content:"}"}.card{color:red}}@font-face{src:url(x.woff)}')
  self.assertIn('.card{color:red}',keep);self.assertIn('@font-face',keep);self.assertIn('content:"}"',owned)
if __name__=='__main__':unittest.main()
