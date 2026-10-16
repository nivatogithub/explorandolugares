#!/usr/bin/env python3
"""Proyecta únicamente campos públicos del plan; nunca copia manifiestos privados."""
import json,unicodedata
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def norm(s):return ''.join(c for c in unicodedata.normalize('NFD',s.upper()) if unicodedata.category(c)!='Mn').replace('.','').replace(',','').strip()
def generate():
 plan=json.loads((ROOT/'crecimiento-colombia/inventario.json').read_text());geo=json.loads((ROOT/'backend/app/content/coverage-geography.json').read_text())
 aliases={'EL PEÑOL':'PEÑOL','EL PENOL':'PENOL','BOGOTA':'BOGOTA DC','EL RETIRO':'RETIRO','SANTA CRUZ DE MOMPOX':'MOMPOS','SANTIAGO DE TOLU':'TOLU','GUADALAJARA DE BUGA':'GUADALAJARA DE BUGA','CARTAGENA':'CARTAGENA DE INDIAS','CUCUTA':'SAN JOSE DE CUCUTA'}
 rows=[]
 for d in plan['departments']:
  places=[]
  for p in d['places']:
   m=json.loads((ROOT/'crecimiento-colombia'/p['path']).read_text());name=norm(p['name']);expected=aliases.get(name,name)
   match=[g for g in geo if g['department_code']==d['dane_code'] and norm(g['name']) in {name,expected}]
   if p['parent_place_id']:
    parent=next(z for z in d['places'] if z['id']==p['parent_place_id']);pn=norm(parent['name']);match=[g for g in geo if g['department_code']==d['dane_code'] and norm(g['name']) in {pn,aliases.get(pn,pn)}]
   if len(match)!=1:raise ValueError(f'Asociación cartográfica ambigua/ausente: {p["name"]}: {match}')
   places.append({'id':p['id'],'name':p['name'],'slug':p['slug'],'municipality_code':match[0]['code'],'parent_place_id':p['parent_place_id'],'type':p['type'],'priority':p['priority'],'weight':p['weight'],'preparing':m['state'] in ['researching','draft','review','approved']})
  rows.append({'code':d['dane_code'],'name':d['name'],'weight':d['weight'],'places':places})
 output={'schema_version':1,'universe_version':plan['universe_version'],'catalog_updated_at':plan['as_of'],'departments':rows}
 (ROOT/'backend/app/content/coverage-plan.json').write_text(json.dumps(output,ensure_ascii=False,indent=2)+'\n')
 print('Catálogo público seguro:',sum(len(d['places']) for d in rows),'destinos')
if __name__=='__main__':generate()
