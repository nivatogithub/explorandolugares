"""Construye revisión privada; nunca selecciona referencias ni genera IA o importa BD."""
from pathlib import Path
import json,hashlib,html
from PIL import Image, ImageOps
r=Path(__file__).resolve().parent
read=lambda f:json.loads((r/f).read_text())
def write(f,d):(r/f).write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n')
def esc(v):return html.escape(str(v),quote=True)
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
langs=['es','en','pt-BR'];c=read('contenido.json');sources={s['id']:s for s in read('fuentes.json')};main=read('principales-v3.json');assets=read('imagenes.json');candidates=read('descargas-v4.json');inspections=read('inspeccion-visual-v4.json') if (r/'inspeccion-visual-v4.json').exists() else {}
results={d['id']:d for d in read('descargas-v4-resultados.json')} if (r/'descargas-v4-resultados.json').exists() else {}
for d in candidates:
 p=r/d['target'];q=r/'images'/(d['id']+'.webp');review=inspections.get(d['id'],{})
 d['visual_review']=review;d['human_approved']=False;d['selected']=False
 if not p.exists():continue
 with Image.open(p) as im:
  im=ImageOps.exif_transpose(im).convert('RGB');im.thumbnail((1200,1200) if d['component']=='portada' else (800,800),Image.Resampling.LANCZOS);quality=82;budget=300000 if d['component']=='portada' else 120000
  im.save(q,'WEBP',quality=quality,method=6)
  while q.stat().st_size>budget and quality>54:quality-=4;im.save(q,'WEBP',quality=quality,method=6)
  dims=list(im.size)
 d.update(file=str(q.relative_to(r)),sha256=sha(q),original_sha256=sha(p),dimensions=dims,bytes=q.stat().st_size,status='inspeccionada_sin_aprobacion' if review else 'descargada_pendiente_inspeccion',download_evidence=results.get(d['id']))
write('referencias-propuestas-v4.json',candidates)
byplace={p['slug']:p for p in c['places']}
for p in main:p['translations']=byplace[p['slug']]['translations']
write('principales-v3.json',main)
rejected={'portada':{'file':'images/panorama-2005.webp','reason':'Coltejer diminuto en panorama: sujeto no reconocible a tamaño de uso.'},'arvi':{'file':'images/arvi-2012.webp','reason':'Pinos genéricos sin rasgo identificador suficiente de Arví.'},'comuna13':{'file':'images/comuna13-2023.webp','reason':'Viviendas sin arte/escaleras que identifiquen el circuito visitable.'}}
write('correcciones-referencias-v4.json',{'review_date':'2026-10-17','rejected':rejected,'remaining_main_count':8,'new_cards_added':0,'generation_authorized':False,'publication_authorized':False,'package_v3':'antecedente importado local con tres referencias rechazadas; no reutilizar como versión visual aprobada'})
# Estado histórico v3 se conserva; no permite que una comprobación técnica cuente como aptitud actual.
old=read('inspeccion-visual-v3.json');old['cover_current_suitability']=False;old['current_rejection']='Coltejer diminuto; sustituir. Ver inspeccion-visual-v4.json.';write('inspeccion-visual-v3.json',old)
parts=['<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Medellín · revisión de referencias</title><style>body{font:17px system-ui;line-height:1.6;max-width:1150px;margin:auto;padding:24px}img{max-width:100%;height:auto}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}article{border:1px solid #aaa;padding:18px}.grid img{width:100%;height:330px;object-fit:contain;background:#eee}label{display:block}details{margin:18px 0}.alert{background:#fff2d1;padding:16px}@media(max-width:650px){.grid{grid-template-columns:1fr}}</style><h1>Medellín · referencias y ocho principales</h1><p class="alert">Revisión privada. Referencias propuestas, textos e idiomas se deciden por separado. No hay aprobación de generación ni publicación. Se retiraron de la revisión vigente las tres fotografías rechazadas.</p><label>Revisor <input id="reviewer"></label><button id="export">Exportar decisiones locales</button><p id="status"></p><h2>Referencias nuevas: portada, Arví y Comuna 13</h2><p>Se conservan los números anteriores: portada nueva 8. Arví y Comuna 13 tienen numeración propia. Cada imagen muestra su aptitud antes de la foto.</p><div class="grid">']
def controls(id,components,digest):
 return ''.join(f'<label>{esc(k)}<select data-id="{esc(id)}" data-part="{esc(k)}" data-hash="{esc(digest)}"><option>Pendiente</option><option>Proponer aprobar</option><option>Corregir</option><option>Rechazar</option></select></label>' for k in components)
