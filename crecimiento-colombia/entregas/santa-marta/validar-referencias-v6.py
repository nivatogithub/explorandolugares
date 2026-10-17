"""Valida trazabilidad de referencias de investigación; no certifica aprobación/publicabilidad."""
import hashlib,json
from pathlib import Path
from PIL import Image
root=Path(__file__).resolve().parent
checks=[]
for name in ['candidatas-v6.json','portadas-extra-v6.json','fotos-experiencias-v6.json','acuario-referencia-v6.json','cafe-candidata-v6.json','video-portada-v6.json','centro-bahia-v7.json']:
 data=json.loads((root/name).read_text());data=data if isinstance(data,list) else [data]
 for r in data:
  filename=r.get('path') or r.get('original_path')
  if not filename:continue
  path=root/filename
  assert path.is_file(),(r['id'],'original ausente')
  if r.get('sha256'):assert hashlib.sha256(path.read_bytes()).hexdigest()==r['sha256'],(r['id'],'hash no coincide')
  assert r.get('source') or r.get('source_page'),(r['id'],'falta fuente')
  assert r.get('license'),(r['id'],'falta licencia')
  if path.suffix.lower()!='.mp4':
   with Image.open(path) as im: dimensions=list(im.size)
  else:dimensions=None
  checks.append({'id':r['id'],'file':filename,'integrity':'PASS','dimensions':dimensions,'status':r.get('status'),'approved':r.get('approval',r.get('reference_approved',False))})
report={'checked':len(checks),'checks':checks,'scope':'Integridad y trazabilidad de originales. No implica pertinencia, derechos adicionales, aprobación o generación autorizada.','destination_complete':False}
(root/'validacion-referencias-v6.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print('PASS',len(checks),'originales con trazabilidad íntegra; destino sigue incompleto.')
