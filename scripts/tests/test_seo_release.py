import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('seo_release', ROOT / 'scripts/generate-release-sitemap.py')
seo = importlib.util.module_from_spec(spec)
spec.loader.exec_module(seo)

class SeoReleaseTests(unittest.TestCase):
    def test_reviewed_metadata_cannot_be_reintroduced_from_an_old_active_snapshot(self):
        catalog=json.loads((ROOT/'content/release-places-businesses.json').read_text())
        changes=json.loads((ROOT/'backend/app/content/seo-description-patch.json').read_text())['changes']
        seed={d['slug']:d for d in json.loads((ROOT/'content/destinos-dinamicos.json').read_text())['destinations']}
        for change in changes:
            slug,locale=change['slug'],change['locale']
            self.assertEqual(catalog['editorial'][slug]['translations'][locale]['meta_description'],change['after'])
            snapshot=json.loads((ROOT/'content/destination-migration'/f'{slug}.json').read_text())
            translations={t['locale']:t for t in snapshot['tables']['destination_translations']}
            self.assertEqual(translations[locale]['meta_description'],change['after'])
            if slug in seed:self.assertEqual(seed[slug]['translations'][locale]['meta_description'],change['after'])

    def test_internal_links_are_canonical_without_touching_external_or_query(self):
        composer_spec=importlib.util.spec_from_file_location('seo_composer',ROOT/'scripts/compose-presentation.py')
        composer=importlib.util.module_from_spec(composer_spec);composer_spec.loader.exec_module(composer)
        source='<a href="../privacidad/index.html?from=one&amp;lang=en#terms">Privacy</a><a href="https://other.invalid/index.html">Other</a><a href="#main">Jump</a>'
        result=composer.canonical_body_links(source,'/en/lugares/')
        self.assertIn('href="/en/privacidad/?from=one&amp;lang=en#terms"',result)
        self.assertIn('https://other.invalid/index.html',result)
        self.assertIn('href="#main"',result)
        self.assertEqual(composer.canonical_body_links(result,'/en/lugares/'),result)

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
        for template, gate in [('place.php', 'if (!$d)'), ('business.php', 'if(!$business)')]:
            source = (ROOT / 'backend/integration-analytics/public' / template).read_text()
            self.assertLess(source.index(gate), source.index("header('Location:"))

if __name__ == '__main__':
    unittest.main()
