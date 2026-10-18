#!/usr/bin/env python3
"""Comprueba aislamiento real de los workers locales; sin modificar Git."""
import json,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];REPO=ROOT.parent

def git(path,*args):return subprocess.check_output(['git','-C',str(path),*args],text=True).strip()
def validate():
 op=json.loads((ROOT/'operacion.json').read_text());qs={q['place_id']:q for q in op['queue']};trees={}
 for chunk in git(REPO,'worktree','list','--porcelain').split('\n\n'):
  fields=dict(line.split(' ',1) for line in chunk.splitlines() if ' ' in line)
  if 'branch' in fields:trees[fields['branch'].removeprefix('refs/heads/')]=Path(fields['worktree'])
 seen=set();result=[]
 for worker in op['workers']:
  if not worker['current_place_id']:continue
  q=qs[worker['current_place_id']];branch=q['branch'];path=trees.get(branch)
  if path is None or branch in seen:raise ValueError('Rama ausente/compartida: '+branch)
  seen.add(branch)
  if git(path,'branch','--show-current')!=branch:raise ValueError('Worker en otra rama')
  slug=q['place_id'][6:];allowed=('crecimiento-colombia/entregas/'+slug+'/', 'crecimiento-colombia/'+q['manifest'],str(Path('crecimiento-colombia')/q['manifest']).replace('lugar.json','PROCESO.md'))
  changed=git(path,'diff','--name-only','75d8d16','HEAD').splitlines()+git(path,'diff','--name-only').splitlines()+git(path,'diff','--cached','--name-only').splitlines()+git(path,'ls-files','--others','--exclude-standard').splitlines()
  for file in changed:
   if not any(file==prefix or file.startswith(prefix) for prefix in allowed):raise ValueError(worker['name']+' cambia archivo fuera de su destino: '+file)
  result.append({'worker':worker['name'],'place_id':q['place_id'],'branch':branch,'worktree':str(path),'checked_files':len(set(changed))})
 return result
if __name__=='__main__':print(json.dumps(validate(),ensure_ascii=False,indent=2))
