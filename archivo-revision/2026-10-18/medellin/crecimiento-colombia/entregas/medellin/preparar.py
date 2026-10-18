"""Rebuild private editorial draft and evidence; never writes public assets."""
from pathlib import Path
import json, hashlib, shutil, html
import PIL
from PIL import Image, ImageOps
ROOT=Path(__file__).resolve().parent
REPO=ROOT.parents[2]
DATE='2026-10-16'
def save(name,data):
 (ROOT/name).write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
sources=[
 ('historia','https://www.medellin.gov.co/es/historia-y-simbolos-de-medellin/','Alcaldía de Medellín','Erección como Villa el 2 de noviembre de 1675; comercio, industria y patrimonio artístico.'),
 ('turismo','https://www.medellin.travel/','Guía oficial de turismo','Destino Medellín; orientación para visitantes.'),
 ('botanico','https://www.botanicomedellin.org/','Jardín Botánico','Colecciones vivas, programación educativa y cultural. Fotografías comerciales requieren consultar permisos del recinto.'),
 ('antioquia','https://museodeantioquia.co/','Museo de Antioquia','Salas de Fernando Botero y Débora Arango; Plaza Botero; tienda y visita.'),
 ('explora','https://www.parqueexplora.org/en','Parque Explora','Museo de ciencias, planetario y acuario; programación y visita.'),
 ('mamm','https://www.elmamm.org/','MAMM','Museo de Arte Moderno de Medellín; agenda cultural.'),
 ('metro','https://www.metrodemedellin.gov.co/','Metro de Medellín','Información oficial de transporte y servicios.'),
 ('dann','https://www.danncarlton.com/','Dann Carlton Medellín','Hotel en Medellín y oferta gastronómica propia.'),
 ('cielo','https://www.elcielohotel.com/','Elcielo Hotel','Hotel en Astorga, El Poblado; Elcielo Restaurant, Bistró y La Sere identificados en su sitio.'),
 ('fernando','https://www.hotelsanfernandoplaza.com/','Hotel San Fernando Plaza','Hotel en El Poblado, Medellín.')]
