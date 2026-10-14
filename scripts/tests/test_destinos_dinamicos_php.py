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
        env = os.environ | {"EL_DESTINATIONS_DSN": f"sqlite:{cls.database}"}
        subprocess.run(["php", "backend/bin/importar-destinos.php"], cwd=ROOT, env=env,
                       check=True, capture_output=True, text=True)
        cls.env = env | {"EL_SITE_BASE": "/v1"}

    @classmethod
    def tearDownClass(cls):
        cls.tempdir.cleanup()

    def render(self, slug, locale):
        uri_locale = {"es": "", "en": "en/", "pt-BR": "pt-br/"}[locale]
        php = (
            f"$_GET['destino']={json.dumps(slug)};"
            f"$_GET['idioma']={json.dumps(locale)};"
            f"$_SERVER['REQUEST_URI']='/v1/{uri_locale}destinos/{slug}/';"
            "include 'ensayos/v1/destino.php';"
        )
        result = subprocess.run(["php", "-r", php], cwd=ROOT, env=self.env,
                                check=True, capture_output=True, text=True)
        return result.stdout

    def test_all_21_destinations_render_in_three_languages(self):
        for destination in CATALOG["destinations"]:
            for locale in CATALOG["locales"]:
                with self.subTest(destination=destination["slug"], locale=locale):
                    page = self.render(destination["slug"], locale)
                    expected = destination["translations"][locale]["name"]
                    self.assertIn(expected, page)
                    self.assertIn('rel="canonical"', page)
                    self.assertNotIn("Destino temporalmente no disponible", page)

    def test_jerico_attractions_are_single_cards_and_load_by_row(self):
        page = self.render("jerico", "es")
        self.assertEqual(page.count("Jardín Botánico Los Balsos"), 1)
        self.assertEqual(page.count("<article>"), 5)
        self.assertNotIn("highlight-extra", page)
        self.assertIn("Encuentra qué te gustaría conocer ↓", page)
        self.assertIn('href="#que-conocer">Qué conocer</a>', page)
        self.assertIn('id="cargar-mas-atractivos"', page)

    def test_destination_images_exist_in_the_static_asset_tree(self):
        for media in CATALOG["media"]:
            with self.subTest(path=media["path"]):
                self.assertTrue((ROOT / "ensayos/v1" / media["path"]).is_file())


if __name__ == "__main__":
    unittest.main()
