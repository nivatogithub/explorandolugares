from html.parser import HTMLParser
from urllib.parse import urlsplit, urljoin
from pathlib import Path
import importlib.util
import tempfile
import unittest

ROOT=Path(__file__).resolve().parents[2]
DEPLOY=ROOT/'scripts/desplegar-sftp.py'
SPEC=importlib.util.spec_from_file_location('navigation_deploy',DEPLOY);deploy=importlib.util.module_from_spec(SPEC);SPEC.loader.exec_module(deploy)

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
        deploy.build(Path(cls.temp.name))
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
            primary=[item for item in parsed if item['main'] and item['text'].strip() in {'Negocios locales','Local businesses','Negócios locais'}]
            self.assertEqual(len(primary),1,page)
            target=urlsplit(urljoin('https://example.invalid/v1/'+page.relative_to(self.public).as_posix(),primary[0]['attrs'].get('href',''))).path
            self.assertTrue(target.rstrip('/').endswith('/negocios-locales'),(page,target))
            secondary=[item for item in parsed if item['business'] and item['attrs'].get('aria-current')=='page']
            self.assertEqual(len(secondary),1,page)
    def test_information_and_account_creation_are_distinct_and_gate_aware(self):
        for relative,label,create_text in [
            ('negocios-locales/index.html','Negocios locales','Crear cuenta empresarial'),
            ('en/negocios-locales/index.html','Local businesses','Create a business account'),
            ('pt-br/negocios-locales/index.html','Negócios locais','Criar conta empresarial'),
        ]:
            source=(self.public/relative).read_text()
            self.assertIn(label,source,relative)
            self.assertIn('Solicitar información' if relative.startswith('negocios-locales/') else ('Request information' if relative.startswith('en/') else 'Solicitar informações'),source,relative)
            self.assertIn('BUSINESS_ACCOUNT_ACTION',source,relative)
            self.assertNotIn(create_text,source,relative)
    def test_admin_uses_its_own_sidebar_stylesheet(self):
        admin=(self.public/'admin/index.php').read_text()
        styles=(self.public/'assets/admin.css').read_text()
        self.assertIn('admin.css?v=admin-layout-',admin)
        self.assertIn('.admin-sidebar nav section{display:block!important',styles)
        self.assertIn('.admin-sidebar nav a,.admin-nav-disabled{display:flex!important',styles)
        rewrite=(self.public/'.htaccess').read_text()
        self.assertIn('^negocios-locales/?$ business/index.php?lang=es',rewrite)

if __name__=='__main__': unittest.main()
