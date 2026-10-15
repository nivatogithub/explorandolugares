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
        dynamic_slugs={item['slug'] for item in __import__('json').loads((ROOT/'content/destinos-dinamicos.json').read_text())['destinations']}
        for page in self.public.rglob('*.html'):
            parser=References(); parser.feed(page.read_text())
            for tag,attribute,value in parser.references:
                url=urlsplit(value)
                if not url.path or url.scheme in SKIP_SCHEMES or url.netloc: continue
                target=(page.parent/unquote(url.path)).resolve()
                if not str(target).startswith(str(self.public.resolve())):
                    broken.append(f'{page.relative_to(self.public)}: {tag}[{attribute}] escapes public root: {value}')
                elif not target.exists():
                    relative=target.relative_to(self.public.resolve()).as_posix()
                    route=__import__('re').fullmatch(r'(?:en/|pt-br/)?destinos/([a-z0-9-]+)/index\.html',relative)
                    claim_route=relative.rstrip('/')=='reclamar-negocio' and (self.public/'reclamar-negocio.php').is_file()
                    business_routes={'negocios/registro':'register.php','negocios/acceso':'login.php','negocios/portal':'dashboard.php','negocios/salir':'logout.php'}
                    business_route=relative.rstrip('/') in business_routes and (self.public/'business'/business_routes[relative.rstrip('/')]).is_file()
                    if not claim_route and not business_route and (not route or route.group(1) not in dynamic_slugs):
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
    def test_trip_planning_page_is_public_in_both_languages(self):
        for relative, title in (("planea-tu-visita/index.html", "Planea tu visita"), ("en/planea-tu-visita/index.html", "Plan your visit")):
            source=(self.public/relative).read_text()
            self.assertIn(title, source, relative)
            self.assertIn('index,follow', source, relative)
        sitemap=(self.public/'sitemap.xml').read_text()
        self.assertIn('https://explorandolugares.com/planea-tu-visita/', sitemap)
        self.assertIn('https://explorandolugares.com/en/planea-tu-visita/', sitemap)
        destination_urls=[line for line in sitemap.splitlines() if '/destinos/' in line and '/destinos/</loc>' not in line]
        self.assertTrue(destination_urls)
        self.assertTrue(all('/destinos/jerico/' in line for line in destination_urls), destination_urls)
        directory=(self.public/'destinos/index.html').read_text()
        self.assertEqual(directory.count('class="destination-card"'),1)
        seed=__import__('json').loads((ROOT/'content/destinos-dinamicos.json').read_text())
        drafts=[item for item in seed['destinations'] if item['slug']!='jerico']
        self.assertTrue(all(item['publication_status']=='draft' for item in drafts))
        self.assertTrue(all(not (self.public/f'{locale}destinos/{item["slug"]}/index.html').exists()
                            for locale in ('','en/','pt-br/') for item in drafts))
        self.assertTrue((self.public/'.htaccess').is_file())

    def test_business_registration_is_grouped_and_preserves_submission_fields(self):
        expected = {
            'negocios-locales/interes/index.html': ('Tu negocio', 'Cómo contactarte', 'Confirma tu solicitud', 'Enviar solicitud'),
            'en/negocios-locales/interes/index.html': ('Your business', 'How to contact you', 'Confirm your request', 'Send request'),
        }
        required_fields = ('name="name"', 'name="business"', 'name="category"', 'name="municipality"', 'name="email"', 'name="phone_number"', 'name="understanding"', 'name="consent"')
        for relative, labels in expected.items():
            source=(self.public/relative).read_text()
            self.assertGreaterEqual(source.count('class="registration-group'), 3, relative)
            for label in labels: self.assertIn(label, source, relative)
            for field in required_fields: self.assertIn(field, source, relative)
        registration_script=(self.public/'assets/registro.js').read_text()
        self.assertIn("window.location.assign('../../index.html')", registration_script)

    def test_public_image_credit_catalogue_is_removed_but_internal_provenance_remains(self):
        for locale in ('','en/','pt-br/'):
            self.assertFalse((self.public/f'{locale}creditos-fotograficos/index.html').exists())
        for suffix in ('.html','.php'):
            for page in self.public.rglob('*'+suffix):
                self.assertNotIn('creditos-fotograficos/index.html',page.read_text(),str(page))
        self.assertNotIn('creditos-fotograficos', (self.public/'sitemap.xml').read_text())
        catalog=(Path(self.temp.name)/'app/content/destinos-dinamicos.json').read_text()
        self.assertIn('source_url',catalog)
        self.assertIn('license_name',catalog)

    def test_dynamic_destination_runtime_is_included_in_local_package(self):
        self.assertTrue((self.public/'destino.php').is_file())
        self.assertTrue((self.public/'.htaccess').is_file())
        self.assertTrue((self.public/'admin/paquete-destino.php').is_file())
        self.assertTrue((self.public/'admin/paquete-destino-imagen.php').is_file())
        self.assertTrue((self.public/'admin/negocios.php').is_file())
        self.assertTrue((self.public/'admin/suscripciones.php').is_file())
        self.assertTrue((Path(self.temp.name)/'app/BusinessSubscriptionAdminService.php').is_file())
        self.assertTrue((Path(self.temp.name)/'app/BusinessPublicImporter.php').is_file())
        self.assertTrue((self.public/'plantillas/paquete-destino-v2.zip').is_file())
        catalog=__import__('json').loads((Path(self.temp.name)/'app/content/destinos-dinamicos.json').read_text())
        for item in catalog['media']:
            self.assertTrue((self.public/item['path']).is_file(),item['path'])
        for locale in ('','en/','pt-br/'):
            self.assertFalse((self.public/f'{locale}destinos/jerico/index.html').exists())
        self.assertTrue((self.public/'assets/destinos/1/parque-principal-ilustrado.webp').is_file())
        for obsolete in ('casa-santa-laura-ilustrada.webp','cerro-de-las-nubes-sendero-ilustrado.webp',
                         'chorros-quebradona-ilustrado.webp','cristo-cerro-el-salvador-ilustrado.webp',
                         'monumento-santa-laura-ilustrado.webp','santuario-santa-laura-ilustrado.webp'):
            self.assertFalse((self.public/'assets/destinos/1'/obsolete).exists(),obsolete)
        self.assertFalse((self.public/'assets/jerico.webp').exists())
        self.assertFalse((self.public/'assets/jerico').exists())
        self.assertTrue((Path(self.temp.name)/'app/DestinationRepository.php').is_file())
        self.assertTrue((Path(self.temp.name)/'app/BusinessRepository.php').is_file())
        self.assertTrue((Path(self.temp.name)/'app/BusinessAdminService.php').is_file())
        self.assertTrue((Path(self.temp.name)/'content/destinos-dinamicos.json').is_file())
        self.assertTrue((Path(self.temp.name)/'bin/importar-destinos.php').is_file())
        self.assertTrue((Path(self.temp.name)/'app/bin/importar-destinos.php').is_file())
        self.assertTrue((Path(self.temp.name)/'app/content/destinos-dinamicos.json').is_file())
        self.assertTrue((Path(self.temp.name)/'app/database/migrations/003-dynamic-destinations.mysql.sql').is_file())
        self.assertTrue((Path(self.temp.name)/'app/database/migrations/007-media-provenance.mysql.sql').is_file())
        self.assertTrue((Path(self.temp.name)/'app/database/migrations/008-business-subscriptions.mysql.sql').is_file())
        self.assertTrue((Path(self.temp.name)/'app/database/migrations/009-business-origin.mysql.sql').is_file())
        self.assertTrue((Path(self.temp.name)/'app/database/migrations/010-business-location-subscription-lifecycle.mysql.sql').is_file())
        self.assertTrue((Path(self.temp.name)/'app/database/migrations/011-premium-business-story.mysql.sql').is_file())
        self.assertTrue((Path(self.temp.name)/'app/database/migrations/012-business-image-references.mysql.sql').is_file())
        self.assertTrue((Path(self.temp.name)/'app/bin/importar-negocios.php').is_file())
        self.assertTrue((Path(self.temp.name)/'app/content/plantilla-importar-negocios.json').is_file())
        self.assertFalse((self.public/'private/config.php').exists())

if __name__=='__main__': unittest.main()
