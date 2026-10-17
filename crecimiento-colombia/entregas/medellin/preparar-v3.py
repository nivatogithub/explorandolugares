"""Exact eight-place private package, after coordinator downloads and visual inspection."""
from pathlib import Path
import json,hashlib,zipfile,subprocess,html
from PIL import Image,ImageOps
import PIL
r=Path(__file__).resolve().parent
def read(n):return json.loads((r/n).read_text())
def save(n,v):(r/n).write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
selection=read('seleccion-principal-v3.json');content=read('contenido.json');downloads=read('descargas-v3.json')
inspection=read('inspeccion-visual-v3.json')
assert inspection['cover_silhouette_verified'] is True,'Portada distante sin verificar; no emitir paquete como listo.'
assert all(inspection['images'][x['id']]['usable'] for x in downloads)
texts={
 'parque-explora':[
 'Parque Explora acerca la ciencia al visitante mediante espacios interactivos y un acuario. Es una visita para explorar preguntas y dedicar tiempo a las salas, sin reducirla a una parada fotográfica. Consulta la programación y las condiciones de entrada con el operador antes de ir.',
 'Parque Explora introduces visitors to science through interactive spaces and an aquarium. Allow time to explore its rooms and questions rather than treating it as a photo stop. Check the programme and entry arrangements directly with the operator.',
 'O Parque Explora aproxima o visitante da ciência por meio de espaços interativos e um aquário. Reserve tempo para explorar as salas e suas perguntas, em vez de fazer apenas uma parada para fotos. Consulte a programação e as condições de entrada diretamente com o operador.'],
 'pueblito-paisa':[
 'En la cima del Cerro Nutibara, Pueblito Paisa recrea la arquitectura de un pueblo tradicional antioqueño y ofrece vistas de Medellín. La plaza y sus construcciones forman un conjunto turístico, no un municipio colonial conservado. Confirma las condiciones de acceso al cerro y planea el recorrido según el tiempo y tu movilidad.',
 'At the top of Cerro Nutibara, Pueblito Paisa recreates the architecture of a traditional Antioquian town and offers views of Medellín. Its square and buildings form a visitor attraction, rather than a preserved colonial municipality. Check access conditions and plan around the weather and your mobility needs.',
 'No alto do Cerro Nutibara, o Pueblito Paisa recria a arquitetura de um povoado tradicional antioquenho e oferece vistas de Medellín. A praça e suas construções formam um conjunto turístico, não um município colonial preservado. Confirme o acesso e planeje a visita conforme o clima e suas necessidades de mobilidade.'],
 'jardin-botanico':[
 'El Jardín Botánico Joaquín Antonio Uribe combina colecciones vivas con educación ambiental y programación cultural. El Orquideorama destaca por su estructura arquitectónica que se abre sobre el jardín. Consulta las actividades y condiciones de visita directamente con el Jardín.',
 'Jardín Botánico Joaquín Antonio Uribe combines living collections with environmental education and cultural programmes. The Orquideorama stands out for its architectural structure opening over the garden. Check activities and visiting arrangements directly with the garden.',
 'O Jardín Botánico Joaquín Antonio Uribe reúne coleções vivas, educação ambiental e programação cultural. O Orquideorama se destaca por sua estrutura arquitetônica que se abre sobre o jardim. Consulte as atividades e condições de visita diretamente com o jardim.'],
 'comuna13':[
 'La Comuna 13 reúne iniciativas comunitarias que relacionan arte urbano, memoria y vida de barrio. El circuito turístico no representa todo San Javier: define el recorrido y considera un operador comunitario con información verificable. Respeta a los residentes y sus relatos; consulta el recorrido y la programación con un operador comunitario.',
 'Comuna 13 is home to community initiatives connecting urban art, memory and neighbourhood life. The visitor circuit does not represent all of San Javier: define the route and consider a community operator with verifiable information. Respect residents and their stories; check the route and programme with a community operator.',
 'A Comuna 13 reúne iniciativas comunitárias que relacionam arte urbana, memória e cotidiano do bairro. O circuito turístico não representa toda San Javier: defina o percurso e considere um operador comunitário com informações verificáveis. Respeite os moradores e seus relatos; consulte o percurso e a programação com um operador comunitário.'],
 'museo-antioquia':[
 'El Museo de Antioquia permite acercarse al arte y a historias de la región mediante sus colecciones y exposiciones. Su oferta incluye salas relacionadas con Fernando Botero y Débora Arango. La vecina Plaza Botero complementa la visita al centro, pero las obras exteriores y las salas del museo son espacios diferentes; consulta entradas y programación.',
 'Museo de Antioquia offers an introduction to regional art and history through its collections and exhibitions, including rooms associated with Fernando Botero and Débora Arango. Nearby Plaza Botero complements a downtown visit. Outdoor works and museum galleries are separate spaces; check tickets and programmes.',
 'O Museo de Antioquia apresenta arte e histórias da região por meio de coleções e exposições, incluindo salas relacionadas a Fernando Botero e Débora Arango. A vizinha Plaza Botero complementa a visita ao centro. As obras externas e as salas do museu são espaços diferentes; consulte ingressos e programação.'],
 'arvi':[
 'Parque Arví ofrece una aproximación al paisaje rural de Santa Elena mediante senderos y actividades de naturaleza. La Corporación Parque Arví publica opciones guiadas y autoguiadas y orientación para visitantes. Confirma el recorrido específico y las condiciones de acceso con la Corporación antes de salir.',
 'Parque Arví introduces the rural landscape of Santa Elena through trails and nature activities. Corporación Parque Arví publishes guided and self-guided options and visitor information. Confirm your specific route and access arrangements with the corporation before setting out.',
 'O Parque Arví permite conhecer a paisagem rural de Santa Elena por meio de trilhas e atividades de natureza. A Corporación Parque Arví divulga opções guiadas e autoguiadas e informações para visitantes. Confirme o percurso específico e as condições de acesso com a corporação antes de sair.'],
 'catedral-metropolitana':[
 'La Catedral Basílica Metropolitana, junto al Parque Bolívar, es un referente del patrimonio religioso y arquitectónico del centro. Es distinta de la Basílica de Nuestra Señora de la Candelaria, situada en el entorno del Parque Berrío. Se propone conocer su exterior y consultar las condiciones de visita interior, respetando los momentos de culto.',
 'The Metropolitan Cathedral Basilica, beside Parque Bolívar, is a landmark of downtown religious and architectural heritage. It is distinct from the Basilica of Nuestra Señora de la Candelaria near Parque Berrío. Explore the exterior and check arrangements for interior visits, respecting worship times.',
 'A Catedral Basílica Metropolitana, junto ao Parque Bolívar, é uma referência do patrimônio religioso e arquitetônico do centro. É distinta da Basílica de Nuestra Señora de la Candelaria, perto do Parque Berrío. Conheça o exterior e consulte as condições de visita interna, respeitando os momentos de culto.'],
 'pies-descalzos':[
 'El Parque de los Pies Descalzos propone una pausa en el espacio público del entorno de EPM y Plaza Mayor. Sus áreas sensoriales forman parte de una experiencia urbana diferente de los museos y miradores. Es distinto de Parques del Río; confirma el estado de las zonas y las reglas de uso, sin suponer que todas las áreas acuáticas estén habilitadas.',
 'Parque de los Pies Descalzos offers a pause in the public spaces around EPM and Plaza Mayor. Its sensory areas provide an urban experience different from museums and viewpoints. It is separate from Parques del Río; check current conditions and rules rather than assuming every water area is open.',
 'O Parque de los Pies Descalzos oferece uma pausa no espaço público perto da EPM e da Plaza Mayor. Suas áreas sensoriais proporcionam uma experiência urbana diferente de museus e mirantes. É distinto de Parques del Río; confira as condições e regras de uso, sem presumir que todas as áreas com água estejam abertas.']}
