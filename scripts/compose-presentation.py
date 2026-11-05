#!/usr/bin/env python3
"""Compose all page shells from the same PHP components before publication."""
from pathlib import Path
import argparse, html, re
from urllib.parse import urljoin, urlsplit, urlunsplit
ROOT=Path(__file__).resolve().parents[1]
RETIRED=['portal-identity.js','bogota-compacta.js','cartagena-compacta.js','medellin-compacta.js','santa-marta-compacta.js','place-reading.js','nomenclatura-lugares.js','language-navigation.js','home-attractions.js','portada.js']
SHELL=re.compile(r'<header\b[^>]*>.*?</header>',re.S)
FOOTER=re.compile(r'<footer\b[^>]*>.*?</footer>',re.S)
def canonical_body_links(text, route):
 """Emit canonical directory links, not aliases that waste an extra crawl hop."""
 def replace(match):
  value=html.unescape(match['url'])
  resolved=urlsplit(urljoin('https://explorandolugares.com'+route,value))
  if resolved.scheme!='https' or resolved.netloc!='explorandolugares.com' or not resolved.path.endswith('/index.html'):
   return match[0]
  value=urlunsplit(('', '', resolved.path.removesuffix('index.html'),resolved.query,resolved.fragment))
  return match['start']+html.escape(value,quote=True)+match['quote']
 return re.sub(r'(?P<start><a\b[^>]*?\bhref=(?P<quote>["\x27]))(?P<url>[^"\x27]*)(?P=quote)',replace,text,flags=re.I)

def apply(public):
 public=Path(public);pages=[]
 for path in sorted(public.rglob('*.html')):
  # Migrated informational pages render their shared shell directly.
  if re.fullmatch(r'(?:en/|pt-br/)?cobertura/colombia/index\.html',path.relative_to(public).as_posix()):continue
  if re.fullmatch(r'(?:en/|pt-br/)?negocios-locales/(?:interes|impulsor)/index\.html',path.relative_to(public).as_posix()):continue
  if re.fullmatch(r'(?:en/|pt-br/)?negocios-locales/index\.html',path.relative_to(public).as_posix()):continue
  if path.relative_to(public).as_posix() in {'index.html','en/index.html','pt-br/index.html'}:continue
  if re.fullmatch(r'(?:en/|pt-br/)?negocios-locales/suscripciones/index\.html',path.relative_to(public).as_posix()):continue
  if re.fullmatch(r'(?:en/|pt-br/)?negocios-locales/proceso/index\.html',path.relative_to(public).as_posix()):continue
  if re.fullmatch(r'(?:en/|pt-br/)?privacidad/index\.html',path.relative_to(public).as_posix()):continue
  if re.fullmatch(r'(?:en/|pt-br/)?negocios-locales/(?:por-que-participar|impulsa-tu-destino)/index\.html',path.relative_to(public).as_posix()):continue
  if path.relative_to(public).as_posix() in {'etapas/index.html','en/etapas/index.html','pt-br/etapas/index.html','planea-tu-visita/index.html','en/planea-tu-visita/index.html','pt-br/planea-tu-visita/index.html'}:continue
  text=path.read_text()
  if SHELL.search(text):pages.append(path.relative_to(public).as_posix())
 if pages:
  raise ValueError('La entrega contiene páginas HTML que aún requieren el shell compartido: '+', '.join(sorted(pages)))
 # PHP pages must render the shared shell directly; fail instead of rewriting them.
 for path in public.rglob('*.php'):
  if path.name.startswith('_') or path.relative_to(public).parts[0]=='admin':continue
  if SHELL.search(path.read_text()):
   raise ValueError('La página PHP debe invocar directamente el shell compartido: '+str(path.relative_to(public)))
 print('Shell compartido verificado sin recomposición de páginas')
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--public',type=Path,required=True);apply(p.parse_args().public)
