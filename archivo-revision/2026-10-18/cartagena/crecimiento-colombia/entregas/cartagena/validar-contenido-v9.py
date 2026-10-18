"""Validate content delivery against local preprod receipts without DB/network writes."""
from pathlib import Path
import json,hashlib
P=Path(__file__).resolve().parent
read=lambda n:json.loads((P/n).read_text())
p=read('contenido-cierre-v9.json');selected=p['editorial_selection'];by={x['slug']:x for x in p['places']}
assert len(selected)==len(set(selected))==8
assert not p['selection_approved'] and not p['publishable']
assert read('RECIBO_PREPROD_CONTENIDO_V9.json')['content_sha256']==hashlib.sha256((P/'contenido-cierre-v9.json').read_bytes()).hexdigest()
assert read('RECIBO_PREPROD_DIRECTORIO_V9.json')['directory_sha256']==hashlib.sha256((P/'directorio.json').read_bytes()).hexdigest()
ids={x['id'] for x in read('fuentes.json')}
for s in selected:
 assert set(by[s].get('source_ids',[]))<=ids
for l in ['es','en','pt-BR']:
 assert all(by[s]['translations'][l]['name'] and by[s]['translations'][l]['description'] for s in selected)
 assert sum(bool(x.get('review_ready') and x.get('display_translations',{}).get(l,{}).get('description')) for x in p['experiences'])==7
 assert len(read('directorio.json'))==10 and all(x['translations'][l]['description'] for x in read('directorio.json'))
 q=read('QA_PREPROD_CONTENIDO_V9.json')[l]
 assert q['status']=='draft' and q['places']==8 and q['experiences']==7
 assert read('RECIBO_PREPROD_DIRECTORIO_V9.json')['visible_in_private_preview'][l]==10
print('PASS:8atractivos,7experiencias,10directorio,3idiomas; hashes y fuentes válidos; destino draft.')
