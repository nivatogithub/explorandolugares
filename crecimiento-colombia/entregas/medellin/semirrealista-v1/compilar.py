import json,shutil,hashlib,zipfile,re,html
from pathlib import Path
from PIL import Image
root=Path(__file__).resolve().parents[4]
d=root/'crecimiento-colombia/entregas/medellin';out=d/'semirrealista-v1'
read=lambda p:json.loads(p.read_text())
write=lambda p,x:p.write_text(json.dumps(x,ensure_ascii=False,indent=2)+'\n')
meta=read(out/'GENERACION-COMPLETA.json');selected={x['id']:x for x in meta};prompts=read(out/'PROMPTS.json');roles={x['id']:x for x in prompts};manifest=[]
for ident,m in selected.items():
    master=out/'maestros'/f'{ident}-v{m["variant"]}.png'
    if not master.exists():shutil.copy2(m['path'],master)
    im=Image.open(master).convert('RGB');widths=[640,1200] if ident=='portada' else [480,800];variants=[]
    for w in widths:
        f=out/'web'/f'{ident}-{w}.webp';copy=im.resize((w,round(im.height*w/im.width)),Image.Resampling.LANCZOS);copy.save(f,'WEBP',quality=79,method=6)
        variants.append({'path':'web/'+f.name,'width':w,'height':copy.height,'bytes':f.stat().st_size,'sha256':hashlib.sha256(f.read_bytes()).hexdigest()})
    manifest.append({'id':ident,'role':roles[ident]['role'],'style':'EL-SEMI-REALISTA-1','origin':'ai_generated','master':str(master.relative_to(out)),'master_sha256':hashlib.sha256(master.read_bytes()).hexdigest(),'prompt':m['prompt'],'input_images':[],'source_ids':roles[ident]['source_ids'],'variants':variants,'visual_qa':'inspeccionada_individualmente_propuesta_editorial','human_approved':False,'publication_approved':False,'interpretation':'Composición ilustrada; geometría, personas, entorno y detalles interpretativos. No acredita estado actual ni servicio contratado.'})
write(out/'MANIFIESTO.json',manifest)
package=read(d/'paquete-preprod.json');content=read(d/'contenido.json');experiences=read(d/'experiencias.json');locales=['es','en','pt-BR']
for lang in locales:
    package['destination']['translations'][lang].update({k:v for k,v in content['translations'][lang].items() if k in ['display_name','eyebrow','headline','intro']})
package['destination']['history']={l:content['translations'][l]['history'] for l in locales}
package['destination'].pop('history_image',None)
def image(ident,names):
    return {'file':'images/'+ident+'.webp','image_origin':'ai_generated','image_creator':'OpenAI imagegen · composición editorial EL-SEMI-REALISTA-1','alt':{l:({'es':'Ilustración de ','en':'Illustration of ','pt-BR':'Ilustração de '}[l]+names[l]) for l in locales},'caption':{l:'' for l in locales}}
package['hero_image']=image('portada',{l:'Medellín y Coltejer' for l in locales})
for p in package['places']:
    p['image']=image(p['slug'],{l:p['translations'][l]['name'] for l in locales})
    for l in locales:
        text=p['translations'][l]['description'];p['translations'][l]['description']=re.split(r'(?<=[.!?])\s+',text)[0]
        p['translations'][l]['external_label']={'es':'Sitio oficial','en':'Official website','pt-BR':'Site oficial'}[l]
for e in experiences:
    if e['id'] not in selected:continue
    t={l:{**e['translations'][l],'external_url':e.get('booking_url',''),'external_label':{'es':'Consultar recorrido','en':'Check the visit','pt-BR':'Consultar o passeio'}[l]} for l in locales}
    package['places'].append({'slug':e['id'],'type':'experience','publication_status':'draft','translations':t,'image':image(e['id'],{l:t[l]['name'] for l in locales})})
write(out/'paquete-preprod.json',package)
with zipfile.ZipFile(out/'paquete-preprod.zip','w',zipfile.ZIP_DEFLATED) as z:
    z.write(out/'paquete-preprod.json','paquete-destino.json')
    for p in [package['hero_image']]+[p['image'] for p in package['places']]:
        ident=Path(p['file']).stem;w=1200 if ident=='portada' else 800;z.write(out/'web'/f'{ident}-{w}.webp',p['file'])
