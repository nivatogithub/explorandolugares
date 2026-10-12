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
  link=Path(os.path.relpath(site/'etapas/index.html',page.parent)).as_posix()
  source=re.sub(r'<!-- etapas-piloto -->.*?<!-- /etapas-piloto -->','',source,flags=re.S)
  banner='<!-- etapas-piloto --><aside class="pilot-banner" aria-label="Etapa piloto · Comenzamos en Jericó"><div class="wrap"><div><strong>Importante · Etapa piloto en Jericó</strong><span>El portal está creciendo. Explora los destinos y conoce nuestros próximos pasos.</span></div><a href="'+link+'">Conoce las etapas →</a></div></aside><!-- /etapas-piloto -->'
  source=source.replace('</header>','</header>'+banner,1)
  # Enlace normal antes de la llamada comercial, sin duplicarlo al regenerar.
  source=re.sub(r'<a\b[^>]*href="'+re.escape(link)+r'"[^>]*>(?:Etapas|Nuestro avance)</a>','',source)
  source=re.sub(r'(<a\b[^>]*class="pill light"[^>]*>)',lambda m:'<a href="'+link+'">Nuestro avance</a>'+m[0],source,count=1)
  page.write_text(source)
if __name__=='__main__':generate()
