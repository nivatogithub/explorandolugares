from pathlib import Path
import json, hashlib
from PIL import Image
r=Path(__file__).resolve().parent
c=json.loads((r/'contenido.json').read_text())
b=json.loads((r/'directorio.json').read_text())
a=json.loads((r/'imagenes.json').read_text())
s={x['id'] for x in json.loads((r/'fuentes.json').read_text())}
assert c['status']=='draft' and set(c['translations'])=={'es','en','pt-BR'}
assert len(c['places'])>=28 and len(b)>=10 and len({x['category'] for x in b})>=3
assert len({p['slug'] for p in c['places']})==len(c['places'])
choices=json.loads((r/'portadas-propuestas.json').read_text())
assert len(choices)>=4 and all(not x['selected'] and not x['reference_approved'] for x in choices)
assert any('coltejer' in x['image_file'] for x in choices)
review=json.loads((r/'revision-componentes.json').read_text())
assert review['selected_cover'] is None and not review['illustrations']['generated'] and not review['publication']['authorized']
activities=json.loads((r/'experiencias.json').read_text())
assert {x['kind'] for x in activities}=={'propuesta_editorial_autonoma','oferta_documentada_operador','candidato_por_validar'}
assert all(x.get('photo_status') for x in activities)
for p in c['places']:
 assert set(p['translations'])==set(c['translations'])
 assert set(p['source_ids'])<=s and p['publication_status']=='draft'
for x in b:
 assert not x['claimed'] and not x['contacted'] and x['publication_status']=='draft'
 assert set(x['source_ids'])<=s
for x in a:
 assert x['original_downloaded'] and x['commercial_use'] and x['adaptation']
 assert hashlib.sha256((r/x['file']).read_bytes()).hexdigest()==x['sha256']
 assert hashlib.sha256((r/x['original_file']).read_bytes()).hexdigest()==x['original_sha256']
 assert x['bytes']<=(300000 if '1.webp' in x['file'] else 120000)
 with Image.open(r/x['file']) as im:assert im.format=='WEBP' and not im.getexif()
files=sorted(p for p in r.rglob('*') if p.is_file() and p.name!='SHA256SUMS')
(r/'SHA256SUMS').write_text(''.join(hashlib.sha256(p.read_bytes()).hexdigest()+'  '+str(p.relative_to(r))+'\n' for p in files))
print(f'OK: 3 idiomas, {len(c["places"])} atractivos sin duplicados, 10 fichas/3 categorías, {len(a)} originales/derivados, {len(choices)} propuestas numeradas con aptitud explícita, 3 clases de actividad, fuentes/hashes y decisiones independientes sin aprobación. No certifica importación final ni publicación.')
