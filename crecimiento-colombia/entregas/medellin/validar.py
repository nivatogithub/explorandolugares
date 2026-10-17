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
preview=(r/'preview.html').read_text()
if (r/'principales-v3.json').exists():
 main=json.loads((r/'principales-v3.json').read_text());pkg=json.loads((r/'paquete-preprod.json').read_text())
 assert len(main)==len(pkg['places'])==8 and not any(p['slug']=='coltejer' for p in main)
 references=[pkg['hero_image']['file']]+[p['image']['file'] for p in pkg['places']]
 assert len(references)==len(set(references))==9
 assert len({hashlib.sha256((r/f).read_bytes()).hexdigest() for f in references})==9
 assert all(set(p['translations'])=={'es','en','pt-BR'} for p in main)
 assert 'Inventario amplio secundario' in preview
 assert json.loads((r/'inspeccion-visual-v3.json').read_text())['cover_current_suitability'] is False
 candidates=json.loads((r/'referencias-propuestas-v4.json').read_text())
 assert len(candidates)==5 and sum(x['visual_review']['recommend_for_review'] for x in candidates)==3
 for x in candidates:
  assert not x['selected'] and not x['human_approved'] and not x['ai_generated']
  assert hashlib.sha256((r/x['target']).read_bytes()).hexdigest()==x['original_sha256']
  assert hashlib.sha256((r/x['file']).read_bytes()).hexdigest()==x['sha256']
  assert x['download_evidence']['full_original']
  assert x['bytes'] <= (300000 if x['component']=='portada' else 120000)
  with Image.open(r/x['file']) as im:assert im.format=='WEBP' and not im.getexif()
 for rejected in ['panorama-2005.webp','arvi-2012.webp','comuna13-2023.webp']:assert rejected not in preview
else:
 assert preview.index('id="portada-4"')<preview.index('id="portada-1"')
 assert 'Descartadas/solo referencia, no portada' in preview
 assert 'data-item="portada-1" data-component="eleccion_portada"' not in preview
review=json.loads((r/'revision-componentes.json').read_text())
assert review['selected_cover'] is None and not review['illustrations']['generated'] and not review['publication']['authorized']
activities=json.loads((r/'experiencias.json').read_text())
assert {x['kind'] for x in activities}=={'propuesta_editorial_autonoma','oferta_documentada_operador','candidato_por_validar'}
assert all(x.get('photo_status') for x in activities)
assert all(set(x['translations'])=={'es','en','pt-BR'} for x in activities)
assert all(set(x['translations'])=={'es','en','pt-BR'} for x in b)
assert all(c['translations'][l].get('mobility') for l in ['es','en','pt-BR'])
assert {'metro-j','metro-l','metro-historia'}<=s
for p in c['places']:
 assert set(p['translations'])==set(c['translations'])
 assert set(p['source_ids'])<=s and p['publication_status']=='draft'
for x in b:
 assert not x['claimed'] and not x['contacted'] and x['publication_status']=='draft'
 assert set(x['source_ids'])<=s
for x in a:
 assert (x['original_downloaded'] or x.get('downloaded_source_file')) and x['commercial_use'] and x['adaptation']
 assert hashlib.sha256((r/x['file']).read_bytes()).hexdigest()==x['sha256']
 assert hashlib.sha256((r/x['original_file']).read_bytes()).hexdigest()==x['original_sha256']
 assert x['bytes']<=(300000 if 'medellin-1.webp' in x['file'] or 'panorama-2005.webp' in x['file'] else 120000)
 with Image.open(r/x['file']) as im:assert im.format=='WEBP' and not im.getexif()
if (r/'verificacion-vigencia-v5.json').exists():
 v=json.loads((r/'verificacion-vigencia-v5.json').read_text())
 assert len(v['directory'])==10 and v['summary']['commercial_businesses']==6 and v['summary']['cultural_institutions']==4
 assert not any(x['registry_verified'] or x['contacted'] or x['availability_confirmed'] for x in v['directory'])
 if (r/'imagen-actividad-mamm-v5.json').exists():
  x=json.loads((r/'imagen-actividad-mamm-v5.json').read_text())
  assert x['inspected']['subject_matches'] and not x['human_approved'] and not x['generated_illustration']
  assert hashlib.sha256((r/x['original_file']).read_bytes()).hexdigest()==x['original_sha256']
  assert hashlib.sha256((r/x['file']).read_bytes()).hexdigest()==x['sha256'] and x['bytes']<=120000
files=sorted(p for p in r.rglob('*') if p.is_file() and p.name!='SHA256SUMS' and '__pycache__' not in p.parts)
(r/'SHA256SUMS').write_text(''.join(hashlib.sha256(p.read_bytes()).hexdigest()+'  '+str(p.relative_to(r))+'\n' for p in files))
print(f'Antecedente visual V4 conservado. Contenido actual: {len(activities)} experiencias registradas, {len(b)} fichas y tres idiomas; ninguna aprobación nueva. ZIP V3 es histórico y tiene referencias rechazadas.')
print(f'Antecedentes comprobados: 3 idiomas, {len(c["places"])} sitios amplios, 8 principales/9 fotos distintas enZIP, {len(b)} fichas/3 categorías, {len(a)} archivosfuente trazables/derivados (incluyen miniaturasoficiales, no todosmaestros), decisiones independientes sinaprobación. No certifica importaciónMySQL ni publicación.')
