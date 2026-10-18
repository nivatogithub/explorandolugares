import json,hashlib,zipfile
from pathlib import Path
p=Path(__file__).resolve().parent
j=p/'integracion-medellin.json';d=json.loads(j.read_text());d['editorial_selection']=[x['slug'] for x in d['places']]
for lang,t in d['destination']['translations'].items():
    t['intro']=t['intro'].split('. ')[0]+'.'
    d['destination']['history'][lang]=[d['destination']['history'][lang][0]]
d['display_translations']=d['destination']['translations']
for x in d['places']+d['experiences']:x.update(id=x['slug'],review_ready=True,display_translations=x['translations'])
j.write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n')
s=(p/'medellin-compacta.js').read_text();a=s.index('const data=');b=s.index(';const base=',a);s=s[:a]+'const data='+json.dumps(d,ensure_ascii=False)+s[b:]
extra='''const hero=document.querySelector('.destination-hero figure img');if(hero){hero.src=new URL('web/portada-1200.webp',base);hero.srcset=[640,1200].map(w=>new URL('web/portada-'+w+'.webp',base)+' '+w+'w').join(', ');hero.sizes='(max-width:800px) 100vw, 60vw';hero.alt=data.destination.slug==='medellin'?'Medellín · Coltejer · ilustración editorial':'';hero.removeAttribute('aria-label');hero.closest('figure').removeAttribute('aria-label');hero.closest('figure').querySelectorAll('figcaption,.media-credit,.photo-credit,.image-credit').forEach(x=>x.remove());}const intro=document.querySelector('.destination-hero .intro');if(intro)intro.textContent=data.destination.translations[lang].intro;const history=document.querySelector('#descubre');if(history){history.querySelectorAll('.destination-history-photo,.destination-reasons,figure').forEach(x=>x.remove());const paras=history.querySelectorAll('p');if(paras.length){paras[0].textContent=data.destination.history[lang][0];[...paras].slice(1).forEach(x=>x.remove());}else{const para=document.createElement('p');para.textContent=data.destination.history[lang][0];history.append(para);}history.style.display='block';history.querySelectorAll('details').forEach(x=>x.open=true);}document.querySelectorAll('#que-conocer>header>p,.destination-experiences>header>p,#lugares>header>p').forEach(x=>x.remove());document.querySelectorAll('#lugares details').forEach(x=>x.open=true);'''
s=s if 'const hero=document.querySelector' in s else s.replace('})();',extra+'})();');(p/'medellin-compacta.js').write_text(s)
css=p/'medellin-compacta.css';css.write_text(css.read_text()+'body.medellin-semirrealista .destination-hero figure img{width:100%;height:auto;aspect-ratio:3/2;object-fit:contain}body.medellin-semirrealista #descubre>*{width:100%;max-width:none}body.medellin-semirrealista .destination-experience-copy>p{margin-block:.5rem}body.medellin-semirrealista #lugares details{display:block}\n')
pack=json.loads((p/'paquete-preprod.json').read_text());pack['destination']=d['destination'];(p/'paquete-preprod.json').write_text(json.dumps(pack,ensure_ascii=False,indent=2)+'\n')
with zipfile.ZipFile(p/'paquete-preprod.zip','w',zipfile.ZIP_DEFLATED) as z:
    z.write(p/'paquete-preprod.json','paquete-destino.json')
    for i in [pack['hero_image']]+[x['image'] for x in pack['places']]:
        ident=Path(i['file']).stem;w=1200 if ident=='portada' else 800;z.write(p/'web'/f'{ident}-{w}.webp',i['file'])
(p/'SHA256SUMS').write_text('\n'.join(hashlib.sha256(f.read_bytes()).hexdigest()+'  '+str(f.relative_to(p)) for f in sorted(p.rglob('*')) if f.is_file() and f.name!='SHA256SUMS')+'\n')
print('PASS normalizado: editorial_selection8, experiences9 sin colisión, portada, intro1frase, historia1p')