assets=read('imagenes.json');assets=[a for a in assets if a.get('id') not in {d['id'] for d in downloads}]
alts={
 'panorama-2005':['Vista distante del centro de Medellín con Coltejer y ciudad','Distant view of downtown Medellín with Coltejer and the city','Vista distante do centro de Medellín com Coltejer e a cidade'],
 'explora-2012':['Parque Explora en Medellín','Parque Explora in Medellín','Parque Explora em Medellín'],
 'pueblito-2012':['Plaza y construcciones del Pueblito Paisa','Square and buildings of Pueblito Paisa','Praça e construções do Pueblito Paisa'],
 'comuna13-2023':['Vista del barrio Comuna 13 en Medellín','View of the Comuna 13 neighbourhood in Medellín','Vista do bairro Comuna 13 em Medellín'],
 'museo-2020':['Fachada del Museo de Antioquia','Façade of Museo de Antioquia','Fachada do Museo de Antioquia'],
 'arvi-2012':['Bosque del Parque Arví','Woodland in Parque Arví','Bosque do Parque Arví'],
 'catedral-2012':['Fachada de la Catedral Metropolitana de Medellín','Façade of Medellín Metropolitan Cathedral','Fachada da Catedral Metropolitana de Medellín'],
 'pies-2006':['Espacio del Parque de los Pies Descalzos','Public space in Parque de los Pies Descalzos','Espaço do Parque de los Pies Descalzos']}
