"""Protección de publicación, rutas y escape de contenido de participantes."""
import importlib.util,json,tempfile,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('fichas',ROOT/'scripts/generar-fichas.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class Fichas(unittest.TestCase):
 def setUp(self):
  self.temp=tempfile.TemporaryDirectory();self.site=Path(self.temp.name)
  self.record=dict(destino='jerico',registroConfirmado=True,informacionVerificada=True,estado='publicado',slug='negocio-de-prueba',nombre='<script>prueba</script>',tipoServicio='hospedaje',descripcion='Solo datos ficticios de prueba.',descripcionEn='Fictional test data only.',contactoUrl='https://example.invalid/contacto')
 def tearDown(self):self.temp.cleanup()
 def test_publicacion_y_retiro(self):
  self.assertEqual(m.generate({'publicaciones':[self.record]},self.site),1)
  p=self.site/'destinos/jerico/lugares/negocio-de-prueba/index.html';html=p.read_text()
  self.assertIn('&lt;script&gt;prueba&lt;/script&gt;',html);self.assertNotIn('<script>prueba</script>',html)
  self.assertIn('href="../../index.html"',html);self.assertIn('href="../../../../index.html"',html)
  unrelated=p.parent/'manual.html';unrelated.write_text('No generado')
  self.assertEqual(m.generate({'publicaciones':[{**self.record,'estado':'borrador'}]},self.site),0)
  self.assertFalse(p.exists());self.assertTrue(unrelated.exists())
 def test_bloquea_sin_verificacion(self):
  for changes in [{'registroConfirmado':False},{'informacionVerificada':False},{'slug':'../../otro'},{'descripcionEn':''},{'tipoServicio':'inventado'}]:
   self.assertEqual(m.generate({'publicaciones':[{**self.record,**changes}]},self.site),0)
 def test_bloquea_contacto_inseguro(self):
  with self.assertRaises(ValueError):m.generate({'publicaciones':[{**self.record,'contactoUrl':'javascript:alert(1)'}]},self.site)
 def test_mismo_slug_en_destinos_distintos(self):
  second={**self.record,'destino':'medellin'}
  self.assertEqual(m.generate({'publicaciones':[self.record,second]},self.site),2)
  jerico=self.site/'destinos/jerico/lugares/negocio-de-prueba/index.html'
  medellin=self.site/'destinos/medellin/lugares/negocio-de-prueba/index.html'
  self.assertIn('Medellín',medellin.read_text());self.assertNotIn('Jericó',medellin.read_text())
  m.generate({'publicaciones':[second]},self.site)
  self.assertFalse(jerico.exists());self.assertTrue(medellin.exists())
 def test_destino_fuera_del_piloto(self):
  self.assertEqual(m.generate({'publicaciones':[{**self.record,'destino':'envigado'}]},self.site),0)
 def test_no_duplicar_rutas(self):
  with self.assertRaises(ValueError):m.generate({'publicaciones':[self.record,self.record]},self.site)
if __name__=='__main__':unittest.main()
