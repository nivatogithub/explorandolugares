"""Límite explícito del propietario: P4 fuera, horizonte operativo P1/P2."""
import json
from validar import ROOT,require

def eligible(d,p,t):
    legacy=json.loads((ROOT/'historico/clasificacion-antioquia-propietario.json').read_text())
    original4={x['name'] for x in legacy['municipalityCatalog'] if x['pilotCategory']==4}
    return d['priority'] in (1,2) and p['priority'] in (1,2) and t.get('legacy_pilot_category')!=4 and not (d['id']=='CO-05' and p['name'] in original4)

def scoped_rows(rows,tourism):
    policy=json.loads((ROOT/'alcance.json').read_text())
    require(policy['allowed_department_priorities']==[1,2] and policy['allowed_place_priorities']==[1,2],'Alcance P1/P2 alterado sin nueva decisión')
    require(policy['excluded_legacy_categories']==[4] and policy['deferred_priorities']==[3],'Exclusión P4 o reserva P3 alterada')
    legacy=json.loads((ROOT/'historico/clasificacion-antioquia-propietario.json').read_text())
    require(policy['legacy_category4_names']==[x['name'] for x in legacy['municipalityCatalog'] if x['pilotCategory']==4],'Lista P4 incompleta')
    byid={p['id']:p for p in tourism['places']};active=[];deferred=[]
    for row in rows:
        d,p,di,pi=row
        (active if eligible(d,p,byid[p['id']]) else deferred).append(row)
    return active,deferred,policy
