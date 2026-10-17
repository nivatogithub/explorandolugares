"""Coordinator downloads verified candidates; worker never requests hidden network approval."""
from pathlib import Path
import json,subprocess
r=Path(__file__).resolve().parent
results=[]
for x in json.loads((r/'descargas-v4.json').read_text()):
 target=r/x['target']
 if target.exists() and target.stat().st_size:
  print('Existe',x['id']);continue
 ok=False
 for url in x['download_urls']:
  done=subprocess.run(['curl','-L','--fail','--max-time','30',url,'-o',str(target)])
  if done.returncode==0:
   results.append(dict(id=x['id'],downloaded_url=url,full_original=url==x['original_url']))
   ok=True;break
 if not ok:results.append(dict(id=x['id'],status='pending_network'))
 print(x['id'],'descargado' if ok else 'pendiente')
(r/'descargas-v4-resultados.json').write_text(json.dumps(results,ensure_ascii=False,indent=2)+'\n')
