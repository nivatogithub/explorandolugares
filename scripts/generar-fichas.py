#!/usr/bin/env python3
"""Genera fichas de destinos piloto solo a partir de participantes publicados y verificados."""
from pathlib import Path
from html import escape
from urllib.parse import urlsplit
import argparse,json,re
ROOT=Path(__file__).resolve().parents[1]
MARKER='<!-- ficha-generada-explorando -->'
TYPES={item['value']:item for item in json.loads((ROOT/'content/service-categories.json').read_text())['categories']}
DESTINATIONS={'jerico':'Jericó', **{d['slug']:d['name'] for d in json.loads((ROOT/'content/destinos-piloto.json').read_text())['destinations']}}
def eligible(item):
 return isinstance(item,dict) and item.get('destino') in DESTINATIONS and item.get('registroConfirmado') is True and item.get('informacionVerificada') is True and item.get('estado')=='publicado' and isinstance(item.get('slug'),str) and re.fullmatch(r'[a-z0-9]+(?:-[a-z0-9]+)*',item['slug']) and item.get('tipoServicio') in TYPES and all(isinstance(item.get(k),str) and item[k].strip() for k in ['nombre','descripcion','descripcionEn'])
def generate(catalog,site):
 items=[item for item in catalog.get('publicaciones',[]) if eligible(item)]
 slugs=[(i['destino'],i['slug']) for i in items]
 if len(slugs)!=len(set(slugs)):raise ValueError('Slugs duplicados; no se generaron fichas.')
 template=(ROOT/'content/plantillas/ficha-participante.html').read_text()
 active=set()
 for item in items:
  category=TYPES[item['tipoServicio']]
  item={**item,'categoria':category['label'],'categoriaEn':category['en']}
  def e(key):return escape(item.get(key,''),quote=True)
  image=''
  if item.get('imagen'):
   relative=Path(item['imagen']);path=(site/relative).resolve()
   if relative.is_absolute() or not path.is_relative_to((site/'assets').resolve()) or not path.is_file():raise ValueError('Imagen fuera de assets o inexistente')
   image='<img class="participant-photo" src="../../../../'+escape(relative.as_posix(),quote=True)+'" alt="'+e('nombre')+'">'
  services=''
  if item.get('servicios'):
   if not isinstance(item['servicios'],list) or not all(isinstance(x,str) for x in item['servicios']):raise ValueError('Servicios inválidos')
   if not isinstance(item.get('serviciosEn'),list) or len(item['servicios'])!=len(item['serviciosEn']):raise ValueError('Falta traducción de servicios')
   services='<h3>Qué ofrece</h3><ul>'+''.join('<li>'+escape(x)+'</li>' for x in item['servicios'])+'</ul>'
  details=''
  for key,label in [('direccion','Ubicación'),('horarios','Horarios')]:
   if item.get(key):details+='<div><h3>'+label+'</h3><p>'+e(key)+'</p></div>'
  details='<section class="participant-facts">'+details+'</section>' if details else ''
  contact=''
  url=item.get('contactoUrl','');parsed=urlsplit(url)
  if url:
   if parsed.scheme not in {'https','http'} or not parsed.netloc or parsed.username or parsed.password:raise ValueError('URL de contacto inválida')
   contact='<a class="pill" href="'+escape(url,quote=True)+'" target="_blank" rel="noopener noreferrer">Contactar al negocio ↗</a>'
  if not contact:raise ValueError('Falta un canal de contacto verificado')
  values={'destino_nombre':escape(DESTINATIONS[item['destino']]),'nombre':e('nombre'),'categoria':e('categoria'),'descripcion_meta':escape(item['descripcion'][:160],quote=True),'descripcion':e('descripcion'),'imagen':image,'servicios':services,'datos':details,'contacto':contact}
  html=re.sub(r'\{\{([a-z_]+)\}\}',lambda m:values[m[1]],template)+MARKER
  dest=site/'destinos'/item['destino']/'lugares'/item['slug']/'index.html';dest.parent.mkdir(parents=True,exist_ok=True);dest.write_text(html);active.add(dest.resolve())
 # Retirar únicamente fichas que este generador creó y ya no están publicadas.
 for p in (site/'destinos').glob('*/lugares/*/index.html'):
  if p.resolve() not in active and MARKER in p.read_text():
   p.unlink();en=site/'en'/p.relative_to(site)
   if en.is_file() and MARKER in en.read_text():en.unlink()
 return len(items)
if __name__=='__main__':
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--catalog',type=Path,default=ROOT/'ensayos/v1/assets/catalogo.json');parser.add_argument('--site',type=Path,default=ROOT/'ensayos/v1');args=parser.parse_args()
 print(f'OK: {generate(json.loads(args.catalog.read_text()),args.site)} fichas publicadas generadas.')
