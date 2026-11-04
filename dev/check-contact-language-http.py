#!/usr/bin/env python3
"""Check dev1 contact navigation without submitting requests."""
from urllib.request import urlopen
from html.parser import HTMLParser
class Page(HTMLParser):
 def __init__(self):super().__init__();self.main=False;self.links=[];self.title=False;self.t=''
 def handle_starttag(self,t,attrs):
  if t=='main':self.main=True
  if t=='title':self.title=True
  if t=='a' and self.main:self.links.append(dict(attrs).get('href'))
 def handle_endtag(self,t):
  if t=='main':self.main=False
  if t=='title':self.title=False
 def handle_data(self,t):
  if self.title:self.t+=t
for locale,title in [('es','Contacto y ayuda'),('en','Contact and help'),('pt-br','Contato e ajuda')]:
 p=Page();p.feed(urlopen('http://127.0.0.1:8086/contact.php?lang='+locale,timeout=10).read().decode());prefix='' if locale=='es' else '/'+locale
 assert p.t.startswith(title),(locale,p.t)
 for route in ('/','/negocios-locales/interes/','/negocios-locales/impulsor/','/privacidad/'):
  assert prefix+route in p.links,(locale,route,p.links)
  with urlopen('http://127.0.0.1:8086'+prefix+route,timeout=10) as response:assert response.status==200
 print(locale,'contacto y cuatro enlaces: OK')
