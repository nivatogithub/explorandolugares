import json
import os
import sqlite3
import subprocess
import tempfile
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
CATALOG = json.loads((ROOT / "content/destinos-dinamicos.json").read_text())


class DynamicDestinationRenderTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tempdir = tempfile.TemporaryDirectory(prefix="el-destinations-")
        cls.database = Path(cls.tempdir.name) / "destinations.sqlite"
        with sqlite3.connect(cls.database) as connection:
            connection.executescript((ROOT / "backend/database/destinations.sqlite.sql").read_text())
            connection.executescript((ROOT / "backend/database/migrations/006-poi-experience-links.sqlite.sql").read_text())
        env = os.environ | {"EL_DESTINATIONS_DSN": f"sqlite:{cls.database}"}
        subprocess.run(["php", "backend/bin/importar-destinos.php"], cwd=ROOT, env=env,
                       check=True, capture_output=True, text=True)
        cls.env = env | {"EL_SITE_BASE": "/v1"}

    @classmethod
    def tearDownClass(cls):
        cls.tempdir.cleanup()

    def render(self, slug, locale, preview=False):
        uri_locale = {"es": "", "en": "en/", "pt-BR": "pt-br/"}[locale]
        session = ""
        if preview:
            session = "$_GET['preview']='1';session_name('el_session');session_start();$_SESSION['admin']=true;$_SESSION['last_seen']=time();"
        php = (
            f"$_GET['destino']={json.dumps(slug)};"
            f"$_GET['idioma']={json.dumps(locale)};"
            f"{session}"
            f"$_SERVER['REQUEST_URI']='/v1/{uri_locale}destinos/{slug}/';"
            "include 'ensayos/v1/destino.php';"
        )
        result = subprocess.run(["php", "-r", php], cwd=ROOT, env=self.env,
                                check=True, capture_output=True, text=True)
        return result.stdout

    def test_only_jerico_is_public_and_other_pilot_destinations_stay_drafts(self):
        for destination in CATALOG["destinations"]:
            for locale in CATALOG["locales"]:
                with self.subTest(destination=destination["slug"], locale=locale):
                    page = self.render(destination["slug"], locale)
                    expected = destination["translations"][locale]["name"]
                    if destination["slug"] == "jerico":
                        self.assertIn(expected, page)
                        self.assertIn('rel="canonical"', page)
                        self.assertNotIn("Destino temporalmente no disponible", page)
                        self.assertNotIn("commons.wikimedia.org/wiki/File", page)
                        self.assertNotIn("Ilustración editorial generada con IA", page)
                    else:
                        self.assertEqual("Destino no encontrado.", page)
                        self.assertNotIn(expected, page)
                        preview = self.render(destination["slug"], locale, preview=True)
                        self.assertIn(expected, preview)
                        self.assertIn("Vista previa privada", preview)

    def test_operator_experiences_stay_hidden_until_approved(self):
        page = self.render("jerico", "es")
        self.assertIn("<h3>Jardín Botánico Los Balsos</h3>", page)
        self.assertEqual(page.count("<article"), 7)
        self.assertIn("Subida al Cerro de las Nubes", page)
        self.assertIn("<h3>Chorros de Quebradona</h3>", page)
        self.assertIn("Atractivo natural rural de Jericó", page)
        self.assertIn("Confirma con fuentes locales dónde inicia el recorrido", page)
        self.assertIn("jardin-botanico-los-balsos-ilustrado.webp", page)
        self.assertIn("cerro-de-las-nubes-sendero-ilustrado-v2.webp", page)
        self.assertIn("chorros-quebradona-ilustrado-v2.webp", page)
        self.assertIn("cristo-cerro-el-salvador-ilustrado-v2.webp", page)
        self.assertIn("santuario-santa-laura-ilustrado-v2.webp", page)
        self.assertIn("casa-santa-laura-ilustrada-v2.webp", page)
        for locale, labels in {
            "es": ("Jardín Botánico Los Balsos", "Subida al Cerro de las Nubes", "Chorros de Quebradona"),
            "en": ("Los Balsos Botanical Garden", "Cerro de las Nubes hike", "Check with local sources for the trailhead"),
            "pt-BR": ("Jardim Botânico Los Balsos", "Subida ao Cerro de las Nubes", "Confirme com fontes locais onde começa o percurso"),
        }.items():
            localized = self.render("jerico", locale)
            with self.subTest(locale=locale):
                for label in labels:
                    self.assertIn(label, localized)
                self.assertIn("jardin-botanico-los-balsos-ilustrado.webp", localized)
                self.assertIn("cerro-de-las-nubes-sendero-ilustrado-v2.webp", localized)
                self.assertIn("chorros-quebradona-ilustrado-v2.webp", localized)
        self.assertNotIn("Vuelo privado en helicóptero Medellín–Jericó", page)
        self.assertNotIn("Parapente biplaza en Jericó", page)
        self.assertNotIn("Experiencia en finca cafetera", page)
        self.assertNotIn("turismojerico.com/producto/", page)
        private = self.render("jerico", "es", preview=True)
        self.assertEqual(private.count('class="destination-experience-card"><img'), 3)
        self.assertIn("Experiencias ofrecidas por operadores locales", private)
        self.assertNotIn("turismojerico.com/producto/", private)
        self.assertNotIn("highlight-extra", page)
        self.assertIn("Encuentra qué te gustaría conocer ↓", page)
        self.assertIn('href="#que-conocer">Qué conocer</a>', page)
        self.assertIn('id="cargar-mas-atractivos"', page)

    def test_destination_images_exist_in_the_static_asset_tree(self):
        for media in CATALOG["media"]:
            with self.subTest(path=media["path"]):
                self.assertTrue((ROOT / "ensayos/v1" / media["path"]).is_file())
                if media["path"].startswith("assets/destinos/1/"):
                    self.assertLess((ROOT / "ensayos/v1" / media["path"]).stat().st_size, 350_000)

    def test_destination_router_serves_public_destination_assets(self):
        assets = (
            "/v1/assets/destinos/1/parque-principal-ilustrado.webp",
            "/v1/assets/destinos/1/cristo-cerro-el-salvador-ilustrado-v2.webp",
            "/v1/assets/destinos/1/santuario-santa-laura-ilustrado-v2.webp",
            "/v1/assets/destinos/1/casa-santa-laura-ilustrada-v2.webp",
            "/v1/assets/destinos/1/jardin-botanico-los-balsos-ilustrado.webp",
            "/v1/assets/destinos/1/cerro-de-las-nubes-sendero-ilustrado-v2.webp",
            "/v1/assets/destinos/1/chorros-quebradona-ilustrado-v2.webp",
        )
        for relative in assets:
            with self.subTest(asset=relative):
                php = f"$_SERVER['REQUEST_URI']={json.dumps(relative)};include 'scripts/php-destinations-router.php';"
                result = subprocess.run(["php", "-r", php], cwd=ROOT, capture_output=True, check=True)
                self.assertTrue(result.stdout.startswith(b"RIFF"), f"The public image route must return {relative}")


if __name__ == "__main__":
    unittest.main()
