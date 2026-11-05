"""Exercise the real directory entry point with the hosting's split layout."""
from pathlib import Path
import importlib.util,json,os,shutil,sqlite3,subprocess,tempfile,unittest
ROOT=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('directory_deploy',ROOT/'scripts/desplegar-sftp.py')
deploy=importlib.util.module_from_spec(spec);spec.loader.exec_module(deploy)

class SplitDirectoryLayout(unittest.TestCase):
    def test_private_app_and_config_outside_public_root(self):
        with tempfile.TemporaryDirectory(prefix='split-directory-',dir=ROOT/'.local') as folder:
            folder=Path(folder);private=folder/'private-site';public=folder/'document-root';public.mkdir();(private/'private').mkdir(parents=True)
            shutil.copytree(ROOT/'backend/app',private/'app')
            for name in ['places.css','place-directory.css','place-directory.js','cobertura/sectors.json']:
                target=public/'assets'/name;target.parent.mkdir(parents=True,exist_ok=True);source=ROOT/('ensayos/v1/assets' if name=='places.css' else 'backend/public/assets')/name;shutil.copyfile(source,target)
            shutil.copyfile(ROOT/'backend/integration-analytics/public/places.php',public/'places.php')
            deploy.rewrite_private_app_includes(public,str(private))
            database=private/'private/test.sqlite';db=sqlite3.connect(database)
            db.executescript((ROOT/'backend/database/destinations.sqlite.sql').read_text())
            db.execute("INSERT INTO destinations(slug,country_code,department,subregion,publication_status) VALUES('layout-fixture','CO','Antioquia','Oriente','published')")
            for locale in ['es','en','pt-BR']:
                db.execute("INSERT INTO destination_translations(destination_id,locale,display_name,eyebrow,headline,intro,meta_description) VALUES(1,?,'Layout fixture','','','Synthetic content','Synthetic description')",[locale])
            db.commit();db.close()
            (private/'private/config.php').write_text('<?php return '+"['dsn'=>"+repr('sqlite:'+str(database))+",'db_user'=>null,'db_password'=>null];")
            environment={k:v for k,v in os.environ.items() if not k.startswith('EL_DESTINATIONS_')}
            for locale in ['es','en','pt-BR']:
                for query in [{},{'q':'Layout'},{'sector':'Andina'}]:
                    query={'locale':locale,**query}
                    code='$_GET=json_decode('+json.dumps(json.dumps(query))+',true);$_SERVER["REQUEST_URI"]="/lugares/";require '+json.dumps(str(public/'places.php'))+';'
                    result=subprocess.run(['php','-d','display_errors=stderr','-r',code],capture_output=True,text=True,env=environment)
                    self.assertEqual(result.returncode,0,result.stderr)
                    self.assertEqual(result.stderr,'')
                    self.assertIn('Layout fixture',result.stdout)
                    self.assertNotIn('temporalmente no disponibles',result.stdout)
                    self.assertIn('lang="'+locale+'"',result.stdout)

if __name__=='__main__':unittest.main()
