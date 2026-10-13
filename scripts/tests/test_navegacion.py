from html.parser import HTMLParser
from urllib.parse import urlsplit
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
            if attrs.get('aria-label') in {'Principal','Main navigation','Navegação principal'}: self.nav_depth+=1
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
            parts=page.relative_to(self.public).parts
            expected='About the portal' if 'en' in parts else ('Conheça o portal' if 'pt-br' in parts else 'Conoce el portal')
            self.assertEqual(main.count(expected),1,page)
            trip='Plan your visit' if 'en' in parts else ('Planeje sua visita' if 'pt-br' in parts else 'Planea tu visita')
            self.assertEqual(main.count(trip),1,page)
    def test_internal_links_use_internal_or_anchor_arrows(self):
        class PublicLinks(HTMLParser):
            def __init__(self):
                super().__init__(); self.current=None; self.items=[]
            def handle_starttag(self, tag, attrs):
                if tag == 'a': self.current = {'href': dict(attrs).get('href',''), 'text': ''}
            def handle_data(self, data):
                if self.current: self.current['text'] += data
            def handle_endtag(self, tag):
                if tag == 'a' and self.current:
                    self.items.append(self.current); self.current = None
        for page in self.public.rglob('*.html'):
            parser=PublicLinks(); parser.feed(page.read_text())
            for link in parser.items:
                href=link['href']; label=link['text'].strip(); parsed=urlsplit(href)
                if parsed.scheme or parsed.netloc: continue
                self.assertNotIn('↗', label, f'{page}: {href} {label}')
                if href.startswith('#') and '↓' in label:
                    self.assertNotIn('→', label, f'{page}: {href} {label}')

    def test_brand_uses_a_single_capitalised_name(self):
        for page in self.public.rglob('*.html'):
            source=page.read_text()
            if '<header>' not in source: continue
            self.assertIn('<span>Explorando <b>Lugares</b></span>', source, page)
            self.assertNotIn('<span>explorando<b>lugares</b></span>', source, page)

    def test_portal_page_does_not_repeat_its_own_pilot_banner(self):
        for relative in ('etapas/index.html', 'en/etapas/index.html'):
            source=(self.public/relative).read_text()
            self.assertNotIn('<!-- etapas-piloto -->', source, relative)

    def test_business_subpages_keep_primary_and_secondary_state(self):
        for page in self.public.rglob('negocios-locales/*/index.html'):
            if page.parent.name=='negocios-locales': continue
            parsed=links(page)
            primary=[item for item in parsed if item['main'] and item['text'].strip() in {'Registrar mi negocio','Register my business','Cadastrar minha empresa'}]
            self.assertEqual(len(primary),1,page)
            self.assertTrue(primary[0]['attrs'].get('href','').endswith('interes/index.html') or primary[0]['attrs'].get('href','')=='index.html',page)
            secondary=[item for item in parsed if item['business'] and item['attrs'].get('aria-current')=='page']
            self.assertEqual(len(secondary),1,page)

if __name__=='__main__': unittest.main()