save('fuentes.json',[dict(id=i,url=u,editor=e,consultado=DATE,observacion=o,metodo='Lectura web de fuente primaria; resumen propio, sin reutilización de imágenes del sitio') for i,u,e,o in sources])
texts={
 'es':dict(display_name='Medellín',eyebrow='Antioquia · Valle de Aburrá',headline='Arte, ciencia y vida urbana en Medellín',intro='Medellín reúne espacios para acercarse al arte, la ciencia y la vida de una ciudad del valle de Aburrá. El Museo de Antioquia, el Jardín Botánico, Parque Explora y el MAMM permiten construir una visita con intereses distintos. Esta guía propone combinar espacios culturales y tiempo para conocer la oferta local.',history=['El 2 de noviembre de 1675 se estableció la Villa de Nuestra Señora de la Candelaria de Medellín. Su historia posterior estuvo ligada al comercio y al desarrollo industrial.','La producción de artistas como Débora Arango y Fernando Botero forma parte del patrimonio cultural de la ciudad. Visitar sus museos permite acercarse a miradas diversas sobre la sociedad y su historia.'],practical=['Consulta horarios, tarifas y programación directamente con cada recinto antes de viajar; esta entrega no fija precios ni garantiza disponibilidad.','Planea la movilidad con la información oficial del Metro. No todos los atractivos están en una misma zona: separa las visitas por sectores.','La accesibilidad depende del recinto y del recorrido; confirma necesidades concretas con el operador. No se garantiza accesibilidad universal.'],reasons=['Arte en museos y espacio público','Ciencia y educación ambiental','Gastronomía y alojamientos con canales directos']),
 'en':dict(display_name='Medellín',eyebrow='Antioquia · Aburrá Valley',headline='Art, science and urban life in Medellín',intro='Medellín offers places to explore art, science and daily life in the Aburrá Valley. Museo de Antioquia, the Botanical Garden, Parque Explora and MAMM provide different starting points for a visit. This guide suggests combining cultural venues with time to discover local services.',history=['The Villa of Nuestra Señora de la Candelaria de Medellín was established on 2 November 1675. Trade and industrial development shaped its later history.','Artists including Débora Arango and Fernando Botero are part of the city’s cultural heritage. Its museums offer different perspectives on society and history.'],practical=['Check opening hours, prices and programmes with each venue before travelling; this draft does not guarantee availability.','Plan journeys using official Metro information. Attractions are spread across different districts, so group visits by area.','Accessibility varies by venue and route; confirm specific requirements directly with the operator. Universal access is not guaranteed.'],reasons=['Art in museums and public spaces','Science and environmental education','Food and accommodation with direct official channels']),
 'pt-BR':dict(display_name='Medellín',eyebrow='Antioquia · Vale do Aburrá',headline='Arte, ciência e vida urbana em Medellín',intro='Medellín reúne espaços para conhecer a arte, a ciência e o cotidiano de uma cidade do vale do Aburrá. O Museo de Antioquia, o Jardim Botânico, o Parque Explora e o MAMM oferecem diferentes pontos de partida. A proposta é combinar espaços culturais com tempo para descobrir os serviços locais.',history=['Em 2 de novembro de 1675 foi estabelecida a Vila de Nuestra Señora de la Candelaria de Medellín. O comércio e o desenvolvimento industrial marcaram sua história posterior.','Artistas como Débora Arango e Fernando Botero fazem parte do patrimônio cultural da cidade. Seus museus permitem conhecer diferentes olhares sobre a sociedade e a história.'],practical=['Consulte horários, preços e programação diretamente com cada espaço antes de viajar; este rascunho não garante disponibilidade.','Planeje os trajetos com as informações oficiais do Metrô. Os atrativos ficam em diferentes setores; organize as visitas por região.','A acessibilidade depende do espaço e do percurso; confirme necessidades específicas com o operador. Não há garantia de acessibilidade universal.'],reasons=['Arte em museus e espaços públicos','Ciência e educação ambiental','Gastronomia e hospedagem com canais oficiais diretos'])}
pois=[('museo-antioquia','Museo de Antioquia','antioquia',['Arte e historia regional con salas dedicadas a Fernando Botero y Débora Arango.','Art and regional history, including rooms dedicated to Fernando Botero and Débora Arango.','Arte e história regional, com salas dedicadas a Fernando Botero e Débora Arango.']),('jardin-botanico','Jardín Botánico Joaquín Antonio Uribe','botanico',['Colecciones vivas y actividades de educación ambiental; consulta la programación oficial.','Living collections and environmental education; check the official programme.','Coleções vivas e educação ambiental; consulte a programação oficial.']),('parque-explora','Parque Explora','explora',['Museo de ciencias con acuario y propuesta de aprendizaje interactivo.','Science museum with an aquarium and interactive learning.','Museu de ciências com aquário e aprendizagem interativa.']),('mamm','Museo de Arte Moderno de Medellín','mamm',['Arte moderno y programación cultural; revisa las exposiciones disponibles antes de ir.','Modern art and cultural programmes; check available exhibitions before visiting.','Arte moderna e programação cultural; confira as exposições antes da visita.'])]
source_urls={i:u for i,u,_,_ in sources}
places=[]
for slug,name,s,desc in pois:
 places.append(dict(slug=slug,type='place',publication_status='draft',source_ids=[s],translations={lang:dict(name=name,description=desc[n],external_url=source_urls[s],external_label='Sitio oficial') for n,lang in enumerate(texts)}))