def translations(d):
 return ''.join(f'<details {"open" if l=="es" else ""}><summary>{l}</summary><h3>{esc(d[l].get("name",""))}</h3><p>{esc(d[l]["description"])}</p></details>' for l in langs)
# Recomendadas primero; dudosas/rechazadas solo en sección colapsada.
for bucket in ['recommended','secondary']:
 if bucket=='secondary':parts+=['</div><details><summary>Opciones limitadas, descartadas o pendientes de inspección</summary><div class="grid">']
 for d in candidates:
  v=d.get('visual_review',{});recommended=v.get('recommend_for_review') is True
  if (bucket=='recommended')!=recommended:continue
  label=v.get('aptitude','Pendiente de inspección; no recomendada todavía')
  parts.append(f'<article id="{esc(d["id"])}"><h3>{esc(d["component"])} · opción {d["number"]}</h3><p><strong>{esc(label)}</strong></p>')
  if d.get('file'):parts.append(f'<img src="{esc(d["file"])}" alt="{esc(v.get("subject",d["brief"]))}">')
  else:parts.append('<p>Descarga pendiente; no se presenta fotografía.</p>')
  parts.append(f'<p>{esc(d["author"])} · {esc(d["taken_at"])} · <a href="{esc(d["source_url"])}">Ficha original y autor</a> · <a href="{esc(d["license_url"])}">{esc(d["license"])}</a></p><p>{esc(v.get("observation",d["brief"]))}</p><p>{esc(d["framing"])}</p><p>{esc(d["ai_conditions"])}</p><p>{esc(d["additional_rights"])}</p>')
  if recommended:parts.append(controls(d['id'],['referencia','licencia_y_uso','encuadre'],d.get('sha256','')))
  parts.append('</article>')
parts+=['</div></details><h2>Identidad y movilidad</h2>']
for l in langs:
 t=c['translations'][l];parts.append(f'<details {"open" if l=="es" else ""}><summary>{l}</summary><p>{esc(t["intro"])}</p><p>{esc(t["mobility"])}</p>'+''.join(f'<p>{esc(x)}</p>' for x in t['practical'])+'</details>')
parts+=[controls('identidad-movilidad',['texto_'+l for l in langs],sha(r/'contenido.json')),'<p>Fuentes: <a href="https://www.metrodemedellin.gov.co/usuarios/sistema-integrado/linea-j">Metro línea J</a> · <a href="https://www.metrodemedellin.gov.co/usuarios/sistema-integrado/linea-l">Metro línea L</a></p><div class="grid">']
parts.append('</div><h2>Historia de Medellín</h2><img src="images/medellin-1.webp" alt="Vista urbana de Medellín desde Picacho; contexto del paisaje, no imagen de la fundación"><p>Luis90f · <a href="https://commons.wikimedia.org/wiki/File:Panor%C3%A1mica_de_Medell%C3%ADn_desde_el_Cerro_El_Picacho.jpg">Fuente original</a> · CC BY-SA 4.0. Reducción técnica sin recorte.</p>')
for l in langs:parts.append(f'<details {"open" if l=="es" else ""}><summary>{l}</summary>'+''.join('<p>'+esc(t)+'</p>' for t in c['translations'][l]['history'])+'</details>')
parts.append(controls('historia',['foto_referencia','texto_es','texto_en','texto_pt-BR'],sha(r/'contenido.json'))+'<h2>Ocho atractivos principales</h2><div class="grid">')
for p in main:
 parts.append(f'<article id="{esc(p["slug"])}"><h2>{p["order"]}. {esc(p["translations"]["es"]["name"])}</h2><p>{esc(p["reason"])}</p>')
 if p['slug'] in rejected:parts.append('<p class="alert">Referencia anterior rechazada; revisa las opciones nuevas de arriba. El atractivo conserva su lugar en la selección.</p>')
 else:
  a=p['image'];parts.append(f'<img src="{esc(a["file"])}" alt="{esc(a["alt"]["es"])}"><p>{esc(a["image_creator"])} · <a href="{esc(a["image_source_url"])}">Original</a> · <a href="{esc(a["image_license_url"])}">{esc(a["image_license"])}</a>. Reducción técnica sin recorte.</p>')
 parts.append(translations(p['translations']));parts.append(controls(p['slug'],['ubicacion','texto_es','texto_en','texto_pt-BR']+([] if p['slug'] in rejected else ['foto_referencia','licencia']),sha(r/'principales-v3.json')));parts.append('</article>')