for d in downloads:
 original=r/d['target'];target=r/'images'/(d['id']+'.webp')
 with Image.open(original) as im:
  im.verify()
 with Image.open(original) as im:
  im=ImageOps.exif_transpose(im).convert('RGB');im.thumbnail((1600,1600) if d['id']=='panorama-2005' else (800,800),Image.Resampling.LANCZOS)
  quality=82;budget=300000 if d['id']=='panorama-2005' else 120000
  im.save(target,'WEBP',quality=quality,method=6);size=list(im.size)
  while target.stat().st_size>budget and quality>54:
   quality-=4;im.save(target,'WEBP',quality=quality,method=6)
 assets.append(dict(id=d['id'],file='images/'+target.name,author=d['author'],source_url=d['source_url'],license=d['license'],license_url=d['license_url'],original_download_url=d['url'],original_file=d['target'],original_downloaded=True,original_sha256=sha(original),sha256=sha(target),commercial_use=True,adaptation=True,consulted_at='2026-10-17',taken_at=d['date'],bytes=target.stat().st_size,dimensions=size,review_status='pendiente_humana',conversion_parameters=dict(tool='Pillow',version=PIL.__version__,quality=quality,method=6,crop=None,metadata='sin EXIF/GPS',resize='sin ampliar,1600portada/800tarjeta'),translations={l:dict(alt=t,caption=t) for l,t in zip(['es','en','pt-BR'],alts[d['id']])},notes='Fotografía histórica de referencia; no demuestra condiciones actuales. '+d['purpose']))
for d in downloads:
 if d.get('retrieved_variant'):
  a=next(a for a in assets if a.get('id')==d['id'])
  a.update(original_downloaded=False,downloaded_source_file=True,source_variant=d['retrieved_variant'],full_original_url=d['original_url'],master_downloaded=False,notes=a['notes']+' Se recuperó miniatura oficial; maestro completo no descargado porHTTP429. Hashoriginal_file corresponde variante recibida, no maestro.')
save('imagenes.json',assets)
def image(asset_id):
 a=next(a for a in assets if a.get('id')==asset_id) if asset_id!='orquideorama-existing' else next(a for a in assets if a['file']=='images/medellin-3.webp')
 return dict(file=a['file'],image_origin='external',image_creator=a['author'],image_source_url=a['source_url'],image_license=a['license'],image_license_url=a['license_url'],alt={l:t['alt'] for l,t in a['translations'].items()},caption={l:t['caption'] for l,t in a['translations'].items()})
sources=read('fuentes.json')
if not any(s['id']=='arvi-operador' for s in sources):sources.append(dict(id='arvi-operador',url='https://parquearvi.org/',editor='Corporación Parque Arví',consultado='2026-10-17',observacion='Sede Santa Elena vereda Piedras Blancas, sectorElTambo; senderos guiados/autoguiados. Consultado directamente.',metodo='Fuente primaria web'))
save('fuentes.json',sources)
pkg=read('paquete-destino-v2.borrador.json');pkg['places']=[];pkg['hero_image']=image('panorama-2005');pkg['destination']['destination_type']='city'
main=[]
for n,s in enumerate(selection['main_places'],1):
 p=next(p for p in content['places'] if p['slug']==s['slug'])
 for i,l in enumerate(['es','en','pt-BR']):p['translations'][l]['description']=texts[s['slug']][i]
 if s['slug']=='arvi':
  p['source_ids']=['arvi-operador']
  for t in p['translations'].values():t['external_url']='https://parquearvi.org/'
 im=image(s['image_id'])
 pkg['places'].append(dict(slug=s['slug'],type='place',publication_status='draft',translations=p['translations'],image=im))
 main.append(dict(order=n,slug=s['slug'],reason=s['reason'],source_ids=p['source_ids'],translations=p['translations'],image=im,status='draft',photo_approved=False,text_approved={l:False for l in ['es','en','pt-BR']}))
