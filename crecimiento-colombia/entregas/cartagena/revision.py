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
  body=f'<img src="{image}" alt="{esc(value["alt"]["es"])}"><p>{esc(value["credit"])} · <a href="{value["source_page"]}">Procedencia</a> · <a href="{value["license_url"]}">Licencia</a></p><p>{esc(value["recency_warning"])}</p>'
 else:
  for lang,text in value.items():
   body+=f'<details {"open" if lang=="es" else ""}><summary>{esc(lang)}</summary>'
   body+=''.join(f'<p>{esc(str(x))}</p>' for x in (text if isinstance(text,list) else [text]))+'</details>'
 blocks.append(f'<article data-id="{ident}"><h3>{esc(ident)} · {esc(scope)}</h3>{body}<p><strong>Alcance de aprobación: {esc(scope)} exclusivamente.</strong></p><label>Decisión sobre este elemento <select><option value="pendiente">Pendiente</option><option value="aprobado">Apruebo este elemento para el uso indicado</option><option value="corregir">Solicito cambios en este elemento</option></select></label><label>Correcciones o comentario<textarea rows="3"></textarea></label></article>')
def heading(text,note):blocks.append(f'<section><h2>{text}</h2><p>{note}</p></section>')
heading('1. Portada del destino','Esta foto se usará como imagen principal de Cartagena, junto al título y la presentación. Su antigüedad también forma parte de la revisión.')
component('CTG-01','Foto principal / portada de Cartagena',assets[0],package['hero_image']['file'].replace('images/','imagenes/derivados/'))
for lang,t in d['destination']['translations'].items():
 component('CTG-PORTADA-TEXTO-'+lang,'Título, presentación y descripción de buscadores · '+lang,{lang:[t['display_name'],t['eyebrow'],t['headline'],t['intro'],t['meta_description']]})
heading('2. Historia','Se aprueban únicamente los textos. No hay una fotografía asignada a historia en el paquete; las fotos de atractivos no se aprueban aquí.')
for lang,text in d['destination']['history'].items(): component('CTG-HISTORIA-'+lang,'Texto de historia · '+lang,{lang:text})
heading('3. Lugares por conocer','Cada foto pertenece a la ficha identificada debajo. Aprobarla no aprueba otras secciones ni sus textos.')
ids={'san-felipe':('CTG-02',assets[1]),'getsemani':('CTG-03',assets[3]),'museo-historico':('CTG-04',assets[4])}
for place in d['places']:
 name=place['translations']['es']['name']
 heading(esc(name),'Ficha de lugar por conocer.' if place['image'] else 'Falta foto apta. Esta ficha está en investigación y NO está incluida en el paquete de integración actual.')
 if place['image']:
  ident,a=ids[place['slug']]; component(ident,'Foto de la ficha «'+name+'»',a,place['image']['file'])
 for lang,t in place['translations'].items():component('CTG-LUGAR-'+place['slug']+'-'+lang,'Nombre y descripción de «'+name+'» · '+lang,{lang:[t['name'],t['description']]})
heading('4. Experiencias','Estas propuestas están solo en el borrador de investigación, sin fotos asignadas y sin integración en el paquete actual. No implican reserva ni operador contratado.')
for exp in d['experiences']:
 for lang,t in exp['translations'].items():component('CTG-EXP-'+exp['id']+'-'+lang,'Texto de experiencia «'+exp['id']+'» · '+lang,{lang:t})
heading('5. Información práctica','Textos del borrador de investigación. No hay fotos asignadas ni horarios o tarifas confirmados.')
for lang,t in d['practical'].items():component('CTG-PRACTICA-'+lang,'Información práctica · '+lang,{lang:t})
heading('6. Directorio gratuito','Cada decisión corresponde a un negocio. Aprobar su ficha no confirma representación, operación vigente, alianza ni contacto.')
for business in read('directorio.json'):
 component('CTG-DIR-'+business['id'],'Ficha gratuita de '+business['name'],{'es':[business['name'],business['category'],business['source_url'],business['notes']]})
revision=hashlib.sha256(json.dumps(components,ensure_ascii=False,sort_keys=True).encode()).hexdigest()
(P/'revision-componentes.json').write_text(json.dumps(dict(destination='cartagena',revision=revision,publication_approved=False,components=components),ensure_ascii=False,indent=2)+'\n')
script='''const revision=REVISION;const key='revision-cartagena-'+revision;const articles=[...document.querySelectorAll('article')];function snapshot(){return {destino:'cartagena',revision,alcance:'Decisiones individuales; no autoriza publicación',elementos:articles.map(a=>({id:a.dataset.id,alcance:a.querySelector('h3').textContent,decision:a.querySelector('select').value,comentario:a.querySelector('textarea').value}))}}let saved;try{saved=JSON.parse(localStorage.getItem(key)||'null')}catch{}if(saved)for(const a of articles){let x=saved.elementos.find(x=>x.id===a.dataset.id);if(x){a.querySelector('select').value=x.decision;a.querySelector('textarea').value=x.comentario}}document.addEventListener('input',()=>{try{localStorage.setItem(key,JSON.stringify(snapshot()))}catch{}});document.querySelector('#exportar').onclick=()=>{const output=JSON.stringify(snapshot(),null,2);document.querySelector('#resultado').value=output;const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([output],{type:'application/json'}));a.download='revision-cartagena.json';a.click();URL.revokeObjectURL(a.href)};'''.replace('REVISION',json.dumps(revision))
(P/'preview.html').write_text('''<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Cartagena · revisión por elemento</title><style>body{font:17px/1.6 system-ui;background:#fffaf2;color:#172b42;margin:0}main{max-width:1000px;margin:auto;padding:24px}article,section{margin:24px 0;padding:24px;border:1px solid #f2c77e;border-radius:12px;background:#fff8ed}img{max-width:100%;height:auto}a{color:#a83b22}label{display:block;margin:16px 0}select,textarea,button{font:inherit;max-width:100%;padding:10px}textarea{display:block;width:95%}h1{font-size:clamp(2rem,5vw,3rem)}</style><main><p>BORRADOR PRIVADO · Lugar 1 · sin aprobación ni publicación</p><h1>Qué estás revisando: Cartagena</h1><p>Apruebas por separado cada fotografía para su ubicación indicada y cada texto para su idioma. Nada está preaprobado. Las decisiones guardadas en este navegador son un borrador: exporta la revisión y compártela en este chat para registrar tus instrucciones. Aprobar componentes no autoriza publicar el destino.</p><p>Las versiones modificadas tendrán una revisión nueva; las decisiones anteriores no se trasladan automáticamente.</p>'''+''.join(blocks)+'''<section><h2>Entregar mi revisión</h2><p>El archivo incluye el identificador de esta versión, las decisiones individuales y tus correcciones. Puedes también copiar el resultado y pegarlo en el chat.</p><button id="exportar">Exportar mis decisiones</button><textarea id="resultado" rows="8" readonly aria-label="Decisiones para copiar"></textarea><p><a href="revision-componentes.json">Versión y alcance de cada elemento</a> · <a href="fuentes.json">Fuentes</a> · <a href="trazabilidad.json">Trazabilidad</a></p></section></main><script>'''+script+'</script></html>')
print('Review built:',len(components),'individually scoped components; no approvals applied.')
