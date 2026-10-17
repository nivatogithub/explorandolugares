from pathlib import Path
import json,zipfile,hashlib
root=Path(__file__).resolve().parent
p=json.loads((root/'paquete-preprod.json').read_text());places=p['places'];assert len(places)==8
images=[p['hero_image']]+[x['image'] for x in places];assert len({x['image_source_url'] for x in images})==9
assert len({x['file'] for x in images})==9
for locale in ['es','en','pt-BR']:
 assert p['destination']['translations'][locale]['intro']
 assert len(p['destination']['reasons'][locale])==4
 assert all(len(s)<100 for s in p['destination']['reasons'][locale])
 for place in places:assert place['translations'][locale]['name'] and place['translations'][locale]['description'] and place['image']['alt'][locale]
with zipfile.ZipFile(root/'paquete-preprod.zip') as z:
 assert z.testzip() is None
 assert json.loads(z.read('paquete-destino.json'))==p
 for image in images:assert z.read(image['file'])==(root/'imagenes'/Path(image['file']).name).read_bytes()
h=json.loads((root/'santa-marta-history.json').read_text());assert h['image']['image_source_url'] not in {i['image_source_url'] for i in images}
assert h['sha256']==hashlib.sha256((root/'santa-marta-history.webp').read_bytes()).hexdigest()
print('PASS: eight attractions; nine distinct package images plus independent history sidecar; ES/EN/PT complete; four compact reasons; ZIP integrity and history hash verified. Editorial and production approvals remain pending.')
