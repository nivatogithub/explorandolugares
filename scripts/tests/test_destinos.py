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
  self.assertEqual(names,json.loads((SITE/'assets/contact-options.json').read_text())['municipalities'])
  for lang in ['', 'en/']:
   index=(SITE/(lang+'destinos/index.html')).read_text()
   self.assertEqual(index.count('class="destination-card"'),21)
   for slug in ['jerico']+[d['slug'] for d in DATA]:self.assertIn('href="'+slug+'/index.html"',index)
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
    if target.suffix=='.php':continue # Endpoint agregado al paquete PHP, no al servidor estático.
    self.assertTrue(target.is_file(),(path,href))
    if u.fragment and target.suffix=='.html':self.assertIn(unquote(u.fragment),Page(target).ids,(path,href))
if __name__=='__main__':unittest.main()
