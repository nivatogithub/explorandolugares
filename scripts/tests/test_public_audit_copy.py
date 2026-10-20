import unittest,tempfile,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
class CopyTest(unittest.TestCase):
 def test_copy_and_routes_are_consistent(self):
  with tempfile.TemporaryDirectory() as t:
   base=Path(t)
   for prefix in ('','en/','pt-br/'):
    p=base/prefix/'privacidad/index.html';p.parent.mkdir(parents=True,exist_ok=True);p.write_text('<main><p>La configuración de los datos del responsable debe completarse antes de activar la recepción en el hosting.</p><section id="conteo-basico">old</section><section id="medicion-opcional">cookies old</section></main><a href="/negocios-locales/interes/">Registrar mi negocio</a><a href="/negocios-locales/interes/">Solicitar información</a></body>')
    p=base/prefix/'index.html';p.write_text('<head></head><body><section id="destinos" class="section">old</section></body>')
   command=[sys.executable,str(ROOT/'scripts/ajustar-auditoria-publica.py'),'--public',t];subprocess.run(command,check=True)
   for p in base.rglob('*.html'):
    s=p.read_text()
    if p.parent.name=='privacidad':
     self.assertNotIn('id="medicion-opcional"',s);self.assertNotIn('La configuración de los datos del responsable',s);self.assertIn('href="/negocios/registro/?tipo=business">Registrar',s);self.assertIn('href="/negocios-locales/interes/">Solicitar',s)
    else:self.assertIn('data-home-attractions',s)
   before={str(p):p.read_text() for p in base.rglob('*.html')};subprocess.run(command,check=True)
   self.assertEqual(before,{str(p):p.read_text() for p in base.rglob('*.html')})
if __name__=='__main__':unittest.main()
