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
  if '</header>' not in source:continue
  relative=page.relative_to(site)
  is_business='negocios-locales' in relative.parts
  link=Path(os.path.relpath(site/'etapas/index.html',page.parent)).as_posix()
  business_link=Path(os.path.relpath(site/'negocios-locales/index.html',page.parent)).as_posix()
  source=re.sub(r'<!-- etapas-piloto -->.*?<!-- /etapas-piloto -->','',source,flags=re.S)
  banner='<!-- etapas-piloto --><aside class="pilot-banner" aria-label="Etapa piloto · Comenzamos en Jericó"><div class="wrap"><div><strong>Importante · Etapa piloto en Jericó</strong><span>El portal está creciendo. Explora los destinos y conoce nuestros próximos pasos.</span></div><a href="'+link+'">Conoce las etapas →</a></div></aside><!-- /etapas-piloto -->'
  if not is_business:
   source=source.replace('</header>','</header>'+banner,1)
  # Enlace normal antes de la llamada comercial, sin duplicarlo al regenerar.
  source=re.sub(r'<a\b[^>]*href="'+re.escape(link)+r'"[^>]*>(?:Etapas|Nuestro avance)</a>','',source)
  if re.search(r'<a\b[^>]*class="pill light"',source):
   source=re.sub(r'(<a\b[^>]*class="pill light"[^>]*>)',lambda m:'<a href="'+link+'">Nuestro avance</a>'+m[0],source,count=1)
  else:
   source=source.replace('<span class="language-switch"','<a href="'+link+'">Nuestro avance</a><span class="language-switch"',1)
  # El acceso a Negocios locales es parte del encabezado global, incluso en páginas solo para viajeros.
  if not re.search(r'<a\b[^>]*class="pill light"',source):
   source=source.replace('<span class="language-switch"','<a class="pill light" href="'+business_link+'">Negocios locales ↗</a><span class="language-switch"',1)
  page.write_text(source)
if __name__=='__main__':generate()
