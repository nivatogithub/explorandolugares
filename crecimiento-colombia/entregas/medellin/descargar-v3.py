"""Coordinator executes this exact licensed batch; worker does not request network escalation."""
from pathlib import Path
import json,subprocess
r=Path(__file__).resolve().parent
for x in json.loads((r/'descargas-v3.json').read_text()):
 target=r/x['target']
 if target.exists() and target.stat().st_size>0:
  print('Existe',x['id']);continue
 subprocess.run(['curl','-L','--fail','--retry','2','--max-time','60',x['url'],'-o',str(target)],check=True)
 print('Descargado',x['id'],x['license'])
