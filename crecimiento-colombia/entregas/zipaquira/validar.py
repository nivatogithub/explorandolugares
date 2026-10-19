"""Validate actual deliverables; distinguish manifest compatibility from import readiness."""
from pathlib import Path
import json, hashlib, zipfile, subprocess
P=Path(__file__).resolve().parent; R=P.parents[2]
load=lambda n:json.loads((P/n).read_text())
p=load('paquete-destino.borrador.json'); d=load('paquete-negocios.json'); a=load('auditoria-top8.json'); c=load('checklist.json'); v=load('encargos-visuales.json')
assert p['destination']['slug']==d['destination_slug']=='zipaquira'
assert len([x for x in p['places'] if x['type']=='place'])==8
assert len([x for x in p['places'] if x['type']=='experience'])==5
assert len({x['slug'] for x in p['places']})==13
assert len(d['businesses'])==len({b['name'].casefold() for b in d['businesses']})==30
assert {b['category_code'] for b in d['businesses']}=={'hospedaje','alimentacion','comercio'}
for b in d['businesses']:
 assert not b['summary'] and not b['description'] and b['information_status']=='PUBLICO'
 assert b['sources'][0]['checked_at']=='2026-10-19' and 'sin verificar' in b['sources'][0]['note']
for l in ['es','en','pt-BR']:
 assert len(p['destination']['history'][l])==1
 assert len(p['destination']['history'][l][0].split())<=60
 assert p['destination']['reasons'][l]==[]
 for row in p['places']:
  t=row['translations'][l]
  assert 1<=len(t['description'].split())<=18
  assert t['name'] and row['publication_status']=='draft'
assert [x['rank'] for x in a['selection']]==list(range(1,9))
assert [x['score'] for x in a['selection']]==sorted([x['score'] for x in a['selection']],reverse=True)
assert len(v)==14 and all(not x['generated'] and not x['approved'] for x in v)
assert not c['publication_approved'] and not c['imported']
assert not any((P/x['image']['file']).exists() for x in p['places'])
assert not (P/'paquete-destino.zip').exists(), 'Do not offer an image-less destination ZIP as importable'
with zipfile.ZipFile(P/'paquete-negocios.zip') as z:
 assert z.namelist()==['paquete-negocios.json']
 assert json.loads(z.read('paquete-negocios.json'))==d
php='/opt/homebrew/bin/php'
# Run the repository's own validators, no database or server involved.
code='require $argv[1]."/backend/app/DestinationPackage.php";require $argv[1]."/backend/app/BusinessPackage.php";DestinationPackage::validateManifest(json_decode(file_get_contents($argv[2]."/paquete-destino.borrador.json"),true));BusinessPackage::validateManifest(json_decode(file_get_contents($argv[2]."/paquete-negocios.json"),true));echo "Both native manifests valid\\n";'
run=subprocess.run([php,'-r',code,str(R),str(P)],capture_output=True,text=True)
assert run.returncode==0,run.stderr
report=dict(date='2026-10-19',native_manifest_validators=run.stdout.strip(),top8=8,experiences=5,businesses=30,languages=['es','en','pt-BR'],images_present=0,images_required=14,destination_zip_importable=False,business_zip_structure_valid=True,database_import_tested=False,browser_reviewed=False,preprod_modified=False,production_modified=False,approved=False,hashes={f.name:hashlib.sha256(f.read_bytes()).hexdigest() for f in P.iterdir() if f.suffix in ['.json','.html','.zip'] and f.name!='qa.json'})
(P/'qa.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({k:v for k,v in report.items() if k!='hashes'},ensure_ascii=False,indent=2))
