"""Recompila metadatos/ZIP sin modificar imágenes, estilo ni ajustes visuales aprobados para revisión."""
import json,hashlib,zipfile
from pathlib import Path
p=Path(__file__).resolve().parent
read=lambda f:json.loads((p/f).read_text())
write=lambda f,d:(p/f).write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n')
d=read('integracion-medellin.json');manifest=read('MANIFIESTO.json')
titles={'es':'Cultura entre montañas','en':'Culture among mountains','pt-BR':'Cultura entre montanhas'}
for l,t in titles.items():d['destination']['translations'][l]['headline']=t;d['display_translations'][l]['headline']=t
d['editorial_selection']=[x['slug'] for x in d['places']]
assert len(d['places'])==8 and len(d['experiences'])==9
assert not set(d['editorial_selection'])&{x['id'] for x in d['experiences']}
for x in d['places']+d['experiences']:
 assert set(x['translations'])==set(titles) and x['review_ready'] is True
 assert all(len(t['description'].split())<=15 for t in x['translations'].values())
assert len(manifest)==18 and len({x['master_sha256'] for x in manifest})==18
for x in manifest:
 for v in x['variants']:assert hashlib.sha256((p/v['path']).read_bytes()).hexdigest()==v['sha256']
write('integracion-medellin.json',d)
package=read('paquete-preprod.json');package['destination']=d['destination'];package['places']=[{k:x[k] for k in ['slug','type','publication_status','translations','image']} for x in d['places']+d['experiences']];write('paquete-preprod.json',package)
with zipfile.ZipFile(p/'paquete-preprod.zip','w',zipfile.ZIP_DEFLATED) as z:
 z.write(p/'paquete-preprod.json','paquete-destino.json')
 for i in [package['hero_image']]+[x['image'] for x in package['places']]:
  ident=Path(i['file']).stem;w=1200 if ident=='portada' else 800;z.write(p/'web'/f'{ident}-{w}.webp',i['file'])
v=read('VALIDACION.json');v['browser_qa']='PASS_coordinador_IAB_18imagenes_8lugares_9experiencias_sin_rotas_ni_acordeones';v['description_max_words']=15;write('VALIDACION.json',v)
(p/'SHA256SUMS').write_text('\n'.join(hashlib.sha256(f.read_bytes()).hexdigest()+'  '+str(f.relative_to(p)) for f in sorted(p.rglob('*')) if f.is_file() and f.name!='SHA256SUMS')+'\n')
print('PASS 18 imágenes conservadas, 8+9 registros, 3 idiomas, máximo15 palabras, metadatos/ZIP/hashes')
