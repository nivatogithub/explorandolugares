#!/usr/bin/env python3
"""Dos reservas globales de desarrollo; no crea puertos ni entornos adicionales."""
import argparse,fcntl,json,os,time
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('action',choices=['status','acquire','release']);p.add_argument('--owner');p.add_argument('--wait',action='store_true');p.add_argument('--slot',type=int,choices=[1,2]);p.add_argument('--state',default=os.environ.get('EL_ENV_STATE',str(Path(__file__).resolve().parents[3]/'.local/environments') if Path(__file__).resolve().parents[2].name=='trabajo' else str(Path.home()/'.local/state/explorandolugares/environments')));a=p.parse_args()
state=Path(a.state);state.mkdir(parents=True,exist_ok=True)
if a.action!='status' and not a.owner:p.error('--owner es obligatorio')
while True:
 with (state/'reservations.lock').open('a+') as lock:
  fcntl.flock(lock,fcntl.LOCK_EX)
  file=state/'reservations.json';data=json.loads(file.read_text()) if file.exists() else {'1':None,'2':None}
  if a.action=='status':print(json.dumps({'preprod':8088,'dev1':{'port':8086,'reservation':data['1']},'dev2':{'port':8087,'reservation':data['2']}},ensure_ascii=False,indent=2));break
  if a.action=='release':
   if not a.slot:p.error('--slot es obligatorio para liberar')
   old=data[str(a.slot)]
   if old and old['owner']!=a.owner:raise SystemExit('Reserva ajena: no se libera ni reemplaza.')
   data[str(a.slot)]=None
  else:
   own=next((k for k,v in data.items() if v and v['owner']==a.owner),None)
   free=own or next((k for k,v in data.items() if v is None and (a.slot is None or int(k)==a.slot)),None)
   if free:
    data[free]={'owner':a.owner,'reserved_at':time.strftime('%Y-%m-%dT%H:%M:%S%z')};a.slot=int(free)
   elif not a.wait:raise SystemExit('Los dos dev están ocupados. Usar --wait; no crear otro ambiente.')
  if a.action=='release' or free:
   temp=file.with_suffix('.tmp');temp.write_text(json.dumps(data,ensure_ascii=False,indent=2));temp.replace(file);print(json.dumps({'slot':a.slot,'port':8085+a.slot,'action':a.action}));break
 time.sleep(3)
