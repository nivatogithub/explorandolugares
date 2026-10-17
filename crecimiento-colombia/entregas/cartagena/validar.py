"""Validate evidence references, permissions, image files and delivery integrity."""
from pathlib import Path
import hashlib,json
from PIL import Image
P=Path(__file__).resolve().parent
read=lambda n:json.loads((P/n).read_text())
d=read('borrador.json'); sources={s['id'] for s in read('fuentes.json')}
assert d['destination_id']=='CO-13-cartagena' and d['worker']=='Lugar 1'
assert d['approved'] is False and d['publishable'] is False
assert set(d['destination']['translations'])=={'es','en','pt-BR'}
assert len(d['places'])>=3
inventory=read('inventario-componentes.json')
assert inventory['sites_total']==len(d['places'])>=17
assert inventory['experiences_total']==len(d['experiences'])>=9
assert inventory['illustrations_generated']==1 and not inventory['art_generation_authorized']
hero=read('imagenes/ia/procedencia-portada-v1.json')
assert hero['review']['status']=='draft_pending_human_review' and not hero['publication']['approved_at']
assert read('correcciones-v3.json')['hero_generation_authorized'] is True
assert (P/hero['file']).is_file() and (P/hero['master_file']).is_file()
assert inventory['references_human_approved']==3 and inventory['published_sites']==0
assert len({p['slug'] for p in d['places']})==len(d['places'])
selection=read('seleccion-editorial.json');assert len(selection['selection'])==8 and not selection['approved']
selected={p['slug']:p for p in d['places'] if p.get('editorial_selected')}
assert len(selected)==8 and 'islas-rosario' in selected and 'playa-blanca-baru' in selected
assert len({p['image']['asset_id'] for p in selected.values()})==8
assert all(p['image'] and p['image']['asset_id']!=selection['hero_asset'] for p in selected.values())
for exp in d['experiences']:
 assert exp['experience_class'] in {'editorial_autonoma','oferta_operador_documentada','candidato_por_validar'}
 assert not exp['public_visible'] and not exp['partnership'] and not exp['availability_confirmed']
 assert exp['visual_candidates'] and exp['image'] is None
 if exp['experience_class']=='oferta_operador_documentada':
  assert exp['operator'] and exp['operator_url'].startswith('https://') and exp['source_ids']
review=read('revision-componentes.json')
assert len(review['components'])>=79 and not review['publication_approved']
assert len({c['id'] for c in review['components']})==len(review['components'])
for component in review['components']:
 assert component['decision'] in {None,'pendiente','corregir','aprobado'}
 if component['decision']=='aprobado':
  assert component['approval_source_revision']==read('decisiones-usuario.json')['source_revision']
 assert component['sha256']==hashlib.sha256(json.dumps(component['value'],ensure_ascii=False,sort_keys=True).encode()).hexdigest()
assert review['historical_approvals']==22 and review['active_unchanged_approvals']==15
assert sum(c['decision']=='aprobado' for c in review['components'])==15
assert not any(c['id'].startswith('CTG-PORTADA-LICENCIA') for c in review['components'])
assert (P/'historial/revision-41-componentes.json').is_file()
assert (P/'historial/decision-usuario-original-3fab25.json').is_file()
if (P/'reporte-preprod.json').exists():
 report=read('reporte-preprod.json'); package=read('paquete-preprod.json')
 assert report['intended_environment']=='preprod_local_only' and not report['publication_approved']
 assert not report['database_modified'] and not report['browser_used'] and not report['container_started']
 assert report['package_sha256']==hashlib.sha256((P/report['package']).read_bytes()).hexdigest()
 assert report['source_package_sha256']==hashlib.sha256((P/report['source_package']).read_bytes()).hexdigest()
 assert len(package['places'])==8 and all(p['publication_status']=='draft' for p in package['places'])
 assert package['hero_image']['image_origin']=='external'
 for locale in ['es','en','pt-BR']:
  assert 'provis' in package['destination']['translations'][locale]['intro'].lower()
  for source in package['destination']['sources'][locale]:assert source['label'] and source['url'].startswith('https://')
  for image in [package['hero_image'],*[p['image'] for p in package['places']]]:
   assert 'provis' in image['caption'][locale].lower() and len(image['caption'][locale])<=500
for lang in ['es','en','pt-BR']:
 assert d['destination']['history'][lang] and d['practical'][lang]
 assert all(p['translations'][lang]['description'] for p in d['places'])
directory=read('directorio.json')
assert len(directory)>=10 and len({x['category'] for x in directory})>=3
assert len({x['id'] for x in directory})==len(directory)
for x in directory:
 assert x['source_id'] in sources and x['partnership'] is False and x['claimed'] is False
 assert x['publication_status']=='draft' and x['operation_status']=='no_confirmado'
for row in read('trazabilidad.json')['mapping'].values():
 assert set(row)<=sources
for a in read('imagenes/manifiesto.json'):
 assert a['commercial_use'] and a['adaptation'] and not a['approved']
 for item in [a['original'],*a['derivatives']]:
  file=P/item['file']; assert file.is_file()
  assert hashlib.sha256(file.read_bytes()).hexdigest()==item['sha256']
  assert file.stat().st_size==item['bytes']
  with Image.open(file) as image:
   image.verify()
  if file.suffix=='.webp':
   with Image.open(file) as image:
    assert len(image.getexif())==0 and not image.info.get('exif')
   assert item['budget_ok']
manifest=P/'SHA256SUMS.json'
files={str(f.relative_to(P)):hashlib.sha256(f.read_bytes()).hexdigest() for f in sorted(P.rglob('*')) if f.is_file() and f.name!='SHA256SUMS.json' and '__pycache__' not in f.parts}
if manifest.exists():
 assert read('SHA256SUMS.json')==files,'Delivery changed: regenerate and request a new review.'
else:
 manifest.write_text(json.dumps(files,ensure_ascii=False,indent=2)+'\n')
print(f'PASS: {len(files)} files; draft gates, source references, 3 languages, directory, image hashes/MIME/metadata/budgets and delivery integrity.')
