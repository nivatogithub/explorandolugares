import importlib.util
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('seo_release', ROOT / 'scripts/generar-sitemap-entrega.py')
seo = importlib.util.module_from_spec(spec)
spec.loader.exec_module(seo)

class SeoReleaseTests(unittest.TestCase):
    def test_final_pages_added_without_a_manual_url_list(self):
        with tempfile.TemporaryDirectory() as folder:
            public = Path(folder)
            for path, directive in [('index.html', 'index,follow'), ('nueva/index.html', 'index,follow'),
                                    ('borrador/index.html', 'noindex,follow'), ('admin/index.html', 'index,follow')]:
                page = public / path; page.parent.mkdir(parents=True, exist_ok=True)
                url = 'https://explorandolugares.com/' + path.removesuffix('index.html')
                page.write_text(f'<meta name="robots" content="{directive}"><link rel="canonical" href="{url}">')
            self.assertEqual(seo.generate(public), 2)
            xml = (public / 'sitemap.xml').read_text()
            self.assertIn('/nueva/', xml)
            self.assertNotIn('/borrador/', xml)
            self.assertNotIn('/admin/', xml)
            (public / 'nueva/index.html').unlink()
            self.assertEqual(seo.generate(public), 1)

    def test_sitemap_route_is_internal_and_templates_redirect_after_validation(self):
        rules = (ROOT / 'ensayos/v1/.htaccess').read_text()
        self.assertIn('RewriteRule ^sitemap\\.xml$ sitemap-live.php [L]', rules)
        for template, gate in [('destino.php', 'if (!$d)'), ('negocio.php', 'if(!$business)')]:
            source = (ROOT / 'backend/integration-medicion/public' / template).read_text()
            self.assertLess(source.index(gate), source.index("header('Location:"))

if __name__ == '__main__':
    unittest.main()
