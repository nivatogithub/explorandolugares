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
assert all(sha(r/f)==h for f,h in p['protected_files_sha256'].items())
assert len(refs)==4 and len({x['id'] for x in refs})==4
assert all(not x['selected'] and not x['human_approved'] and not x['ai_generated'] for x in refs)
for x in refs:
 if not x.get('file'):continue
 assert sha(r/x['target'])==x['original_sha256'] and sha(r/x['file'])==x['sha256'] and x['bytes']<=120000
 with Image.open(r/x['file']) as im:assert im.format=='WEBP' and not im.getexif()
class QA(HTMLParser):
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='img':assert (r/a['src']).exists()
q=QA();q.feed((r/'propuestos-top8-v6.html').read_text())
print('PASS: dos borradores trilingües, cuatro referencias trazables, archivos protegidos idénticos, sin aprobación. Descargadas:',sum(bool(x.get('file')) for x in refs),'Inspeccionadas:',sum(bool(x.get('visual_review')) for x in refs))
