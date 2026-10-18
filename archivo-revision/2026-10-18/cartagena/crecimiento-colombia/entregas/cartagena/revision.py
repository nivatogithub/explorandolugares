"""Build scoped human review from the actual draft and import package."""
from pathlib import Path
import json, hashlib, html
P=Path(__file__).resolve().parent
read=lambda f:json.loads((P/f).read_text())
esc=html.escape
d=read('borrador.json'); package=read('paquete-v2.json'); assets=read('imagenes/manifiesto.json'); components=[]; blocks=[]
def component(ident,scope,value,image=None):
 fingerprint=hashlib.sha256(json.dumps(value,ensure_ascii=False,sort_keys=True).encode()).hexdigest()
 components.append(dict(id=ident,scope=scope,sha256=fingerprint,value=value,decision=None))
 body=''
 if image:
  body=f'<img src="{image}" alt="{esc(value["alt"]["es"])}"><p>{esc(value["credit"])} · <a href="{value["source_page"]}">Procedencia</a> · <a href="{value["license_url"]}">Licencia</a> · <a href="{value["original"]["file"]}">Original sin conversión</a></p><p>{esc(value["recency_warning"])}</p>'
 else:
  for lang,text in value.items():
   body+=f'<details {"open" if lang=="es" else ""}><summary>{esc(lang)}</summary>'
   body+=''.join(f'<p>{esc(str(x))}</p>' for x in (text if isinstance(text,list) else [text]))+'</details>'
 blocks.append(f'<article data-id="{ident}"><h3>{esc(ident)} · {esc(scope)}</h3>{body}<p><strong>Alcance de aprobación: {esc(scope)} exclusivamente.</strong></p><label>Decisión sobre este elemento <select><option value="pendiente">Pendiente</option><option value="aprobado">Apruebo solo este componente borrador; no habilita oferta ni publicación</option><option value="corregir">Solicito cambios en este elemento</option></select></label><label>Correcciones o comentario<textarea rows="3"></textarea></label></article>')
def heading(text,note):blocks.append(f'<section><h2>{text}</h2><p>{note}</p></section>')
heading('1. Portada · alternativas visibles','Elige una opción y revisa por separado el original, su uso y licencia. Estas son referencias fotográficas; ninguna ilustración se generará antes de tu aprobación explícita de originales y textos.')
blocks.append('<label>Elección de portada (no aprueba licencia, texto ni ilustración)<select id="portada-eleccion"><option value="">Sin elegir</option><option value="1">Opción 1 · Panorama desde La Popa</option><option value="2">Opción 2 · San Felipe de Barajas</option></select></label><div class="portada-options">')
component('CTG-01','Opción 1 · original para posible portada · panorama Cartagena desde La Popa (2008); muestra bahía/ciudad, cruz dominante; sin recorte móvil ni escritorio',assets[0],package['hero_image']['file'].replace('images/','imagenes/derivados/'))
component('CTG-PORTADA-OPCION-2','Opción 2 · original para posible portada · San Felipe de Barajas (2011); patrimonio reconocible, no visión total de Cartagena; sin recorte móvil ni escritorio',assets[1],assets[1]['derivatives'][0]['file'])
blocks.append('</div>')
for index in [0,1]:
 a=assets[index];component('CTG-PORTADA-LICENCIA-'+str(index+1),'Licencia de referencia candidata de portada '+str(index+1),{'es':[a['author'],a['license'],a['license_url'],'Uso comercial y adaptación permitidos; atribución obligatoria; ShareAlike: '+str(a['share_alike'])]})
for lang,t in d['destination']['translations'].items():
 component('CTG-PORTADA-TEXTO-'+lang,'Título, presentación y descripción de buscadores · '+lang,{lang:[t['display_name'],t['eyebrow'],t['headline'],t['intro'],t['meta_description']]})
heading('2. Historia','Se aprueban únicamente los textos. No hay una fotografía asignada a historia en el paquete; las fotos de atractivos no se aprueban aquí.')
for lang,text in d['destination']['history'].items(): component('CTG-HISTORIA-'+lang,'Texto de historia · '+lang,{lang:text})
heading('3. Lugares por conocer','Cada foto pertenece a la ficha identificada debajo. Aprobarla no aprueba otras secciones ni sus textos.')
ids={'san-felipe':('CTG-02',assets[1]),'getsemani':('CTG-03',assets[3]),'museo-historico':('CTG-04',assets[4])}
for place in d['places']:
 name=place['translations']['es']['name']
 heading(esc(name),'Estado de foto: '+place.get('image_status','pendiente')+' · '+('Integrado en ZIP parcial.' if place['slug'] in {p['slug'] for p in package['places']} else 'Solo investigación; fuera del ZIP parcial.') if place['image'] else 'Falta foto apta. Esta ficha está en investigación y NO está incluida en el paquete de integración actual.')
 if place['image']:
  ident,a=ids.get(place['slug'],('CTG-FOTO-'+place['slug'],next(a for a in assets if a['id']==place['image']['asset_id']))); component(ident,'Foto de la ficha «'+name+'»',a,place['image']['file'])
 for lang,t in place['translations'].items():component('CTG-LUGAR-'+place['slug']+'-'+lang,'Nombre y descripción de «'+name+'» · '+lang,{lang:[t['name'],t['description']]})
