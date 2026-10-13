from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlsplit, unquote
import subprocess
import sys
import tempfile
import unittest

ROOT=Path(__file__).resolve().parents[2]
SKIP_SCHEMES={'http','https','mailto','tel','javascript','data'}

class References(HTMLParser):
    def __init__(self): super().__init__(); self.references=[]
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs)
        for name in ('href','src','action'):
            value=attrs.get(name)
            if value: self.references.append((tag,name,value))

class PublicLinkTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp=tempfile.TemporaryDirectory()
        subprocess.run([sys.executable,str(ROOT/'scripts/preparar-hosting.py'),'--output',cls.temp.name],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
        cls.public=Path(cls.temp.name)/'public'
    @classmethod
    def tearDownClass(cls): cls.temp.cleanup()
    def test_all_internal_navigation_and_resources_resolve(self):
        broken=[]
        for page in self.public.rglob('*.html'):
            parser=References(); parser.feed(page.read_text())
            for tag,attribute,value in parser.references:
                url=urlsplit(value)
                if not url.path or url.scheme in SKIP_SCHEMES or url.netloc: continue
                target=(page.parent/unquote(url.path)).resolve()
                if not str(target).startswith(str(self.public.resolve())):
                    broken.append(f'{page.relative_to(self.public)}: {tag}[{attribute}] escapes public root: {value}')
                elif not target.exists():
                    broken.append(f'{page.relative_to(self.public)}: {tag}[{attribute}] missing: {value}')
        self.assertEqual(broken,[], '\n'.join(broken))
    def test_public_forms_have_a_real_local_endpoint(self):
        forms=[]
        for page in self.public.rglob('*.html'):
            parser=References(); parser.feed(page.read_text())
            for tag,attribute,value in parser.references:
                if tag=='form' and attribute=='action': forms.append((page,value))
        self.assertTrue(forms)
        for page,value in forms:
            target=(page.parent/urlsplit(value).path).resolve()
            self.assertTrue(target.is_file(),f'{page.relative_to(self.public)}: form endpoint missing: {value}')
    def test_image_credit_catalogue_is_available_in_both_languages(self):
        spanish=(self.public/'creditos-fotograficos/index.html').read_text()
        english=(self.public/'en/creditos-fotograficos/index.html').read_text()
        self.assertGreaterEqual(spanish.count('class="credit-card"'),70)
        self.assertGreaterEqual(english.count('class="credit-card"'),70)
        self.assertIn('Créditos de imágenes',spanish)
        self.assertIn('Image credits',english)

if __name__=='__main__': unittest.main()
