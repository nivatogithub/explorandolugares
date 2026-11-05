from pathlib import Path
import json,base64,hashlib,subprocess,html
p=Path(__file__).parent
rows=json.loads((p/'uribia-new-references.json').read_text())
out=p/'uribia-generated-v2'
cards=[];manifest=[]
for i,r in enumerate(rows,1):
 original=p/'uribia-selected-originals'/f'{i:02d}.jpg'
 master=out/f'{i:02d}.png'
 if not master.exists() and not (out/f'{i:02d}-1200.webp').exists():raise FileNotFoundError(f'Falta imagen {i}')
 web=out/f'{i:02d}-1200.webp'
 if master.exists(): subprocess.run(['/opt/homebrew/bin/cwebp','-quiet','-q','82','-m','6','-resize','1200','800',str(master),'-o',str(web)],check=True)
 original_preview=out/f'{i:02d}-original.webp'
 subprocess.run(['/opt/homebrew/bin/cwebp','-quiet','-q','82','-m','6','-resize','800','0',str(original),'-o',str(original_preview)],check=True)
 def embed(f):return 'data:image/webp;base64,'+base64.b64encode(f.read_bytes()).decode()
 sha=hashlib.sha256(web.read_bytes()).hexdigest()
 name=r['name']
 note='Pendiente de revisión humana; no publicado.'
 if i==4:note+=' La hornacina de la referencia no permite distinguir una estatua; no se inventó una figura.'
 if i==8:note+=' Corrige el rótulo anterior «Playa del Cabo»: esta referencia muestra el sector junto al Pilón.'
 cards.append(f'<section id="img-{i}"><h2>{i}. {html.escape(name)}</h2><div class="pair"><figure><figcaption>Original seleccionado</figcaption><img src="{embed(original_preview)}"></figure><figure><figcaption>Propuesta generada</figcaption><img src="{embed(web)}"></figure></div><p>{html.escape(note)}</p><p><a href="{html.escape(r["page"],quote=True)}">Procedencia del original</a></p><label>Decisión <select data-key="{sha}" data-field="decision"><option value="pending">Pendiente</option><option value="approved">Aprobar visualmente</option><option value="changes">Solicitar cambios</option></select></label><textarea data-key="{sha}" data-field="comment" placeholder="Comentarios para esta imagen"></textarea></section>')
 manifest.append({'index':i,'name':name,'original_source':r['page'],'original_image_url':r['image'],'original_sha256':hashlib.sha256(original.read_bytes()).hexdigest(),'file':web.name,'sha256':sha,'status':'pending_owner_review','publication_allowed':False,'rights_status':'unconfirmed_for_some_sources_not_cleared_by_visual_approval','tool':'built-in image_gen'})
 js='''<script>const key='uribia-v2-review';let state={};try{state=JSON.parse(localStorage.getItem(key)||'{}')}catch(e){}document.querySelectorAll('[data-key]').forEach(e=>{e.value=state[e.dataset.key]?.[e.dataset.field]||e.value;e.addEventListener('change',()=>{state[e.dataset.key]??={};state[e.dataset.key][e.dataset.field]=e.value;try{localStorage.setItem(key,JSON.stringify(state))}catch(e){}})});document.querySelector('#export').onclick=()=>{let a=document.createElement('a');a.href=URL.createObjectURL(new Blob([JSON.stringify({review:'uribia-v2',decisions:state},null,2)],{type:'application/json'}));a.download='revision-uribia-v2.json';a.click()};</script>'''
 page='''<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Uribia · originales y propuestas</title><style>body{font:17px system-ui;background:#f4f1eb;color:#222;max-width:1400px;margin:0 auto;padding:20px}section{background:white;border:1px solid #d9d5cc;border-radius:12px;padding:20px;margin:24px 0}.pair{display:grid;grid-template-columns:1fr 1fr;gap:16px}figure{margin:0}img{width:100%;height:400px;object-fit:contain;background:#eee}figcaption{font-weight:bold;padding:8px}select,textarea,button{font:inherit;padding:10px}textarea{display:block;box-sizing:border-box;width:100%;margin-top:12px;min-height:65px}a{overflow-wrap:anywhere}@media(max-width:700px){.pair{grid-template-columns:1fr}img{height:auto}}</style><h1>Uribia · originales y propuestas</h1><p>11 imágenes para revisión. La aprobación visual no publica ni acredita permisos del fotógrafo. Todas las imágenes están incorporadas al archivo.</p><button id="export">Descargar decisiones</button>'''+''.join(cards)+js+'</html>'
 (p/'uribia-comparativo-v2.html').write_text(page)
 (out/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
 print(f'{len(cards)} pares incorporados; HTML {len(page.encode())/1024/1024:.2f} MB')
