#!/usr/bin/env python3
"""Mantiene navegación y retira el antiguo aviso de piloto."""
from pathlib import Path
import os,re
ROOT=Path(__file__).resolve().parents[1]
SITE=ROOT/'ensayos/v1'
def generate(site=SITE):
 for page in site.rglob('*.html'):
  if 'en' in page.relative_to(site).parts:continue
  source=page.read_text()
  # La marca se escribe en una sola línea y conserva su capitalización en todo el sitio.
  source=source.replace('<span>explorando<b>lugares</b></span>','<span>Explorando <b>Lugares</b></span>')
  if '</header>' not in source:continue
  relative=page.relative_to(site)
  is_business='negocios-locales' in relative.parts
  link=Path(os.path.relpath(site/'etapas/index.html',page.parent)).as_posix()
  trip_link=Path(os.path.relpath(site/'planea-tu-visita/index.html',page.parent)).as_posix()
  business_link=Path(os.path.relpath(site/'negocios-locales/',page.parent)).as_posix()
  business_label='Negocios locales'
  source=re.sub(r'<!-- etapas-piloto -->.*?<!-- /etapas-piloto -->','',source,flags=re.S)
  # Aviso retirado por el propietario; no reintroducirlo al regenerar.
  # Enlaces del viajero antes de la llamada comercial, sin duplicarlos al regenerar.
  source=re.sub(r'<a\b[^>]*href="'+re.escape(trip_link)+r'"[^>]*>Planea tu visita</a>','',source)
  source=re.sub(r'<a\b[^>]*href="'+re.escape(link)+r'"[^>]*>(?:Etapas|Nuestro avance|Conoce el portal)</a>','',source)
  if re.search(r'<a\b[^>]*class="pill light"',source):
   source=re.sub(r'(<a\b[^>]*class="pill light"[^>]*>)',lambda m:'<a href="'+trip_link+'">Planea tu visita</a><a href="'+link+'">Conoce el portal</a>'+m[0],source,count=1)
  elif not re.search(r'<a\b[^>]*class="pill light"',source):
   source=source.replace('<span class="language-switch"','<a href="'+trip_link+'">Planea tu visita</a><a href="'+link+'">Conoce el portal</a><span class="language-switch"',1)
  # La navegación principal lleva al espacio informativo para negocios locales;
  # la solicitud de información y la creación de cuentas son acciones distintas.
  if not re.search(r'<a\b[^>]*class="pill light"',source):
   source=source.replace('<span class="language-switch"','<a class="pill light" href="'+business_link+'">'+business_label+'</a><span class="language-switch"',1)
  else:
   source=re.sub(r'(<a\b[^>]*class="pill light"[^>]*href=")[^"]*("[^>]*>)(?:Registrar mi negocio|Negocios locales ↗|Conecta tu negocio ↗|Tengo un negocio ↗|Participa con tu negocio)(</a>)',r'\1'+business_link+r'\2'+business_label+r'\3',source,count=1)
  # Los enlaces del footer tampoco se presentan como alta de una cuenta.
  for label in ('Conecta tu negocio ↗','Participa con tu negocio','Tengo un negocio ↗'):
   source=re.sub(r'(<a\b[^>]*href=")[^"]*("[^>]*>)'+re.escape(label)+r'(</a>)',r'\1'+business_link+r'\2'+business_label+r'\3',source)
  source=re.sub(r'(<a\b[^>]*href=")[^"]*("[^>]*>)Registrar mi negocio(</a>)',r'\1'+business_link+r'\2'+business_label+r'\3',source)
  page.write_text(source)
if __name__=='__main__':generate()
