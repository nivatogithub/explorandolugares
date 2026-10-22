import importlib.util,tempfile,unittest,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('coverage_languages',ROOT/'scripts/localizar-cobertura.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
class CoverageLanguages(unittest.TestCase):
 def test_real_pages_and_interaction_copy(self):
  with tempfile.TemporaryDirectory() as directory:
   public=Path(directory);page=public/'cobertura/colombia/index.html';page.parent.mkdir(parents=True);page.write_text((ROOT/'backend/public/cobertura/colombia/index.html').read_text())
   script=public/'assets/cobertura/cobertura-meta.js';script.parent.mkdir(parents=True);script.write_text((ROOT/'backend/public/assets/cobertura/cobertura-meta.js').read_text());module.apply(public)
   for language,title,label in [('en',"Every place matters.",'Published'),('pt-br','Cada lugar importa.','Publicado')]:
    text=(public/language/'cobertura/colombia/index.html').read_text();js=script.with_name('cobertura-meta-'+language+'.js')
    self.assertIn(title,text);self.assertIn('https://explorandolugares.com/'+language+'/cobertura/colombia/',text)
    self.assertIn('published:\''+label+'\'',js.read_text());self.assertNotIn('Días completos:',js.read_text());self.assertIn("root=new URL('/',location.href)",js.read_text());self.assertIn("current.hash=location.hash",js.read_text())
    subprocess.run(['node','--check',str(js)],check=True,capture_output=True)
if __name__=='__main__':unittest.main()