businesses=[]
for name,cat,s,description in [('Dann Carlton Medellín','alojamiento','dann','Hotel con sitio oficial propio.'),('Elcielo Hotel','alojamiento','cielo','Hotel en Astorga, El Poblado.'),('Hotel San Fernando Plaza','alojamiento','fernando','Hotel en El Poblado.'),('Elcielo Restaurant Medellín','gastronomia','cielo','Restaurante identificado en el sitio del hotel.'),('El Bistró by Juanma','gastronomia','cielo','Oferta gastronómica identificada en el sitio del hotel.'),('La Sere by Juanma','gastronomia','cielo','Restaurante italiano identificado en el sitio del hotel.'),('Museo de Antioquia','cultura','antioquia','Institución cultural; ficha informativa, sin alianza.'),('Jardín Botánico Joaquín Antonio Uribe','cultura','botanico','Institución botánica y cultural; ficha informativa.'),('Parque Explora','cultura','explora','Museo de ciencias; ficha informativa.'),('Museo de Arte Moderno de Medellín','cultura','mamm','Institución de arte moderno; ficha informativa.')]:
 businesses.append(dict(name=name,category=cat,description=description,url=source_urls[s],source_ids=[s],consulted_at=DATE,publication_status='draft',listing_plan='gratuito',data_status='PUBLICO',verification='pendiente_verificar_vigencia',claimed=False,operating_status='no_confirmado',relationship='ninguna',contacted=False))
save('directorio.json',businesses)
experiences=[dict(id=p['slug']+'-visita',source_ids=p['source_ids'],operator=p['translations']['es']['name'],booking_url=p['translations']['es']['external_url'],status='borrador',translations={lang:dict(name=p['translations'][lang]['name'],description=p['translations'][lang]['description']) for lang in texts}) for p in places]
save('experiencias.json',experiences)
save('contenido.json',dict(destination_id='CO-05-medellin',status='draft',translations=texts,places=places,experiences=experiences,claim_sources={'intro':['turismo','antioquia','botanico','explora','mamm'],'history':['historia'],'practical':['metro','botanico','antioquia','explora','mamm']}))
catalog=json.loads((REPO/'content/destinos-dinamicos.json').read_text())
def walk(v):
 if isinstance(v,dict):
  if v.get('path','') in ['assets/destinos/medellin-1.webp','assets/destinos/medellin-3.webp','assets/destinos/medellin-4.webp'] and 'license_name' in v: yield v
  for x in v.values(): yield from walk(x)
 elif isinstance(v,list):
  for x in v: yield from walk(x)
assets=[]
(ROOT/'images').mkdir(exist_ok=True)
for a in walk(catalog):
 target=ROOT/'images'/Path(a['path']).name
 original_name={'medellin-1.webp':'picacho.jpg','medellin-3.webp':'orquideorama.jpg','medellin-4.webp':'poblado.jpg'}[target.name]
 original=ROOT/'originales'/original_name
 with Image.open(original) as im:
  im.verify()
 with Image.open(original) as im:
  image=ImageOps.exif_transpose(im).convert('RGB')
  image.thumbnail((1600,1600) if target.name=='medellin-1.webp' else (800,800),Image.Resampling.LANCZOS)
  image.save(target,'WEBP',quality=82,method=6)
 assets.append(dict(file='images/'+target.name,author=a['author'],source_url=a['source_url'],license=a['license_name'],license_url=a['license_url'],commercial_use=True,adaptation=True,consulted_at=DATE,license_evidence='Commons: descripción, autor y sección Licensing consultados el 16/10/2026; CC BY-SA o liberación mundial dominio público.',translations=a['translations'],sha256=hashlib.sha256(target.read_bytes()).hexdigest(),origin='derivado histórico existente, no original',original_downloaded=False,conversion_parameters=None,review_status='pendiente',notes='No demuestra estado actual. No se regenera sin recuperar maestro. No ampliar ni atribuir parámetros desconocidos.'))
for a in assets:
 original_name={'medellin-1.webp':'picacho.jpg','medellin-3.webp':'orquideorama.jpg','medellin-4.webp':'poblado.jpg'}[Path(a['file']).name]
 original=ROOT/'originales'/original_name
 with Image.open(ROOT/a['file']) as im: dimensions=list(im.size)
 a.update(origin='fotografia real, derivado del original recuperado',original_file='originales/'+original_name,original_downloaded=True,original_sha256=hashlib.sha256(original.read_bytes()).hexdigest(),bytes=(ROOT/a['file']).stat().st_size,dimensions=dimensions,conversion_parameters=dict(tool='Pillow',version=PIL.__version__,orientation='exif_transpose',color='RGB/sRGB fuente',format='WEBP',quality=82,method=6,resize='thumbnail LANCZOS; máximo 1600 portada/800 tarjeta; sin ampliar ni recortar',metadata='EXIF/GPS omitidos del derivado'),notes='Fotografía histórica; no prueba de condiciones actuales. Derivado bajo la misma licencia del original, con crédito, enlace y declaración de cambios.')
