#!/usr/bin/env python3
"""Genera las guías piloto y el índice desde contenido editorial bilingüe.

Jericó conserva su página artesanal. No crea negocios ni eventos.
"""
from pathlib import Path
from html import escape
from urllib.parse import urlencode, urlsplit
import json,re
ROOT=Path(__file__).resolve().parents[1]
SITE=ROOT/'ensayos/v1'
MARKER='<!-- destino-generado-explorando -->'
def read(name):return json.loads((ROOT/'content'/name).read_text())
def registry():
 return [{'slug':'jerico','name':'Jericó','region':'Suroeste'}]+read('destinos-piloto.json')['destinations']
def validate(destinations,photos,site=SITE):
 contact=json.loads((ROOT/'backend/app/contact-options.json').read_text());active=[row['name'] for row in contact['municipalityCatalog'] if row['pilotCategory'] in contact['enabledMunicipalityCategories']]
 if [d['name'] for d in registry()]!=active:raise ValueError('Las guías deben corresponder exactamente al piloto del formulario, en el mismo orden.')
 slugs=[d['slug'] for d in destinations]
 if len(slugs)!=len(set(slugs)):raise ValueError('Rutas duplicadas')
 for d in destinations:
  if not re.fullmatch(r'[a-z0-9]+(?:-[a-z0-9]+)*',d['slug']):raise ValueError('Ruta inválida')
  for pair in [d['headline'],d['intro'],d['history'],*d['reasons'],*[p[k] for p in d['places'] for k in ['name','description']]]:
   if not all(isinstance(pair.get(lang),str) and pair[lang].strip() for lang in ['es','en']):raise ValueError('Falta contenido bilingüe: '+d['slug'])
  if not d['sources'] or not all(urlsplit(u).scheme=='https' and urlsplit(u).netloc for u in d['sources']):raise ValueError('Faltan fuentes')
  if not photos.get(d['slug']):raise ValueError('Faltan fotografías: '+d['slug'])
  for index in [d['heroPhotoIndex'], *[p['photoIndex'] for p in d['places']]]:
   if index is not None and not photo_for(d,photos,index):raise ValueError('Fotografía seleccionada inexistente: '+d['slug'])
  for p in photos[d['slug']]:
   path=(site/p['file']).resolve()
   if not path.is_relative_to((site/'assets/destinos').resolve()) or not path.is_file():raise ValueError('Fotografía inválida')
   if not p['author'] or not p['license'] or not p['source'] or not p['caption']['en']:raise ValueError('Faltan créditos o traducción de fotografía')
   if not (p['license'].startswith(('CC BY','CC0')) or p['license']=='Public domain'):raise ValueError('Licencia no admitida')
def photo_for(d,photos,index):
 if index is None:return None
 file=f"assets/destinos/{d['slug']}-{index+1}.webp"
 return next((p for p in photos[d['slug']] if p['file']==file),None)
def image(p,lazy=True):
 return '<img src="../../'+escape(p['file'],quote=True)+'" alt="'+escape(p['caption']['es'],quote=True)+f'" width="{p["width"]}" height="{p["height"]}" '+('loading="lazy" decoding="async"' if lazy else 'fetchpriority="high"')+'>'
def credit(p):
 return '<a href="'+escape(p['source'],quote=True)+'" target="_blank" rel="noopener noreferrer">'+escape(p['author'])+'</a> · <a href="'+escape(p['licenseUrl'],quote=True)+'" target="_blank" rel="noopener noreferrer">'+escape(p['license'])+'</a>'
