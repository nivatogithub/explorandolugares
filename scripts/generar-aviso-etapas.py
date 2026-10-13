#!/usr/bin/env python3
"""Añade navegación y aviso permanente de piloto a las páginas españolas."""
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
  business_link=Path(os.path.relpath(site/'negocios-locales/interes/index.html',page.parent)).as_posix()
  source=re.sub(r'<!-- etapas-piloto -->.*?<!-- /etapas-piloto -->','',source,flags=re.S)
  banner='<!-- etapas-piloto --><aside class="pilot-banner" aria-label="Etapa piloto · Comenzamos en Jericó"><div class="wrap"><div><strong>Importante · Etapa piloto en Jericó</strong><span>El portal está creciendo. Explora los destinos y conoce nuestros próximos pasos.</span></div><a href="'+link+'">Conoce el portal →</a></div></aside><!-- /etapas-piloto -->'
  # En Conoce el portal el aviso duplicaría la información de la propia página.
  if not is_business and relative not in {Path('etapas/index.html'), Path('en/etapas/index.html')}:
   source=source.replace('</header>','</header>'+banner,1)
  # Enlaces del viajero antes de la llamada comercial, sin duplicarlos al regenerar.
  source=re.sub(r'<a\b[^>]*href="'+re.escape(trip_link)+r'"[^>]*>Planea tu visita</a>','',source)
  source=re.sub(r'<a\b[^>]*href="'+re.escape(link)+r'"[^>]*>(?:Etapas|Nuestro avance|Conoce el portal)</a>','',source)
  if re.search(r'<a\b[^>]*class="pill light"',source):
   source=re.sub(r'(<a\b[^>]*class="pill light"[^>]*>)',lambda m:'<a href="'+trip_link+'">Planea tu visita</a><a href="'+link+'">Conoce el portal</a>'+m[0],source,count=1)
  elif not re.search(r'<a\b[^>]*class="pill light"',source):
   source=source.replace('<span class="language-switch"','<a href="'+trip_link+'">Planea tu visita</a><a href="'+link+'">Conoce el portal</a><span class="language-switch"',1)
  # El CTA comercial siempre lleva a la solicitud, no al contenido informativo intermedio.
  if not re.search(r'<a\b[^>]*class="pill light"',source):
   source=source.replace('<span class="language-switch"','<a class="pill light" href="'+business_link+'">Registrar mi negocio</a><span class="language-switch"',1)
  else:
   source=re.sub(r'(<a\b[^>]*class="pill light"[^>]*href=")[^"]*("[^>]*>)(?:Negocios locales ↗|Conecta tu negocio ↗|Tengo un negocio ↗|Participa con tu negocio)(</a>)',r'\1'+business_link+r'\2Registrar mi negocio\3',source,count=1)
  # CTAs comerciales y footer: una sola acción y una sola denominación.
  for label in ('Conecta tu negocio ↗','Participa con tu negocio','Tengo un negocio ↗'):
   source=re.sub(r'(<a\b[^>]*href=")[^"]*("[^>]*>)'+re.escape(label)+r'(</a>)',r'\1'+business_link+r'\2Registrar mi negocio\3',source)
  page.write_text(source)
if __name__=='__main__':generate()