save('imagenes.json',assets)
pkg=json.loads((REPO/'ensayos/v1/plantillas/paquete-destino-v2.json').read_text())
pkg['destination'].update(slug='medellin',department='Antioquia',subregion='Valle de Aburrá',translations={lang:{k:v for k,v in t.items() if k in ['display_name','eyebrow','headline','intro']}|{'meta_description':t['intro'][:150]} for lang,t in texts.items()},history={lang:t['history'] for lang,t in texts.items()},reasons={lang:t['reasons'] for lang,t in texts.items()},sources={lang:[dict(label=e,url=u) for _,u,e,_ in sources[:7]] for lang in texts})
pkg['hero_image']=dict(file=assets[0]['file'],image_origin='external',image_creator=assets[0]['author'],image_source_url=assets[0]['source_url'],image_license=assets[0]['license'],image_license_url=assets[0]['license_url'],alt={lang:a['alt'] for lang,a in assets[0]['translations'].items()},caption={lang:a['caption'] for lang,a in assets[0]['translations'].items()})
pkg['places']=places
pkg['local_research']=dict(schema_version=1,researched_at=DATE,positioning='Arte, ciencia y servicios urbanos; borrador.',competitors=[],potential_allies=[],business_prospects=businesses,acquisition_strategies=[],sources=[dict(url=u,label=e) for _,u,e,_ in sources],notes=['No contacto, alianza, publicación ni aprobación.'])
save('paquete-destino-v2.borrador.json',pkg)
out=['<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Medellín — borrador privado</title><style>body{font:18px system-ui;max-width:1050px;margin:25px auto;padding:20px;line-height:1.6}img{max-width:100%;height:auto}article{padding:18px;border:1px solid #aaa;margin:15px 0}small{display:block}h1{line-height:1.2}</style><body><h1>Medellín · borrador privado</h1><p>Sin aprobación ni publicación. Fotografías históricas con originales y derivados trazables. No es la plantilla pública final.</p>']
for a in assets:
 out.append('<figure><img src="'+a['file']+'" alt="'+html.escape(a['translations']['es']['alt'])+'"><figcaption>'+html.escape(a['author'])+' · <a href="'+a['source_url']+'">Fuente</a> · <a href="'+a['license_url']+'">'+a['license']+'</a>. Cambios: orientación, reducción y WebP; sin recorte. Derivado bajo la licencia indicada.</figcaption></figure>')
for lang,t in texts.items():
 out.append('<section lang="'+lang+'"><h2>'+lang+' · '+t['headline']+'</h2><p>'+t['intro']+'</p><h3>Historia / History / História</h3>')
 for p in t['history']+t['practical']:out.append('<p>'+p+'</p>')
 for p in places:out.append('<article><h3>'+p['translations'][lang]['name']+'</h3><p>'+p['translations'][lang]['description']+'</p><a href="'+p['translations'][lang]['external_url']+'">Fuente / Source</a></article>')
 out.append('</section>')
out.append('<h2>Directorio gratuito propuesto</h2><p>Información empresarial pública, sin vigencia comprobada, reclamación ni alianza.</p>')
for b in businesses:out.append('<article><a href="'+b['url']+'">'+b['name']+'</a> · '+b['category']+'<p>'+b['description']+'</p></article>')
out.append('<h2>Fuentes consultadas · '+DATE+'</h2>')
for _,u,e,o in sources:out.append('<p><a href="'+u+'">'+e+'</a>: '+o+'</p>')
(ROOT/'preview.html').write_text('\n'.join(out)+'</body></html>')
print('Borrador creado: 3 idiomas, 4 atractivos, 4 experiencias, 10 fichas, '+str(len(assets))+' candidatos fotográficos.')