integration={'slug':'medellin','review_ready':True,'human_approved':False,'publication_approved':False,'style':'EL-SEMI-REALISTA-1','history_image':None,'destination':package['destination'],'places':[p for p in package['places'] if p['type']=='place'],'experiences':[p for p in package['places'] if p['type']=='experience'],'display_translations':package['destination']['translations'],'assets':[{'role':x['role'],'key':x['id'],'slug':x['id'],'path':x['variants'][-1]['path'],'srcset':', '.join(v['path']+' '+str(v['width'])+'w' for v in x['variants'])} for x in manifest]}
write(out/'integracion-medellin.json',integration);write(out/'fuentes.json',read(d/'fuentes.json'))
body='<h1>Medellín · revisión semirrealista</h1><p>Borrador privado. Ilustraciones editoriales pendientes de aprobación.</p><label>Idioma <select id="lang"><option>es</option><option>en</option><option>pt-BR</option></select></label><main id="app"></main>'
js='const data='+json.dumps(integration,ensure_ascii=False)+';function render(){const l=document.querySelector("#lang").value;const app=document.querySelector("#app");app.replaceChildren();const t=data.destination.translations[l];function el(tag,text){let e=document.createElement(tag);e.textContent=text;return e}app.append(el("h2",t.headline),el("p",t.intro));const hero=document.createElement("img");hero.src="web/portada-1200.webp";hero.className="hero";app.append(hero);app.append(el("h2",l==="en"?"History":l==="pt-BR"?"História":"Historia"));data.destination.history[l].forEach(x=>app.append(el("p",x)));for(const [key,title] of [["places",l==="en"?"Places":l==="pt-BR"?"Lugares":"Lugares"],["experiences",l==="en"?"Experiences":l==="pt-BR"?"Experiências":"Experiencias"]]){app.append(el("h2",title));const grid=el("section","");grid.className="grid";for(const p of data[key]){const card=el("article","");card.dataset[key==="places"?"placeSlug":"experienceSlug"]=p.slug;const img=el("img","");img.src="web/"+p.slug+"-800.webp";img.alt=p.image.alt[l];card.append(img,el("h3",p.translations[l].name),el("p",p.translations[l].description));grid.append(card)}app.append(grid)}}document.querySelector("#lang").onchange=render;render();'
css='body{font:16px/1.5 system-ui;background:#faf8f3;color:#263832;max-width:1180px;margin:auto;padding:24px}img{max-width:100%;display:block}.hero{width:100%;max-height:640px;object-fit:contain;background:#e6ece7}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}article{background:white;border:1px solid #dde3db;border-radius:12px;overflow:hidden}article img{width:100%;aspect-ratio:3/2;object-fit:cover}article h3,article p{margin:12px 16px}article p{font-size:14px}h2{margin-top:32px}@media(max-width:800px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.grid{grid-template-columns:1fr}}'
(out/'preview.html').write_text('<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Medellín · revisión</title><style>'+css+'</style>'+body+'<script>'+js+'</script></html>')
(out/'medellin-compacta.css').write_text('body.medellin-semirrealista .highlight-photo,body.medellin-semirrealista .medellin-experience-photo{width:100%;aspect-ratio:3/2;object-fit:cover;display:block}body.medellin-semirrealista #descubre{display:block}body.medellin-semirrealista .destination-history-photo{display:none}body.medellin-semirrealista .highlight-card p,body.medellin-semirrealista .destination-experience-copy p{font-size:.9rem;line-height:1.45}\n')
(out/'medellin-compacta.js').write_text('(()=>{if(!/(^|\\/)medellin(\\/|$)/.test(location.pathname))return;document.body.classList.add("medellin-semirrealista");const data='+json.dumps(integration,ensure_ascii=False)+';const base=new URL("./medellin-semirrealista/",document.currentScript.src);const lang=document.documentElement.lang.startsWith("en")?"en":document.documentElement.lang.startsWith("pt")?"pt-BR":"es";for(const p of [...data.places,...data.experiences]){const card=document.querySelector(`[data-${p.type==="place"?"place":"experience"}-slug="${p.slug}"]`);if(!card)continue;let img=card.querySelector("img");if(!img){img=document.createElement("img");card.prepend(img)}img.classList.add(p.type==="place"?"highlight-photo":"medellin-experience-photo");img.src=new URL("web/"+p.slug+"-800.webp",base);img.srcset=[480,800].map(w=>new URL("web/"+p.slug+"-"+w+".webp",base)+" "+w+"w").join(", ");img.sizes="(max-width:600px) 100vw, (max-width:900px) 50vw, 33vw";img.alt=p.image.alt[lang];img.loading="lazy";const text=card.querySelector(".destination-experience-copy>p,p");if(text)text.textContent=p.translations[lang].description;}document.querySelectorAll(".destination-history-photo").forEach(x=>x.remove());})();\n')
assert len(manifest)==18 and len(package['places'])==17
assert len({x['master_sha256'] for x in manifest})==18
assert all(len(p['translations'])==3 for p in package['places'])
assert all(v['bytes']<250000 for x in manifest for v in x['variants'])
write(out/'VALIDACION.json',{'status':'PASS','assets':18,'places':8,'experiences':9,'languages':locales,'webp_variants':36,'unique_masters':18,'history_image':False,'human_approved':False,'production':False,'browser_qa':'pendiente_IAB_coordinador','interpretation_limits':['Pueblito conserva composición interpretativa del entorno; no vista fotográfica exacta.','Museos y EPM: detalles arquitectónicos interpretativos, no prueba de servicio vigente.','Metro: tren ilustrado, no identificación técnica de flota o estación.']})
print('PASS: 18 activos, 36 WebP, 8 lugares, 9 experiencias, 3 idiomas')
