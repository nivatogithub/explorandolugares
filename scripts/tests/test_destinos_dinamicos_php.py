import json
import hashlib
import html
import os
import sqlite3
import struct
import subprocess
import tempfile
import unittest
import zlib
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile


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
            connection.executescript((ROOT / "backend/database/migrations/008-business-subscriptions.sqlite.sql").read_text())
            connection.executescript((ROOT / "backend/database/migrations/009-business-origin.sqlite.sql").read_text())
            connection.executescript((ROOT / "backend/database/migrations/010-business-location-subscription-lifecycle.sqlite.sql").read_text())
            connection.executescript((ROOT / "backend/database/migrations/011-premium-business-story.sqlite.sql").read_text())
            connection.executescript((ROOT / "backend/database/migrations/012-business-image-references.sqlite.sql").read_text())
            connection.executescript((ROOT / "backend/database/migrations/013-accounts-location-map.sqlite.sql").read_text())
            connection.executescript((ROOT / "backend/database/migrations/014-tiered-business-description.sqlite.sql").read_text())
            connection.executescript((ROOT / "backend/database/migrations/015-business-media-quotas.sqlite.sql").read_text())
            connection.executescript((ROOT / "backend/database/migrations/016-plan-content-limits.sqlite.sql").read_text())
            connection.executescript((ROOT / "backend/database/migrations/017-business-claims.sqlite.sql").read_text())
            connection.executescript((ROOT / "backend/database/migrations/018-business-portal.sqlite.sql").read_text())
            connection.executescript((ROOT / "backend/database/migrations/025-subscription-renewal-history.sqlite.sql").read_text())
            for name in ["019-traveler-profile", "023-business-representation-evidence", "034-business-onboarding"]:
                matches = list((ROOT / "backend/database/migrations").glob(name[:3]+"-*.sqlite.sql"))
                connection.executescript(matches[0].read_text())
        env = os.environ | {"EL_DESTINATIONS_DSN": f"sqlite:{cls.database}"}
        subprocess.run(["php", "backend/bin/importar-destinos.php"], cwd=ROOT, env=env,
                       check=True, capture_output=True, text=True)
        cls.env = env
        cls.package = Path(cls.tempdir.name)/"package"
        subprocess.run(["python3", "scripts/preparar-hosting.py", "--output", str(cls.package)], cwd=ROOT, check=True, capture_output=True)


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
            f"$_SERVER['REQUEST_URI']='/{uri_locale}destinos/{slug}/';"
            f"include {json.dumps(str(self.package / 'public/destino.php'))};"
        )
        result = subprocess.run(["php", "-r", php], cwd=ROOT, env=self.env,
                                check=True, capture_output=True, text=True)
        return html.unescape(result.stdout)

    def render_business(self, public_id, locale):
        uri_locale = {"es": "", "en": "en/", "pt-BR": "pt-br/"}[locale]
        php = (
            f"$_GET['id']={json.dumps(public_id)};"
            f"$_GET['idioma']={json.dumps(locale)};"
            f"$_SERVER['REQUEST_URI']='/{uri_locale}negocios/{public_id}/';"
            f"include {json.dumps(str(self.package / 'public/negocio.php'))};"
        )
        result = subprocess.run(["php", "-r", php], cwd=ROOT, env=self.env,
                                check=True, capture_output=True, text=True)
        return html.unescape(result.stdout)

    def test_approved_seed_destinations_are_public_and_remaining_pilots_stay_private(self):
        approved = {"jerico"}
        approved.update(json.loads((ROOT / "content/releases/cinco-destinos-20261024.json").read_text())["public_places"])
        self.assertEqual({d["slug"] for d in CATALOG["destinations"] if d["publication_status"] == "published"}, approved)
        for destination in CATALOG["destinations"]:
            for locale in CATALOG["locales"]:
                with self.subTest(destination=destination["slug"], locale=locale):
                    page = self.render(destination["slug"], locale)
                    expected = destination["translations"][locale]["name"]
                    if destination["slug"] in approved:
                        self.assertIn(expected, page)
                        self.assertIn('rel="canonical"', page)
                        self.assertNotIn("Destino temporalmente no disponible", page)
                        self.assertNotIn("commons.wikimedia.org/wiki/File", page)
                        self.assertNotIn("Ilustración editorial generada con IA", page)
                    else:
                        self.assertEqual("Lugar no encontrado.", page)
                        self.assertNotIn(expected, page)
                        preview = self.render(destination["slug"], locale, preview=True)
                        self.assertIn(expected, preview)
                        preview_label = {"es": "Vista previa privada", "en": "Private preview", "pt-BR": "Prévia privada"}[locale]
                        self.assertIn(preview_label, preview)

    def test_approved_editorial_experiences_are_public_without_free_operator_ads(self):
        page = self.render("jerico", "es")
        for channel in ["whatsapp", "instagram", "email"]:
            self.assertIn('footer-contact-'+channel, page)
        for title in ["Jardín Botánico Los Balsos", "Subida al Cerro de las Nubes", "Chorros de Quebradona"]:
            self.assertIn(title, page)
        for title in ["Vuelo privado en helicóptero Medellín–Jericó", "Parapente biplaza en Jericó", "Experiencia en finca cafetera"]:
            self.assertIn(title, page)
        self.assertNotIn("turismojerico.com/producto/", page)
        self.assertIn('id="experiencias-locales"', page)
        self.assertIn('class="destination-hero-links"', page)
        for locale in ["es", "en", "pt-BR"]:
            localized = self.render("jerico", locale)
            self.assertIn('id="experiencias-locales"', localized)
            self.assertIn('destination-experience-card', localized)
            self.assertIn('maps', localized)
            self.assertNotIn("turismojerico.com/producto/", localized)
        private = self.render("jerico", "es", preview=True)
        self.assertIn('noindex', private)
        self.assertIn('id="experiencias-locales"', private)

    def test_bulk_import_keeps_verification_tag_and_private_draft_state(self):
        with tempfile.TemporaryDirectory(prefix="el-business-import-") as folder:
            package=Path(folder)/"businesses.json"
            package.write_text(json.dumps({
                "schema_version":"business-import-v1","destination_slug":"jerico",
                "businesses":[{"category_code":"alimentacion","information_status":"VERIFICADO",
                    "name":"Registro de importación verificada","summary":"Dato de prueba",
                    "description":"Registro de prueba para validar la importación.",
                    "sources":[{"url":"https://example.org/business","note":"Prueba","checked_at":"2026-10-15"}]}]
            }))
            code=("require 'backend/app/BusinessPublicImporter.php';"
                  "$pdo=new PDO(getenv('EL_DESTINATIONS_DSN'));"
                  "$package=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);"
                  "echo BusinessPublicImporter::import($pdo,$package);")
            result=subprocess.run(["php","-r",code,str(package)],cwd=ROOT,env=self.env,check=True,capture_output=True,text=True)
            self.assertEqual(result.stdout,"1")
        with sqlite3.connect(self.database) as connection:
            row=connection.execute("SELECT b.information_status,b.claim_status,b.publication_status,t.display_name FROM businesses b JOIN business_translations t ON t.business_id=b.id WHERE t.display_name=?",("Registro de importación verificada",)).fetchone()
            connection.execute("DELETE FROM businesses WHERE id=(SELECT business_id FROM business_translations WHERE display_name=?)",("Registro de importación verificada",))
        self.assertEqual(row,("VERIFICADO","NO_RECLAMADO","draft","Registro de importación verificada"))

    def test_business_zip_import_keeps_reference_images_private_and_business_unpublished(self):
        def png_chunk(kind, payload):
            body=kind+payload
            return struct.pack('!I',len(payload))+body+struct.pack('!I',zlib.crc32(body)&0xffffffff)
        width,height=240,160
        raw=b''.join(b'\x00'+bytes([192,128,64])*width for _ in range(height))
        png=(b'\x89PNG\r\n\x1a\n'+png_chunk(b'IHDR',struct.pack('!2I5B',width,height,8,2,0,0,0))+
             png_chunk(b'IDAT',zlib.compress(raw))+png_chunk(b'IEND',b''))
        business_name=f'Negocio paquete ZIP {os.getpid()}'
        manifest={"schema_version":"business-import-v1","destination_slug":"jerico","businesses":[{
            "category_code":"alimentacion","information_status":"PUBLICO","name":business_name,
            "summary":"Dato de ejemplo","description":"Referencia privada para revisar el perfil.",
            "sources":[{"url":"https://example.org/business","note":"Prueba de paquete"}],
            "image_references":[{"file":"images/referencia.png","name":"Foto de referencia"}]
        }]}
        with tempfile.TemporaryDirectory(prefix='el-business-zip-test-') as folder:
            folder=Path(folder);archive=folder/'businesses.zip';stage=folder/'stage';private=folder/'private';stage.mkdir()
            with ZipFile(archive,'w',ZIP_DEFLATED) as zipped:
                zipped.writestr('paquete-negocios.json',json.dumps(manifest))
                zipped.writestr('images/referencia.png',png)
            code=("require 'backend/app/BusinessPackage.php';require 'backend/app/BusinessPublicImporter.php';"
                  "$pdo=new PDO(getenv('EL_DESTINATIONS_DSN'));$parsed=BusinessPackage::readZip($argv[1],$argv[2]);"
                  "$count=BusinessPublicImporter::import($pdo,$parsed['manifest'],$parsed['images'],$argv[3]);"
                  "$q=$pdo->prepare('SELECT b.publication_status,b.claim_status,r.original_name,r.mime_type,r.file_size,r.storage_name FROM businesses b JOIN business_translations t ON t.business_id=b.id JOIN business_image_references r ON r.business_id=b.id WHERE t.display_name=?');"
                  "$q->execute([$argv[4]]);echo json_encode([$count,$q->fetch(PDO::FETCH_ASSOC)]);")
            result=subprocess.run(['php','-r',code,str(archive),str(stage),str(private),business_name],cwd=ROOT,env=self.env,check=True,capture_output=True,text=True)
            imported,record=json.loads(result.stdout)
            self.assertEqual(imported,1)
            self.assertEqual(record['publication_status'],'draft')
            self.assertEqual(record['claim_status'],'NO_RECLAMADO')
            self.assertEqual(record['original_name'],'Foto de referencia')
            self.assertEqual(record['mime_type'],'image/png')
            self.assertEqual(record['file_size'],len(png))
            self.assertTrue((private/record['storage_name']).is_file())
            with sqlite3.connect(self.database) as connection:
                connection.execute('DELETE FROM businesses WHERE id=(SELECT business_id FROM business_translations WHERE display_name=?)',(business_name,))

    def test_public_claim_search_and_review_state_changes(self):
        with sqlite3.connect(self.database) as connection:
            destination_id=connection.execute("SELECT id FROM destinations WHERE slug='jerico'").fetchone()[0]
            category=connection.execute("SELECT code FROM service_categories WHERE is_active=1 ORDER BY code LIMIT 1").fetchone()[0]
            cursor=connection.execute("INSERT INTO businesses(public_id,destination_id,category_code,slug,information_status,claim_status,operation_status,publication_status) VALUES(?,?,?,?,?,?,?,?)",
                ("claim-test-"+str(os.getpid()),destination_id,category,"claim-test-"+str(os.getpid()),"PUBLICO","NO_RECLAMADO","DESCONOCIDO","published"))
            business_id=cursor.lastrowid
            connection.execute("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)",
                (business_id,"es","Negocio de prueba para reclamar","","Prueba"))
            account_id=connection.execute("INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status) VALUES(?,?,?,?,?,?)",
                ("claim-account-"+str(os.getpid()),"business","Persona de prueba","persona-"+str(os.getpid())+"@example.org",None,"active")).lastrowid
        code=("function throttle(...$args):bool{return true;}"
              "session_id('claim-test');session_start();$_SESSION['business_account_id']=(int)$argv[4];"
              "require 'backend/app/BusinessClaimService.php';"
              "$pdo=new PDO(getenv('EL_DESTINATIONS_DSN'));$service=new BusinessClaimService($pdo);"
              "$rows=$service->search((int)$argv[1],$argv[2],'Negocio de prueba');"
              "if(count($rows)!==1)throw new RuntimeException('No apareció en la búsqueda filtrada.');"
              "$id=$service->submit(['business_id'=>$argv[3],'relationship_code'=>'owner','consent'=>'1']);"
              "$service->updateRequest($id,'in_review','Revisar documento');"
              "$service->updateRequest($id,'not_approved','Prueba cerrada');"
              "$status=$pdo->query('SELECT claim_status FROM businesses WHERE id='.(int)$argv[3])->fetchColumn();"
              "if($status!=='NO_RECLAMADO')throw new RuntimeException('No se liberó el negocio tras cerrar la reclamación.');echo 'ok';")
        result=subprocess.run(["php","-r",code,str(destination_id),category,str(business_id),str(account_id)],cwd=ROOT,env=self.env,check=True,capture_output=True,text=True)
        self.assertEqual(result.stdout,"ok")
        with sqlite3.connect(self.database) as connection:
            connection.execute("DELETE FROM businesses WHERE id=?",(business_id,))

    def test_bulk_enrichment_keeps_free_location_and_contacts_private(self):
        public_id='99999999-9999-4999-8999-999999999999'
        with sqlite3.connect(self.database) as connection:
            connection.execute('PRAGMA foreign_keys=ON')
            for name in ['026-business-research-contacts','027-business-bulk-update-audit']:
                connection.executescript((ROOT/'backend/database/migrations'/f'{name}.sqlite.sql').read_text())
            destination_id=connection.execute("SELECT id FROM destinations WHERE slug='jerico'").fetchone()[0]
            business_id=connection.execute('INSERT INTO businesses(public_id,destination_id,category_code,slug,publication_status) VALUES(?,?,?,?,?)',(public_id,destination_id,'hospedaje','prueba-enriquecimiento','published')).lastrowid
            connection.execute('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)',(business_id,'es','Prueba de enriquecimiento privado','',''))
            connection.execute('INSERT INTO business_subscriptions(business_id,plan_code,status) VALUES(?,?,?)',(business_id,'free','active'))
        code=("require 'backend/app/BusinessBulkEditService.php';$pdo=new PDO(getenv('EL_DESTINATIONS_DSN'));"
              "$service=new BusinessBulkEditService($pdo);$p=$service->export('jerico','','Prueba de enriquecimiento privado');"
              "$p['businesses'][0]['fields']['address_text']='DireccionInternaNoPublicar';"
              "$p['businesses'][0]['translations']['es']['summary']='ResumenInternoNoPublicar';"
              "$p['businesses'][0]['contacts'][]=['channel'=>'email','contact_value'=>'privado-bulk@example.invalid'];"
              "$service->apply($p,'prueba-local');echo 'ok';")
        result=subprocess.run(['php','-r',code],cwd=ROOT,env=self.env,check=True,capture_output=True,text=True)
        self.assertEqual(result.stdout,'ok')
        page=self.render('jerico','es')
        self.assertIn('Prueba de enriquecimiento privado',page)
        for private_value in ['DireccionInternaNoPublicar','ResumenInternoNoPublicar','privado-bulk@example.invalid']:
            self.assertNotIn(private_value,page)
        with sqlite3.connect(self.database) as connection:
            self.assertEqual(connection.execute('SELECT address_text,publication_status FROM businesses WHERE id=?',(business_id,)).fetchone(),('DireccionInternaNoPublicar','published'))
            connection.execute('PRAGMA foreign_keys=ON');connection.execute('DELETE FROM businesses WHERE id=?',(business_id,))

    def test_free_public_import_can_publish_without_exposing_a_contact_channel(self):
        with sqlite3.connect(self.database) as connection:
            destination_id=connection.execute("SELECT id FROM destinations WHERE slug='jerico'").fetchone()[0]
            cursor=connection.execute("INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status,address_text) VALUES(?,?,?,?,?,?,?,?,?,?)",
                ("public-import-test-"+str(os.getpid()),destination_id,"alimentacion","public_import","public-import-test-"+str(os.getpid()),"PUBLICO","NO_RECLAMADO","DESCONOCIDO","draft","Parque principal, Jericó"))
            business_id=cursor.lastrowid
            connection.execute("UPDATE businesses SET latitude=5.7914719,longitude=-75.7862113 WHERE id=?",(business_id,))
            connection.execute("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)",
                (business_id,"es","Importado público de prueba","","Prueba"))
            connection.execute("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)",
                (business_id,"en","Imported public test","","Test"))
            connection.execute("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)",
                (business_id,"pt-BR","Importado público de teste","","Teste"))
            connection.execute("INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status) VALUES(?,?,?,?)",
                (business_id,"free",None,"pending_publication"))
        code=("require 'backend/app/BusinessSubscriptionAdminService.php';"
              "$pdo=new PDO(getenv('EL_DESTINATIONS_DSN'));"
              "(new BusinessSubscriptionAdminService($pdo))->publish((int)$argv[1]);"
              "$status=$pdo->query('SELECT publication_status FROM businesses WHERE id='.(int)$argv[1])->fetchColumn();"
              "if($status!=='published')throw new RuntimeException('La ficha no quedó publicada.');echo 'ok';")
        result=subprocess.run(["php","-r",code,str(business_id)],cwd=ROOT,env=self.env,check=True,capture_output=True,text=True)
        self.assertEqual(result.stdout,"ok")
        with sqlite3.connect(self.database) as connection:
            connection.execute("DELETE FROM businesses WHERE id=?",(business_id,))

    def test_admin_navigation_stays_one_column_and_keeps_business_import_inside_business_hub(self):
        navigation=(ROOT/"backend/public/admin/_navigation.php").read_text()
        businesses=(ROOT/"backend/public/admin/negocios.php").read_text()
        styles=(ROOT/"ensayos/v1/assets/style.css").read_text()
        self.assertNotIn('href="importar-negocios.php"',navigation)
        self.assertIn('href="negocios.php?tab=importar"',businesses)
        self.assertIn('$manualCreationDisabled',businesses)
        self.assertIn("$tab==='crear'&&!$id",businesses)
        self.assertIn('href="negocios.php?tab=revisar"',businesses)
        self.assertIn('publish_imported_bulk',businesses)
        self.assertIn('Posibles duplicados',businesses)
        bulk=(ROOT/"backend/app/BusinessBulkPublicationService.php").read_text()
        self.assertIn("$business['record_origin']!=='public_import'",bulk)
        self.assertIn("$business['plan_code']!=='free'",bulk)
        self.assertIn('Posible duplicado de',bulk)
        script=(ROOT/"backend/public/assets/admin-business-bulk.js").read_text()
        self.assertIn('window.confirm',script)
        self.assertIn('href="solicitudes-cuenta.php"',navigation)
        self.assertIn(".admin-sidebar nav section{display:block!important",styles)
        self.assertIn("column-count:1!important",styles)
        claim_page=(ROOT/"backend/public/reclamar-negocio.php").read_text()
        self.assertIn('name="destino"',claim_page)
        self.assertIn('name="categoria"',claim_page)
        self.assertIn('name="nombre"',claim_page)
        self.assertIn('name="business_id"',claim_page)
        self.assertIn('information_status',claim_page)
        claim_script=(ROOT/"backend/public/assets/business-claim-filter.js").read_text()
        self.assertIn("AbortController",claim_script)
        self.assertIn("fetch(`${window.location.pathname}?${parameters}`",claim_script)
        self.assertIn("requestSubmit()",claim_script)

    def test_subscription_tiers_control_directory_cards_and_own_pages(self):
        public_ids = {
            "free": "11111111-1111-4111-8111-111111111111",
            "basic": "22222222-2222-4222-8222-222222222222",
            "featured": "33333333-3333-4333-8333-333333333333",
            "page": "44444444-4444-4444-8444-444444444444",
            "page_premium": "55555555-5555-4555-8555-555555555555",
        }
        with sqlite3.connect(self.database) as connection:
            destination_id = connection.execute("SELECT id FROM destinations WHERE slug='jerico'").fetchone()[0]
            for index, (plan, public_id) in enumerate(public_ids.items(), start=1):
                connection.execute(
                    "INSERT INTO businesses(public_id,destination_id,category_code,slug,information_status,claim_status,operation_status,publication_status) VALUES(?,?,?,?,?,?,?,?)",
                    (public_id,destination_id,"alimentacion",f"negocio-{plan}","VERIFICADO","RECLAMADO","ABIERTO","published"),
                )
                business_id=connection.execute("SELECT id FROM businesses WHERE public_id=?",(public_id,)).fetchone()[0]
                for locale, name in (("es",f"Negocio {plan}"),("en",f"Business {plan}"),("pt-BR",f"Negócio {plan}")):
                    story = {"es":"Historia del negocio", "en":"Business story", "pt-BR":"História do negócio"}[locale] if plan=="page_premium" else None
                    services = {"es":"Servicios destacados", "en":"Featured services", "pt-BR":"Serviços em destaque"}[locale] if plan=="page_premium" else None
                    connection.execute("INSERT INTO business_translations(business_id,locale,display_name,summary,description,brand_story,featured_services) VALUES(?,?,?,?,?,?,?)",(business_id,locale,name,f"Resumen {plan}",f"Descripción completa {plan}",story,services))
                if plan!="free":
                    connection.execute("INSERT INTO business_subscriptions(business_id,plan_code,status) VALUES(?,?,?)",(business_id,plan,"active"))
                if plan=="featured":
                    connection.execute("INSERT INTO business_contacts(business_id,channel,contact_value,is_public) VALUES(?,?,?,1)",(business_id,"whatsapp","+573001234567"))
        directory=self.render("jerico","es")
        self.assertIn('class="directory-card directory-card-tier-free"',directory)
        self.assertIn('class="directory-card directory-card-tier-basic"',directory)
        self.assertIn('class="directory-card directory-card-tier-featured is-featured"',directory)
        self.assertIn('directory-contact',directory)
        import re
        free_card=re.search(r'<article[^>]*class="directory-card directory-card-tier-free".*?</article>',directory)
        self.assertIsNotNone(free_card)
        self.assertIn('Negocio free',free_card.group(0))
        self.assertNotIn('<img',free_card.group(0))
        self.assertNotIn('directory-description',free_card.group(0))
        self.assertNotIn('directory-card-details',free_card.group(0))
        self.assertNotIn('WhatsApp',free_card.group(0))
        self.assertNotIn('directory-business-mark',free_card.group(0))
        self.assertIn('class="directory-premium-star"',directory)
        self.assertIn('aria-label="Premium"',directory)
        self.assertIn('id="negocio-44444444-4444-4444-8444-444444444444"',directory)
        self.assertIn('href="/negocios/44444444-4444-4444-8444-444444444444/"',directory)
        page=self.render_business(public_ids["page_premium"],"es")
        self.assertIn("Negocio page_premium",page)
        self.assertIn("Página Premium",page)
        self.assertIn("Descripción completa page_premium",page)
        self.assertNotIn("La historia del negocio",page)
        self.assertNotIn("Historia del negocio",page)
        self.assertIn("ES",page)
        self.assertIn("Business page_premium",self.render_business(public_ids["page_premium"],"en"))
        self.assertIn("Negócio page_premium",self.render_business(public_ids["page_premium"],"pt-BR"))
        self.assertNotIn("História do negócio",self.render_business(public_ids["page_premium"],"pt-BR"))

    def test_destination_images_exist_in_the_static_asset_tree(self):
        for media in CATALOG["media"]:
            with self.subTest(path=media["path"]):
                # The shipping tree composes legacy static and backend-owned media.
                asset = self.package / "public" / media["path"]
                self.assertTrue(asset.is_file())
                sources = [ROOT / "backend/public" / media["path"], ROOT / "ensayos/v1" / media["path"]]
                source = next((path for path in sources if path.is_file()), None)
                self.assertIsNotNone(source, "Packaged media must have a repository source")
                self.assertEqual(hashlib.sha256(asset.read_bytes()).hexdigest(), hashlib.sha256(source.read_bytes()).hexdigest())
                if media["path"].startswith("assets/destinos/1/"):
                    self.assertLess(asset.stat().st_size, 350_000)

    def test_destination_router_serves_public_destination_assets(self):
        assets = (
            "/assets/destinos/1/parque-principal-ilustrado.webp",
            "/assets/destinos/1/parque-principal-ilustrado.webp",
            "/assets/destinos/1/santuario-santa-laura-ilustrado-v2.webp",
            "/assets/destinos/1/parque-principal-ilustrado.webp",
            "/assets/destinos/1/parque-principal-ilustrado.webp",
            "/assets/destinos/1/parque-principal-ilustrado.webp",
            "/assets/destinos/1/parque-principal-ilustrado.webp",
        )
        for relative in assets:
            with self.subTest(asset=relative):
                php = f"$_SERVER['REQUEST_URI']={json.dumps(relative)};include 'scripts/php-destinations-router.php';"
                result = subprocess.run(["php", "-r", php], cwd=ROOT, capture_output=True, check=True)
                self.assertTrue(result.stdout.startswith(b"RIFF"), f"The public image route must return {relative}")

    def test_business_editor_keeps_lifecycle_and_ranking_fields_out_of_creation(self):
        editor=(ROOT/"backend/public/admin/negocios.php").read_text()+(ROOT/"backend/public/_business-map-dialog.php").read_text()
        for forbidden in ('name="slug"','name="display_order"','name="claim_status"',
                          'name="operation_status"','name="information_status"',
                          'name="publication_status"','name="subscription_status"'):
            self.assertNotIn(forbidden,editor)
        self.assertIn('Guardar negocio y continuar a su suscripción',editor)
        self.assertIn('translations[es][display_name]',editor)
        self.assertIn('business-map-open',editor)
        self.assertIn('business-map-dialog',editor)
        self.assertNotIn('business-map-retry',editor)
        self.assertIn('business-map-pan-mode',editor)
        self.assertIn('business-map-mark-mode',editor)
        self.assertIn("img-src 'self' https://tile.openstreetmap.org",editor)
        self.assertIn('connect-src \'self\' https://nominatim.openstreetmap.org',editor)
        self.assertIn('https://ipwho.is',editor)
        self.assertIn('Referrer-Policy: strict-origin-when-cross-origin',editor)
        self.assertIn('../assets/leaflet.js',editor)
        self.assertIn('../assets/leaflet.css',editor)
        self.assertIn('data-lat=',editor)
        location=(ROOT/'backend/public/assets/admin-business-location.js').read_text()
        self.assertIn('openstreetmap.org',location)
        self.assertIn('nominatim.openstreetmap.org/search',location)
        self.assertIn('navigator.geolocation.getCurrentPosition',location)
        self.assertIn('https://ipwho.is/',location)
        self.assertIn('centerOnDestination',location)
        self.assertIn('Volver al destino',location)
        self.assertIn('pointInGeometry',location)
        self.assertIn('map.setMaxBounds',location)
        self.assertIn('El mapa se queda en el destino',location)
        self.assertIn("mapLayer.on('tileload'",location)
        self.assertIn('No se pudieron cargar los mapas de OpenStreetMap',location)
        self.assertIn('referrerPolicy:',location)
        form_script=(ROOT/'backend/public/assets/admin-business-form.js').read_text()
        self.assertIn('selected.text_limits',form_script)
        self.assertIn('field.maxLength=limit||10000',form_script)
        self.assertIn('business-plan',editor)
        self.assertIn('data-plan-feature="brand_presentation"',editor)
        self.assertIn('name="hours[<?=esc($day)?>][open]"',editor)
        self.assertIn('name="hours[<?=esc($day)?>][close]"',editor)
        self.assertIn('$$$$$',editor)
        self.assertIn('phone_fixed',editor)
        self.assertIn("'en'=>'English'",editor)
        self.assertIn("'pt-BR'=>'Português (Brasil)'",editor)
        self.assertIn('translations[<?=esc($locale)?>][display_name]',editor)
        router=(ROOT/'scripts/php-destinations-router.php').read_text()
        for route in ('importar-negocios','reclamaciones','reclamar-negocio.php'):
            self.assertIn(route,router)
        for script in ('admin-business-location','admin-subscriptions','admin-accounts','admin-business-form','business-claim-filter'):
            self.assertIn(script,router)


if __name__ == "__main__":
    unittest.main()
