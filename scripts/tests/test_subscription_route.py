import importlib.util
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('subscription_route', ROOT / 'scripts/normalizar-ruta-suscripciones.py')
route = importlib.util.module_from_spec(spec)
spec.loader.exec_module(route)


class SubscriptionRouteTest(unittest.TestCase):
    def test_languages_links_canonical_and_redirects_are_consistent(self):
        with tempfile.TemporaryDirectory() as directory:
            public = Path(directory)
            (public / '.htaccess').write_text('RewriteEngine On\n')
            for prefix in ('', 'en/', 'pt-br/'):
                old = public / prefix / route.OLD / 'index.html'
                old.parent.mkdir(parents=True)
                old.write_text('<link rel="canonical" href="https://explorandolugares.com/' + prefix + route.OLD + '"><p>Oferta comercial</p>')
            (public / 'menu.html').write_text('<a href="../planes/index.html">Suscripciones</a>')
            (public / 'checkout.php').write_text('<a href="/' + route.OLD + '?plan=featured#tarifas">Volver</a>')
            route.normalize(public)
            first = {p.relative_to(public): p.read_bytes() for p in public.rglob('*') if p.is_file()}
            route.normalize(public)
            self.assertEqual(first, {p.relative_to(public): p.read_bytes() for p in public.rglob('*') if p.is_file()})
            for prefix in ('', 'en/', 'pt-br/'):
                new = (public / prefix / route.NEW / 'index.html').read_text()
                self.assertIn('https://explorandolugares.com/' + prefix + route.NEW, new)
                self.assertIn('Oferta comercial', new)
                old = (public / prefix / route.OLD / 'index.html').read_text()
                self.assertIn('noindex,follow', old)
                self.assertNotIn('Oferta comercial', old)
            self.assertIn(route.NEW + '?plan=featured#tarifas', (public / 'checkout.php').read_text())
            self.assertEqual((public / '.htaccess').read_text().count(route.RULE), 1)
            self.assertIn('[R=301,L,NE]', route.RULE)
            self.assertIn('../suscripciones/index.html', (public / 'menu.html').read_text())


if __name__ == '__main__':
    unittest.main()
