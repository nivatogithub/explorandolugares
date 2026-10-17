"""Prepara revisión separada de dos propuestas; no altera preview/selección/paquete vigentes."""
from pathlib import Path
import json,hashlib,html
from PIL import Image,ImageOps
r=Path(__file__).resolve().parent
read=lambda f:json.loads((r/f).read_text())
def save(f,x):(r/f).write_text(json.dumps(x,ensure_ascii=False,indent=2)+'\n')
sha=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
e=lambda s:html.escape(str(s),quote=True)
p=read('PROPUESTOS_TOP8_V6.json');photos=read('descargas-propuestos-v6.json');ins=read('inspeccion-propuestos-v6.json') if (r/'inspeccion-propuestos-v6.json').exists() else {};results={x['id']:x for x in read('descargas-propuestos-v6-resultados.json')} if (r/'descargas-propuestos-v6-resultados.json').exists() else {}
assert all(sha(r/f)==h for f,h in p['protected_files_sha256'].items())
for x in photos:
 x['visual_review']=ins.get(x['id'],{});orig=r/x['target'];out=r/'images'/(x['id']+'.webp')
 if not orig.exists():continue
 with Image.open(orig) as im:
  im=ImageOps.exif_transpose(im).convert('RGB');im.thumbnail((1000,1000),Image.Resampling.LANCZOS);quality=82;im.save(out,'WEBP',quality=quality,method=6)
  while out.stat().st_size>120000 and quality>54:quality-=4;im.save(out,'WEBP',quality=quality,method=6)
  dims=list(im.size)
 x.update(file=str(out.relative_to(r)),sha256=sha(out),original_sha256=sha(orig),bytes=out.stat().st_size,dimensions=dims,download_evidence=results.get(x['id']),conversion=dict(tool='Pillow',orientation='EXIF transpose',size='thumbnail1000 LANCZOS, sin ampliar ni recortar',quality=quality,format='WebP',method=6,metadata='EXIF/GPS omitidos'),status='inspeccionada_sin_aprobacion' if x['visual_review'] else 'descargada_pendiente_inspeccion')
save('referencias-propuestos-v6.json',photos)
head='<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Medellín · dos propuestas</title><style>body{font:17px system-ui;line-height:1.6;max-width:1100px;margin:auto;padding:24px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}article{border:1px solid #bbb;padding:18px}img{width:100%;height:330px;object-fit:contain;background:#eee}label{display:block}details{margin:16px 0}@media(max-width:650px){.grid{grid-template-columns:1fr}}</style><h1>Medellín · dos propuestas separadas</h1><p>Plaza Botero y Metro/Metrocable están propuestos por la auditoría; todavía no sustituyen fichas de la selección vigente. Ninguna referencia está elegida, aprobada o generada mediante IA.</p><label>Revisor <input id="reviewer"></label><button id="export">Exportar decisiones locales</button>'
parts=[head];md=['# Dos propuestas de Medellín · V6','','Borradores independientes; no aplicados a selección física, textos vigentes o aprobaciones.']
def controls(id,components,digest):return ''.join(f'<label>{e(k)}<select data-id="{e(id)}" data-part="{e(k)}" data-hash="{digest}"><option>Pendiente</option><option>Proponer aprobar</option><option>Corregir</option><option>Rechazar</option></select></label>' for k in components)
for item in p['items']:
 parts.append('<h2>'+e(item['translations']['es']['name'])+'</h2>');md+=['','## '+item['translations']['es']['name']]
 for lang,t in item['translations'].items():
  parts.append(f'<details {"open" if lang=="es" else ""}><summary>{lang}</summary><h3>{e(t["name"])}</h3><p>{e(t["description"])}</p><p>{e(t["practical"])}</p></details>');md+=['','### '+lang,'',t['description'],'',t['practical']]
 parts.append(controls(item['id'],['ubicacion','texto_es','texto_en','texto_pt-BR'],sha(r/'PROPUESTOS_TOP8_V6.json')))
 parts.append('<h3>Referencias propuestas</h3><div class="grid">')
 for x in [a for a in photos if a['component']==item['id']]:
  v=x['visual_review'];parts.append(f'<article><h3>Opción {x["number"]} · {e(x["id"])}</h3><p><strong>{e(v.get("aptitude","Pendiente inspección; no recomendada aún"))}</strong></p>')
  if x.get('file'):parts.append(f'<img src="{e(x["file"])}" alt="{e(v.get("subject",x["id"]))}">')
  else:parts.append('<p>Descarga pendiente.</p>')
  parts.append(f'<p>{e(x["author"])} · {e(x["taken_at"])} · <a href="{e(x["source_url"])}">Ficha original</a> · <a href="{e(x["license_url"])}">{e(x["license"])}</a>. Reducción técnica sin recorte.</p><p>{e(v.get("observation","Inspección pendiente"))}</p><p>{e(x.get("artwork_review",x.get("territory_review")))}</p><p>{e(x["ai_conditions"])}</p>')
  if v.get('recommend_for_review'):parts.append(controls(x['id'],['referencia','licencia_y_uso','encuadre'],x.get('sha256','')))
  parts.append('</article>');md+=['',f'- Opción{x["number"]}: [{x["id"]}]({x["source_url"]}) · {x["author"]} · {x["taken_at"]} · [{x["license"]}]({x["license_url"]}) · {v.get("aptitude","Inspección pendiente")}.']
 parts.append('</div>')
parts.append('<h2>Fuentes de textos y revisión de derechos</h2>')
md+=['','## Fuentes y derechos','',p['rights_review']['ai_transformation']]
for s in p['sources']:
 parts.append(f'<p><a href="{e(s["url"])}">{e(s["editor"])}</a>: {e(s["role"])}</p>');md.append(f'- [{s["editor"]}]({s["url"]}): {s["role"]} Consulta17/10/2026.')
parts.append('<script>const key="medellin-propuestos-v6";let d=JSON.parse(localStorage.getItem(key)||"{}");document.querySelectorAll("select").forEach(s=>{let k=s.dataset.id+":"+s.dataset.part+":"+s.dataset.hash;s.value=d[k]||"Pendiente";s.onchange=()=>{d[k]=s.value;localStorage.setItem(key,JSON.stringify(d))}});document.getElementById("export").onclick=()=>{let a=document.createElement("a");a.href=URL.createObjectURL(new Blob([JSON.stringify({destination_id:"CO-05-medellin",reviewer:document.getElementById("reviewer").value,decisions:d,exported_at:new Date().toISOString(),effect:"none_until_user_decision_validated"},null,2)],{type:"application/json"}));a.download="medellin-propuestos-v6-revision.json";a.click()}</script></html>')
(r/'propuestos-top8-v6.html').write_text('\n'.join(parts)+'\n');(r/'PROPUESTOS_TOP8_V6.md').write_text('\n'.join(md)+'\n')
print('Dos borradores y galería separada preparados; archivos protegidos sin cambios.')
