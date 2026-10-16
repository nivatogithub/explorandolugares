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
