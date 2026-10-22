"""Cobertura de las guías, idioma, enlaces locales y separación entre destinos."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urlsplit,unquote,parse_qs
import json,unittest,importlib.util
ROOT=Path(__file__).resolve().parents[2];SITE=ROOT/'ensayos/v1'
DATA=json.loads((ROOT/'content/destinos-piloto.json').read_text())['destinations']
class Page(HTMLParser):
 def __init__(self,path):
  super().__init__();self.links=[];self.ids=[];self.images=[];self.catalog=None;self.lang=None;self.feed(path.read_text())
 def handle_starttag(self,tag,attrs):
  d=dict(attrs)
  if 'id' in d:self.ids.append(d['id'])
  if tag=='html':self.lang=d.get('lang')
  if tag=='img':self.images.append(d)
  if d.get('id')=='catalogo-local':self.catalog=d.get('data-destino')
  for attr in ['href','src']:
   if attr in d:self.links.append(d[attr])
class Destinos(unittest.TestCase):
 def test_piloto_exactamente_21(self):
  names=['Jericó']+[d['name'] for d in DATA]
  contacts=json.loads((SITE/'assets/contact-options.json').read_text())['municipalities'];self.assertEqual(names,[n for n in contacts if n not in {'Bogotá','Cartagena','Santa Marta'}]);self.assertTrue({'Bogotá','Cartagena','Santa Marta'}.issubset(contacts))
  for lang in ['', 'en/']:
   index=(SITE/(lang+'destinos/index.html')).read_text()
   self.assertEqual(index.count('class="destination-card"'),1)
   self.assertIn('href="jerico/index.html"',index)
   for d in DATA:self.assertNotIn('href="'+d['slug']+'/index.html"',index)
 def test_paginas_bilingues_y_recursos(self):
  for lang in ['', 'en/']:
   for d in DATA:
    path=SITE/(lang+'destinos/'+d['slug']+'/index.html');page=Page(path)
    self.assertEqual(page.lang,'en' if lang else 'es');self.assertEqual(page.catalog,d['slug'])
    self.assertEqual(len(page.ids),len(set(page.ids)))
    self.assertGreaterEqual(len(page.images),3)
    text=path.read_text();self.assertIn(d['history']['en' if lang else 'es'],text)
    self.assertNotIn('{{',text)
    self.assertIn('There are no upcoming events published.' if lang else 'No hay próximos eventos publicados.',text)
    contacts=[u for u in page.links if 'negocios-locales/interes/index.html?' in u]
    self.assertTrue(contacts)
    self.assertTrue(all(parse_qs(urlsplit(u).query)['municipio']==[d['name']] for u in contacts))
 def test_todos_los_enlaces_locales(self):
  for path in SITE.rglob('*.html'):
   page=Page(path)
   for href in page.links:
    u=urlsplit(href)
    if u.scheme or u.netloc or not u.path:continue
    target=(path.parent/unquote(u.path)).resolve()
    self.assertTrue(target.is_relative_to(SITE.resolve()),(path,href))
    # Browsers/web servers resolve directory URLs such as `.` to index.html.
    if target.is_dir() and (target/'index.html').is_file():target=target/'index.html'
    if target.suffix=='.php':continue # Endpoint agregado al paquete PHP, no al servidor estático.
    route=target.relative_to(SITE.resolve()).as_posix().rstrip('/')
    dynamic_routes={'negocios/registro':'public/business/register.php','negocios/acceso':'public/business/login.php','negocios/portal':'public/business/dashboard.php','negocios/salir':'public/business/logout.php','reclamar-negocio':'public/reclamar-negocio.php'}
    if route in dynamic_routes:
     endpoint=ROOT/'backend'/dynamic_routes[route]
     self.assertTrue(endpoint.is_file(),(path,href,endpoint));continue
    if not target.is_file() and route.startswith('assets/'):
     # Shared assets are provided by the authoritative backend layer at build time.
     target=ROOT/'backend/public'/route
    self.assertTrue(target.is_file(),(path,href))
    if u.fragment and target.suffix=='.html':self.assertIn(unquote(u.fragment),Page(target).ids,(path,href))
if __name__=='__main__':unittest.main()
