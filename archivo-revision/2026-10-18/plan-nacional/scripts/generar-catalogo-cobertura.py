#!/usr/bin/env python3
"""Proyecta únicamente campos públicos del plan; nunca copia manifiestos privados."""
import json,unicodedata
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def norm(s):return ''.join(c for c in unicodedata.normalize('NFD',s.upper()) if unicodedata.category(c)!='Mn').replace('.','').replace(',','').strip()
def generate():
 plan=json.loads((ROOT/'crecimiento-colombia/inventario.json').read_text());geo=json.loads((ROOT/'backend/app/content/coverage-geography.json').read_text())
 aliases={'LA CEJA DEL TAMBO':'LA CEJA','CAROLINA DEL PRINCIPE':'CAROLINA','EL PEÑOL':'PEÑOL','EL PENOL':'PENOL','BOGOTA':'BOGOTA DC','EL RETIRO':'RETIRO','SANTA CRUZ DE MOMPOX':'MOMPOS','SANTIAGO DE TOLU':'TOLU','GUADALAJARA DE BUGA':'GUADALAJARA DE BUGA','CARTAGENA':'CARTAGENA DE INDIAS','CUCUTA':'SAN JOSE DE CUCUTA'}
 operation=json.loads((ROOT/'crecimiento-colombia/operacion.json').read_text())
 tourism=json.loads((ROOT/'crecimiento-colombia/turismo.json').read_text());tby={p['id']:p for p in tourism['places']}
 active={q['place_id'] for q in operation['queue']};qby={q['place_id']:q for q in operation['queue']+operation['deferred']}
 legacy=json.loads((ROOT/'backend/app/contact-options.json').read_text())['municipalityCatalog'];legacy_by={key:p['pilotCategory'] for p in legacy for key in {norm(p['name']),aliases.get(norm(p['name']),norm(p['name']))}}
 rows=[]
 for d in plan['departments']:
  places=[]
  for p in d['places']:
   m=json.loads((ROOT/'crecimiento-colombia'/p['path']).read_text());name=norm(p['name']);expected=aliases.get(name,name)
   match=[g for g in geo if g['department_code']==d['dane_code'] and norm(g['name']) in {name,expected}]
   if p['parent_place_id']:
    parent=next(z for z in d['places'] if z['id']==p['parent_place_id']);pn=norm(parent['name']);match=[g for g in geo if g['department_code']==d['dane_code'] and norm(g['name']) in {pn,aliases.get(pn,pn)}]
   if len(match)!=1:raise ValueError(f'Asociación cartográfica ambigua/ausente: {p["name"]}: {match}')
   places.append({'id':p['id'],'name':p['name'],'slug':p['slug'],'municipality_code':match[0]['code'],'parent_place_id':p['parent_place_id'],'type':p['type'],'priority':p['priority'],'weight':p['weight'],'preparing':qby[p['id']]['state'] in ['produccion','revision','correcciones','aprobado'] or m['state'] in ['researching','draft','review','approved'],'in_work_scope':p['id'] in active,'original_priority':tby[p['id']]['legacy_pilot_category'],'prepared_percent':sum(w for k,w in {'identidad':5,'fuentes':10,'texto':20,'fotos':20,'idiomas':15,'directorio':10,'tecnica':10,'privacidad':5,'revision':5}.items() if m['gates'][k]['passed']),'depth_percent':100*sum(c['status']=='completo' for c in qby[p['id']]['components'].values())/len(qby[p['id']]['components']),'publishable':m['state'] in ['approved','published'] and qby[p['id']]['state'] in ['aprobado','publicado']})
  rows.append({'code':d['dane_code'],'name':d['name'],'weight':d['weight'],'priority':d['priority'],'places':places})
 registry=[]
 for g in geo:
  original=legacy_by.get(norm(g['name'])) if g['department_code']=='05' else None
  related=[p for d in rows for p in d['places'] if p['municipality_code']==g['code']]
  registry.append({'code':g['code'],'department_code':g['department_code'],'name':g['name'],'original_priority':original,'in_work_scope':any(p['in_work_scope'] for p in related),'tracked_place_count':len(related)})
 if sum(g['original_priority'] is not None for g in registry)!=125:raise ValueError('Clasificación original de Antioquia incompleta')
 if sum(g['original_priority']==4 for g in registry)!=22:raise ValueError('No se conservan todos los P4 de Antioquia')
 output={'schema_version':2,'scope_version':operation['scope_version'],'measurement_inventory':registry,'measurement_reference':'MGN2018_historical_not_current_administrative_census','universe_version':plan['universe_version'],'catalog_updated_at':plan['as_of'],'departments':rows}
 (ROOT/'backend/app/content/coverage-plan.json').write_text(json.dumps(output,ensure_ascii=False,indent=2)+'\n')
 print('Catálogo público seguro:',sum(len(d['places']) for d in rows),'destinos')
if __name__=='__main__':generate()
