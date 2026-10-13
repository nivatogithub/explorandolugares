from html.parser import HTMLParser
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

ROOT=Path(__file__).resolve().parents[2]

class Links(HTMLParser):
    def __init__(self):
        super().__init__(); self.nav_depth=0; self.business_depth=0; self.links=[]; self.current=None
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs)
        if tag=='nav':
            if attrs.get('aria-label') in {'Principal','Main navigation'}: self.nav_depth+=1
            if attrs.get('class')=='business-nav': self.business_depth+=1
        if tag=='a' and (self.nav_depth or self.business_depth):
            self.current={'attrs':attrs,'text':'','main':bool(self.nav_depth),'business':bool(self.business_depth)}
    def handle_data(self,data):
        if self.current:self.current['text']+=data
    def handle_endtag(self,tag):
        if tag=='a' and self.current:
            self.links.append(self.current); self.current=None
        if tag=='nav':
            if self.nav_depth:self.nav_depth-=1
            elif self.business_depth:self.business_depth-=1

def links(page):
    parser=Links(); parser.feed(page.read_text()); return parser.links

class NavigationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp=tempfile.TemporaryDirectory()
        subprocess.run([sys.executable,str(ROOT/'scripts/preparar-hosting.py'),'--output',cls.temp.name],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
        cls.public=Path(cls.temp.name)/'public'
    @classmethod
    def tearDownClass(cls): cls.temp.cleanup()
    def test_main_navigation_has_no_duplicate_labels(self):
        for page in self.public.rglob('*.html'):
            main=[item['text'].strip() for item in links(page) if item['main']]
            self.assertEqual(len(main),len(set(main)),page)
            if not main: continue
            expected='Our progress' if 'en' in page.relative_to(self.public).parts else 'Nuestro avance'
            self.assertEqual(main.count(expected),1,page)
    def test_business_subpages_keep_primary_and_secondary_state(self):
        for page in self.public.rglob('negocios-locales/*/index.html'):
            if page.parent.name=='negocios-locales': continue
            parsed=links(page)
            primary=[item for item in parsed if item['main'] and item['text'].strip() in {'Negocios locales ↗','Local businesses ↗'}]
            self.assertEqual(len(primary),1,page)
            self.assertIn(primary[0]['attrs'].get('aria-current'),{'page','location'},page)
            secondary=[item for item in parsed if item['business'] and item['attrs'].get('aria-current')=='page']
            self.assertEqual(len(secondary),1,page)

if __name__=='__main__': unittest.main()