save('principales-v3.json',main);save('contenido.json',content)
pkg['destination']['sources']={l:[dict(label=s['editor'],url=s['url']) for s in sources] for l in ['es','en','pt-BR']}
pkg['local_research']['notes']=['Exactamente8 principales elegidos por relevancia/variedad, no por fotos. Coltejer soloportada distante, no tarjeta. Fotografías provisionales, aprobación humana y generaciónIAfinal pendientes. Inventario amplio secundario conservado.']
save('paquete-preprod.json',pkg)
files=[pkg['hero_image']['file']]+[p['image']['file'] for p in pkg['places']]
assert len(pkg['places'])==8 and len(files)==len(set(files))==9 and 'coltejer' not in [p['slug'] for p in pkg['places']]
assert len({sha(r/f) for f in files})==9
with zipfile.ZipFile(r/'paquete-preprod.zip','w',zipfile.ZIP_DEFLATED) as z:
 z.writestr('paquete-destino.json',json.dumps(pkg,ensure_ascii=False,indent=2)+'\n')
 for f in files:z.write(r/f,f)
importer=r.parents[3]/'preprod/backend/app/DestinationPackage.php'
result=subprocess.run(['php','-r','require $argv[1];$p=DestinationPackage::readZip($argv[2]);echo json_encode(["places"=>count($p["manifest"]["places"]),"images"=>count($p["images"]),"php"=>PHP_VERSION]);',str(importer),str(r/'paquete-preprod.zip')],capture_output=True,text=True,check=True)
save('reporte-preprod.json',dict(version=3,destination_id='CO-05-medellin',zip='paquete-preprod.zip',zip_sha256=sha(r/'paquete-preprod.zip'),main_place_count=8,unique_image_files=9,unique_image_hashes=9,selection_criterion=selection['criterion'],included=[p['slug'] for p in main],secondary=[p['slug'] for p in content['places'] if p['slug'] not in {s['slug'] for s in main}],secondary_decisions=selection['secondary_decisions'],photographs='provisionales_reales_no_IAfinal',approval=False,generation=False,publication=False,database_touched=False,imported=False,history_image_sidecar=None,history_image_limitation='Historia conserva texto; fotografía histórica específica distinta de9activos pendiente.',validation=dict(php_real_importer=json.loads(result.stdout),python_unique_images='passed',inspection='inspeccion-visual-v3.json'),limits=['Fotos históricas no prueban condiciones actuales.','Aprobación original/textos y segunda revisión antes de IA/publicación.','Coltejer no tarjeta; no eventoMacarena prometido.']))
history_asset=next(a for a in assets if a['file']=='images/medellin-1.webp')
history_image=dict(file=history_asset['file'],image_origin='external',image_creator=history_asset['author'],image_source_url=history_asset['source_url'],image_license=history_asset['license'],image_license_url=history_asset['license_url'],alt={l:t['alt'] for l,t in history_asset['translations'].items()},caption=dict(zip(['es','en','pt-BR'],['Medellín desde Cerro El Picacho,2016: paisaje urbano posterior al desarrollo comercial e industrial; no representación de1675.','Medellín from Cerro El Picacho,2016: an urban landscape following commercial and industrial development, not a depiction of1675.','Medellín vista do Cerro El Picacho,2016: paisagem urbana posterior ao desenvolvimento comercial e industrial, não uma representação de1675.'])))
assert history_asset['sha256'] not in {sha(r/f) for f in files}
save('historia-sidecar-preprod.json',dict(version=1,destination_slug='medellin',role='history',image=history_image,sha256=history_asset['sha256'],original_file=history_asset['original_file'],original_sha256=history_asset['original_sha256'],history=pkg['destination']['history'],source_ids=['historia'],integration_status='sidecar_pendiente_coordinador',approved=False,note='Imagen contextual del paisaje urbano2016; independiente de9fotosZIP. No muestra fundación1675 ni prueba época colonial. No publicar ni generarIA.'))
report=read('reporte-preprod.json');report.update(history_image_sidecar='historia-sidecar-preprod.json',history_image_limitation='Foto contextual2016 desdePicacho distinta de9fotosZIP; integración privada por coordinador pendiente, no muestra1675.');save('reporte-preprod.json',report)
# Fresh private review: eight primary cards, secondary inventory and activities kept apart.
urls={s['id']:s['url'] for s in sources}
out=['<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Medellín · ocho principales</title><style>body{font:17px system-ui;line-height:1.6;max-width:1150px;margin:auto;padding:24px}img{max-width:100%;height:auto}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}article{border:1px solid #aaa;padding:18px}.grid img{width:100%;height:280px;object-fit:contain;background:#eee}label{display:block}details{margin:18px 0}@media(max-width:650px){.grid{grid-template-columns:1fr}}</style><h1>Medellín · ocho atractivos principales</h1><p>Selección por relevancia y variedad. Fotografías provisionales para revisión privada; sin aprobación ni publicación.</p><h2>Portada propuesta: Coltejer desde lejos con ciudad</h2>']
def figure(im):return '<figure><img src="'+im['file']+'" alt="'+html.escape(im['alt']['es'])+'"><figcaption>'+html.escape(im['image_creator'])+' · <a href="'+im['image_source_url']+'">Original</a> · <a href="'+im['image_license_url']+'">'+im['image_license']+'</a>. Miniatura técnica sin recorte; foto histórica.</figcaption></figure>'
out.append(figure(pkg['hero_image']))
out.append('<h2>Historia: del establecimiento de la Villa a la ciudad actual</h2>'+figure(history_image))
for lang,paragraphs in pkg['destination']['history'].items():out.append('<details '+('open' if lang=='es' else '')+'><summary>'+lang+'</summary>'+''.join('<p>'+html.escape(t)+'</p>' for t in paragraphs)+'</details>')
out.append('<label>Revisor <input id="reviewer"></label><button id="export">Exportar decisiones locales</button><p id="status"></p><div class="grid">')
for p in main:
 out.append('<article id="'+p['slug']+'"><h2>'+str(p['order'])+'. '+p['translations']['es']['name']+'</h2>'+figure(p['image'])+'<p>'+p['reason']+'</p>')
 for l,t in p['translations'].items():out.append('<details '+('open' if l=='es' else '')+'><summary>'+l+'</summary><p>'+html.escape(t['description'])+'</p></details>')
 for sid in p['source_ids']:out.append('<a href="'+urls[sid]+'">Fuente oficial '+sid+'</a> ')
 digest=hashlib.sha256(json.dumps(p,ensure_ascii=False,sort_keys=True).encode()).hexdigest()
 for part in ['ubicacion','foto_referencia','licencia','texto_es','texto_en','texto_pt-BR']:out.append('<label>'+part+'<select data-id="'+p['slug']+'" data-part="'+part+'" data-hash="'+digest+'"><option>Pendiente</option><option>Proponer aprobar</option><option>Corregir</option><option>Rechazar</option></select></label>')
 out.append('</article>')
