"""Validate delivered review artefacts, no network/database/publication."""
from pathlib import Path
from PIL import Image
import json,hashlib
p=Path(__file__).resolve().parent;root=p.parents[3]
data=json.load(open(p/'santa-marta-semirrealista.json'));manifest=json.load(open(p/'MANIFIESTO.json'));pack=json.load(open(p/'contenido-cierre-semirrealista-v1.json'));checks=[]
def check(name,condition):
 checks.append({'check':name,'pass':bool(condition)})
 if not condition:raise AssertionError(name)
check('Destination scope',data['destination']=='santa-marta')
check('8 distinct geographic sites',len(data['places'])==len({r['id'] for r in data['places']})==8)
check('Tayrona before Rodadero',next(r['order'] for r in data['places'] if r['id']=='tayrona')<next(r['order'] for r in data['places'] if r['id']=='rodadero'))
check('No Acuario in Top8','visita-acuario' not in {r['id'] for r in data['places']} and 'acuario' not in {r['id'] for r in data['places']})
check('9 distinct experiences',len(data['experiences'])==len({r['id'] for r in data['experiences']})==9)
check('Place/experience IDs disjoint',not ({r['id'] for r in data['places']} & {r['id'] for r in data['experiences']}))
check('Import place/experience slugs disjoint',not ({r['slug'] for r in pack['places']} & {r['id'] for r in pack['experiences']}))
check('History has no image',data['history_image'] is None and manifest['history_image'] is None)
for row in data['places']+data['experiences']:
 check(row['id']+' complete brief translations',set(row['translations'])=={'es','en','pt-BR'} and all(t['name'] and 10<len(t['description'])<240 for t in row['translations'].values()))
 check(row['id']+' unapproved draft',row['publication_status']=='draft' and not row['text_approval'] and not row['image_approval'])
check('No confirmed moto offer',not data['experiences'][-1]['availability_verified'] and not data['experiences'][-1]['reservable'] and data['experiences'][-1]['location_id'] is None)
check('18 separate selected masters',len(manifest['images'])==18 and len({r['source_sha256'] for r in manifest['images']})==18)
count=0
for row in manifest['images']:
 master=root/row['file'];check(row['asset_id']+' master hash',hashlib.sha256(master.read_bytes()).hexdigest()==row['source_sha256'])
 check(row['asset_id']+' outside hosting','backend/public' not in row['file'])
 for f in row['files']:
  path=root/f['path'];im=Image.open(path);count+=1
  check(path.name+' dimensions/hash',list(im.size)==[f['width'],f['height']] and hashlib.sha256(path.read_bytes()).hexdigest()==f['sha256'])
 check(row['asset_id']+' no inputs or approval',not row['generation']['referenced_image_paths'] and not row['review']['human_result_approved'])
check('36 responsive WebPs',count==36)
check('Import content count/flags',len(pack['places'])==8 and len(pack['experiences'])==9 and all(e['review_ready'] and set(e['display_translations'])=={'es','en','pt-BR'} for e in pack['experiences']) and not pack['approved'] and not pack['publishable'])
check('Public JSON matches private draft',json.load(open(root/'backend/public/assets/santa-marta-semirrealista/contenido.json'))==data)
result={'status':'PASS','checks':checks,'count':len(checks),'scope':'File integrity, source associations and translation/role completeness only; browser rendering and human approval not implied.'}
(p/'QA_PAQUETE.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');print('PASS',len(checks),'checks')