parts+=['</div><h2>Actividades separadas</h2>']
for a in read('experiencias.json'):
 if a['kind']=='candidato_por_validar':continue
 parts.append(f'<article><h3>{esc(a.get("name",a["translations"]["es"]["name"]))}</h3><p>{esc(a["kind"])} · fotografía: {esc(a["photo_status"])}</p>');parts.append(f'<img src="{esc(a["image_file"])}" alt="{esc(a["translations"]["es"]["name"])}"><p>{esc(a.get("image_scope","Referencia del lugar; sin aprobación"))}</p>' if a.get('image_file') else '<p>No hay fotografía específica autorizada del servicio.</p>');parts.append(translations(a['translations']));parts.append(controls(a['id'],['texto_'+l for l in langs],sha(r/'experiencias.json')));parts.append('</article>')
parts+=['<details><summary>Directorio gratuito propuesto · diez fichas</summary>']
for b in read('directorio.json'):parts.append(f'<article><h3>{esc(b["name"])}</h3><p><a href="{esc(b["url"])}">Canal oficial</a> · sin relación comercial ni vigencia confirmada</p>'+translations(b['translations'])+'</article>')
parts+=['</details><details><summary>Inventario amplio secundario · 28 sitios</summary>']
for p in c['places']:parts.append(f'<p>{esc(p["translations"]["es"]["name"])} · {esc(p["slug"])}</p>')
parts+=['</details><details><summary>Referencias rechazadas · historial, no opciones vigentes</summary>']
for k,v in rejected.items():parts.append(f'<p>{esc(k)}: {esc(v["reason"])}</p>')
parts+=['</details><script>const key="medellin-v4-review";let saved=JSON.parse(localStorage.getItem(key)||"{}");document.querySelectorAll("select").forEach(s=>{let k=s.dataset.id+":"+s.dataset.part+":"+s.dataset.hash;s.value=saved[k]||"Pendiente";s.onchange=()=>{saved[k]=s.value;localStorage.setItem(key,JSON.stringify(saved))}});document.getElementById("export").onclick=()=>{let d={destination_id:"CO-05-medellin",reviewer:document.getElementById("reviewer").value,exported_at:new Date().toISOString(),decisions:saved,approval_effect:"none_until_coordinator_validates_user_decision"};let a=document.createElement("a");a.href=URL.createObjectURL(new Blob([JSON.stringify(d,null,2)],{type:"application/json"}));a.download="medellin-revision-v4.json";a.click();document.getElementById("status").textContent="Exportación local preparada; no publica ni activa generación."}</script></html>']
(r/'preview.html').write_text('\n'.join(parts)+'\n')
print('Revisión v4 construida. Referencias recomendadas:',sum(d.get('visual_review',{}).get('recommend_for_review') is True for d in candidates))
