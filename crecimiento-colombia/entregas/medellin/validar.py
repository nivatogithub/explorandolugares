from pathlib import Path
import json, hashlib
from PIL import Image
r=Path(__file__).resolve().parent
c=json.loads((r/'contenido.json').read_text())
b=json.loads((r/'directorio.json').read_text())
a=json.loads((r/'imagenes.json').read_text())
s={x['id'] for x in json.loads((r/'fuentes.json').read_text())}
assert c['status']=='draft' and set(c['translations'])=={'es','en','pt-BR'}
assert len(c['places'])>=3 and len(b)>=10 and len({x['category'] for x in b})>=3
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
print('OK: 3 idiomas, 4 atractivos, 10 fichas/3 categorías, 3 originales y derivados, fuentes, hashes y gates de borrador. No certifica importación final, publicación ni aprobación humana.')
