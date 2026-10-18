"""Coordinator executes this exact licensed batch; worker does not request network escalation."""
from pathlib import Path
import json,subprocess
r=Path(__file__).resolve().parent
failures=[]
for x in json.loads((r/'descargas-v3.json').read_text()):
 target=r/x['target']
 if target.exists() and target.stat().st_size>0:
  print('Existe',x['id']);continue
 result=subprocess.run(['curl','-L','--fail','--max-time','30',x['url'],'-o',str(target)])
 if result.returncode:
  failures.append(x['id']);print('Fallo, continuar',x['id'],result.returncode)
 else:print('Descargado',x['id'],x['license'])
print('Pendientes:',failures)