heading('4. Experiencias y operadores','Revisión privada. Distinguir propuesta editorial autónoma, oferta documentada de operador y candidato oculto. Ninguna implica alianza, disponibilidad o reserva. Fotos de actividades pendientes, sin sustituirlas por fotos de monumentos.')
for exp in d['experiences']:
 heading(esc(exp['id']),esc('Estado: '+exp.get('evidence_status','pendiente')+' · Operador: '+str(exp.get('operator'))+' · Fuente propia/institucional: '+exp.get('operator_url','')+' · Foto: '+exp.get('image_status','pendiente')+' · Registro, vigencia, disponibilidad, tarifas y permisos pendientes de verificar.'))
 if exp['id']=='pesca-boquilla':
  a=next(a for a in assets if a['id']=='boquilla-2020');component('CTG-EXP-PESCA-CONTEXTO','Original contextual de barcos La Boquilla; NO muestra pesca ejecutada ni identifica IMPULSE; no completa foto de actividad',a,a['derivatives'][1]['file'])
 if exp.get('visual_candidates'):
  component('CTG-EXP-REFERENCIAS-'+exp['id'],'Referencias propuestas de actividad; permisos/identidad pendientes, no imagen aprobada',{'es':[str(v) for v in exp['visual_candidates']]})
 for lang,t in exp['translations'].items():component('CTG-EXP-'+exp['id']+'-'+lang,'Texto borrador privado de experiencia «'+exp['id']+'» · '+lang+' · NO habilita oferta, reserva ni publicación',{lang:t})
heading('5. Información práctica','Textos del borrador de investigación. No hay fotos asignadas ni horarios o tarifas confirmados.')
for lang,t in d['practical'].items():component('CTG-PRACTICA-'+lang,'Información práctica · '+lang,{lang:t})
heading('6. Directorio gratuito','Cada decisión corresponde a un negocio. Aprobar su ficha no confirma representación, operación vigente, alianza ni contacto.')
for business in read('directorio.json'):
 component('CTG-DIR-'+business['id'],'Ficha gratuita de '+business['name'],{'es':[business['name'],business['category'],business['source_url'],business['notes']]})
revision=hashlib.sha256(json.dumps(components,ensure_ascii=False,sort_keys=True).encode()).hexdigest()
(P/'revision-componentes.json').write_text(json.dumps(dict(destination='cartagena',revision=revision,publication_approved=False,components=components),ensure_ascii=False,indent=2)+'\n')
script='''const revision=REVISION;const key='revision-cartagena-'+revision;const articles=[...document.querySelectorAll('article')];function snapshot(){return {destino:'cartagena',revision,portada_seleccionada:document.querySelector('#portada-eleccion').value,alcance:'Decisiones individuales; no autoriza publicación',elementos:articles.map(a=>({id:a.dataset.id,alcance:a.querySelector('h3').textContent,decision:a.querySelector('select').value,comentario:a.querySelector('textarea').value}))}}let saved;try{saved=JSON.parse(localStorage.getItem(key)||'null')}catch{}if(saved){document.querySelector('#portada-eleccion').value=saved.portada_seleccionada||'';}if(saved)for(const a of articles){let x=saved.elementos.find(x=>x.id===a.dataset.id);if(x){a.querySelector('select').value=x.decision;a.querySelector('textarea').value=x.comentario}}document.addEventListener('input',()=>{try{localStorage.setItem(key,JSON.stringify(snapshot()))}catch{}});document.querySelector('#exportar').onclick=()=>{const output=JSON.stringify(snapshot(),null,2);document.querySelector('#resultado').value=output;const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([output],{type:'application/json'}));a.download='revision-cartagena.json';a.click();URL.revokeObjectURL(a.href)};'''.replace('REVISION',json.dumps(revision))
(P/'preview.html').write_text('''<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Cartagena · revisión por elemento</title><style>body{overflow-wrap:anywhere;font:17px/1.6 system-ui;background:#fffaf2;color:#172b42;margin:0}main{max-width:1000px;margin:auto;padding:24px}article,section{margin:24px 0;padding:24px;border:1px solid #f2c77e;border-radius:12px;background:#fff8ed}img{max-width:100%;height:auto}.portada-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.portada-options article{min-width:0;padding:16px}h3{overflow-wrap:anywhere}@media(max-width:700px){.portada-options{grid-template-columns:1fr}}a{color:#a83b22}label{display:block;margin:16px 0}select,textarea,button{box-sizing:border-box;font:inherit;max-width:100%;padding:10px}select{width:100%}textarea{display:block;width:95%}h1{font-size:clamp(2rem,5vw,3rem)}</style><main><p>BORRADOR PRIVADO · Lugar 1 · sin aprobación ni publicación</p><h1>Qué estás revisando: Cartagena</h1><p>Apruebas por separado cada fotografía para su ubicación indicada y cada texto para su idioma. Nada está preaprobado. Las decisiones guardadas en este navegador son un borrador: exporta la revisión y compártela en este chat para registrar tus instrucciones. Aprobar componentes no autoriza publicar el destino.</p><p>Inventario ampliado: '''+str(len(d['places']))+''' sitios y '''+str(len(d['experiences']))+''' actividades. Los faltantes de fotografía se muestran en cada ficha; el ZIP aún es parcial.</p><p>Originales y textos primero. Ilustración editorial después de aprobación explícita, con una segunda revisión separada. Aún no se ha generado ninguna.</p><p>Las versiones modificadas tendrán una revisión nueva; las decisiones anteriores no se trasladan automáticamente.</p>'''+''.join(blocks)+'''<section><h2>Entregar mi revisión</h2><p>El archivo incluye el identificador de esta versión, las decisiones individuales y tus correcciones. Puedes también copiar el resultado y pegarlo en el chat.</p><button id="exportar">Exportar mis decisiones</button><textarea id="resultado" rows="8" readonly aria-label="Decisiones para copiar"></textarea><p><a href="revision-componentes.json">Versión y alcance de cada elemento</a> · <a href="fuentes.json">Fuentes</a> · <a href="trazabilidad.json">Trazabilidad</a></p></section></main><script>'''+script+'</script></html>')
print('Review built:',len(components),'individually scoped components; no approvals applied.')
