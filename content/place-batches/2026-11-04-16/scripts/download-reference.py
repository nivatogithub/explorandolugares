import json,sys,urllib.request,hashlib
from pathlib import Path
root=Path(__file__).resolve().parents[1]
slug,key,index=sys.argv[1],sys.argv[2],int(sys.argv[3]);folder=root/'places'/slug;record=json.loads((folder/'reference-searches'/f'{key}.json').read_text())['candidates'][index]
if not record['license'] or not any(x in record['license'] for x in ['CC BY','CC0','Public domain']):raise SystemExit('No compatible recorded license')
url=record['preview_url'] or record['original_url'];out=folder/'references'/f'{key}.jpg';out.parent.mkdir(exist_ok=True)
if not out.exists():
 with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'ExplorandoLugaresEditorialResearch/1.0'}),timeout=40) as r:out.write_bytes(r.read())
record.update(file=str(out.relative_to(root)),sha256=hashlib.sha256(out.read_bytes()).hexdigest(),approval=None,selected_for_review=True)
(out.with_suffix('.json')).write_text(json.dumps(record,ensure_ascii=False,indent=2)+'\n');print(out.resolve())