out.append('</div><details><summary>Inventario amplio secundario (no son tarjetas principales)</summary>')
for p in content['places']:
 if p['slug'] not in {x['slug'] for x in main}:out.append('<p>'+html.escape(p['translations']['es']['name'])+' · conservado para profundidad; fuera de selección principal.</p>')
out.append('</details><details><summary>Actividades separadas; sin ofertas aprobadas</summary>')
for e in read('experiencias.json'):out.append('<p>'+html.escape(e.get('name',e['id']))+' · '+e['kind']+' · '+html.escape(e['photo_status'])+'</p>')
out.append('''</details><script>document.querySelector('#export').onclick=()=>{let reviewer=document.querySelector('#reviewer').value.trim();if(!reviewer){document.querySelector('#status').textContent='Indica la persona revisora';return}let decisions=[...document.querySelectorAll('select[data-id]')].map(s=>({id:s.dataset.id,component:s.dataset.part,hash:s.dataset.hash,decision:s.value}));let payload={destination:'CO-05-medellin',version:3,reviewer,at:new Date().toISOString(),status:'local_draft',decisions,generation_authorized:false,publication_authorized:false};let a=document.createElement('a');a.href=URL.createObjectURL(new Blob([JSON.stringify(payload,null,2)],{type:'application/json'}));a.download='medellin-revision-v3.json';a.click();URL.revokeObjectURL(a.href);document.querySelector('#status').textContent='Exportado borrador local; comunicar al coordinador. No genera ni publica.'};</script></html>''')
(r/'preview.html').write_text('\n'.join(line.rstrip() for line in out)+'\n')
print('V3:8principales,9fotosdistintas,PHPreadZip válido; revisión humana/importación pendientes.')
