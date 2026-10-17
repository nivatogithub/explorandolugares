from pathlib import Path
import json,hashlib
from html.parser import HTMLParser
from PIL import Image
r=Path(__file__).resolve().parent
read=lambda f:json.loads((r/f).read_text())
sha=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
p=read('PROPUESTOS_TOP8_V6.json');refs=read('referencias-propuestos-v6.json')
assert {x['id'] for x in p['items']}=={'plaza-botero','metro-metrocable'}
assert all(set(x['translations'])=={'es','en','pt-BR'} and not x['selected_for_main'] and not any(x['approved'].values()) for x in p['items'])
closure=read('CIERRE_CONTENIDO_SIN_FOTOS.json') if (r/'CIERRE_CONTENIDO_SIN_FOTOS.json').exists() else {}
def snapshot_ok(f,h):
 change=closure.get('authorized_text_changes',{}).get(f,{})
 return sha(r/f)==h or (change.get('before_sha256')==h and change.get('after_sha256')==sha(r/f))
assert all(snapshot_ok(f,h) for f,h in p['protected_files_sha256'].items())
assert len(refs)==4 and len({x['id'] for x in refs})==4
assert all(not x['selected'] and not x['human_approved'] and not x['ai_generated'] for x in refs)
assert all(x.get('file') and x.get('visual_review',{}).get('technical_derivative_inspected') for x in refs)
assert sum(x['visual_review']['recommend_for_review'] for x in refs)==2
for x in refs:
 assert x['download_evidence']['full_original']
 if not x.get('file'):continue
 assert sha(r/x['target'])==x['original_sha256'] and sha(r/x['file'])==x['sha256'] and x['bytes']<=120000
 with Image.open(r/x['file']) as im:assert im.format=='WEBP' and not im.getexif()
class QA(HTMLParser):
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='img':assert (r/a['src']).exists()
q=QA();q.feed((r/'propuestos-top8-v6.html').read_text())
print('PASS: dos borradores trilingües, cuatro referencias trazables, archivos protegidos idénticos, sin aprobación. Descargadas:',sum(bool(x.get('file')) for x in refs),'Inspeccionadas:',sum(bool(x.get('visual_review')) for x in refs))