def generate():
 data=read('destinos-piloto.json')['destinations'];photos=read('destinos-fotografias.json');validate(data,photos)
 publication=read('estado-publicacion-destinos.json')
 expected={'jerico',*[d['slug'] for d in data]}
 if set(publication)!=expected or any(status not in {'published','draft'} for status in publication.values()):raise ValueError('El estado de publicación debe definir cada destino piloto como published o draft.')
 # También atribuir archivos de reserva editorial incluidos en el paquete público.
 (SITE/'assets/destinos/creditos.json').write_text(json.dumps(photos,ensure_ascii=False,indent=2)+'\n')
 template=(ROOT/'content/plantillas/destino.html').read_text()
 cards=[]
 for d in data:
  ps=photos[d['slug']];hero=photo_for(d,photos,d['heroPhotoIndex'])
  if not hero:raise ValueError('Falta foto principal: '+d['slug'])
  used=[hero];secondary=next((p for p in ps if p!=hero and 'Cabildo' not in p['title']),None)
  history_photo=''
  if secondary:
   used.append(secondary);history_photo='<figure class="destination-history-photo">'+image(secondary)+'<figcaption>'+escape(secondary['caption']['es'])+'</figcaption></figure>'
  places=[]
  for n,p in enumerate(d['places'],1):
   pic=photo_for(d,photos,p['photoIndex']);visual=''
   if pic:
    used.append(pic);visual='<a class="highlight-photo" href="../../'+escape(pic['file'],quote=True)+'" target="_blank" rel="noopener noreferrer" aria-label="'+escape(pic['caption']['es'],quote=True)+'">'+image(pic)+'</a>'
   places.append('<article>'+visual+f'<span class="story-number">{n:02}</span><h3>'+escape(p['name']['es'])+'</h3><p>'+escape(p['description']['es'])+'</p><a class="text-link" href="https://www.google.com/maps/search/?'+escape(urlencode({'api':1,'query':p['mapQuery']}),quote=True)+'" target="_blank" rel="noopener noreferrer">Ubicar en el mapa ↗</a></article>')
  used=list({p['file']:p for p in used}.values())
  values={'nombre':escape(d['name']),'slug':d['slug'],'region':escape(d['region']),'intro_meta':escape(d['intro']['es'],quote=True),'headline':escape(d['headline']['es']),'intro':escape(d['intro']['es']),'history':escape(d['history']['es']),'hero':'<figure>'+image(hero,False)+'<figcaption class="credit">'+escape(hero['caption']['es'])+'</figcaption></figure>','history_photo':history_photo,'places':''.join(places),'reasons':''.join('<li>'+escape(r['es'])+'</li>' for r in d['reasons']),'sources':''.join('<li><a href="'+escape(u,quote=True)+'" target="_blank" rel="noopener noreferrer">'+escape(urlsplit(u).netloc)+'</a></li>' for u in d['sources']),'credits':''.join('<li>'+escape(p['caption']['es'])+': '+credit(p)+'</li>' for p in used),'contacto_url':'../../negocios-locales/interes/index.html?'+escape(urlencode({'municipio':d['name']}),quote=True)+'#contacto','mapa_query':escape(urlencode({'query':d['name']+', Antioquia, Colombia'}).split('=',1)[1],quote=True)}
  page=re.sub(r'\{\{([a-z_]+)\}\}',lambda m:values[m[1]],template)
  dest=SITE/'destinos'/d['slug']/'index.html';dest.parent.mkdir(parents=True,exist_ok=True);dest.write_text(page)
  if publication[d['slug']]=='published':cards.append(card(d,hero))
 index=(ROOT/'content/plantillas/indice-destinos.html').read_text()
 jerico={'slug':'jerico','name':'Jericó','region':'Suroeste','intro':{'es':'Cultura, café y tradición entre montañas. Conoce el destino y empieza a imaginar tu visita.'}}
 first=card(jerico,{'file':'assets/jerico.webp','caption':{'es':'Parque principal de Jericó al atardecer'},'width':800,'height':600})
 regions=list(dict.fromkeys(d['region'] for d in registry() if publication[d['slug']]=='published'))
 index=index.replace('{{cards}}',first+''.join(cards)).replace('{{regions}}',''.join('<option value="'+escape(r,quote=True)+'">'+escape(r)+'</option>' for r in regions))
 (SITE/'destinos/index.html').write_text(index)
 # Mismo destino de origen para las invitaciones de Jericó.
 jerico_page=SITE/'destinos/jerico/index.html';s=jerico_page.read_text();s=s.replace('../../negocios-locales/index.html?municipio=Jeric%C3%B3#contacto','../../negocios-locales/interes/index.html?municipio=Jeric%C3%B3#contacto').replace('../../negocios-locales/interes/index.html#contacto','../../negocios-locales/interes/index.html?municipio=Jeric%C3%B3#contacto');jerico_page.write_text(s)
 print(f'OK: {len(data)} guías preparadas; índice con {sum(1 for status in publication.values() if status=="published")} destinos publicados.')
def card(d,p):
 return '<article class="destination-card" data-region="'+escape(d['region'],quote=True)+'" data-name="'+escape(d['name'],quote=True)+'"><a class="destination-card-image" href="'+d['slug']+'/index.html"><img src="../'+escape(p['file'],quote=True)+'" alt="'+escape(p['caption']['es'],quote=True)+f'" width="{p["width"]}" height="{p["height"]}" loading="lazy" decoding="async"></a><div class="copy"><span class="badge">'+escape(d['region'])+'</span><h2><a href="'+d['slug']+'/index.html">'+escape(d['name'])+'</a></h2><p>'+escape(d['intro']['es'])+'</p><a class="text-link" href="'+d['slug']+'/index.html">Conocer el destino →</a></div></article>'
if __name__=='__main__':generate()
